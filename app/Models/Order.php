<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class OrderItem extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'order_id',
        'product_id',
        'variant_id',
        'product_name',
        'variant_name',
        'price',
        'discount_amount',
        'quantity',
        'subtotal',
    ];

    protected $casts = [
        'price'           => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'subtotal'        => 'decimal:2',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function review(): HasOne
    {
        return $this->hasOne(Review::class);
    }

    // Cek sudah diulas
    public function hasReview(): bool
    {
        return $this->review()->exists();
    }
}