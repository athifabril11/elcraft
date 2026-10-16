<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Shipment extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'order_id',
        'courier',
        'service',
        'tracking_number',
        'shipping_cost',
        'estimated_days',
        'shipped_at',
        'delivered_at',
    ];

    protected $casts = [
        'shipping_cost' => 'decimal:2',
        'shipped_at'    => 'datetime',
        'delivered_at'  => 'datetime',
        'created_at'    => 'datetime',
    ];
}