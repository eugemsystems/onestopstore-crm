<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderProduct;
use App\Services\OrdersApi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class OrderItemStatusController extends Controller
{
    public function __construct(
        protected OrdersApi $ordersApi
    ) {}

    /**
     * Update the status of a specific order item
     */
    public function update(Request $request, Order $order, OrderProduct $orderProduct)
    {
        // IMMEDIATE TEST - Write to file synchronously
        $testFile = storage_path('logs/crm-item-update-' . date('YmdHis') . '.txt');
        file_put_contents($testFile, "CRM Controller called at " . date('Y-m-d H:i:s') . "\nOrder: {$order->id}\nItem: {$orderProduct->id}\nRequest: " . json_encode($request->all()) . "\n");

        $validated = $request->validate([
            'item_status' => 'required|string|in:pending,processing,shipped,delivered,collected,cancelled,out_of_stock,refunded,arrived at local branch',
            'cancellation_reason' => 'nullable|string|max:1000',
            'eta' => 'nullable|date',
        ]);


        $orderProduct->update([
            'item_status' => $validated['item_status'],
            'cancellation_reason' => $validated['cancellation_reason'] ?? null,
            'eta' => $validated['eta'] ?? null,
        ]);

    
        try {
            // Ensure order_number is present (do not fall back to numeric id)
            if (empty($order->order_number)) {
                
                // Rollback local changes and inform client
                $orderProduct->update([
                    'item_status' => $orderProduct->getOriginal('item_status'),
                    'cancellation_reason' => $orderProduct->getOriginal('cancellation_reason'),
                    'eta' => $orderProduct->getOriginal('eta'),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Order number missing; cannot sync with external API',
                ], 422);
            }

            $apiData = [
                'product_id' => $orderProduct->product_id,
                'variation_id' => $orderProduct->variation_id,
                'item_status' => $validated['item_status'],
                'cancellation_reason' => $validated['cancellation_reason'] ?? null,
                'eta' => $validated['eta'] ?? null,
            ];
           
            $response = $this->ordersApi->updateItemStatus((string)$order->order_number, $apiData, $request->user()?->email);

        } catch (\Exception $e) {
            // Rollback local changes and throw error to user
            $orderProduct->update([
                'item_status' => $orderProduct->getOriginal('item_status'),
                'cancellation_reason' => $orderProduct->getOriginal('cancellation_reason'),
                'eta' => $orderProduct->getOriginal('eta'),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to sync with API: ' . $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Item status updated successfully',
            'data' => [
                'item_status' => $orderProduct->item_status,
                'cancellation_reason' => $orderProduct->cancellation_reason,
                'eta' => $orderProduct->eta,
                'status_label' => $orderProduct->status_label,
                'status_color' => $orderProduct->status_color,
            ],
        ]);
    }

    /**
     * Bulk update item statuses
     */
    public function bulkUpdate(Request $request, Order $order)
    {
        $validated = $request->validate([
            'items' => 'required|array',
            'items.*.product_id' => 'required|integer',
            'items.*.variation_id' => 'nullable|integer',
            'items.*.item_status' => 'required|string|in:pending,processing,shipped,delivered,cancelled,out_of_stock,refunded',
            'items.*.cancellation_reason' => 'nullable|string|max:1000',
            'items.*.eta' => 'nullable|date',
        ]);

        $updated = [];
        foreach ($validated['items'] as $itemData) {
            $orderProduct = $order->order_items()
                ->where('product_id', $itemData['product_id'])
                ->where('variation_id', $itemData['variation_id'] ?? null)
                ->first();

            if (!$orderProduct) {
                continue;
            }

            // Update local database
            $orderProduct->update([
                'item_status' => $itemData['item_status'],
                'cancellation_reason' => $itemData['cancellation_reason'] ?? null,
                'eta' => $itemData['eta'] ?? null,
            ]);

            // Sync with API
            try {
                if (empty($order->order_number)) {
                    Log::warning('Skipping remote sync for bulk update: missing order_number', ['order_id' => $order->id, 'product_id' => $itemData['product_id']]);
                } else {
                    $this->ordersApi->updateItemStatus((string)$order->order_number, [
                        'product_id' => $itemData['product_id'],
                        'variation_id' => $itemData['variation_id'] ?? null,
                        'item_status' => $itemData['item_status'],
                        'cancellation_reason' => $itemData['cancellation_reason'] ?? null,
                        'eta' => $itemData['eta'] ?? null,
                    ]);

                    $updated[] = $orderProduct->id;
                }
            } catch (\Exception $e) {
                Log::error('Failed to sync item status to API', [
                    'order_id' => $order->id,
                    'product_id' => $itemData['product_id'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => count($updated) . ' item(s) updated successfully',
            'updated_count' => count($updated),
        ]);
    }
}
