<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\OrderStatus;
use App\Models\OrderTransaction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class UpsertOrderStatusFromWebhook implements ShouldQueue
{
    use Queueable;

    public function __construct(public array $os, public string $event) {}

    public function handle(): void
    {
        try{
            $os = $this->os;
            OrderStatus::updateOrCreate([ 'name'=> $os['name'] ],$os);

        } catch (\Throwable $e) {
            Log::error($e->getMessage(), ['order status trace' => $e->getTraceAsString()]);
        }
    }
}
