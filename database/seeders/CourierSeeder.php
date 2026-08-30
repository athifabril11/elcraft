<?php

namespace Database\Seeders;

use App\Models\Courier;
use Illuminate\Database\Seeder;

class CourierSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $couriers = [
            ['code' => 'jne',  'name' => 'JNE',           'is_active' => true],
            ['code' => 'jnt',  'name' => 'J&T Express',   'is_active' => true],
            ['code' => 'pos',  'name' => 'POS Indonesia', 'is_active' => true],
            ['code' => 'tiki', 'name' => 'TIKI',          'is_active' => true],
            ['code' => 'lion', 'name' => 'Lion Parcel',   'is_active' => true],
            ['code' => 'sap',  'name' => 'SAP Express',   'is_active' => true],
        ];

        foreach ($couriers as $courier) {
            Courier::updateOrCreate(
                ['code' => $courier['code']],
                [
                    'name'      => $courier['name'],
                    'is_active' => $courier['is_active'],
                ]
            );
        }

        Courier::clearCache();
    }
}
