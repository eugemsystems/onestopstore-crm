<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use App\Notifications\OrderItemsUpdatedNotification;

class OrderController extends Controller
{
    //
    public function orders()
    {
        return view('orders.orders');
    }

    public function ordersItems()
    {
        return view('orders.items');
    }

    public function updateStatus(Request $request, Order $order)
    {
        $request->validate([
            'order_status_id' => ['required', 'integer', 'exists:order_statuses,id'],
            'order_number' => ['nullable', 'string'],
        ]);

        $newStatus = OrderStatus::findOrFail((int)$request->input('order_status_id'));

        // Get the old status before updating
        $oldStatus = $order->order_status;
        $oldStatusSlug = $oldStatus ? strtolower(trim($oldStatus->slug ?? $oldStatus->name ?? '')) : null;

        try {
            $base = rtrim(config('services.api.base_url'), '/');
            $token = (string) config('services.api.token');

            $payload = [
                'order_status_id' => $newStatus->id,
            ];

            // Allow client to provide order_number explicitly; otherwise use stored order_number
            $externalOrderIdentifier = $request->input('order_number') ? (string) $request->input('order_number') : ($order->order_number ?? null);

            if (empty($externalOrderIdentifier)) {
                return response()->json(['ok' => false, 'message' => 'Order number missing; cannot update remote order'], 422);
            }


            $resp = Http::withToken($token)
                ->acceptJson()
                ->put($base . '/api/order/' . $externalOrderIdentifier, $payload);

            if ($resp->failed()) {
                $msg = $resp->json('message') ?? $resp->body();
                return response()->json(['ok' => false, 'message' => $msg ?: 'Failed to update remote order'], 422);
            }

            $order->update(['order_status_id' => $newStatus->id]);

            // Auto-update order items that still have the old order status
            // Only update items whose status matches the old order status (not manually changed items)
            $this->syncOrderItemsStatus($order, $oldStatusSlug, $newStatus, $externalOrderIdentifier);

            // Customer notification on order status change is handled via Order Items flows.
            // Disabled here to avoid duplicate emails when updating the main order status.
            // (Intentionally left blank)

            return response()->json([
                'ok' => true,
                'status' => ['id' => $newStatus->id, 'name' => $newStatus->name],
            ]);
        } catch (\Throwable $e) {
            Log::error('order status update failed', ['err' => $e->getMessage()]);
            return response()->json(['ok' => false, 'message' => 'Error updating order status'], 500);
        }
    }

    /**
     * Sync order items status when order status changes
     * Only updates items that still have the old order status (not manually changed)
     *
     * @param string|null $externalOrderIdentifier The external order identifier (order_number) to use for API calls. If null, remote sync is skipped.
     */
    protected function syncOrderItemsStatus(Order $order, ?string $oldStatusSlug, OrderStatus $newStatus, ?string $externalOrderIdentifier = null): void
    {
        try {

            // Map order status to item status
            $statusMap = [
                'pending' => 'pending',
                'processing' => 'processing',
                'shipped' => 'shipped',
                'in transit' => 'shipped',
                'in transit to zim' => 'shipped',
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

            $newStatusSlug = strtolower(trim($newStatus->slug ?? $newStatus->name ?? ''));
            $newItemStatus = $statusMap[$newStatusSlug] ?? null;
            $oldItemStatus = $oldStatusSlug ? ($statusMap[$oldStatusSlug] ?? null) : null;

            // If we can't map the new status, don't update items
            if (!$newItemStatus) {
                return;
            }

            // Get all order items
            $orderItems = $order->order_items()->get();
            $updatedCount = 0;
            $skippedCount = 0;

            // $externalOrderIdentifier parameter is used; if null, remote sync will be skipped to avoid using numeric id

            foreach ($orderItems as $item) {
                $currentItemStatus = strtolower(trim($item->item_status ?? $item->status ?? ''));

                // Normalize '--' or empty status to null for comparison
                if ($currentItemStatus === '--' || $currentItemStatus === '') {
                    $currentItemStatus = null;
                }


                // Only update if:
                // 1. Item has no status set (null or '--'), OR
                // 2. Item status matches the old order status (hasn't been manually changed)
                $shouldUpdate = false;

                if ($currentItemStatus === null) {
                    // Item has no status, inherit from order
                    $shouldUpdate = true;
                } elseif ($oldItemStatus && $currentItemStatus === $oldItemStatus) {
                    // Item status matches old order status, so it wasn't manually changed
                    $shouldUpdate = true;

                } 

                if ($shouldUpdate) {
                    // Update local database
                    $item->update(['item_status' => $newItemStatus]);
                    $updatedCount++;


                    // Sync with API
                    try {
                        $base = rtrim(config('services.api.base_url'), '/');
                        $token = (string) config('services.api.token');

                        if ($externalOrderIdentifier !== null) {

                            Http::withToken($token)
                                ->acceptJson()
                                ->timeout(10)
                                ->put($base . '/api/order/' . $externalOrderIdentifier . '/item-status', [
                                    'product_id' => $item->product_id,
                                    'variation_id' => $item->variation_id,
                                    'item_status' => $newItemStatus,
                                ]);
                        } 

          
                    } catch (\Throwable $e) {
                        Log::warning('Failed to sync item status to API during order status update', [
                            'order_id' => $order->id,
                            'item_id' => $item->id,
                            'product_id' => $item->product_id,
                            'error' => $e->getMessage(),
                        ]);
                        // Continue with other items even if one fails
                    }
                } else {
                    $skippedCount++;
                }
            }


        } catch (\Throwable $e) {
            Log::error('Failed to sync order items status', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            // Don't throw - order status update already succeeded
        }
    }

    /**
     * Map order status slug to order item status
     */
    protected function mapOrderStatusToItemStatus(?string $orderStatusSlug): ?string
    {
        $map = [
            'pending' => 'pending',
            'processing' => 'processing',
            'shipped' => 'shipped',
            'in-transit' => 'shipped',
            'in transit' => 'shipped',
            'in transit to zim' => 'shipped',
            'out-for-delivery' => 'shipped',
            'out for delivery' => 'shipped',
            'ready-for-collection' => 'shipped',
            'ready for collection' => 'shipped',
            'delivered' => 'delivered',
            'cancelled' => 'cancelled',
            'canceled' => 'cancelled',
            'stuck' => 'processing',
        ];

        if (!$orderStatusSlug) {
            return null;
        }

        $normalized = strtolower(trim($orderStatusSlug));
        return $map[$normalized] ?? null;
    }
}
