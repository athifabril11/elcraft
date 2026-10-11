<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Models\Voucher;
use App\Services\MidtransService;
use App\Services\ShippingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * AbandonedOrderCancelTest
 *
 * Regression/behavior tests for abandoned pending orders during re-checkout:
 *   - Midtrans Cancel API MUST be called for each abandoned pending order
 *     (otherwise the old Snap session stays payable after local cancellation)
 *   - local 'dibatalkan' only when Midtrans outcome is cancelled/not_found
 *   - outcome cannot_modify/error leaves the old order untouched (retry later)
 *
 * The Midtrans call happens OUTSIDE the checkout DB transaction.
 */
class AbandonedOrderCancelTest extends TestCase
{
    use RefreshDatabase;

    private function setupShippingAndSnapMocks(string $cancelOutcome): void
    {
        $shippingMock = Mockery::mock(ShippingService::class);
        $shippingMock->shouldReceive('calculateCost')
            ->once()
            ->andReturn([
                ['code' => 'jne', 'service' => 'REG', 'description' => 'Layanan Reguler', 'cost' => 10000, 'etd' => '2'],
            ]);
        $this->app->instance(ShippingService::class, $shippingMock);

        $midtransMock = Mockery::mock(MidtransService::class);
        $midtransMock->shouldReceive('createSnapToken')
            ->once()
            ->andReturn(['token' => 'new_token', 'redirect_url' => 'https://dummy.url']);
        $midtransMock->shouldReceive('cancelTransaction')
            ->once()
            ->andReturn([
                'outcome'     => $cancelOutcome,
                'status_code' => $cancelOutcome === 'cancelled' ? 200 : 412,
                'message'     => '',
            ]);
        $this->app->instance(MidtransService::class, $midtransMock);
    }

    /** @return array{0: User, 1: Order, 2: Payment} */
    private function setupUserWithAbandonedPendingOrder(): array
    {
        $user = User::factory()->create();
        $category = Category::create(['name' => 'Kalung', 'slug' => 'kalung-ab-' . uniqid(), 'is_active' => true]);
        $product = Product::create([
            'category_id' => $category->id,
            'name'        => 'Kalung Silver',
            'slug'        => 'kalung-silver-ab-' . uniqid(),
            'price'       => 50000,
            'stock'       => 10,
            'weight'      => 10,
            'is_active'   => true,
        ]);

        $address = $user->addresses()->create([
            'label'          => 'Utama',
            'recipient_name' => 'Jane Doe',
            'phone'          => '081111111111',
            'province'       => 'Jawa Barat',
            'city'           => 'Bandung',
            'district'       => 'Coblong',
            'postal_code'    => '40135',
            'full_address'   => 'Jl. Abandoned No. 1',
        ]);

        $oldOrder = Order::create([
            'order_number' => 'ELCRAFT-ABANDONED-' . uniqid(),
            'user_id'      => $user->id,
            'address_id'   => $address->id,
            'subtotal'     => 50000,
            'total_amount' => 50000,
            'status'       => 'pending',
        ]);

        $oldPayment = Payment::create([
            'order_id'          => $oldOrder->id,
            'midtrans_order_id' => $oldOrder->order_number,
            'amount'            => 50000,
            'status'            => 'pending',
            'snap_token'        => 'old_snap_token',
        ]);

        $cart = $user->getOrCreateCart();
        $cart->items()->create([
            'product_id' => $product->id,
            'quantity'   => 1,
        ]);

        return [$user, $oldOrder, $oldPayment];
    }

    private function postCheckout(User $user): \Illuminate\Testing\TestResponse
    {
        return $this
            ->actingAs($user)
            ->postJson(route('checkout.token'), [
                'recipient_name'  => 'Jane Doe',
                'recipient_phone' => '081111111111',
                'full_address'    => 'Jl. Abandoned No. 1',
                'city'            => 'Bandung',
                'city_id'         => 23,
                'postal_code'     => '40135',
                'courier'         => 'jne',
                'courier_service' => 'jne-REG',
                'payment_method'  => 'gopay',
                'shipping_cost'   => 0,
            ]);
    }

    public function test_checkout_cancels_abandoned_pending_order_at_midtrans(): void
    {
        [$user, $oldOrder, $oldPayment] = $this->setupUserWithAbandonedPendingOrder();
        $this->setupShippingAndSnapMocks('cancelled');

        $this->postCheckout($user)->assertOk();

        // Midtrans cancel was invoked with the old order number
        // (Mockery ->once() on cancelTransaction fails the test otherwise)

        $this->assertDatabaseHas('orders', ['id' => $oldOrder->id, 'status' => 'dibatalkan']);
        $this->assertDatabaseHas('payments', ['id' => $oldPayment->id, 'status' => 'failed']);
    }

    public function test_checkout_keeps_abandoned_order_pending_when_midtrans_blocks_cancel(): void
    {
        [$user, $oldOrder, $oldPayment] = $this->setupUserWithAbandonedPendingOrder();
        $this->setupShippingAndSnapMocks('cannot_modify');

        // New checkout must still succeed even though the old order couldn't be
        // closed at Midtrans — but the old order must NOT be locally cancelled
        // (a locally-cancelled-but-still-payable order traps customer money).
        $this->postCheckout($user)->assertOk();

        $this->assertDatabaseHas('orders', ['id' => $oldOrder->id, 'status' => 'pending']);
        $this->assertDatabaseHas('payments', ['id' => $oldPayment->id, 'status' => 'pending']);
    }

    public function test_checkout_treats_not_found_as_cancellable(): void
    {
        [$user, $oldOrder] = $this->setupUserWithAbandonedPendingOrder();
        $this->setupShippingAndSnapMocks('not_found');

        $this->postCheckout($user)->assertOk();

        $this->assertDatabaseHas('orders', ['id' => $oldOrder->id, 'status' => 'dibatalkan']);
    }

    public function test_invalid_voucher_aborts_checkout_without_cancelling_abandoned_order(): void
    {
        // Intent dari CheckoutController reorder: validasi (voucher, is_active,
        // subtotal) dilakukan SEBELUM membatalkan pesanan lama di Midtrans.
        // Checkout yang gagal validasi tidak boleh mengganggu pesanan pending lama.
        [$user, $oldOrder, $oldPayment] = $this->setupUserWithAbandonedPendingOrder();

        $shippingMock = Mockery::mock(ShippingService::class);
        $shippingMock->shouldReceive('calculateCost')->once()->andReturn([
            ['code' => 'jne', 'service' => 'REG', 'description' => 'Reg', 'cost' => 5000, 'etd' => '2'],
        ]);
        $this->app->instance(ShippingService::class, $shippingMock);

        $midtransMock = Mockery::mock(MidtransService::class);
        $midtransMock->shouldReceive('cancelTransaction')->never();
        $midtransMock->shouldReceive('createSnapToken')->never();
        $this->app->instance(MidtransService::class, $midtransMock);

        $this->postCheckoutWith($user, ['voucher_code' => 'KOSONG99'])
            ->assertStatus(422);

        $this->assertDatabaseHas('orders', ['id' => $oldOrder->id, 'status' => 'pending']);
        $this->assertDatabaseHas('payments', ['id' => $oldPayment->id, 'status' => 'pending']);
    }

    private function postCheckoutWith(User $user, array $extra): \Illuminate\Testing\TestResponse
    {
        return $this
            ->actingAs($user)
            ->postJson(route('checkout.token'), array_merge([
                'recipient_name'  => 'Jane Doe',
                'recipient_phone' => '081111111111',
                'full_address'    => 'Jl. Abandoned No. 1',
                'city'            => 'Bandung',
                'city_id'         => 23,
                'postal_code'     => '40135',
                'courier'         => 'jne',
                'courier_service' => 'jne-REG',
                'payment_method'  => 'gopay',
                'shipping_cost'   => 0,
            ], $extra));
    }

    /** @return Voucher Voucher 10% aktif, min_purchase 0, quota longgar. */
    private function makeVoucher(): Voucher
    {
        return Voucher::create([
            'code'           => 'RECHK' . strtoupper(substr(uniqid(), -5)),
            'discount_type'  => 'percent',
            'discount_value' => 10,
            'min_purchase'   => 0,
            'quota'          => 100,
            'used_count'     => 0,
            'start_date'     => now()->subDay()->toDateString(),
            'end_date'       => now()->addDay()->toDateString(),
            'is_active'      => true,
        ]);
    }

    public function test_checkout_applies_voucher_when_abandoned_order_closed_at_midtrans(): void
    {
        // Bug: pelanggan yang menutup popup Snap lalu checkout ulang dengan
        // voucher yang sama kehilangan diskon diam-diam, karena isUsedBy
        // menghitung order abandoned miliknya sendiri SEBELUM order itu
        // ditandai dibatalkan.
        [$user, $oldOrder] = $this->setupUserWithAbandonedPendingOrder();
        $voucher = $this->makeVoucher();
        $oldOrder->update(['voucher_id' => $voucher->id]);

        $this->setupShippingAndSnapMocks('cancelled');

        $this->postCheckoutWith($user, ['voucher_code' => $voucher->code])->assertOk();

        $this->assertDatabaseHas('orders', ['id' => $oldOrder->id, 'status' => 'dibatalkan']);

        $newOrder = Order::where('user_id', $user->id)->where('id', '!=', $oldOrder->id)->first();
        $this->assertNotNull($newOrder);
        $this->assertSame($voucher->id, $newOrder->voucher_id, 'Diskon voucher harus diterapkan pada checkout ulang');
        $this->assertEquals(5000, (float) $newOrder->voucher_discount); // 10% dari 50000
        $this->assertEquals(55000, (float) $newOrder->total_amount);   // 50000 + 10000 - 5000
    }

    public function test_checkout_ignores_voucher_when_abandoned_order_cannot_be_closed(): void
    {
        // Order yang GAGAL ditutup di Midtrans (cannot_modify) masih bisa
        // dibayar — harus tetap dihitung "sudah dipakai", jadi diskon diabaikan.
        [$user, $oldOrder] = $this->setupUserWithAbandonedPendingOrder();
        $voucher = $this->makeVoucher();
        $oldOrder->update(['voucher_id' => $voucher->id]);

        $this->setupShippingAndSnapMocks('cannot_modify');

        $this->postCheckoutWith($user, ['voucher_code' => $voucher->code])->assertOk();

        $this->assertDatabaseHas('orders', ['id' => $oldOrder->id, 'status' => 'pending']);

        $newOrder = Order::where('user_id', $user->id)->where('id', '!=', $oldOrder->id)->first();
        $this->assertNotNull($newOrder);
        $this->assertNull($newOrder->voucher_id, 'Order yang tidak tertutup di Midtrans masih mengunci voucher');
        $this->assertEquals(0, (float) $newOrder->voucher_discount);
        $this->assertEquals(60000, (float) $newOrder->total_amount); // 50000 + 10000 - 0
    }

    public function test_checkout_ignores_voucher_used_by_paid_order(): void
    {
        $user = User::factory()->create();
        $category = Category::create(['name' => 'Gelang', 'slug' => 'gelang-' . uniqid(), 'is_active' => true]);
        $product = Product::create([
            'category_id' => $category->id,
            'name'        => 'Gelang Tali',
            'slug'        => 'gelang-tali-' . uniqid(),
            'price'       => 50000,
            'stock'       => 10,
            'weight'      => 10,
            'is_active'   => true,
        ]);
        $user->getOrCreateCart()->items()->create(['product_id' => $product->id, 'quantity' => 1]);

        $voucher = $this->makeVoucher();
        $address = $user->addresses()->create([
            'label'          => 'Utama',
            'recipient_name' => $user->name,
            'phone'          => '081122233344',
            'province'       => 'Jawa Barat',
            'city'           => 'Bandung',
            'district'       => 'Coblong',
            'postal_code'    => '40135',
            'full_address'   => 'Jl. Paid No. 9',
        ]);
        Order::create([
            'order_number'     => 'ELCRAFT-PAIDV-' . uniqid(),
            'user_id'          => $user->id,
            'address_id'       => $address->id,
            'voucher_id'       => $voucher->id,
            'voucher_discount' => 5000,
            'subtotal'         => 50000,
            'total_amount'     => 45000,
            'status'           => 'paid',
        ]);

        $shippingMock = Mockery::mock(ShippingService::class);
        $shippingMock->shouldReceive('calculateCost')->once()->andReturn([
            ['code' => 'jne', 'service' => 'REG', 'description' => 'Reg', 'cost' => 10000, 'etd' => '2'],
        ]);
        $this->app->instance(ShippingService::class, $shippingMock);

        // Tidak ada order pending — cancelTransaction tidak boleh dipanggil sama sekali.
        $midtransMock = Mockery::mock(MidtransService::class);
        $midtransMock->shouldReceive('createSnapToken')->once()->andReturn([
            'token' => 't', 'redirect_url' => 'https://dummy.url',
        ]);
        $this->app->instance(MidtransService::class, $midtransMock);

        $this->postCheckoutWith($user, ['voucher_code' => $voucher->code])->assertOk();

        $newOrder = Order::where('user_id', $user->id)->where('status', 'pending')->first();
        $this->assertNotNull($newOrder);
        $this->assertNull($newOrder->voucher_id, 'Voucher yang dipakai order paid harus diabaikan diam-diam');
        $this->assertEquals(0, (float) $newOrder->voucher_discount);
    }

    public function test_inactive_voucher_aborts_checkout_without_cancelling_abandoned_order(): void
    {
        [$user, $oldOrder, $oldPayment] = $this->setupUserWithAbandonedPendingOrder();
        $voucher = $this->makeVoucher();
        $voucher->update(['is_active' => false]);

        $shippingMock = Mockery::mock(ShippingService::class);
        $shippingMock->shouldReceive('calculateCost')->once()->andReturn([
            ['code' => 'jne', 'service' => 'REG', 'description' => 'Reg', 'cost' => 5000, 'etd' => '2'],
        ]);
        $this->app->instance(ShippingService::class, $shippingMock);

        $midtransMock = Mockery::mock(MidtransService::class);
        $midtransMock->shouldReceive('cancelTransaction')->never();
        $midtransMock->shouldReceive('createSnapToken')->never();
        $this->app->instance(MidtransService::class, $midtransMock);

        $this->postCheckoutWith($user, ['voucher_code' => $voucher->code])
            ->assertStatus(422);

        $this->assertDatabaseHas('orders', ['id' => $oldOrder->id, 'status' => 'pending']);
        $this->assertDatabaseHas('payments', ['id' => $oldPayment->id, 'status' => 'pending']);
    }

    public function test_checkout_without_pending_orders_never_calls_cancel(): void
    {
        $user = User::factory()->create();
        $category = Category::create(['name' => 'Pin', 'slug' => 'pin-' . uniqid(), 'is_active' => true]);
        $product = Product::create([
            'category_id' => $category->id,
            'name'        => 'Pin Buket',
            'slug'        => 'pin-buket-' . uniqid(),
            'price'       => 30000,
            'stock'       => 5,
            'weight'      => 5,
            'is_active'   => true,
        ]);
        $user->getOrCreateCart()->items()->create([
            'product_id' => $product->id,
            'quantity'   => 1,
        ]);

        $shippingMock = Mockery::mock(ShippingService::class);
        $shippingMock->shouldReceive('calculateCost')->once()->andReturn([
            ['code' => 'jne', 'service' => 'REG', 'description' => 'Reg', 'cost' => 5000, 'etd' => '2'],
        ]);
        $this->app->instance(ShippingService::class, $shippingMock);

        $midtransMock = Mockery::mock(MidtransService::class);
        $midtransMock->shouldReceive('createSnapToken')->once()->andReturn([
            'token' => 't', 'redirect_url' => 'https://dummy.url',
        ]);
        // No cancelTransaction expectation — calling it would throw an
        // "unexpected method call" Mockery exception and fail this test.
        $this->app->instance(MidtransService::class, $midtransMock);

        $this->actingAs($user)->postJson(route('checkout.token'), [
            'recipient_name'  => 'Jane Doe',
            'recipient_phone' => '081234567890',
            'full_address'    => 'Jl. Merdeka No. 10',
            'city'            => 'Bandung',
            'city_id'         => 23,
            'postal_code'     => '40115',
            'courier'         => 'jne',
            'courier_service' => 'jne-REG',
            'payment_method'  => 'gopay',
            'shipping_cost'   => 0,
        ])->assertOk();
    }
}
