<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VoucherUsage extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'voucher_id',
        'user_id',
        'order_id',
        'discount_applied',
    ];

    protected $casts = [
        'discount_applied' => 'decimal:2',
        'used_at'          => 'datetime',
    ];

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}