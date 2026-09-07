<?php

namespace Tests\Feature\Admin;

use App\Models\Banner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BannerAdminTest extends TestCase
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
        $response = $this->get(route('admin.banners.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_customer_cannot_access_banners(): void
    {
        $customer = $this->customerUser();
        $response = $this->actingAs($customer)->get(route('admin.banners.index'));
        $response->assertForbidden();
    }

    public function test_admin_can_view_banners_list(): void
    {
        $admin = $this->adminUser();

        Banner::create([
            'title'       => 'Koleksi Emas Mawar 2026',
            'eyebrow'     => 'EXCLUSIVE SELECTION',
            'description' => 'Sentuhan keanggunan modern.',
            'button_text' => 'VIEW COLLECTION',
            'button_url'  => '/products',
            'image_path'  => 'banners/test_banner.jpg',
            'sort_order'  => 1,
            'is_active'   => true,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.banners.index'));

        $response->assertOk();
        $response->assertViewIs('admin.banners.index');
        $response->assertSee('Koleksi Emas Mawar 2026');
        $response->assertSee('EXCLUSIVE SELECTION');
    }

    public function test_admin_can_create_banner_with_uploaded_image(): void
    {
        Storage::fake('public');
        $admin = $this->adminUser();

        $file = UploadedFile::fake()->image('hero_banner.jpg', 1200, 500);

        $response = $this->actingAs($admin)->post(route('admin.banners.store'), [
            'title'       => 'Kilau Mutiara Air Tawar',
            'eyebrow'     => 'NEW ARRIVAL',
            'description' => 'Keindahan alami mutiara Nusantara.',
            'button_text' => 'BELANJA SEKARANG',
            'button_url'  => '/products?category=mutiara',
            'image'       => $file,
            'sort_order'  => 2,
            'is_active'   => '1',
        ]);

        $response->assertRedirect(route('admin.banners.index'));

        $this->assertDatabaseHas('banners', [
            'title'       => 'Kilau Mutiara Air Tawar',
            'eyebrow'     => 'NEW ARRIVAL',
            'button_text' => 'BELANJA SEKARANG',
            'is_active'   => true,
        ]);

        $banner = Banner::first();
        Storage::disk('public')->assertExists($banner->image_path);
    }

    public function test_admin_can_update_banner(): void
    {
        $admin = $this->adminUser();

        $banner = Banner::create([
            'title'       => 'Judul Lama',
            'eyebrow'     => 'EYEBROW LAMA',
            'image_path'  => 'banners/old.jpg',
            'button_text' => 'OLD CTA',
            'button_url'  => '/old',
            'sort_order'  => 1,
            'is_active'   => true,
        ]);

        $response = $this->actingAs($admin)->put(route('admin.banners.update', $banner->id), [
            'title'       => 'Judul Baru Diperbarui',
            'eyebrow'     => 'PROMO TERBARU',
            'button_text' => 'LIHAT SEKARANG',
            'button_url'  => '/koleksi-baru',
            'sort_order'  => 5,
            'is_active'   => '1',
        ]);

        $response->assertRedirect(route('admin.banners.index'));

        $this->assertDatabaseHas('banners', [
            'id'    => $banner->id,
            'title' => 'Judul Baru Diperbarui',
            'eyebrow' => 'PROMO TERBARU',
            'sort_order' => 5,
        ]);
    }

    public function test_admin_can_toggle_banner_status(): void
    {
        $admin = $this->adminUser();

        $banner = Banner::create([
            'title'      => 'Banner Toggle',
            'image_path' => 'banners/toggle.jpg',
            'is_active'  => true,
        ]);

        $response = $this->actingAs($admin)->patch(route('admin.banners.toggle', $banner->id));

        $response->assertRedirect();
        $this->assertFalse($banner->fresh()->is_active);

        // Toggle back to active
        $this->actingAs($admin)->patch(route('admin.banners.toggle', $banner->id));
        $this->assertTrue($banner->fresh()->is_active);
    }

    public function test_admin_can_delete_banner_and_file(): void
    {
        Storage::fake('public');
        $admin = $this->adminUser();

        $file = UploadedFile::fake()->image('to_delete.jpg');
        $path = $file->store('banners', 'public');

        $banner = Banner::create([
            'title'      => 'Banner Mau Dihapus',
            'image_path' => $path,
            'is_active'  => true,
        ]);

        Storage::disk('public')->assertExists($path);

        $response = $this->actingAs($admin)->delete(route('admin.banners.destroy', $banner->id));

        $response->assertRedirect();
        $this->assertDatabaseMissing('banners', ['id' => $banner->id]);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_active_banner_is_rendered_on_homepage(): void
    {
        Banner::create([
            'title'       => 'Kemilau Perhiasan Istimewa 2026',
            'eyebrow'     => 'LIMITED SPECIAL',
            'description' => 'Desain mewah dari atelier kami.',
            'button_text' => 'CEK SEKARANG',
            'button_url'  => '/products',
            'image_path'  => 'banners/custom_hero.jpg',
            'sort_order'  => 1,
            'is_active'   => true,
        ]);

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('Kemilau Perhiasan Istimewa 2026');
        $response->assertSee('LIMITED SPECIAL');
        $response->assertSee('CEK SEKARANG');
    }
}
