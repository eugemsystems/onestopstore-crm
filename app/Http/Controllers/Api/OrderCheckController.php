<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;

class OrderCheckController extends Controller
{
    /**
     * Check if an order exists in the CRM system by ORDER ID
     * This is the primary method since CRM uses API order IDs as primary keys
     *
     * @param int $orderId
     * @return JsonResponse
     */
    public function checkExistsById(int $orderId): JsonResponse
    {
        $exists = Order::where('id', $orderId)->exists();

        return response()->json([
            'exists' => $exists,
            'order_id' => $orderId,
        ]);
    }

    /**
     * Get order details by ORDER ID
     *
     * @param int $orderId
     * @return JsonResponse
     */
    public function showById(int $orderId): JsonResponse
    {
        $order = Order::where('id', $orderId)
            ->with(['order_status', 'order_items', 'consumer'])
            ->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'order' => $order,
        ]);
    }

    /**
     * Check if an order exists in the CRM system by ORDER NUMBER
     * (Kept for backward compatibility)
     *
     * @param int $orderNumber
     * @return JsonResponse
     */
    public function checkExists(int $orderNumber): JsonResponse
    {
        $exists = Order::where('order_number', $orderNumber)->exists();

        return response()->json([
            'exists' => $exists,
            'order_number' => $orderNumber,
        ]);
    }

    /**
     * Get order details by ORDER NUMBER
     * (Kept for backward compatibility)
     *
     * @param int $orderNumber
     * @return JsonResponse
     */
    public function show(int $orderNumber): JsonResponse
    {
        $order = Order::where('order_number', $orderNumber)
            ->with(['order_status', 'order_items', 'consumer'])
            ->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'order' => $order,
        ]);
    }
}
