<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Class Cart
 *
 * Model untuk merepresentasikan keranjang belanja pengguna.
 *
 * @property int $id
 * @property int $user_id
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property-read \App\Models\User $user
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\CartItem[] $items
 */
class Cart extends Model
{
    /**
     * Atribut yang dapat diisi secara massal.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
    ];

    /**
     * Relasi default yang akan selalu dimuat (eager load).
     *
     * @var array<int, string>
     */
    protected $with = [
        'items',
    ];

    /**
     * Mendapatkan pengguna yang memiliki keranjang belanja ini.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Mendapatkan semua item di dalam keranjang belanja ini.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    /**
     * Menghitung total harga dari seluruh item di dalam keranjang.
     *
     * @return float
     */
    public function getTotalPrice(): float
    {
        $total = 0.0;
        foreach ($this->items as $item) {
            $total += $item->getSubtotal();
        }
        return $total;
    }

    /**
     * Menghitung total kuantitas fisik dari seluruh item di dalam keranjang.
     *
     * @return int
     */
    public function getTotalItems(): int
    {
        return (int) $this->items->sum('quantity');
    }

    /**
     * Menghitung jumlah baris item unik yang ada di dalam keranjang.
     *
     * @return int
     */
    public function getItemCount(): int
    {
        return $this->items->count();
    }
}
