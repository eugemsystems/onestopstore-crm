<?php

namespace App\Observers;

use App\Models\Order;
use Illuminate\Support\Facades\Log;

/**
 * Order Observer for CRM
 * Handles cleanup of QR code files when orders reach final status
 */
class OrderObserver
{
    /**
     * Handle the Order "updated" event.
     */
    public function updated(Order $order): void
    {
        // Clean up QR codes when order status changes to final states
        if ($order->wasChanged('order_status_id')) {
            $this->cleanupQRCodesOnFinalStatus($order);
        }
    }

    /**
     * Clean up QR code files when order reaches final status
     * (collected, delivered, cancelled)
     */
    protected function cleanupQRCodesOnFinalStatus(Order $order): void
    {
        $orderStatus = \App\Models\OrderStatus::where('id', $order->order_status_id)->first();
        if (!$orderStatus) {
            return;
        }

        $statusSlug = strtolower(trim($orderStatus->slug ?? $orderStatus->name ?? ''));

        // Delete QR codes when order is completed
        $cleanupStatuses = ['collected', 'delivered', 'cancelled', 'canceled', 'refunded'];

        if (in_array($statusSlug, $cleanupStatuses)) {
            $qrCodeService = app(\App\Services\OrderQRCodeService::class);
            $qrCodeService->delete($order->order_number ?? $order->id);

        }
    }

    /**
     * Handle the Order "deleted" event.
     */
    public function deleted(Order $order): void
    {
        // Also cleanup QR codes when order is deleted
        $qrCodeService = app(\App\Services\OrderQRCodeService::class);
        $qrCodeService->delete($order->order_number ?? $order->id);
    }

    /**
     * Handle the Order "force deleted" event.
     */
    public function forceDeleted(Order $order): void
    {
        // Also cleanup QR codes when order is force deleted
        $qrCodeService = app(\App\Services\OrderQRCodeService::class);
        $qrCodeService->delete($order->order_number ?? $order->id);
    }
}
