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
        'courier',
        'courier_service',
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

    public function returnRequest(): HasOne
    {
        return $this->hasOne(ReturnRequest::class);
    }

    /**
     * Nama kurir dan layanan yang diformat rapi (misal: JNE (REG)).
     */
    public function getFormattedCourierAttribute(): string
    {
        if (!$this->courier) {
            return 'Kurir Standar';
        }
        $courierName = strtoupper($this->courier);
        if ($this->courier_service) {
            return "{$courierName} ({$this->courier_service})";
        }
        return $courierName;
    }

    /**
     * Waktu kedaluwarsa batas pembayaran.
     */
    public function getPaymentExpiresAtAttribute(): ?\Carbon\Carbon
    {
        if ($this->relationLoaded('payment') && $this->payment?->expired_at) {
            return $this->payment->expired_at;
        }
        if ($this->payment?->expired_at) {
            return $this->payment->expired_at;
        }
        return $this->created_at ? $this->created_at->copy()->addHours(24) : null;
    }

    /**
     * Cek apakah batas waktu pembayaran sudah lewat.
     */
    public function isPaymentExpired(): bool
    {
        $expiresAt = $this->payment_expires_at;
        return $expiresAt ? now()->isAfter($expiresAt) : false;
    }
}