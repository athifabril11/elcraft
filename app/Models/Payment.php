<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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

    public function isExpired(): bool
    {
        return $this->expired_at && now()->isAfter($this->expired_at);
    }

    /**
     * Nama metode pembayaran yang diformat ramah pengguna (misal: BCA Virtual Account).
     */
    public function getFormattedMethodAttribute(): string
    {
        $raw = $this->payment_method ?: $this->payment_type;
        if (!$raw) {
            return 'Belum Dipilih';
        }

        $map = [
            'bca_va'        => 'BCA Virtual Account',
            'bni_va'        => 'BNI Virtual Account',
            'bri_va'        => 'BRI Virtual Account',
            'other_va'      => 'Mandiri / VA Lainnya',
            'echannel'      => 'Mandiri Bill Payment',
            'bank_transfer' => 'Transfer Bank',
            'qris'          => 'QRIS',
            'gopay'         => 'GoPay',
            'shopeepay'     => 'ShopeePay',
            'credit_card'   => 'Kartu Kredit',
            'indomaret'     => 'Indomaret',
            'alfamart'      => 'Alfamart',
            'cstore'        => 'Convenience Store',
            'snap'          => 'Midtrans Snap',
        ];

        return $map[strtolower($raw)] ?? ucwords(str_replace('_', ' ', $raw));
    }
}