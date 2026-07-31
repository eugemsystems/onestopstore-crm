<?php

namespace App\Models;

use App\Observers\OrderStatusObserver;
use App\Observers\SettingObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy([OrderStatusObserver::class])]
class OrderStatus extends Model
{
    public $incrementing = false;   // we insert API IDs
    protected $keyType = 'int';
    protected $guarded = [];



    /**
     * @return BelongsTo
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }


}
