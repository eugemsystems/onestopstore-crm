<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderTransaction extends Model
{
    public $incrementing = false;
    protected $keyType = 'int';
    protected $guarded = [];
    protected $table = 'order_transactions';
}
