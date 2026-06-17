<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Gelang', 'description' => 'Koleksi gelang cantik untuk wanita'],
            ['name' => 'Kalung', 'description' => 'Kalung elegan untuk berbagai kesempatan'],
            ['name' => 'Cincin', 'description' => 'Cincin cantik pilihan wanita modern'],
            ['name' => 'Bros', 'description' => 'Bros unik untuk tampilan memukau'],
            ['name' => 'Anting', 'description' => 'Anting cantik untuk melengkapi penampilan'],
            ['name' => 'Aksesoris Rambut', 'description' => 'Aksesoris rambut terlengkap'],
        ];

        foreach ($categories as $category) {
            Category::updateOrCreate(
                ['slug' => Str::slug($category['name'])],
                [
                    'name' => $category['name'],
                    'description' => $category['description'],
                    'is_active' => true,
                ]
            );
        }
    }
}