<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderProduct;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class OrderItemWebhookController extends Controller
{
    /**
     * Handle incoming webhook from Laravel API when item status is updated
     */
    public function itemStatusUpdated(Request $request)
    {
        $validated = $request->validate([
            'order_id' => 'required|integer',
            'product_id' => 'required|integer',
            'variation_id' => 'nullable|integer',
            'item_status' => 'required|string',
            'cancellation_reason' => 'nullable|string',
            'eta' => 'nullable|date',
        ]);

        try {
            $order = Order::with('order_items')->find($validated['order_id']);

            if (!$order) {

                return response()->json([
                    'success' => false,
                    'message' => 'Order not found',
                ], 404);
            }

            // Strategy 1: Try exact match with product_id and variation_id
            $orderProduct = $order->order_items()
                ->where('product_id', $validated['product_id'])
                ->when(
                    isset($validated['variation_id']) && $validated['variation_id'] !== null,
                    fn($q) => $q->where('variation_id', $validated['variation_id']),
                    fn($q) => $q->where(function($subQ) {
                        $subQ->whereNull('variation_id')->orWhere('variation_id', 0);
                    })
                )
                ->first();

            // Strategy 2: If not found and variation_id is null, try matching just by product_id
            // This handles cases where the variation_id might be stored as 0 or empty string
            if (!$orderProduct && (!isset($validated['variation_id']) || $validated['variation_id'] === null)) {
                $orderProduct = $order->order_items()
                    ->where('product_id', $validated['product_id'])
                    ->first();
            }

            if (!$orderProduct) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order item not found',
                ], 404);
            }

            // Update the item status and ETA
            $updateData = [
                'item_status' => $validated['item_status'],
                'cancellation_reason' => $validated['cancellation_reason'] ?? null,
            ];

            if (isset($validated['eta'])) {
                $updateData['eta'] = $validated['eta'];
            }

            $orderProduct->update($updateData);


            return response()->json([
                'success' => true,
                'message' => 'Order item status updated successfully',
            ]);

        } catch (\Exception $e) {


            return response()->json([
                'success' => false,
                'message' => 'Failed to update item status',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Handle ready for collection notification from Laravel API
     */
    public function readyForCollection(Request $request)
    {
        $validated = $request->validate([
            'order_id' => 'required|integer',
            'order_number' => 'required|string',
        ]);

        try {
            $order = Order::with(['consumer', 'order_items', 'order_status'])->find($validated['order_id']);

            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found',
                ], 404);
            }

            // Get all order items
            $items = $order->order_items;

            if ($items->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No order items found',
                ], 404);
            }


            // Send notification to customer
            if ($order->consumer) {
                try {
                    // Pass 'status' as the kind since we're notifying about status change
                    // The actual status 'ready for collection' comes from the order status
                    $order->consumer->notify(new \App\Notifications\OrderItemsUpdatedNotification(
                        $order,
                        $items, // Pass all items
                        'status' // kind: 'status', 'eta', or 'both'
                    ));


                    return response()->json([
                        'success' => true,
                        'message' => 'Ready for collection notification sent successfully',
                        'details' => [
                            'order_id' => $order->id,
                            'order_number' => $order->order_number,
                            'consumer_email' => $order->consumer->email,
                        ],
                    ]);

                } catch (\Exception $e) {


                    // Return error with details
                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to send notification: ' . $e->getMessage(),
                        'error_details' => [
                            'exception' => get_class($e),
                            'file' => $e->getFile(),
                            'line' => $e->getLine(),
                        ],
                    ], 500);
                }
            } else {

                return response()->json([
                    'success' => false,
                    'message' => 'No consumer found for order',
                ], 404);
            }

        } catch (\Exception $e) {


            return response()->json([
                'success' => false,
                'message' => 'Failed to process ready for collection',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}


