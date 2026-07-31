<?php

namespace App\Jobs;

use App\Services\OrdersApi;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class TransferItemToInventoryJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $backoff = 10;

    public function __construct(public array $payload, public int $orderProductId) {}

    public function handle(OrdersApi $ordersApi): void
    {
        try {
            $ordersApi->transferToInventory($this->payload);

            Log::info('TransferItemToInventoryJob: success', [
                'order_product_id' => $this->orderProductId,
                'order'            => $this->payload['order'] ?? null,
            ]);
        } catch (\Throwable $e) {
            Log::error('TransferItemToInventoryJob: failed', [
                'order_product_id' => $this->orderProductId,
                'error'            => $e->getMessage(),
            ]);
            throw $e; // re-throw so the queue retries
        }
    }
}
