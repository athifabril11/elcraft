<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * ReturnRequest
 *
 * Represents a customer's return/refund claim for a delivered order.
 * Named ReturnRequest (not Return) because `return` is a reserved PHP keyword.
 *
 * Pipeline: diajukan → disetujui → diproses → selesai
 *                     → ditolak
 *
 * One return per order is enforced at the DB level (unique index on order_id).
 */
class ReturnRequest extends Model
{
    /**
     * The actual table name — overridden because the class name (ReturnRequest)
     * does not match Laravel's pluralisation convention for this table (returns).
     */
    protected $table = 'returns';

    protected $fillable = [
        'order_id',
        'user_id',
        'return_number',
        'resolution_type',
        'refund_amount',
        'reason',
        'evidence_photo_path',
        'status',
        'admin_notes',
        'resolved_at',
        'refund_status',
        'refunded_at',
    ];

    protected $casts = [
        'refund_amount' => 'decimal:2',
        'resolved_at'   => 'datetime',
        'refunded_at'   => 'datetime',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ReturnItem::class, 'return_id');
    }
}
