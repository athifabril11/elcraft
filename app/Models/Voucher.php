<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Voucher extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'code',
        'description',
        'discount_type',
        'discount_value',
        'min_purchase',
        'max_discount',
        'quota',
        'used_count',
        'start_date',
        'end_date',
        'is_active',
    ];

    protected $casts = [
        'discount_value' => 'decimal:2',
        'min_purchase'   => 'decimal:2',
        'max_discount'   => 'decimal:2',
        'start_date'     => 'date',
        'end_date'       => 'date',
        'is_active'      => 'boolean',
        'created_at'     => 'datetime',
    ];

    public function usages(): HasMany
    {
        return $this->hasMany(VoucherUsage::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Apakah voucher ini sudah pernah/sedang dipakai user tertentu?
     * True jika ada riwayat pemakaian (settlement) ATAU ada order aktif
     * (status selain 'dibatalkan') yang memakai voucher ini.
     * Order dibatalkan dianggap belum pernah transaksi — boleh pakai ulang.
     *
     * @param  int     $userId
     * @param  array   $ignoreOrderIds  ID order yang dikecualikan dari pengecekan
     *                                  orders (baris voucher_usages TIDAK terpengaruh).
     *                                  Dipakai checkout: pesanan abandoned pelanggan
     *                                  sendiri yang baru saja ditutup di Midtrans
     *                                  tidak boleh menghitung voucher sebagai "sudah dipakai".
     */
    public function isUsedBy(int $userId, array $ignoreOrderIds = []): bool
    {
        if ($this->usages()->where('user_id', $userId)->exists()) {
            return true;
        }

        $orders = $this->orders()
            ->where('user_id', $userId)
            ->where('status', '!=', 'dibatalkan');

        if ($ignoreOrderIds !== []) {
            $orders->whereNotIn('id', $ignoreOrderIds);
        }

        return $orders->exists();
    }

    // Cek apakah voucher valid
    public function isValid(float $totalPrice): bool
    {
        if (!$this->is_active) return false;
        if ($this->used_count >= $this->quota) return false;

        $now = now()->toDateString();
        if ($now < $this->start_date->toDateString()) return false;
        if ($now > $this->end_date->toDateString()) return false;
        if ($totalPrice < $this->min_purchase) return false;

        return true;
    }

    // Hitung potongan voucher
    public function calculateDiscount(float $totalPrice): float
    {
        if ($this->discount_type === 'percent') {
            $discount = $totalPrice * $this->discount_value / 100;
            if ($this->max_discount) {
                $discount = min($discount, (float) $this->max_discount);
            }
            return $discount;
        }

        return min((float) $this->discount_value, $totalPrice);
    }
}