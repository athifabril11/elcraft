<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherUsage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VoucherSingleUsePerUserTest extends TestCase
{
    use RefreshDatabase;

    private function makeVoucher(array $overrides = []): Voucher
    {
        return Voucher::create(array_merge([
            'code'           => 'HEMAT50',
            'discount_type'  => 'percent',
            'discount_value' => 10,
            'min_purchase'   => 0,
            'max_discount'   => null,
            'quota'          => 100,
            'used_count'     => 0,
            'start_date'     => now()->subDay(),
            'end_date'       => now()->addDay(),
            'is_active'      => true,
        ], $overrides));
    }

    private function makeAddressFor(User $user)
    {
        return $user->addresses()->create([
            'label'          => 'Utama',
            'recipient_name' => $user->name,
            'phone'          => '081200000000',
            'province'       => 'Jawa Timur',
            'city'           => 'Jember',
            'district'       => 'Sumbersari',
            'postal_code'    => '68121',
            'full_address'   => 'Jl. Test No. 1',
        ]);
    }

    private function makeOrderFor(User $user, Voucher $voucher, string $status = 'pending'): Order
    {
        $address = $this->makeAddressFor($user);

        return Order::create([
            'order_number'     => 'ORD-SUV-' . uniqid(),
            'user_id'          => $user->id,
            'address_id'       => $address->id,
            'voucher_id'       => $voucher->id,
            'voucher_discount' => 5000,
            'subtotal'         => 50000,
            'total_amount'     => 45000,
            'status'           => $status,
        ]);
    }

    // ── Apply endpoint (POST /checkout/voucher/apply) ────────────────────────

    public function test_apply_rejected_when_user_already_used_voucher(): void
    {
        $user    = User::factory()->create();
        $voucher = $this->makeVoucher();

        $order = $this->makeOrderFor($user, $voucher, 'selesai');
        VoucherUsage::create([
            'voucher_id'       => $voucher->id,
            'user_id'          => $user->id,
            'order_id'         => $order->id,
            'discount_applied' => 5000,
        ]);

        $response = $this->actingAs($user)
            ->postJson(route('voucher.apply'), [
                'code'     => 'HEMAT50',
                'subtotal' => 100000,
            ])
            ->assertStatus(422)
            ->assertJsonPath('valid', false);

        $this->assertStringContainsString('sudah pernah', $response->json('message'));
    }

    public function test_apply_allowed_when_user_has_pending_order_with_voucher(): void
    {
        $user    = User::factory()->create();
        $voucher = $this->makeVoucher();

        // Order pending = sisa sesi Snap yang ditinggalkan; createSnapToken akan
        // mencoba menutupnya sebelum order baru, jadi preview (apply) juga
        // tidak boleh menghitungnya sebagai "sudah dipakai".
        $this->makeOrderFor($user, $voucher, 'pending');

        $this->actingAs($user)
            ->postJson(route('voucher.apply'), [
                'code'     => 'HEMAT50',
                'subtotal' => 100000,
            ])
            ->assertOk()
            ->assertJsonPath('valid', true)
            ->assertJsonPath('discount_amount', 10000);
    }

    public function test_apply_rejected_when_voucher_used_by_paid_order(): void
    {
        $user    = User::factory()->create();
        $voucher = $this->makeVoucher();

        // Order paid TANPA baris voucher_usages pun harus menolak —
        // pengecekan orders aktif menangkapnya.
        $this->makeOrderFor($user, $voucher, 'paid');

        $response = $this->actingAs($user)
            ->postJson(route('voucher.apply'), [
                'code'     => 'HEMAT50',
                'subtotal' => 100000,
            ])
            ->assertStatus(422)
            ->assertJsonPath('valid', false);

        $this->assertStringContainsString('sudah pernah', $response->json('message'));
    }

    public function test_apply_allowed_when_other_users_pending_order_with_voucher_exists(): void
    {
        $voucher = $this->makeVoucher();

        $otherUser = User::factory()->create();
        $this->makeOrderFor($otherUser, $voucher, 'pending');

        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('voucher.apply'), [
                'code'     => 'HEMAT50',
                'subtotal' => 100000,
            ])
            ->assertOk()
            ->assertJsonPath('valid', true);
    }

    public function test_apply_allowed_when_previous_order_was_cancelled(): void
    {
        $user    = User::factory()->create();
        $voucher = $this->makeVoucher();

        $this->makeOrderFor($user, $voucher, 'dibatalkan');

        $this->actingAs($user)
            ->postJson(route('voucher.apply'), [
                'code'     => 'HEMAT50',
                'subtotal' => 100000,
            ])
            ->assertOk()
            ->assertJsonPath('valid', true);
    }

    public function test_other_user_can_still_use_the_same_voucher(): void
    {
        $voucher = $this->makeVoucher();

        $otherUser = User::factory()->create();
        $order     = $this->makeOrderFor($otherUser, $voucher, 'selesai');
        VoucherUsage::create([
            'voucher_id'       => $voucher->id,
            'user_id'          => $otherUser->id,
            'order_id'         => $order->id,
            'discount_applied' => 5000,
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('voucher.apply'), [
                'code'     => 'HEMAT50',
                'subtotal' => 100000,
            ])
            ->assertOk()
            ->assertJsonPath('valid', true);
    }

    // ── Checkout server-side (POST /checkout/token) ──────────────────────────

    public function test_checkout_ignores_voucher_already_used_by_user(): void
    {
        $user = User::factory()->create();
        $this->makeAddressFor($user);

        $category = \App\Models\Category::create(['name' => 'Lilin', 'slug' => 'lilin-' . uniqid(), 'is_active' => true]);
        $product  = \App\Models\Product::create([
            'category_id' => $category->id,
            'name'        => 'Lilin Aromaterapi',
            'slug'        => 'lilin-aromaterapi-' . uniqid(),
            'price'       => 100000,
            'stock'       => 20,
            'weight'      => 50,
            'is_active'   => true,
        ]);
        $cart = $user->getOrCreateCart();
        $cart->items()->create(['product_id' => $product->id, 'quantity' => 1]);

        $voucher = $this->makeVoucher();
        $order   = $this->makeOrderFor($user, $voucher, 'selesai');
        VoucherUsage::create([
            'voucher_id'       => $voucher->id,
            'user_id'          => $user->id,
            'order_id'         => $order->id,
            'discount_applied' => 5000,
        ]);

        $shippingMock = \Mockery::mock(\App\Services\ShippingService::class);
        $shippingMock->shouldReceive('calculateCost')->once()->andReturn([
            ['code' => 'jne', 'service' => 'REG', 'description' => 'Reguler', 'cost' => 15000, 'etd' => '2-3'],
        ]);
        $this->app->instance(\App\Services\ShippingService::class, $shippingMock);

        $midtransMock = \Mockery::mock(\App\Services\MidtransService::class);
        $midtransMock->shouldReceive('createSnapToken')->once()->andReturn([
            'token' => 'test_snap_token_suv', 'redirect_url' => 'https://dummy.url',
        ]);
        $this->app->instance(\App\Services\MidtransService::class, $midtransMock);

        $this->actingAs($user)
            ->postJson(route('checkout.token'), [
                'recipient_name'  => 'Jane Doe',
                'recipient_phone' => '082100000001',
                'full_address'    => 'Jl. Sudirman No. 5',
                'city'            => 'Jakarta',
                'city_id'         => 151,
                'postal_code'     => '10110',
                'courier'         => 'jne',
                'courier_service' => 'jne-REG',
                'payment_method'  => 'gopay',
                'shipping_cost'   => 0,
                'voucher_code'    => 'HEMAT50',
            ])
            ->assertOk();

        $this->assertDatabaseHas('orders', [
            'user_id'          => $user->id,
            'voucher_discount' => 0,
            'total_amount'     => 115000, // 100000 + 15000 - 0
        ]);
    }

    // ── Voucher::isUsedBy dengan $ignoreOrderIds ─────────────────────────────

    public function test_is_used_by_true_when_usage_row_exists_even_if_orders_ignored(): void
    {
        $user    = User::factory()->create();
        $voucher = $this->makeVoucher();
        $order   = $this->makeOrderFor($user, $voucher, 'selesai');
        VoucherUsage::create([
            'voucher_id'       => $voucher->id,
            'user_id'          => $user->id,
            'order_id'         => $order->id,
            'discount_applied' => 5000,
        ]);

        $this->assertTrue($voucher->isUsedBy($user->id));
        $this->assertTrue(
            $voucher->isUsedBy($user->id, [$order->id]),
            'Baris voucher_usages tidak terpengaruh $ignoreOrderIds'
        );
    }

    public function test_is_used_by_excludes_ignored_order_ids_from_orders_check(): void
    {
        $user    = User::factory()->create();
        $voucher = $this->makeVoucher();
        $order   = $this->makeOrderFor($user, $voucher, 'pending');

        $this->assertTrue($voucher->isUsedBy($user->id));
        $this->assertFalse(
            $voucher->isUsedBy($user->id, [$order->id]),
            'Order yang di-ignore tidak boleh dihitung sebagai "sudah dipakai"'
        );
    }

    public function test_is_used_by_cancelled_orders_never_count_even_without_ignore(): void
    {
        $user    = User::factory()->create();
        $voucher = $this->makeVoucher();
        $order   = $this->makeOrderFor($user, $voucher, 'dibatalkan');

        $this->assertFalse($voucher->isUsedBy($user->id));
        $this->assertFalse($voucher->isUsedBy($user->id, [$order->id]));
    }

    public function test_is_used_by_paid_order_counts_even_when_other_order_ignored(): void
    {
        $user    = User::factory()->create();
        $voucher = $this->makeVoucher();
        $this->makeOrderFor($user, $voucher, 'paid');
        $pending = $this->makeOrderFor($user, $voucher, 'pending');

        $this->assertTrue(
            $voucher->isUsedBy($user->id, [$pending->id]),
            'Order paid tidak boleh diabaikan oleh $ignoreOrderIds order lain'
        );
    }
}
