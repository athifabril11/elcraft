<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Review extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'product_id',
        'user_id',
        'order_item_id',
        'rating',
        'comment',
        'image',
        'is_approved',
    ];

    protected $casts = [
        'is_approved' => 'boolean',
        'created_at'  => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function scopeApproved($query)
    {
        return $query->where('is_approved', true);
    }
}