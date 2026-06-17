<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Class CartItem
 *
 * Model untuk merepresentasikan item individual di dalam keranjang belanja.
 *
 * @property int $id
 * @property int $cart_id
 * @property int $product_id
 * @property int|null $variant_id
 * @property int $quantity
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property-read \App\Models\Cart $cart
 * @property-read \App\Models\Product $product
 * @property-read \App\Models\ProductVariant|null $variant
 */
class CartItem extends Model
{
    /**
     * Atribut yang dapat diisi secara massal.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'cart_id',
        'product_id',
        'variant_id',
        'quantity',
    ];

    /**
     * Relasi default yang akan selalu dimuat (eager load).
     *
     * @var array<int, string>
     */
    protected $with = [
        'product.images',
        'variant',
    ];

    /**
     * Mendapatkan keranjang belanja induk dari item ini.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    /**
     * Mendapatkan produk yang diasosiasikan dengan item ini.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Mendapatkan varian produk yang dipilih (jika ada).
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    /**
     * Menghitung harga satuan item (harga produk + harga tambahan varian).
     *
     * @return float
     */
    public function getUnitPrice(): float
    {
        $basePrice = $this->product->final_price;
        $additionalPrice = $this->variant ? (float) $this->variant->additional_price : 0.0;
        
        return $basePrice + $additionalPrice;
    }

    /**
     * Menghitung subtotal harga berdasarkan kuantitas item.
     *
     * @return float
     */
    public function getSubtotal(): float
    {
        return $this->getUnitPrice() * $this->quantity;
    }

    /**
     * Mendapatkan URL gambar utama produk, atau gambar placeholder jika tidak tersedia.
     *
     * @return string
     */
    public function getPrimaryImage(): string
    {
        return $this->product->primary_image ?? 'https://images.unsplash.com/photo-1599643478518-a784e5dc4c8f?q=80&w=600&auto=format&fit=crop';
    }
}
