<?php

namespace Tests\Feature\Admin;

use App\Models\Address;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductAdminTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        return User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
    }

    private function customerUser(): User
    {
        return User::factory()->create(['role' => 'customer', 'email_verified_at' => now()]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get(route('admin.products.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_customer_cannot_access_products(): void
    {
        $customer = $this->customerUser();
        $response = $this->actingAs($customer)->get(route('admin.products.index'));
        $response->assertForbidden();
    }

    public function test_admin_can_view_products_list(): void
    {
        $admin = $this->adminUser();
        $category = Category::create(['name' => 'Cincin', 'slug' => 'cincin']);

        Product::create([
            'category_id' => $category->id,
            'name' => 'Cincin Rose Gold Elegan',
            'slug' => 'cincin-rose-gold-elegan',
            'sku' => 'ELC-RGD-001',
            'price' => 250000,
            'stock' => 15,
            'weight' => 50,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.products.index'));

        $response->assertOk();
        $response->assertViewIs('admin.products.index');
        $response->assertSee('Cincin Rose Gold Elegan');
        $response->assertSee('ELC-RGD-001');
    }

    public function test_admin_can_filter_products(): void
    {
        $admin = $this->adminUser();
        $cat1 = Category::create(['name' => 'Anting', 'slug' => 'anting']);
        $cat2 = Category::create(['name' => 'Gelang', 'slug' => 'gelang']);

        Product::create([
            'category_id' => $cat1->id,
            'name' => 'Anting Mutiara Air Tawar',
            'slug' => 'anting-mutiara-air-tawar',
            'price' => 120000,
            'stock' => 5,
            'weight' => 20,
            'is_active' => true,
        ]);

        Product::create([
            'category_id' => $cat2->id,
            'name' => 'Gelang Perak Anyam',
            'slug' => 'gelang-perak-anyam',
            'price' => 300000,
            'stock' => 0,
            'weight' => 60,
            'is_active' => true,
        ]);

        // Filter search
        $response = $this->actingAs($admin)->get(route('admin.products.index', ['q' => 'Mutiara']));
        $response->assertOk();
        $response->assertSee('Anting Mutiara Air Tawar');
        $response->assertDontSee('Gelang Perak Anyam');

        // Filter out of stock
        $responseStock = $this->actingAs($admin)->get(route('admin.products.index', ['stock' => 'out']));
        $responseStock->assertOk();
        $responseStock->assertSee('Gelang Perak Anyam');
        $responseStock->assertDontSee('Anting Mutiara Air Tawar');
    }

    public function test_admin_can_create_product_with_variants_and_images(): void
    {
        Storage::fake('public');
        $admin = $this->adminUser();
        $category = Category::create(['name' => 'Gelang', 'slug' => 'gelang']);

        $primaryImage = UploadedFile::fake()->image('gelang_primary.jpg', 600, 600);
        $additionalImage = UploadedFile::fake()->image('gelang_detail.jpg', 600, 600);

        $payload = [
            'name' => 'Gelang Kristal Bohemian',
            'category_id' => $category->id,
            'sku' => 'BHM-GLG-01',
            'price' => 175000,
            'stock' => 10,
            'weight' => 80,
            'description' => 'Kerajinan tangan berkualitas tinggi dengan kristal pilihan.',
            'is_active' => 1,
            'is_featured' => 1,
            'primary_image' => $primaryImage,
            'additional_images' => [$additionalImage],
            'variants' => [
                [
                    'variant_name' => 'Ukuran 16cm',
                    'sku' => 'BHM-GLG-01-16',
                    'additional_price' => 0,
                    'stock' => 5,
                ],
                [
                    'variant_name' => 'Ukuran 18cm',
                    'sku' => 'BHM-GLG-01-18',
                    'additional_price' => 15000,
                    'stock' => 7,
                ],
            ],
        ];

        $response = $this->actingAs($admin)->post(route('admin.products.store'), $payload);

        $response->assertRedirect(route('admin.products.index'));

        $this->assertDatabaseHas('products', [
            'name' => 'Gelang Kristal Bohemian',
            'slug' => 'gelang-kristal-bohemian',
            'category_id' => $category->id,
            'price' => 175000,
            'stock' => 12, // sync with sum of variants (5 + 7)
            'weight' => 80,
            'is_featured' => true,
        ]);

        $product = Product::where('slug', 'gelang-kristal-bohemian')->first();
        $this->assertNotNull($product);

        $this->assertDatabaseHas('product_variants', [
            'product_id' => $product->id,
            'variant_name' => 'Ukuran 16cm',
            'sku' => 'BHM-GLG-01-16',
            'stock' => 5,
        ]);

        $this->assertDatabaseHas('product_variants', [
            'product_id' => $product->id,
            'variant_name' => 'Ukuran 18cm',
            'additional_price' => 15000,
            'stock' => 7,
        ]);

        $this->assertDatabaseHas('product_images', [
            'product_id' => $product->id,
            'is_primary' => true,
        ]);

        $this->assertCount(2, $product->images);
    }

    public function test_admin_can_update_product(): void
    {
        $admin = $this->adminUser();
        $category = Category::create(['name' => 'Bros', 'slug' => 'bros']);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Bros Mutiara Klasik',
            'slug' => 'bros-mutiara-klasik',
            'price' => 85000,
            'stock' => 20,
            'weight' => 30,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->put(route('admin.products.update', $product), [
            'name' => 'Bros Mutiara Klasik Gold Edition',
            'category_id' => $category->id,
            'price' => 95000,
            'stock' => 25,
            'weight' => 35,
            'is_active' => 1,
        ]);

        $response->assertRedirect(route('admin.products.index'));

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Bros Mutiara Klasik Gold Edition',
            'price' => 95000,
            'stock' => 25,
        ]);
    }

    public function test_admin_can_toggle_product_status(): void
    {
        $admin = $this->adminUser();
        $category = Category::create(['name' => 'Liontin', 'slug' => 'liontin']);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Liontin Batu Akik',
            'slug' => 'liontin-batu-akik',
            'price' => 150000,
            'stock' => 10,
            'weight' => 40,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->patch(route('admin.products.toggle', $product));

        $response->assertRedirect(route('admin.products.index'));
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'is_active' => false,
        ]);
    }

    public function test_admin_can_delete_product(): void
    {
        $admin = $this->adminUser();
        $category = Category::create(['name' => 'Kalung', 'slug' => 'kalung']);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Kalung Manik Vintage',
            'slug' => 'kalung-manik-vintage',
            'price' => 110000,
            'stock' => 8,
            'weight' => 50,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->delete(route('admin.products.destroy', $product));

        $response->assertRedirect(route('admin.products.index'));
        $this->assertDatabaseMissing('products', [
            'id' => $product->id,
        ]);
    }

    public function test_admin_cannot_delete_product_with_order_items(): void
    {
        $admin = $this->adminUser();
        $category = Category::create(['name' => 'Cincin', 'slug' => 'cincin']);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Cincin Titanium Emas',
            'slug' => 'cincin-titanium-emas',
            'price' => 175000,
            'stock' => 5,
            'weight' => 20,
            'is_active' => true,
        ]);

        // Create address & order item attached to product
        $address = Address::create([
            'user_id' => $admin->id,
            'recipient_name' => 'Admin Penerima',
            'phone' => '08123456789',
            'province' => 'Jawa Barat',
            'province_id' => 9,
            'city' => 'Bandung',
            'city_id' => 22,
            'district' => 'Coblong',
            'postal_code' => '40111',
            'full_address' => 'Jl. Pengrajin No. 12',
            'is_default' => true,
        ]);

        $order = Order::create([
            'order_number' => 'ORD-TEST-001',
            'user_id' => $admin->id,
            'address_id' => $address->id,
            'subtotal' => 175000,
            'shipping_cost' => 10000,
            'total_amount' => 185000,
            'status' => 'pending',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'price' => $product->price,
            'quantity' => 1,
            'subtotal' => $product->price,
        ]);

        $response = $this->actingAs($admin)->delete(route('admin.products.destroy', $product));

        $response->assertRedirect(route('admin.products.index'));
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
        ]);
    }
}
