<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = [
        'category_id',
        'name',
        'slug',
        'description',
        'price',
        'discount_type',
        'discount_value',
        'discount_start',
        'discount_end',
        'stock',
        'weight',
        'is_featured',
        'is_active',
    ];

    protected $casts = [
        'price'          => 'decimal:2',
        'discount_value' => 'decimal:2',
        'discount_start' => 'date',
        'discount_end'   => 'date',
        'is_featured'    => 'boolean',
        'is_active'      => 'boolean',
    ];

    // ─── Relasi ───────────────────────────────
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function wishlists(): HasMany
    {
        return $this->hasMany(Wishlist::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    // ─── Accessor: foto utama ─────────────────
    public function getPrimaryImageAttribute(): ?string
    {
        $primary = $this->images->where('is_primary', true)->first();
        return $primary?->image_url ?? $this->images->first()?->image_url;
    }

    // ─── Accessor: harga setelah diskon ───────
    public function getFinalPriceAttribute(): float
    {
        if ($this->discount_type === 'none' || !$this->isDiscountActive()) {
            return (float) $this->price;
        }

        if ($this->discount_type === 'percent') {
            return (float) $this->price - ($this->price * $this->discount_value / 100);
        }

        return max(0, (float) $this->price - (float) $this->discount_value);
    }

    // ─── Cek apakah diskon aktif ──────────────
    public function isDiscountActive(): bool
    {
        if ($this->discount_type === 'none') return false;
        if (!$this->discount_start || !$this->discount_end) return false;

        $now = now()->toDateString();
        return $now >= $this->discount_start->toDateString()
            && $now <= $this->discount_end->toDateString();
    }

    // ─── Accessor: persen diskon ──────────────
    public function getDiscountPercentAttribute(): int
    {
        if (!$this->isDiscountActive()) return 0;
        if ($this->discount_type === 'percent') {
            return (int) $this->discount_value;
        }
        if ($this->price > 0) {
            return (int) round(($this->discount_value / $this->price) * 100);
        }
        return 0;
    }

    // ─── Accessor: rating rata-rata ───────────
    public function getAverageRatingAttribute(): float
    {
        return round($this->reviews()->where('is_approved', true)->avg('rating') ?? 0, 1);
    }

    // ─── Scope ────────────────────────────────
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeInStock($query)
    {
        return $query->where('stock', '>', 0);
    }
}