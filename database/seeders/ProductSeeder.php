<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\Category;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $categories = Category::all()->keyBy('slug');

        $products = [
            [
                'category_slug' => 'gelang',
                'name' => 'Gelang Emas Minimalis Rose Gold',
                'price' => 150000,
                'weight' => 15,
                'stock' => 50,
                'is_featured' => true,
                'description' => 'Gelang emas minimalis dengan warna rose gold yang menawan. Cocok untuk menyempurnakan tampilan kasual maupun formal Anda.',
                'variants' => [
                    ['variant_name' => '16 cm', 'variant_type' => 'Ukuran', 'additional_price' => 0, 'stock' => 25, 'sku' => 'GLG-EM-RG-16', 'is_active' => true],
                    ['variant_name' => '18 cm', 'variant_type' => 'Ukuran', 'additional_price' => 10000, 'stock' => 25, 'sku' => 'GLG-EM-RG-18', 'is_active' => true],
                ],
                'images' => [
                    ['image_url' => 'https://images.unsplash.com/photo-1611591437281-460bfbe1220a?q=80&w=600&auto=format&fit=crop', 'is_primary' => true, 'sort_order' => 1],
                ]
            ],
            [
                'category_slug' => 'kalung',
                'name' => 'Kalung Mutiara Air Tawar Premium',
                'price' => 299000,
                'weight' => 30,
                'stock' => 20,
                'is_featured' => true,
                'description' => 'Kalung dari mutiara air tawar asli pilihan dengan kilau alami yang mewah. Dilengkapi dengan pengait perak berlapis emas.',
                'discount_type' => 'percent',
                'discount_value' => 15,
                'discount_start' => now()->subDays(1)->toDateString(),
                'discount_end' => now()->addDays(15)->toDateString(),
                'variants' => [
                    ['variant_name' => 'Putih Mutiara', 'variant_type' => 'Warna', 'additional_price' => 0, 'stock' => 10, 'sku' => 'KLG-MUT-PT', 'is_active' => true],
                    ['variant_name' => 'Merah Muda', 'variant_type' => 'Warna', 'additional_price' => 15000, 'stock' => 10, 'sku' => 'KLG-MUT-MM', 'is_active' => true],
                ],
                'images' => [
                    ['image_url' => 'https://images.unsplash.com/photo-1599643478518-a784e5dc4c8f?q=80&w=600&auto=format&fit=crop', 'is_primary' => true, 'sort_order' => 1],
                ]
            ],
            [
                'category_slug' => 'cincin',
                'name' => 'Cincin Solitaire Sterling Silver 925',
                'price' => 185000,
                'weight' => 10,
                'stock' => 40,
                'is_featured' => false,
                'description' => 'Cincin perak sterling 925 dengan batu zirkonia potongan solitaire yang berkilau indah. Desain klasik yang tidak lekang oleh waktu.',
                'discount_type' => 'nominal',
                'discount_value' => 20000,
                'discount_start' => now()->subDays(2)->toDateString(),
                'discount_end' => now()->addDays(10)->toDateString(),
                'variants' => [
                    ['variant_name' => 'Size 6', 'variant_type' => 'Ukuran', 'additional_price' => 0, 'stock' => 15, 'sku' => 'CCN-SLV-S6', 'is_active' => true],
                    ['variant_name' => 'Size 7', 'variant_type' => 'Ukuran', 'additional_price' => 0, 'stock' => 15, 'sku' => 'CCN-SLV-S7', 'is_active' => true],
                    ['variant_name' => 'Size 8', 'variant_type' => 'Ukuran', 'additional_price' => 5000, 'stock' => 10, 'sku' => 'CCN-SLV-S8', 'is_active' => true],
                ],
                'images' => [
                    ['image_url' => 'https://images.unsplash.com/photo-1605100804763-247f67b3557e?q=80&w=600&auto=format&fit=crop', 'is_primary' => true, 'sort_order' => 1],
                ]
            ],
            [
                'category_slug' => 'bros',
                'name' => 'Bros Kebaya Klasik Lapis Emas',
                'price' => 85000,
                'weight' => 40,
                'stock' => 40,
                'is_featured' => true,
                'description' => 'Bros kebaya etnik dengan detail ukiran klasik berlapis emas. Pilihan sempurna untuk melengkapi kebaya atau busana tradisional Anda.',
                'variants' => [
                    ['variant_name' => 'Emas', 'variant_type' => 'Warna', 'additional_price' => 0, 'stock' => 20, 'sku' => 'BRS-KB-EM', 'is_active' => true],
                    ['variant_name' => 'Silver', 'variant_type' => 'Warna', 'additional_price' => 0, 'stock' => 20, 'sku' => 'BRS-KB-SL', 'is_active' => true],
                ],
                'images' => [
                    ['image_url' => 'https://images.unsplash.com/photo-1535632066927-ab7c9ab60908?q=80&w=600&auto=format&fit=crop', 'is_primary' => true, 'sort_order' => 1],
                ]
            ],
            [
                'category_slug' => 'anting',
                'name' => 'Anting Hijab Rumbai Korea',
                'price' => 45000,
                'weight' => 12,
                'stock' => 60,
                'is_featured' => false,
                'description' => 'Anting rumbai panjang gaya Korea yang modis dan ringan dipakai. Sangat cocok dipadukan dengan hijab modern.',
                'variants' => [
                    ['variant_name' => 'Merah', 'variant_type' => 'Warna', 'additional_price' => 0, 'stock' => 20, 'sku' => 'ANT-RB-MR', 'is_active' => true],
                    ['variant_name' => 'Hitam', 'variant_type' => 'Warna', 'additional_price' => 0, 'stock' => 20, 'sku' => 'ANT-RB-HT', 'is_active' => true],
                    ['variant_name' => 'Pink', 'variant_type' => 'Warna', 'additional_price' => 0, 'stock' => 20, 'sku' => 'ANT-RB-PK', 'is_active' => true],
                ],
                'images' => [
                    ['image_url' => 'https://images.unsplash.com/photo-1630019852942-f89202989a59?q=80&w=600&auto=format&fit=crop', 'is_primary' => true, 'sort_order' => 1],
                ]
            ],
            [
                'category_slug' => 'aksesoris-rambut',
                'name' => 'Jepit Rambut Mutiara Korea Set',
                'price' => 35000,
                'weight' => 25,
                'stock' => 80,
                'is_featured' => true,
                'description' => 'Set jepit rambut mutiara ala Korea yang sedang tren. Terdiri dari berbagai model cantik untuk memperindah tatanan rambut Anda.',
                'discount_type' => 'percent',
                'discount_value' => 10,
                'discount_start' => now()->subDays(5)->toDateString(),
                'discount_end' => now()->addDays(20)->toDateString(),
                'variants' => [
                    ['variant_name' => 'Set A (3 Pcs)', 'variant_type' => 'Pilihan', 'additional_price' => 0, 'stock' => 40, 'sku' => 'JPT-MUT-SA', 'is_active' => true],
                    ['variant_name' => 'Set B (5 Pcs)', 'variant_type' => 'Pilihan', 'additional_price' => 10000, 'stock' => 40, 'sku' => 'JPT-MUT-SB', 'is_active' => true],
                ],
                'images' => [
                    ['image_url' => 'https://images.unsplash.com/photo-1576243345690-4e4b79b63288?q=80&w=600&auto=format&fit=crop', 'is_primary' => true, 'sort_order' => 1],
                ]
            ],
            [
                'category_slug' => 'gelang',
                'name' => 'Gelang Manik-Manik Estetik Pastel',
                'price' => 25000,
                'weight' => 10,
                'stock' => 100,
                'is_featured' => false,
                'description' => 'Gelang manik-manik handmade dengan kombinasi warna pastel estetik. Sangat manis untuk melengkapi gaya santai sehari-hari.',
                'variants' => [
                    ['variant_name' => 'Peach', 'variant_type' => 'Warna', 'additional_price' => 0, 'stock' => 35, 'sku' => 'GLG-MN-PC', 'is_active' => true],
                    ['variant_name' => 'Lilac', 'variant_type' => 'Warna', 'additional_price' => 0, 'stock' => 35, 'sku' => 'GLG-MN-LL', 'is_active' => true],
                    ['variant_name' => 'Mint', 'variant_type' => 'Warna', 'additional_price' => 0, 'stock' => 30, 'sku' => 'GLG-MN-MT', 'is_active' => true],
                ],
                'images' => [
                    ['image_url' => 'https://images.unsplash.com/photo-1573408301185-9146fe634ad0?q=80&w=600&auto=format&fit=crop', 'is_primary' => true, 'sort_order' => 1],
                ]
            ],
            [
                'category_slug' => 'kalung',
                'name' => 'Kalung Layer Choker Perak 925',
                'price' => 125000,
                'weight' => 20,
                'stock' => 35,
                'is_featured' => false,
                'description' => 'Kalung choker dua/tiga lapis berbahan perak 925 antikarat. Desain minimalis yang memberikan kesan jenjang pada leher.',
                'variants' => [
                    ['variant_name' => 'Double Layer', 'variant_type' => 'Gaya', 'additional_price' => 0, 'stock' => 20, 'sku' => 'KLG-LYR-DL', 'is_active' => true],
                    ['variant_name' => 'Triple Layer', 'variant_type' => 'Gaya', 'additional_price' => 15000, 'stock' => 15, 'sku' => 'KLG-LYR-TL', 'is_active' => true],
                ],
                'images' => [
                    ['image_url' => 'https://images.unsplash.com/photo-1515562141207-7a88fb7ce338?q=80&w=600&auto=format&fit=crop', 'is_primary' => true, 'sort_order' => 1],
                ]
            ],
            [
                'category_slug' => 'cincin',
                'name' => 'Cincin Titanium Anti Karat Couple',
                'price' => 95000,
                'weight' => 15,
                'stock' => 50,
                'is_featured' => false,
                'description' => 'Cincin bahan titanium berkualitas premium yang tidak berkarat dan tidak memudar. Cocok sebagai cincin couple/tunangan.',
                'discount_type' => 'nominal',
                'discount_value' => 15000,
                'discount_start' => now()->subDays(1)->toDateString(),
                'discount_end' => now()->addDays(15)->toDateString(),
                'variants' => [
                    ['variant_name' => 'Pria (Size 9)', 'variant_type' => 'Ukuran', 'additional_price' => 0, 'stock' => 25, 'sku' => 'CCN-TTN-P9', 'is_active' => true],
                    ['variant_name' => 'Wanita (Size 6)', 'variant_type' => 'Ukuran', 'additional_price' => 0, 'stock' => 25, 'sku' => 'CCN-TTN-W6', 'is_active' => true],
                ],
                'images' => [
                    ['image_url' => 'https://images.unsplash.com/photo-1603561591411-07134e71a2a9?q=80&w=600&auto=format&fit=crop', 'is_primary' => true, 'sort_order' => 1],
                ]
            ],
            [
                'category_slug' => 'bros',
                'name' => 'Bros Hijab Bunga Rajut Handmade',
                'price' => 15000,
                'weight' => 8,
                'stock' => 120,
                'is_featured' => false,
                'description' => 'Bros hijab bentuk bunga cantik yang dirajut dengan tangan secara detail. Memberikan sentuhan manis dan elegan pada hijab Anda.',
                'variants' => [
                    ['variant_name' => 'Merah Cabe', 'variant_type' => 'Warna', 'additional_price' => 0, 'stock' => 40, 'sku' => 'BRS-RJ-MR', 'is_active' => true],
                    ['variant_name' => 'Kuning Kunyit', 'variant_type' => 'Warna', 'additional_price' => 0, 'stock' => 40, 'sku' => 'BRS-RJ-KN', 'is_active' => true],
                    ['variant_name' => 'Biru Navy', 'variant_type' => 'Warna', 'additional_price' => 0, 'stock' => 40, 'sku' => 'BRS-RJ-BR', 'is_active' => true],
                ],
                'images' => [
                    ['image_url' => 'https://images.unsplash.com/photo-1607082348824-0a96f2a4b9da?q=80&w=600&auto=format&fit=crop', 'is_primary' => true, 'sort_order' => 1],
                ]
            ],
        ];

        foreach ($products as $p) {
            $category = $categories->get($p['category_slug']);
            if (!$category) continue;

            $product = Product::updateOrCreate(
                ['slug' => Str::slug($p['name'])],
                [
                    'category_id' => $category->id,
                    'name' => $p['name'],
                    'description' => $p['description'],
                    'price' => $p['price'],
                    'discount_type' => $p['discount_type'] ?? 'none',
                    'discount_value' => $p['discount_value'] ?? 0,
                    'discount_start' => $p['discount_start'] ?? null,
                    'discount_end' => $p['discount_end'] ?? null,
                    'stock' => $p['stock'],
                    'weight' => $p['weight'],
                    'is_featured' => $p['is_featured'] ?? false,
                    'is_active' => true,
                ]
            );

            // Bersihkan relasi lama agar tidak duplikat saat dijalankan berulang
            $product->variants()->delete();
            $product->images()->delete();

            // Tambahkan variasi baru
            foreach ($p['variants'] as $v) {
                $product->variants()->create($v);
            }

            // Tambahkan gambar baru
            foreach ($p['images'] as $img) {
                $product->images()->create($img);
            }
        }
    }
}