<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    public $incrementing = false;   // we insert API IDs
    protected $keyType = 'int';
    protected $guarded = [];

    protected $with = [
        'order_status:id,name,sequence,slug',
    ];

    protected $casts = [
        'billing_address' => 'array',    // or 'json' on recent Laravel
        'shipping_address'=> 'array',
        'subtotal'        => 'decimal:2',
        'discount_total'  => 'decimal:2',
        'tax_total'       => 'decimal:2',
        'shipping_total'  => 'decimal:2',
        'delivery_price'  => 'decimal:2',
        'payfast_fee'     => 'decimal:2',
        'total'           => 'decimal:2',
        'amount'          => 'decimal:2',
        'order_history'   => 'array',
        'delivered_at'    => 'datetime',
    ];


    public function order_items(): HasMany
    {
        return $this->hasMany(OrderProduct::class, 'order_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(OrderTransaction::class, 'order_id');
    }

    /**
     * @return BelongsTo
     */
    public function order_status(): BelongsTo
    {
        return $this->belongsTo(OrderStatus::class, 'order_status_id');
    }

}
