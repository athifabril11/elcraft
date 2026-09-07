<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Models\Voucher;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VoucherAdminTest extends TestCase
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
        $response = $this->get(route('admin.vouchers.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_customer_cannot_access_vouchers(): void
    {
        $customer = $this->customerUser();
        $response = $this->actingAs($customer)->get(route('admin.vouchers.index'));
        $response->assertForbidden();
    }

    public function test_admin_can_view_vouchers_list(): void
    {
        $admin = $this->adminUser();

        Voucher::create([
            'code'           => 'DISKONHEMAT',
            'description'    => 'Promo Pembukaan',
            'discount_type'  => 'percent',
            'discount_value' => 15,
            'min_purchase'   => 100000,
            'max_discount'   => 50000,
            'quota'          => 100,
            'start_date'     => Carbon::today(),
            'end_date'       => Carbon::today()->addDays(30),
            'is_active'      => true,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.vouchers.index'));

        $response->assertOk();
        $response->assertViewIs('admin.vouchers.index');
        $response->assertSee('DISKONHEMAT');
        $response->assertSee('Promo Pembukaan');
        $response->assertSee('Potongan 15%');
    }

    public function test_admin_can_create_new_voucher(): void
    {
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)->post(route('admin.vouchers.store'), [
            'code'           => 'lebaran2026',
            'description'    => 'Spesial Hari Raya',
            'discount_type'  => 'nominal',
            'discount_value' => 50000,
            'min_purchase'   => 200000,
            'quota'          => 50,
            'start_date'     => Carbon::today()->format('Y-m-d'),
            'end_date'       => Carbon::today()->addDays(15)->format('Y-m-d'),
            'is_active'      => '1',
        ]);

        $response->assertRedirect(route('admin.vouchers.index'));

        $this->assertDatabaseHas('vouchers', [
            'code'           => 'LEBARAN2026', // Harus otomatis uppercase
            'discount_type'  => 'nominal',
            'discount_value' => 50000,
            'is_active'      => true,
        ]);
    }

    public function test_admin_can_update_voucher(): void
    {
        $admin = $this->adminUser();

        $voucher = Voucher::create([
            'code'           => 'OLDCODE',
            'description'    => 'Deskripsi Lama',
            'discount_type'  => 'percent',
            'discount_value' => 10,
            'min_purchase'   => 50000,
            'quota'          => 20,
            'start_date'     => Carbon::today(),
            'end_date'       => Carbon::today()->addDays(10),
            'is_active'      => true,
        ]);

        $response = $this->actingAs($admin)->put(route('admin.vouchers.update', $voucher->id), [
            'code'           => 'NEWCODE',
            'description'    => 'Deskripsi Baru',
            'discount_type'  => 'percent',
            'discount_value' => 20,
            'min_purchase'   => 75000,
            'quota'          => 30,
            'start_date'     => Carbon::today()->format('Y-m-d'),
            'end_date'       => Carbon::today()->addDays(20)->format('Y-m-d'),
            'is_active'      => '1',
        ]);

        $response->assertRedirect(route('admin.vouchers.index'));

        $this->assertDatabaseHas('vouchers', [
            'id'             => $voucher->id,
            'code'           => 'NEWCODE',
            'discount_value' => 20,
        ]);
    }

    public function test_admin_can_toggle_voucher_status(): void
    {
        $admin = $this->adminUser();

        $voucher = Voucher::create([
            'code'           => 'TOGGLEVOUCHER',
            'discount_type'  => 'nominal',
            'discount_value' => 10000,
            'quota'          => 10,
            'start_date'     => Carbon::today(),
            'end_date'       => Carbon::today()->addDays(10),
            'is_active'      => true,
        ]);

        $response = $this->actingAs($admin)->patch(route('admin.vouchers.toggle', $voucher->id));

        $response->assertRedirect();
        $this->assertFalse($voucher->fresh()->is_active);

        // Toggle back to true
        $this->actingAs($admin)->patch(route('admin.vouchers.toggle', $voucher->id));
        $this->assertTrue($voucher->fresh()->is_active);
    }

    public function test_admin_can_delete_voucher_without_usages(): void
    {
        $admin = $this->adminUser();

        $voucher = Voucher::create([
            'code'           => 'DELVOUCHER',
            'discount_type'  => 'nominal',
            'discount_value' => 15000,
            'quota'          => 5,
            'start_date'     => Carbon::today(),
            'end_date'       => Carbon::today()->addDays(5),
            'is_active'      => true,
        ]);

        $response = $this->actingAs($admin)->delete(route('admin.vouchers.destroy', $voucher->id));

        $response->assertRedirect();
        $this->assertDatabaseMissing('vouchers', ['id' => $voucher->id]);
    }
}
