<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItemMessageMention extends Model
{
    protected $guarded = [];

    public function message(): BelongsTo { return $this->belongsTo(OrderItemMessage::class, 'message_id'); }
    public function mentionedUser(): BelongsTo { return $this->belongsTo(User::class, 'mentioned_user_id'); }
}
