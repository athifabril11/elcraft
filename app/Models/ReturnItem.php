<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ReturnItem
 *
 * Represents a single line item within a ReturnRequest.
 * Each row links an OrderItem to a ReturnRequest with the quantity being returned.
 * The compound unique index on (return_id, order_item_id) prevents the same
 * order item from being listed twice in one return request.
 */
class ReturnItem extends Model
{
    protected $fillable = [
        'return_id',
        'order_item_id',
        'quantity',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function returnRequest(): BelongsTo
    {
        return $this->belongsTo(ReturnRequest::class, 'return_id');
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
}
