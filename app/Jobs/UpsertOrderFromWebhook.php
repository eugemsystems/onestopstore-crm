<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\OrderTransaction;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Services\OrdersApi;

class UpsertOrderFromWebhook implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function __construct(public array $order, public string $event) {}

    public function handle(OrdersApi $api): void
    {

        try {
            $order = $this->order;
            DB::transaction(function () use ($order, $api) {
                // -------- orders
                $orderCols = Schema::getColumnListing('orders');

                $data = Arr::only($order, $orderCols);

                // map nested address ids to FK columns if present
                if (isset($order['billing_address']['id']) && in_array('billing_address_id', $orderCols)) {
                    $data['billing_address_id'] = $order['billing_address']['id'];
                }
                if (isset($order['shipping_address']['id']) && in_array('shipping_address_id', $orderCols)) {
                    $data['shipping_address_id'] = $order['shipping_address']['id'];
                }

                // keep JSON copies if those columns exist
                foreach (['billing_address', 'shipping_address'] as $jsField) {
                    if (in_array($jsField, $orderCols) && isset($order[$jsField]) && is_array($order[$jsField])) {
                        $data[$jsField] = $order[$jsField];
                    }
                }

                // if your 'status' column is int but payload is a string label, drop it or map it
                if (array_key_exists('status', $data) && !is_null($data['status']) && !is_numeric($data['status'])) {
                    unset($data['status']);
                }

                // don’t overwrite with nulls
                $data = array_filter($data, fn($v) => !is_null($v));

                if (isset($order['order_status']['slug'])) {
                    $data['order_status'] = $order['order_status']['slug'];
                } else {
                    unset($data['order_status']);
                }
                if (isset($order['order_status']['id'])) {
                    $data['order_status_id'] = $order['order_status']['id'];
                }

                // map consumer details
                if (isset($order['consumer'])) {
                    if (array_key_exists('consumer_name', $data) || in_array('consumer_name', $orderCols)) {
                        $data['consumer_name'] = $order['consumer']['name'] ?? null;
                    }
                    if (array_key_exists('consumer_country_code', $data) || in_array('consumer_country_code', $orderCols)) {
                        $data['consumer_country_code'] = $order['consumer']['country_code'] ?? null;
                    }
                    if (array_key_exists('consumer_phone_number', $data) || in_array('consumer_phone_number', $orderCols)) {
                        $data['consumer_phone_number'] = $order['consumer']['phone'] ?? null;
                    }
                    if (array_key_exists('consumer_email', $data) || in_array('consumer_email', $orderCols)) {
                        $data['consumer_email'] = $order['consumer']['email'] ?? null;
                    }
                }

                $data['order_history'] = Arr::get($order, 'status_histories', []);

                // Remove id from data to prevent insert conflicts
                // id should only be used in the WHERE clause, not in the data payload
                $orderId = $order['id'];
                $orderNumber = $data['order_number'] ?? null;
                unset($data['id']);
                // Keep order_number in data so it gets saved properly
                if ($orderNumber) {
                    $data['order_number'] = $orderNumber;
                }

//                Log::info('UpsertOrderFromWebhook - Attempting to save order', [
//                    'order_id' => $orderId,
//                    'order_number' => $orderNumber,
//                    'data_keys' => array_keys($data),
//                ]);

                // Use id in the WHERE clause for upsert
                Order::query()->updateOrCreate(
                    ['id' => $orderId],
                    $data
                );

//                Log::info('UpsertOrderFromWebhook - Order saved successfully', [
//                    'order_id' => $orderId,
//                    'order_number' => $orderNumber,
//                ]);

                // -------- items
                $itemCols = Schema::getColumnListing('order_products');
                $itemIds = [];

                $rawItems = Arr::get($order, 'items', Arr::get($order, 'products', []));

//                Log::info('UpsertOrderFromWebhook - Processing items', [
//                    'order_id' => $order['id'],
//                    'items_count' => count($rawItems),
//                    'raw_items' => $rawItems, // Log the actual items
//                ]);

                // If payload seems partial (missing image info), try to enrich with a full order fetch
                $needsEnrichment = collect($rawItems)->contains(function($it){
                    return empty(Arr::get($it, 'product_thumbnail_url'))
                        && empty(Arr::get($it, 'variation_image.image_url'))
                        && empty(Arr::get($it, 'product_meta_image.image_url'))
                        && empty(Arr::get($it, 'product_galleries.0.image_url'));
                });
                $fullItemsByKey = [];
                if ($needsEnrichment && !empty($order['id'])) {
                    try {
                        $full = $api->show((int)$order['id']);
                        $fullItems = Arr::get($full, 'items', Arr::get($full, 'products', []));
                        foreach ($fullItems as $fi) {
                            $key = ($fi['id'] ?? null) ?: (($fi['pivot']['product_id'] ?? null).':'.($fi['pivot']['variation_id'] ?? '0'));
                            if ($key) $fullItemsByKey[$key] = $fi;
                        }
                    } catch (\Throwable $e) {
                        Log::warning('Order enrich fetch failed', ['order_id'=>$order['id']??null,'err'=>$e->getMessage()]);
                    }
                }
                foreach ($rawItems as $it) {
                    // Normalize items - handle both flat items (from OrderResource) and pivot-wrapped items
                    $oid = (int)($order['id'] ?? 0);
                    $pid = (int)($it['product_id'] ?? $it['pivot']['product_id'] ?? $it['id'] ?? 0);
                    $vid = (int)($it['variation_id'] ?? $it['pivot']['variation_id'] ?? 0);

                    // Validate we have minimum required data
                    if (!$oid || !$pid) {
                        Log::warning('Skipping order item: missing order_id or product_id', [
                            'order_id' => $oid,
                            'product_id' => $pid,
                            'item' => $it,
                        ]);
                        continue;
                    }

                    // Generate unique ID that accounts for different attribute combinations
                    // Use selected_attribute_ids to differentiate variations with same product_id
                    $selectedAttrIds = $it['selected_attribute_ids'] ?? $it['pivot']['selected_attribute_ids'] ?? [];
                    $attrHash = 0;
                    if (!empty($selectedAttrIds) && is_array($selectedAttrIds)) {
                        // Sort IDs to ensure consistent hash for same combination
                        sort($selectedAttrIds);
                        // Create a hash from the attribute IDs
                        $attrHash = crc32(implode(',', $selectedAttrIds)) & 0x7FFFFFFF; // Keep positive
                    }

                    // Generate composite ID: order_id + product_id + variation_id + attribute_hash
                    // This ensures each unique attribute combination gets a separate item
                    $baseId = ($oid * 1000000) + ($pid % 1000000);
                    $it['id'] = $baseId + ($attrHash % 100000); // Add attribute hash to make it unique

                    // Merge pivot fields if they exist (for backward compatibility)
                    if (isset($it['pivot'])) {
                        $it = array_merge($it, $it['pivot']);
                    }

                    // Ensure top-level fields are present
                    if (!isset($it['product_id'])) $it['product_id'] = $pid;
                    if (!isset($it['variation_id'])) $it['variation_id'] = $vid;
                    if (!isset($it['order_id'])) $it['order_id'] = $oid;

                    // Try enrichment by composite key if missing image in webhook item
                    $enrichKey = $pid . ':' . $vid;
                    if (empty(Arr::get($it, 'product_thumbnail_url'))
                        && empty(Arr::get($it, 'variation_image.image_url'))
                        && empty(Arr::get($it, 'product_meta_image.image_url'))
                        && empty(Arr::get($it, 'product_galleries.0.image_url'))
                        && !empty($fullItemsByKey[$enrichKey] ?? null)) {
                        $it = array_merge($fullItemsByKey[$enrichKey], $it);
                    }

                    if (!isset($it['id'])) {
                        Log::warning('Skipping order item without id', [
                            'order_id' => $order['id'] ?? null,
                            'item'     => $it,
                        ]);
                        continue;
                    }

                    // Ensure we have order_id set
                    if (empty($it['order_id'])) {
                        $it['order_id'] = $order['id'];
                    }

                    // Ensure we have product_id
                    if (empty($it['product_id']) && !empty($it['id'])) {
                        Log::warning('Order item missing product_id, skipping', [
                            'order_id' => $order['id'] ?? null,
                            'item_id' => $it['id'],
                            'item' => $it,
                        ]);
                        continue;
                    }

                    $itemIds[] = $it['id'];


                    $payload   = Arr::only($it, $itemCols);

                    // Ensure id is always in the payload for non-auto-incrementing primary key
                    $payload['id'] = $it['id'];



                    // Variation enrichment (name, attributes, sku) with multiple payload shapes support
                    try {
                        $vid = (int)($payload['variation_id'] ?? ($it['variation_id'] ?? 0));
                        $var = null;

                        // Primary: match by id within variations[]
                        $vars = Arr::get($it, 'variations', []);
                        if ($vid && is_array($vars)) {
                            foreach ($vars as $_v) {
                                if ((int)($_v['id'] ?? 0) === $vid) { $var = $_v; break; }
                            }
                        }

                        // Secondary: enrichment set might have variations
                        if (!$var && $vid && isset($fullItemsByKey)) {
                            $key = isset($it['pivot']) ? (($it['pivot']['product_id'] ?? $it['id'] ?? null).':'.($it['pivot']['variation_id'] ?? 0)) : ($it['id'] ?? null);
                            if ($key && !empty($fullItemsByKey[$key]['variations'] ?? null)) {
                                foreach ($fullItemsByKey[$key]['variations'] as $_v) {
                                    if ((int)($_v['id'] ?? 0) === $vid) { $var = $_v; break; }
                                }
                            }
                        }

                        // Fallbacks: some payloads provide a single 'variation' object or put attrs on the item
                        if (!$var && isset($it['variation']) && is_array($it['variation'])) {
                            $var = $it['variation'];
                        }
                        if (!$var && is_array($vars) && count($vars) === 1) {
                            $var = $vars[0];
                        }

                        // Extract attributes from multiple possible shapes
                        $attrs = null;
                        $attrSources = [
                            fn() => Arr::get($var, 'attribute_values'),
                            fn() => Arr::get($var, 'attributes'),
                            fn() => Arr::get($it, 'selected_options'),
                            fn() => Arr::get($it, 'attributes'),
                        ];
                        foreach ($attrSources as $src) {
                            $val = $src ? $src() : null;
                            if (!empty($val)) { $attrs = $val; break; }
                        }
                        // Normalize attributes to array of objects [{name, value}]
                        if (!is_null($attrs)) {
                            if (is_array($attrs) && \Illuminate\Support\Arr::isAssoc($attrs)) {
                                $norm = [];
                                foreach ($attrs as $k => $v) {
                                    $norm[] = ['name' => $k, 'value' => $v];
                                }
                                $attrs = $norm;
                            } elseif (is_array($attrs)) {
                                $norm = [];
                                foreach ($attrs as $v) {
                                    if (is_array($v)) {
                                        $name = $v['name'] ?? $v['key'] ?? null;
                                        $value = $v['value'] ?? $v['name'] ?? null;
                                        $norm[] = ['name' => $name, 'value' => $value];
                                    } else {
                                        $norm[] = ['name' => null, 'value' => $v];
                                    }
                                }
                                $attrs = $norm;
                            } else {
                                $attrs = [['name' => null, 'value' => (string)$attrs]];
                            }
                        }

                        if (is_array($var) || $attrs) {
                            if (in_array('variation_name', $itemCols)) {
                                $payload['variation_name'] = $var['name'] ?? $payload['variation_name'] ?? null;
                                if (empty($payload['variation_name']) && is_array($attrs)) {
                                    $vals = array_filter(array_map(fn($x) => trim((string)($x['value'] ?? '')), $attrs));
                                    if ($vals) { $payload['variation_name'] = implode(', ', array_unique($vals)); }
                                }
                            }
                            if (in_array('variation_sku', $itemCols)) {
                                $payload['variation_sku'] = $var['sku'] ?? $payload['variation_sku'] ?? ($it['variation_sku'] ?? null);
                            }
                            if (in_array('variation_attributes', $itemCols) && !is_null($attrs)) {
                                $payload['variation_attributes'] = $attrs;
                            }
                            $vImg = Arr::get($var, 'variation_image.image_url') ?? Arr::get($var, 'image_url') ?? Arr::get($var, 'image');
                            if ($vImg && empty($payload['product_thumbnail_url'])) {
                                $payload['product_thumbnail_url'] = $vImg;
                            }
                        }
                    } catch (\Throwable $e) {
                        Log::warning('variation enrichment failed', ['err' => $e->getMessage()]);
                    }

                    // Map common fields from product shape if missing
                    if (empty($payload['name'] ?? null) && isset($it['name'])) $payload['name'] = $it['name'];
                    if (empty($payload['slug'] ?? null) && isset($it['slug'])) $payload['slug'] = $it['slug'];
                    if (empty($payload['sku'] ?? null) && isset($it['sku'])) $payload['sku'] = $it['sku'];
                    if (!isset($payload['single_price']) && isset($it['single_price'])) $payload['single_price'] = $it['single_price'];
                    if (!isset($payload['subtotal']) && isset($it['subtotal'])) $payload['subtotal'] = $it['subtotal'];
                    if (!isset($payload['quantity']) && isset($it['quantity'])) $payload['quantity'] = $it['quantity'];
                    if (!isset($payload['variation_id']) && isset($it['variation_id'])) $payload['variation_id'] = $it['variation_id'];
                    if (!isset($payload['product_id']) && isset($it['product_id'])) $payload['product_id'] = $it['product_id'];

                    // IMPORTANT: Map price and sale_price fields explicitly
                    // The OrderResource sends variation prices (not product prices) when variation exists
                    // We need to ensure these are preserved in the payload
                    if (isset($it['price'])) {
                        $payload['price'] = $it['price'];
                    }
                    if (isset($it['sale_price'])) {
                        $payload['sale_price'] = $it['sale_price'];
                    }


                    // Map fast shipping fields
                    if (in_array('fast_shipping_cost', $itemCols) && isset($it['fast_shipping_cost'])) {
                        $payload['fast_shipping_cost'] = $it['fast_shipping_cost'];
                    }
                    if (in_array('item_shipping_method', $itemCols) && isset($it['item_shipping_method'])) {
                        $payload['item_shipping_method'] = $it['item_shipping_method'];
                    }
                    if (in_array('has_fast_shipping', $itemCols)) {
                        $payload['has_fast_shipping'] = isset($it['has_fast_shipping'])
                            ? $it['has_fast_shipping']
                            : (isset($it['fast_shipping_cost']) && $it['fast_shipping_cost'] > 0);
                    }

                    // Map multi-attribute variation fields (selected_attribute_ids, variation_display_name)
                    if (in_array('selected_attribute_ids', $itemCols) && isset($it['selected_attribute_ids'])) {
                        $payload['selected_attribute_ids'] = $it['selected_attribute_ids'];
                    }
                    if (in_array('variation_display_name', $itemCols) && isset($it['variation_display_name'])) {
                        $payload['variation_display_name'] = $it['variation_display_name'];
                    }

                    // Map estimated_delivery_text field
                    if (in_array('estimated_delivery_text', $itemCols) && isset($it['estimated_delivery_text'])) {
                        $payload['estimated_delivery_text'] = $it['estimated_delivery_text'];
                    }

                    // Try to derive a thumbnail url; preserve existing if webhook doesn't provide enough data
                    if (in_array('product_thumbnail_url', $itemCols)) {
                        $candidates = [
                            'product_thumbnail_url',
                            'variation_image.image_url',
                            'product_meta_image.image_url',
                            'product_galleries.0.image_url',
                            'product_galleries.0.url',
                            'image_url',
                            'image',
                            'thumbnail',
                            'thumbnail_url',
                            'thumb',
                            'featured_image',
                            'featured_image_url',
                            'product.image_url',
                            'product.image',
                            'product.thumbnail_url',
                            'product.thumbnail',
                            'product.thumb',
                            'variation.image_url',
                            'variation.thumbnail_url',
                            'galleries.0.image_url',
                            'galleries.0.url',
                            'media.0.original_url',
                            'media.0.url',
                        ];
                        $thumb = null;
                        foreach ($candidates as $k) {
                            $v = Arr::get($it, $k);
                            if (!empty($v)) { $thumb = $v; break; }
                        }
                        if ($thumb) {
                            // Normalize to absolute URL when needed
                            if (is_string($thumb)) {
                                $isAbsolute = preg_match('/^https?:\/\//i', $thumb) === 1;
                                if (!$isAbsolute) {
                                    if (str_starts_with($thumb, '//')) {
                                        $thumb = 'https:' . $thumb;
                                    } else {
                                        $base = rtrim(config('services.api.base_url') ?: env('FRONTEND_URL') ?: config('app.url'), '/');
                                        if (!str_starts_with($thumb, '/')) {
                                            $thumb = '/' . ltrim($thumb, '/');
                                        }
                                        $thumb = $base . $thumb;
                                    }
                                }
                            }
                            $payload['product_thumbnail_url'] = $thumb;
                        } else {
                            // Fallback: keep existing thumbnail if present
                            $existing = OrderProduct::query()->find($it['id']);
                            if ($existing && !empty($existing->product_thumbnail_url)) {
                                $payload['product_thumbnail_url'] = $existing->product_thumbnail_url;
                            }
                        }
                    }

                    $payload['order_id'] = $order['id'];

                    // Set item_status based on order status if not provided in payload
                    // SAFEGUARD REMOVED - Always sync status from API to CRM
                    if (in_array('item_status', $itemCols)) {
                        $orderStatusSlug = strtolower(trim($order['order_status']['slug'] ?? $order['order_status']['name'] ?? ''));

                        // Map order status to item status
                        $statusMap = [
                            'pending' => 'pending',
                            'processing' => 'processing',
                            'warehouse packing' => 'processing',
                            'from supplier' => 'processing',
                            'shipped' => 'shipped',
                            'in transit to zim' => 'shipped',
                            'dropped at the deport' => 'shipped',
                            'out for delivery' => 'shipped',
                            'ready for collection' => 'shipped',
                            'delivered' => 'delivered',
                            'collected' => 'collected', // Keep collected as collected
                            'cancelled' => 'cancelled',
                            'canceled' => 'cancelled',
                            'stuck' => 'processing',
                            'refunded' => 'cancelled',
                            'arrived at local branch' => 'shipped',
                            'arrived-at-local-branch' => 'shipped',
                        ];

                        // If payload has item_status, use it directly (no downgrade protection)
                        if (!empty($payload['item_status'])) {
                            Log::info('UpsertOrderFromWebhook: Syncing item status from API', [
                                'item_id' => $it['id'],
                                'item_status' => $payload['item_status'],
                                'order_id' => $order['id'],
                            ]);
                        } else {
                            // Payload doesn't have item_status - set from order status
                            $itemStatus = $statusMap[$orderStatusSlug] ?? 'pending';
                            $existing = OrderProduct::query()->find($it['id']);

                            if (!$existing || empty($existing->item_status) || $existing->item_status === '--') {
                                $payload['item_status'] = $itemStatus;
                            }
                        }
                    }

                    OrderProduct::query()->updateOrCreate(['id' => $it['id']], $payload);
                }


                // -------- transactions
                if (Schema::hasTable('order_transactions')) {
                    $txCols = Schema::getColumnListing('order_transactions');
                    $txIds = [];
                    foreach (Arr::get($order, 'transactions', []) as $tx) {
                        $txIds[] = $tx['id'];
                        $payload = Arr::only($tx, $txCols);
                        $payload['order_id'] = $order['id'];
                        OrderTransaction::query()->updateOrCreate(['id' => $tx['id']], $payload);
                    }
                    //Log::info('Updating|creating transactions items', [ ]);
                    if ($txIds) {
                        OrderTransaction::query()
                            ->where('order_id', $order['id'])
                            ->whereNotIn('id', $txIds)
                            ->delete();
                        //Log::info('Deleting order items', [ ]);
                    }
                }
            });
        } catch (\Throwable $e) {
            Log::error($e->getMessage(), ['trace' => $e->getTraceAsString()]);
        }
    }
}
