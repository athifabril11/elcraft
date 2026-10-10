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
        'sku',
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
        'rating_avg'     => 'decimal:1',
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

    /**
     * Mendapatkan semua item keranjang belanja yang mengacu pada produk ini.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
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
        return (float) ($this->rating_avg ?? 0.0);
    }

    /**
     * Mendapatkan distribusi jumlah bintang untuk ulasan yang disetujui.
     */
    public function getStarDistributionAttribute(): array
    {
        return $this->reviews()
            ->where('is_approved', true)
            ->selectRaw('rating, count(*) as count')
            ->groupBy('rating')
            ->pluck('count', 'rating')
            ->toArray();
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

    // ─── Accessor: effective display stock ────
    /**
     * Returns the effective stock for display purposes.
     *
     * - Products WITH at least one variant: sum of stock of ACTIVE variants.
     *   If the variants relation is already loaded, uses the loaded collection;
     *   otherwise queries the database.
     * - Products WITHOUT variants: returns the products.stock column value.
     *
     * @return int
     */
    public function getDisplayStockAttribute(): int
    {
        if ($this->relationLoaded('variants')) {
            // If ANY variant rows exist (active or not) this is a variant product
            if ($this->variants->isNotEmpty()) {
                return (int) $this->variants->where('is_active', true)->sum('stock');
            }
        } else {
            // Relation not loaded — query the DB once
            if ($this->variants()->exists()) {
                return (int) $this->variants()->where('is_active', true)->sum('stock');
            }
        }

        return (int) $this->stock;
    }

    // ─── Method: sync stock column from variants ──
    /**
     * Recomputes products.stock as the sum of stock of active variants and
     * persists it using a direct query (no model events fired).
     *
     * For products without any variant, this is a no-op.
     */
    public function recalculateStockFromVariants(): void
    {
        $hasVariants = $this->variants()->exists();
        if (!$hasVariants) {
            return;
        }

        $computed = (int) $this->variants()->where('is_active', true)->sum('stock');

        Product::where('id', $this->id)->update(['stock' => $computed]);

        // Sync in-memory attribute so the model reflects the new value
        $this->attributes['stock'] = $computed;
    }
}