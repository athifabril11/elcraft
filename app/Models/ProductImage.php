<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductImage extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'product_id',
        'image_url',
        'is_primary',
        'sort_order',
    ];

    protected $casts = [
        'is_primary'  => 'boolean',
        'created_at'  => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}