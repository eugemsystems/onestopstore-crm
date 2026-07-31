<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\OrderTransaction;
use App\Services\OrdersApi;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class OrdersBackfill extends Command
{
    protected $signature = 'orders:backfill {--page=1} {--per=50}';
    protected $description = 'Pull missing orders from API (only processing status) and upsert into CRM';

    public function handle(OrdersApi $api)
    {
        $page = (int)$this->option('page');
        $per = (int)$this->option('per');

        // Step 1: Collect missing orders (only processing status)
        $this->info('Fetching orders from API...');
        $missingOrders = [];

        foreach ($api->stream($per, $page) as $order) {
            // Skip if order already exists in CRM (match by order_number)
            $orderNumber = $order['order_number'] ?? null;
            if ($orderNumber) {
                $exists = Order::query()->where('order_number', $orderNumber)->exists();
                if ($exists) {
                    continue;
                }
            }

            // Only include orders with processing status
            $orderStatus = $order['order_status']['slug'] ?? $order['order_status']['name'] ?? $order['status'] ?? null;
            if (!$orderStatus || strtolower($orderStatus) !== 'processing') {
                $orderRef = $order['order_number'] ?? $order['id'] ?? 'unknown';
                $statusText = $orderStatus ?: 'unknown';
                $this->line("Skipping order #{$orderRef} - Status: {$statusText} (not processing)");
                continue;
            }

            $missingOrders[] = $order;
        }

        if (empty($missingOrders)) {
            $this->info('No missing orders found with "processing" status.');
            return;
        }

        // Step 2: Display missing orders summary
        $this->newLine();
        $this->info('Found ' . count($missingOrders) . ' missing order(s) with "processing" status to import:');
        $this->newLine();

        $headers = ['Order ID', 'Order Number', 'Status', 'Consumer', 'Total', 'Items Count'];
        $rows = [];

        foreach ($missingOrders as $order) {
            $rows[] = [
                $order['id'] ?? 'N/A',
                $order['order_number'] ?? 'N/A',
                $order['order_status']['name'] ?? $order['order_status']['slug'] ?? $order['status'] ?? 'N/A',
                $order['consumer']['name'] ?? $order['consumer_name'] ?? 'N/A',
                number_format($order['total'] ?? 0, 2),
                count($order['items'] ?? $order['products'] ?? []),
            ];
        }

        $this->table($headers, $rows);

        // Step 3: Ask for confirmation
        $this->newLine();
        if (!$this->confirm('Do you want to proceed with importing these orders to the CRM database?', true)) {
            $this->info('Import cancelled.');
            return;
        }

        // Step 4: Process and save orders
        $this->newLine();
        $this->info('Importing orders...');
        $count = 0;

        foreach ($missingOrders as $order) {
            DB::transaction(function () use ($order, &$count, $api) {
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
                        $data[$jsField] = $order[$jsField]; // if your column is text, use json_encode(...)
                    }
                }

                // if your 'status' column is int but payload is a string label, drop it or map it
                if (array_key_exists('status', $data) && !is_null($data['status']) && !is_numeric($data['status'])) {
                    unset($data['status']); // or map to order_status_id here
                }

                // don’t overwrite with nulls (let DB defaults/casts work)
                $data = array_filter($data, fn($v) => !is_null($v));

                if (isset($order['order_status']['slug'])) {
                    $data['order_status'] = $order['order_status']['slug']; // or ['name']
                } else {
                    unset($data['order_status']);
                }
                if (isset($order['order_status']['id'])) {
                    $data['order_status_id'] = $order['order_status']['id'];
                }

                //map consumer details
                if (isset($order['consumer'])) {
                    $data['consumer_name'] = $order['consumer']['name'];
                    $data['consumer_country_code'] = $order['consumer']['country_code'];
                    $data['consumer_phone_number'] = $order['consumer']['phone']; // or ['name']
                    $data['consumer_email'] = $order['consumer']['email'];
                }

                Order::query()->updateOrCreate(['id' => $order['id']], $data);

                // -------- items
                $itemCols = Schema::getColumnListing('order_products');
                $itemIds = [];

                $rawItems = Arr::get($order, 'items', Arr::get($order, 'products', []));

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
                        Log::warning('Backfill enrich fetch failed', ['order_id'=>$order['id']??null,'err'=>$e->getMessage()]);
                    }
                }

                foreach ($rawItems as $it) {
                    // Normalize when API returns products + pivot instead of flat items
                    if (isset($it['pivot'])) {
                        $oid = (int)($order['id'] ?? 0);
                        $pid = (int)($it['pivot']['product_id'] ?? $it['id'] ?? 0);
                        $vid = (int)($it['pivot']['variation_id'] ?? 0);
                        // deterministic composite id within 64-bit range (unique per order/product/variation)
                        $it['id'] = ($oid * 1000000000000) + ($pid * 1000) + ($vid % 1000);
                        // merge pivot fields up so columns like product_id, variation_id, quantity, subtotal are present
                        $it = array_merge($it, $it['pivot'] ?? []);

                        // Try enrichment by composite key if missing image
                        $enrichKey = $pid . ':' . $vid;
                        if (empty(Arr::get($it, 'product_thumbnail_url'))
                            && empty(Arr::get($it, 'variation_image.image_url'))
                            && empty(Arr::get($it, 'product_meta_image.image_url'))
                            && empty(Arr::get($it, 'product_galleries.0.image_url'))
                            && !empty($fullItemsByKey[$enrichKey] ?? null)) {
                            $it = array_merge($fullItemsByKey[$enrichKey], $it);
                        }
                    }

                    if (!isset($it['id'])) {
                        Log::warning('Skipping order item without id', [
                            'order_id' => $order['id'] ?? null,
                            'item'     => $it,
                        ]);
                        continue;
                    }

                    $itemIds[] = $it['id'];
                    $payload   = Arr::only($it, $itemCols);

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
                        Log::warning('backfill variation enrichment failed', ['err' => $e->getMessage()]);
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

                    // Try to derive a thumbnail url (broadened candidates) and normalize
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
                            if (is_string($thumb)) {
                                $isAbsolute = preg_match('/^https?:\/\//i', $thumb) === 1;
                                if (!$isAbsolute) {
                                    if (str_starts_with($thumb, '//')) {
                                        $thumb = 'https:' . $thumb;
                                    } else {
                                        $base = rtrim(config('services.api.base_url') ?: env('FRONTEND_URL') ?: config('app.url'), '/');
                                        if (!str_starts_with($thumb, '/')) { $thumb = '/' . ltrim($thumb, '/'); }
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

                    OrderProduct::query()->updateOrCreate(['id' => $it['id']], $payload);
                    $count++;
                }
                if ($itemIds) {
                    OrderProduct::query()
                        ->where('order_id', $order['id'])
                        ->whereNotIn('id', $itemIds)
                        ->delete();
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
                    if ($txIds) {
                        OrderTransaction::query()
                            ->where('order_id', $order['id'])
                            ->whereNotIn('id', $txIds)
                            ->delete();
                    }
                }


            });
            $this->line("Imported order #{$order['id']}");
        }

        $this->newLine();
        $this->info("✓ Backfill complete! Successfully imported {$count} order(s) to CRM.");
    }
}
