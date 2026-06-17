<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    protected $fillable = [
        'order_number',
        'user_id',
        'address_id',
        'voucher_id',
        'subtotal',
        'discount_amount',
        'voucher_discount',
        'shipping_cost',
        'total_amount',
        'status',
        'notes',
    ];

    protected $casts = [
        'subtotal'         => 'decimal:2',
        'discount_amount'  => 'decimal:2',
        'voucher_discount' => 'decimal:2',
        'shipping_cost'    => 'decimal:2',
        'total_amount'     => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function address(): BelongsTo
    {
        return $this->belongsTo(Address::class);
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    public function shipment(): HasOne
    {
        return $this->hasOne(Shipment::class);
    }
}