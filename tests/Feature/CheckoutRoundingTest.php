<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Category;
use App\Models\Voucher;
use App\Models\CartItem;
use App\Services\MidtransService;
use App\Services\ShippingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Mockery;

class CheckoutRoundingTest extends TestCase
{
    use RefreshDatabase;

    private function makeCategory(string $slug = 'anting'): Category
    {
        return Category::create(['name' => ucfirst($slug), 'slug' => $slug, 'is_active' => true]);
    }

    private function makeProduct(Category $category, array $overrides = []): Product
    {
        return Product::create(array_merge([
            'category_id' => $category->id,
            'name' => 'Produk ' . uniqid(),
            'slug' => 'produk-' . uniqid(),
            'price' => 10000,
            'stock' => 100,
            'weight' => 5,
            'is_active' => true,
            'discount_type' => 'none',
            'discount_value' => 0,
        ], $overrides));
    }

    private function makeVoucher(array $overrides = []): Voucher
    {
        return Voucher::create(array_merge([
            'code' => 'HEMAT' . strtoupper(substr(uniqid(), -4)),
            'discount_type' => 'percent',
            'discount_value' => 10,
            'min_purchase' => 0,
            'max_discount' => null,
            'quota' => 100,
            'used_count' => 0,
            'start_date' => now()->subDay(),
            'end_date' => now()->addDay(),
            'is_active' => true,
        ], $overrides));
    }

    private function fakeShipping(int $cost = 23000): void
    {
        $shippingMock = Mockery::mock(ShippingService::class);
        $shippingMock->shouldReceive('calculateCost')
            ->once()
            ->andReturn([
                ['code' => 'jne', 'service' => 'REG', 'description' => 'Reguler', 'cost' => $cost, 'etd' => '2-3'],
            ]);
        $this->app->instance(ShippingService::class, $shippingMock);
    }

    private array $capturedOrder = [];

    private function fakeMidtransSnap(): void
    {
        $this->capturedOrder = [];
        $midtransMock = Mockery::mock(MidtransService::class);
        $midtransMock->shouldReceive('createSnapToken')
            ->once()
            ->andReturnUsing(function (array $order) {
                $this->capturedOrder = $order;
                return ['token' => 'test_snap_token', 'redirect_url' => 'https://dummy.url'];
            });
        $this->app->instance(MidtransService::class, $midtransMock);
    }

    private function checkout(User $user, array $overrides = [])
    {
        return $this->actingAs($user)->postJson(route('checkout.token'), array_merge([
            'recipient_name'  => 'John Doe',
            'recipient_phone' => '081234567890',
            'full_address'    => 'Jl. Merdeka No. 10',
            'city'            => 'Bandung',
            'city_id'         => 23,
            'postal_code'     => '40115',
            'courier'         => 'jne',
            'courier_service' => 'jne-REG',
            'payment_method'  => 'gopay',
            'shipping_cost'   => 0,
        ], $overrides));
    }

    private function capturedMidtransPayload(): array
    {
        $this->assertNotEmpty($this->capturedOrder, 'createSnapToken() was not called');
        return $this->capturedOrder;
    }

    private function assertPayloadBalanced(array $payload): void
    {
        $gross = (int) $payload['total'];
        $sum = array_sum(array_map(
            fn ($item) => ((int) $item['price']) * ((int) $item['quantity']),
            $payload['items'] ?? []
        ));
        $this->assertSame($gross, $sum, 'Midtrans gross_amount must equal sum(item_details price * quantity)');
    }

    private function assertOrderWholeAndConsistent(User $user, int $gross): void
    {
        $order = \App\Models\Order::where('user_id', $user->id)->latest('id')->first();
        $this->assertNotNull($order, 'Order was not created');
        $this->assertSame($gross, (int) $order->total_amount, 'orders.total_amount must equal Midtrans gross_amount');
        foreach ($order->items as $item) {
            $this->assertSame((float) (int) $item->price, (float) $item->price, 'order_items.price must be a whole rupiah');
        }
    }

    // ─── Scenario 1: fractional unit price × 2, no voucher ─────────────────
    public function test_fractional_unit_price_times_two_balances(): void
    {
        $user = User::factory()->create();
        $category = $this->makeCategory();
        $product = $this->makeProduct($category, ['price' => 49999.50]);

        $cart = $user->getOrCreateCart();
        $cart->items()->create(['product_id' => $product->id, 'quantity' => 2]);

        $this->fakeShipping(10000);
        $this->fakeMidtransSnap();

        $response = $this->checkout($user);
        $payload = $this->capturedMidtransPayload();
        $this->assertPayloadBalanced($payload);
        $this->assertOrderWholeAndConsistent($user, (int) $payload['total']);
    }

    // ─── Scenario 2: fractional product discount, × 3 ─────────────────────
    public function test_fractional_product_discount_times_three_balances(): void
    {
        $user = User::factory()->create();
        $category = $this->makeCategory('cincin');
        // 33% of 100000 = 33000 → final 67000 (whole). Use 15% of 100000.50 style fractional:
        // price 99999, 15% → final = 99999 - 14999.85 = 84999.15 (fractional)
        $product = $this->makeProduct($category, [
            'price' => 99999,
            'discount_type' => 'percent',
            'discount_value' => 15,
            'discount_start' => now()->subDay(),
            'discount_end' => now()->addDay(),
        ]);

        $cart = $user->getOrCreateCart();
        $cart->items()->create(['product_id' => $product->id, 'quantity' => 3]);

        $this->fakeShipping(10000);
        $this->fakeMidtransSnap();

        $response = $this->checkout($user);
        $response->assertOk();

        $payload = $this->capturedMidtransPayload();
        $this->assertPayloadBalanced($payload);
        $this->assertOrderWholeAndConsistent($user, (int) $payload['total']);
    }

    // ─── Scenario 3: fractional variant additional_price ───────────────────
    public function test_fractional_variant_additional_price_balances(): void
    {
        $user = User::factory()->create();
        $category = $this->makeCategory('gelang');
        $product = $this->makeProduct($category, ['price' => 10000]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'variant_name' => 'Silver',
            'variant_type' => 'warna',
            'additional_price' => 2500.75,
            'stock' => 50,
            'is_active' => true,
        ]);

        $cart = $user->getOrCreateCart();
        $cart->items()->create(['product_id' => $product->id, 'variant_id' => $variant->id, 'quantity' => 2]);

        $this->fakeShipping(10000);
        $this->fakeMidtransSnap();

        $response = $this->checkout($user);
        $response->assertOk();

        $payload = $this->capturedMidtransPayload();
        $this->assertPayloadBalanced($payload);
        $this->assertOrderWholeAndConsistent($user, (int) $payload['total']);
    }

    // ─── Scenario 4: percentage voucher with fractional discount + shipping ─
    public function test_percentage_voucher_fractional_discount_balances(): void
    {
        $user = User::factory()->create();
        $category = $this->makeCategory('kalung');
        // subtotal ends in 5 → 10% ends in .5 → fractional discount
        $product = $this->makeProduct($category, ['price' => 45000]);

        $cart = $user->getOrCreateCart();
        $cart->items()->create(['product_id' => $product->id, 'quantity' => 1]); // subtotal 45000, discount 4500

        // Use subtotal ending in 5: price 49555 → 10% = 4955.5
        $product2 = $this->makeProduct($category, ['price' => 49555, 'slug' => 'produk-b-' . uniqid()]);
        $cart->items()->create(['product_id' => $product2->id, 'quantity' => 1]); // +49555

        $voucher = $this->makeVoucher(['discount_type' => 'percent', 'discount_value' => 10]);

        $this->fakeShipping(15000);
        $this->fakeMidtransSnap();

        $response = $this->checkout($user, ['voucher_code' => $voucher->code]);
        $response->assertOk();

        $payload = $this->capturedMidtransPayload();
        $this->assertPayloadBalanced($payload);
        $this->assertOrderWholeAndConsistent($user, (int) $payload['total']);
    }

    // ─── Scenario 5: fixed voucher exceeds subtotal → capped, total = shipping
    public function test_fixed_voucher_capped_to_subtotal_balances(): void
    {
        $user = User::factory()->create();
        $category = $this->makeCategory('kalung');
        $product = $this->makeProduct($category, ['price' => 50000]);

        $cart = $user->getOrCreateCart();
        $cart->items()->create(['product_id' => $product->id, 'quantity' => 1]); // subtotal 50000

        $voucher = $this->makeVoucher(['discount_type' => 'nominal', 'discount_value' => 80000]);

        $this->fakeShipping(12000);
        $this->fakeMidtransSnap();

        $response = $this->checkout($user, ['voucher_code' => $voucher->code]);
        $response->assertOk();

        $payload = $this->capturedMidtransPayload();
        $this->assertPayloadBalanced($payload);
        // discount capped to 50000 → total = 50000 + 12000 - 50000 = 12000
        $this->assertSame(12000, (int) $payload['total']);
        $this->assertOrderWholeAndConsistent($user, 12000);
    }

    // ─── Scenario 6: max_discount cap smaller than computed percent discount ─
    public function test_max_discount_cap_balances(): void
    {
        $user = User::factory()->create();
        $category = $this->makeCategory('cincin');
        $product = $this->makeProduct($category, ['price' => 100000]);

        $cart = $user->getOrCreateCart();
        $cart->items()->create(['product_id' => $product->id, 'quantity' => 1]); // subtotal 100000

        // 50% of 100000 = 50000, but max_discount = 25000.50 → discount = 25000.50 (fractional cap)
        $voucher = $this->makeVoucher([
            'discount_type' => 'percent',
            'discount_value' => 50,
            'max_discount' => 25000.50,
        ]);

        $this->fakeShipping(10000);
        $this->fakeMidtransSnap();

        $response = $this->checkout($user, ['voucher_code' => $voucher->code]);
        $response->assertOk();

        $payload = $this->capturedMidtransPayload();
        $this->assertPayloadBalanced($payload);
        $this->assertOrderWholeAndConsistent($user, (int) $payload['total']);
    }

    // ─── Scenario 7: unit tests for Voucher::calculateDiscount + CartItem::getUnitPrice
    public function test_calculate_discount_and_unit_price_are_whole(): void
    {
        // percent: 10% of 49555 = 4955.5 → round → 4956
        $voucher = $this->makeVoucher(['discount_type' => 'percent', 'discount_value' => 10]);
        $this->assertSame(4956.0, (float) round($voucher->calculateDiscount(49555)), '', 0.001);

        // percent with cap: 50% of 100000 = 50000, but max_discount 25000.50 →
        // round(min(50000, round(25000.50))) = round(min(50000, 25001)) = 25001
        $capped = $this->makeVoucher(['discount_type' => 'percent', 'discount_value' => 50, 'max_discount' => 25000.50]);
        $this->assertSame(25001.0, (float) $capped->calculateDiscount(100000), '', 0.001);

        // nominal exceeding total → capped to total, never negative
        $fixed = $this->makeVoucher(['discount_type' => 'nominal', 'discount_value' => 999999]);
        $this->assertSame(50000.0, (float) $fixed->calculateDiscount(50000));
        $this->assertSame(0.0, (float) $fixed->calculateDiscount(0));

        // CartItem::getUnitPrice() whole for fractional base + fractional variant
        $category = $this->makeCategory('unit');
        $product = $this->makeProduct($category, ['price' => 49999.50]);
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'variant_name' => 'Gold',
            'variant_type' => 'warna',
            'additional_price' => 1234.56,
            'stock' => 10,
            'is_active' => true,
        ]);
        $user = User::factory()->create();
        $cartModel = $user->getOrCreateCart();
        $item = $cartModel->items()->create(['product_id' => $product->id, 'variant_id' => $variant->id, 'quantity' => 1]);
        $item->load(['product.images', 'variant']);
        $unit = $item->getUnitPrice();
        $this->assertSame($unit, floor($unit), 'getUnitPrice() must return a whole rupiah');
    }

    // ─── Scenario 8: voucher apply preview === orders.voucher_discount ─────
    public function test_voucher_apply_preview_matches_stored_discount(): void
    {
        $user = User::factory()->create();
        $category = $this->makeCategory('preview');
        $product = $this->makeProduct($category, ['price' => 49555]);

        $cart = $user->getOrCreateCart();
        $cart->items()->create(['product_id' => $product->id, 'quantity' => 1]);

        $voucher = $this->makeVoucher(['discount_type' => 'percent', 'discount_value' => 10]);

        // Preview endpoint
        $preview = $this->actingAs($user)->postJson(route('voucher.apply'), [
            'code' => $voucher->code,
            'subtotal' => 49555,
        ]);
        $preview->assertOk();
        $previewDiscount = (int) $preview->json('discount_amount');
        $this->assertEquals((int) (float) $preview->json('discount_amount'), $previewDiscount, 'preview discount must be whole');

        // Checkout with same voucher — stored discount must equal preview
        $this->fakeShipping(10000);
        $this->fakeMidtransSnap();
        $response = $this->checkout($user, ['voucher_code' => $voucher->code]);
        $response->assertOk();

        $order = \App\Models\Order::where('user_id', $user->id)->latest('id')->first();
        $this->assertSame($previewDiscount, (int) $order->voucher_discount, 'orders.voucher_discount must equal voucher.apply preview discount_amount');
    }

    // ─── Scenario 9: MidtransService consistency guard ─────────────────────
    public function test_create_snap_token_guard_rejects_mismatched_payload(): void
    {
        $service = app(MidtransService::class);

        // Mismatched: total 1000 but items sum 500 → must throw, no HTTP call.
        // Scope a failing fake so ANY outbound request trips the test.
        Http::fake(function () {
            $this->fail('HTTP request must not be sent when item sum mismatches total');
        });

        $threw = false;
        try {
            $service->createSnapToken(
                [
                    'id' => 'ELCRAFT-TEST-1',
                    'total' => 1000,
                    'items' => [
                        ['id' => '1', 'price' => 500, 'quantity' => 1, 'name' => 'Item'],
                    ],
                ],
                ['name' => 'Test', 'email' => 'test@example.com', 'phone' => '0812']
            );
        } catch (\RuntimeException $e) {
            $threw = true;
            $this->assertSame('Rincian item tidak sama dengan total pembayaran.', $e->getMessage());
        }
        $this->assertTrue($threw, 'createSnapToken must throw RuntimeException on mismatched item sum vs total');
        Http::assertNothingSent();
    }

    // ─── Scenario 9b: matching payload succeeds ───────────────────────────
    public function test_create_snap_token_accepts_matching_payload(): void
    {
        $service = app(MidtransService::class);

        Http::fake([
            'app.sandbox.midtrans.com/snap/v1/transactions' => Http::response([
                'token' => 'ok',
                'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v1/redirect',
            ], 200),
        ]);

        $result = $service->createSnapToken(
            [
                'id' => 'ELCRAFT-TEST-2',
                'total' => 500,
                'items' => [
                    ['id' => '1', 'price' => 500, 'quantity' => 1, 'name' => 'Item'],
                ],
            ],
            ['name' => 'Test', 'email' => 'test@example.com', 'phone' => '0812']
        );
        $this->assertArrayHasKey('token', $result);
    }
}
