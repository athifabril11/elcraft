<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductVariant extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'product_id',
        'variant_name',
        'variant_type',
        'additional_price',
        'stock',
        'sku',
        'is_active',
    ];

    protected $casts = [
        'additional_price' => 'decimal:2',
        'is_active'        => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    // Harga final termasuk tambahan variasi
    public function getFinalPriceAttribute(): float
    {
        return (float) $this->product->final_price + (float) $this->additional_price;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}