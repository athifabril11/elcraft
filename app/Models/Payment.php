<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'order_id',
        'midtrans_order_id',
        'midtrans_transaction_id',
        'payment_method',
        'payment_type',
        'amount',
        'status',
        'paid_at',
        'expired_at',
        'snap_token',
        'response_data',
    ];

    protected $casts = [
        'amount'        => 'decimal:2',
        'paid_at'       => 'datetime',
        'expired_at'    => 'datetime',
        'response_data' => 'array',
        'created_at'    => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function isExpired(): bool
    {
        return $this->expired_at && now()->isAfter($this->expired_at);
    }

    public function isPaid(): bool
    {
        return $this->status === 'success';
    }
}