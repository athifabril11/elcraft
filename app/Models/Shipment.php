<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}