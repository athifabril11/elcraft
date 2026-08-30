<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * ShopSetting
 *
 * Simple key-value store for shop configuration values.
 * Use ShopSetting::get($key, $default) and ShopSetting::set($key, $value).
 */
class ShopSetting extends Model
{
    protected $fillable = ['key', 'value'];

    /**
     * Retrieve a setting value by key.
     *
     * @param  string  $key
     * @param  mixed   $default  Value to return when the key does not exist
     * @return mixed
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = static::where('key', $key)->first();

        return $setting ? $setting->value : $default;
    }

    /**
     * Persist (upsert) a setting value.
     *
     * @param  string  $key
     * @param  mixed   $value
     * @return static
     */
    public static function set(string $key, mixed $value): static
    {
        return static::updateOrCreate(
            ['key' => $key],
            ['value' => $value],
        );
    }
}
