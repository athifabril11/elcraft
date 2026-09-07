<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryAdminTest extends TestCase
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
        $response = $this->get(route('admin.categories.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_customer_cannot_access_categories(): void
    {
        $customer = $this->customerUser();
        $response = $this->actingAs($customer)->get(route('admin.categories.index'));
        $response->assertForbidden();
    }

    public function test_admin_can_view_categories_list(): void
    {
        $admin = $this->adminUser();

        Category::create([
            'name' => 'Cincin Perak Asli',
            'slug' => 'cincin-perak-asli',
            'description' => 'Koleksi cincin perak pengrajin lokal',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.categories.index'));

        $response->assertOk();
        $response->assertViewIs('admin.categories.index');
        $response->assertSee('Cincin Perak Asli');
        $response->assertSee('Koleksi cincin perak pengrajin lokal');
    }

    public function test_admin_can_search_categories(): void
    {
        $admin = $this->adminUser();

        Category::create(['name' => 'Anting Mutiara', 'slug' => 'anting-mutiara', 'is_active' => true]);
        Category::create(['name' => 'Kalung Berlian', 'slug' => 'kalung-berlian', 'is_active' => true]);

        $response = $this->actingAs($admin)->get(route('admin.categories.index', ['q' => 'Mutiara']));
        $response->assertOk();
        $response->assertSee('Anting Mutiara');
        $response->assertDontSee('Kalung Berlian');
    }

    public function test_admin_can_create_category(): void
    {
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)->post(route('admin.categories.store'), [
            'name' => 'Gelang Tali Prasi',
            'description' => 'Gelang anyaman tangan etnik',
            'is_active' => 1,
        ]);

        $response->assertRedirect(route('admin.categories.index'));
        $this->assertDatabaseHas('categories', [
            'name' => 'Gelang Tali Prasi',
            'slug' => 'gelang-tali-prasi',
            'is_active' => 1,
        ]);
    }

    public function test_admin_can_update_category(): void
    {
        $admin = $this->adminUser();

        $category = Category::create([
            'name' => 'Bros Kebaya',
            'slug' => 'bros-kebaya',
            'description' => 'Aksesoris tradisional',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->put(route('admin.categories.update', $category), [
            'name' => 'Bros Kebaya Elegan',
            'description' => 'Aksesoris tradisional diperbarui',
            'is_active' => 1,
        ]);

        $response->assertRedirect(route('admin.categories.index'));
        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'name' => 'Bros Kebaya Elegan',
            'description' => 'Aksesoris tradisional diperbarui',
        ]);
    }

    public function test_admin_can_toggle_category_status(): void
    {
        $admin = $this->adminUser();

        $category = Category::create([
            'name' => 'Aksesoris Rambut',
            'slug' => 'aksesoris-rambut',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->patch(route('admin.categories.toggle', $category));

        $response->assertRedirect(route('admin.categories.index'));
        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'is_active' => false,
        ]);
    }

    public function test_admin_can_delete_empty_category(): void
    {
        $admin = $this->adminUser();

        $category = Category::create([
            'name' => 'Kategori Sementara',
            'slug' => 'kategori-sementara',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->delete(route('admin.categories.destroy', $category));

        $response->assertRedirect(route('admin.categories.index'));
        $this->assertDatabaseMissing('categories', [
            'id' => $category->id,
        ]);
    }

    public function test_admin_cannot_delete_category_with_products(): void
    {
        $admin = $this->adminUser();

        $category = Category::create([
            'name' => 'Kalung Liontin',
            'slug' => 'kalung-liontin',
            'is_active' => true,
        ]);

        Product::create([
            'category_id' => $category->id,
            'name' => 'Kalung Lotus Gold',
            'slug' => 'kalung-lotus-gold',
            'price' => 200000,
            'stock' => 5,
            'weight' => 50,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->delete(route('admin.categories.destroy', $category));

        $response->assertRedirect(route('admin.categories.index'));
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
        ]);
    }
}
