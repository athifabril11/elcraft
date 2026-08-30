<?php

namespace Database\Seeders;

use App\Models\ShopSetting;
use Illuminate\Database\Seeder;

class ShopSettingSeeder extends Seeder
{
    /**
     * Seed default shop settings.
     * Uses updateOrCreate so it is safe to re-run (idempotent).
     */
    public function run(): void
    {
        $defaults = [
            'shipping_origin_city_id'   => '256',
            'shipping_origin_city_name' => 'Jember',
        ];

        foreach ($defaults as $key => $value) {
            ShopSetting::updateOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}
