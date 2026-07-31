<?php

namespace App\Observers;

use App\Models\Setting;

class SettingObserver
{
    /**
     * Handle the Event "created" event.
     */
    public function created(Setting $event): void
    {
        updateCachedSettings();
    }

    /**
     * Handle the Event "updated" event.
     */
    public function updated(Setting $event): void
    {
        updateCachedSettings();
    }

    /**
     * Handle the Event "deleted" event.
     */
    public function deleted(Setting $event): void
    {
        updateCachedSettings();
    }

    /**
     * Handle the Event "restored" event.
     */
    public function restored(Setting $event): void
    {
        updateCachedSettings();
    }

    /**
     * Handle the Event "force deleted" event.
     */
    public function forceDeleted(Setting $event): void
    {
        updateCachedSettings();
    }

}
