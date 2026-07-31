<?php

namespace App\Observers;

use App\Models\OrderStatus;

class OrderStatusObserver
{
    /**
     * Handle the Event "created" event.
     */
    public function created(OrderStatus $event): void
    {
        //updateCachedSettings();
    }

    /**
     * Handle the Event "updated" event.
     */
    public function updated(OrderStatus $event): void
    {
        //updateCachedSettings();
    }

    /**
     * Handle the Event "deleted" event.
     */
    public function deleted(OrderStatus $event): void
    {
        //updateCachedSettings();
    }

    /**
     * Handle the Event "restored" event.
     */
    public function restored(OrderStatus $event): void
    {
        //updateCachedSettings();
    }

    /**
     * Handle the Event "force deleted" event.
     */
    public function forceDeleted(OrderStatus $event): void
    {
        //updateCachedSettings();
    }
}
