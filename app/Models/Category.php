<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'image',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // Relasi: 1 kategori punya banyak produk
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    // Scope: hanya kategori aktif
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}