<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

/**
 * ShippingDiscountRule
 *
 * Represents an admin-configured shipping discount applied automatically
 * to shipping costs for a specific province or city destination.
 *
 * @property string  $scope           'province' or 'city'
 * @property int     $scope_id        Province ID or City ID (RajaOngkir)
 * @property string  $scope_name      Human-readable name for display
 * @property string  $discount_type   'free' or 'fixed'
 * @property int|null $discount_amount Amount in Rupiah (only for 'fixed' type)
 * @property string|null $description  Admin note
 * @property bool    $is_active
 */
class ShippingDiscountRule extends Model
{
    protected $fillable = [
        'scope',
        'scope_id',
        'scope_name',
        'discount_type',
        'discount_amount',
        'description',
        'is_active',
    ];

    protected $casts = [
        'scope_id'        => 'integer',
        'discount_amount' => 'integer',
        'is_active'       => 'boolean',
    ];

    /** Scope: only active rules */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Scope: rules for a specific city */
    public function scopeForCity(Builder $query, int $cityId): Builder
    {
        return $query->where('scope', 'city')->where('scope_id', $cityId);
    }

    /** Scope: rules for a specific province */
    public function scopeForProvince(Builder $query, int $provinceId): Builder
    {
        return $query->where('scope', 'province')->where('scope_id', $provinceId);
    }
}
