<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderItemMessage extends Model
{
    protected $guarded = [];

    public function item(): BelongsTo { return $this->belongsTo(OrderProduct::class, 'order_product_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class, 'user_id'); }
    public function likes(): HasMany { return $this->hasMany(OrderItemMessageLike::class, 'message_id'); }
    public function views(): HasMany { return $this->hasMany(OrderItemMessageView::class, 'message_id'); }
    public function mentions(): HasMany { return $this->hasMany(OrderItemMessageMention::class, 'message_id'); }
}
