<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Courier extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saved(function () {
            static::clearCache();
        });

        static::deleted(function () {
            static::clearCache();
        });
    }

    /**
     * Scope a query to only include active couriers.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Clear cached courier lists.
     */
    public static function clearCache(): void
    {
        Cache::forget('active_couriers_codes');
        Cache::forget('active_couriers_list');
    }

    /**
     * Get active courier codes as an array: ['jne', 'jnt', ...]
     * Cached for 2 minutes.
     *
     * @return array<int, string>
     */
    public static function getActiveCodes(): array
    {
        return Cache::remember('active_couriers_codes', now()->addMinutes(2), function () {
            try {
                // If records exist in DB, strictly respect database is_active state
                if (static::query()->exists()) {
                    return static::active()->pluck('code')->all();
                }
            } catch (\Throwable $e) {
                // In case migration hasn't run yet
            }

            return array_keys(config('rajaongkir.couriers', []));
        });
    }

    /**
     * Get active couriers as an associative array [code => name]: ['jne' => 'JNE', ...]
     * Cached for 2 minutes.
     *
     * @return array<string, string>
     */
    public static function getActiveList(): array
    {
        return Cache::remember('active_couriers_list', now()->addMinutes(2), function () {
            try {
                if (static::query()->exists()) {
                    return static::active()->pluck('name', 'code')->all();
                }
            } catch (\Throwable $e) {
                // In case migration hasn't run yet
            }

            return config('rajaongkir.couriers', []);
        });
    }
}
