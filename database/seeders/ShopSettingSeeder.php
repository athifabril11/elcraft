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
            'order_auto_complete_days'  => '7',
            // TODO: Replace with the real admin WhatsApp number (international format, no '+' or spaces).
            //       Example: '6281234567890' for +62 812-3456-7890
            'admin_whatsapp_number'     => '62XXXXXXXXXX',
        ];

        foreach ($defaults as $key => $value) {
            ShopSetting::updateOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}
