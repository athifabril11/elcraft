<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\MidtransService;
use App\Services\ShippingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class CartChecklistAndBuyNowTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Product $product;
    private ProductVariant $variantA;
    private ProductVariant $variantB;
    private ProductVariant $variantC;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $category = Category::create([
            'name'      => 'Aksesoris',
            'slug'      => 'aksesoris',
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'category_id' => $category->id,
            'name'        => 'Cincin Titanium',
            'slug'        => 'cincin-titanium',
            'price'       => 50000,
            'stock'       => 30,
            'weight'      => 10,
            'is_active'   => true,
        ]);

        $this->variantA = ProductVariant::create([
            'product_id'       => $this->product->id,
            'variant_type'     => 'Ukuran',
            'variant_name'     => 'Varian A',
            'additional_price' => 0,
            'stock'            => 10,
            'is_active'        => true,
        ]);

        $this->variantB = ProductVariant::create([
            'product_id'       => $this->product->id,
            'variant_type'     => 'Ukuran',
            'variant_name'     => 'Varian B',
            'additional_price' => 10000,
            'stock'            => 10,
            'is_active'        => true,
        ]);

        $this->variantC = ProductVariant::create([
            'product_id'       => $this->product->id,
            'variant_type'     => 'Ukuran',
            'variant_name'     => 'Varian C',
            'additional_price' => 20000,
            'stock'            => 10,
            'is_active'        => true,
        ]);
    }

    public function test_items_added_to_cart_are_selected_by_default(): void
    {
        $response = $this->actingAs($this->user)->postJson(route('cart.add'), [
            'product_id' => $this->product->id,
            'variant_id' => $this->variantA->id,
            'quantity'   => 1,
        ]);

        $response->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseHas('cart_items', [
            'product_id'  => $this->product->id,
            'variant_id'  => $this->variantA->id,
            'is_selected' => true,
        ]);
    }

    public function test_user_can_toggle_individual_cart_item_selection(): void
    {
        $cart = $this->user->getOrCreateCart();

        $itemA = $cart->allItems()->create([
            'product_id'  => $this->product->id,
            'variant_id'  => $this->variantA->id,
            'quantity'    => 1,
            'is_selected' => true,
        ]);

        $itemB = $cart->allItems()->create([
            'product_id'  => $this->product->id,
            'variant_id'  => $this->variantB->id,
            'quantity'    => 1,
            'is_selected' => true,
        ]);

        // Uncheck Item A
        $response = $this->actingAs($this->user)->postJson(route('cart.toggle-select'), [
            'item_id'     => $itemA->id,
            'is_selected' => false,
        ]);

        $response->assertOk()->assertJson([
            'success'        => true,
            'is_selected'    => false,
            'selected_count' => 1,
            'cart_total'     => 60000, // only item B (50000 + 10000)
        ]);

        $this->assertDatabaseHas('cart_items', [
            'id'          => $itemA->id,
            'is_selected' => false,
        ]);

        $this->assertDatabaseHas('cart_items', [
            'id'          => $itemB->id,
            'is_selected' => true,
        ]);
    }

    public function test_user_can_toggle_select_all(): void
    {
        $cart = $this->user->getOrCreateCart();

        $cart->allItems()->create([
            'product_id'  => $this->product->id,
            'variant_id'  => $this->variantA->id,
            'quantity'    => 1,
            'is_selected' => true,
        ]);

        $cart->allItems()->create([
            'product_id'  => $this->product->id,
            'variant_id'  => $this->variantB->id,
            'quantity'    => 1,
            'is_selected' => true,
        ]);

        // Unselect all
        $response = $this->actingAs($this->user)->postJson(route('cart.toggle-select-all'), [
            'is_selected' => false,
        ]);

        $response->assertOk()->assertJson([
            'success'        => true,
            'selected_count' => 0,
            'cart_total'     => 0,
        ]);

        $this->assertEquals(0, $cart->items()->count());
        $this->assertEquals(2, $cart->allItems()->count());

        // Select all again
        $response2 = $this->actingAs($this->user)->postJson(route('cart.toggle-select-all'), [
            'is_selected' => true,
        ]);

        $response2->assertOk()->assertJson([
            'success'        => true,
            'selected_count' => 2,
            'cart_total'     => 110000, // 50000 + 60000
        ]);

        $this->assertEquals(2, $cart->items()->count());
    }

    public function test_buy_now_selects_target_item_and_unselects_other_cart_items_without_deleting_them(): void
    {
        $cart = $this->user->getOrCreateCart();

        // Add Variant A and Variant B to cart first
        $cart->allItems()->create([
            'product_id'  => $this->product->id,
            'variant_id'  => $this->variantA->id,
            'quantity'    => 1,
            'is_selected' => true,
        ]);

        $cart->allItems()->create([
            'product_id'  => $this->product->id,
            'variant_id'  => $this->variantB->id,
            'quantity'    => 1,
            'is_selected' => true,
        ]);

        // User does "Beli Sekarang" on Variant C
        $response = $this->actingAs($this->user)->postJson(route('cart.buy-now'), [
            'product_id' => $this->product->id,
            'variant_id' => $this->variantC->id,
            'quantity'   => 1,
        ]);

        $response->assertOk()->assertJson([
            'success'  => true,
            'redirect' => route('cart.index'),
        ]);

        // All 3 items MUST still exist in database
        $this->assertEquals(3, $cart->allItems()->count());

        // Variant A: still in cart, unchecked
        $this->assertDatabaseHas('cart_items', [
            'cart_id'     => $cart->id,
            'variant_id'  => $this->variantA->id,
            'is_selected' => false,
        ]);

        // Variant B: still in cart, unchecked
        $this->assertDatabaseHas('cart_items', [
            'cart_id'     => $cart->id,
            'variant_id'  => $this->variantB->id,
            'is_selected' => false,
        ]);

        // Variant C: in cart, checked
        $this->assertDatabaseHas('cart_items', [
            'cart_id'     => $cart->id,
            'variant_id'  => $this->variantC->id,
            'is_selected' => true,
        ]);

        // Cart items() relationship for checkout must ONLY return Variant C
        $this->assertEquals(1, $cart->items()->count());
        $this->assertEquals($this->variantC->id, $cart->items()->first()->variant_id);
    }

    public function test_checkout_processes_only_selected_item_and_keeps_unselected_items_in_cart(): void
    {
        $cart = $this->user->getOrCreateCart();

        // In cart: Variant A (unchecked, price 50000), Variant C (checked, price 70000)
        $cart->allItems()->create([
            'product_id'  => $this->product->id,
            'variant_id'  => $this->variantA->id,
            'quantity'    => 1,
            'is_selected' => false,
        ]);

        $cart->allItems()->create([
            'product_id'  => $this->product->id,
            'variant_id'  => $this->variantC->id,
            'quantity'    => 1,
            'is_selected' => true,
        ]);

        // Mock Shipping
        $shippingMock = Mockery::mock(ShippingService::class);
        $shippingMock->shouldReceive('calculateCost')
            ->once()
            ->andReturn([
                ['code' => 'jne', 'service' => 'REG', 'description' => 'Reguler', 'cost' => 10000, 'etd' => '2-3'],
            ]);
        $this->app->instance(ShippingService::class, $shippingMock);

        // Mock Midtrans
        $midtransMock = Mockery::mock(MidtransService::class);
        $midtransMock->shouldReceive('createSnapToken')
            ->once()
            ->andReturn(['token' => 'dummy_snap_token', 'redirect_url' => 'https://dummy.url']);
        $this->app->instance(MidtransService::class, $midtransMock);

        // Checkout snap token
        $response = $this->actingAs($this->user)->postJson(route('checkout.token'), [
            'recipient_name'  => 'Test User',
            'recipient_phone' => '081234567890',
            'full_address'    => 'Jl. Mawar No. 1',
            'city'            => 'Bandung',
            'city_id'         => 23,
            'postal_code'     => '40115',
            'courier'         => 'jne',
            'courier_service' => 'jne-REG',
            'payment_method'  => 'snap',
            'shipping_cost'   => 0,
        ]);

        $response->assertOk()->assertJsonStructure(['snap_token', 'order_id']);

        // Order subtotal must be ONLY Variant C (50000 + 20000 = 70000) + 10000 shipping = 80000
        $order = Order::where('user_id', $this->user->id)->first();
        $this->assertNotNull($order);
        $this->assertEquals(70000, $order->subtotal);
        $this->assertEquals(80000, $order->total_amount);

        // Order items must ONLY contain Variant C
        $this->assertEquals(1, $order->items()->count());
        $this->assertEquals($this->variantC->id, $order->items()->first()->variant_id);

        // IN CART: Variant C was checked out, so it should be deleted.
        // Variant A was NOT checked out, so it MUST STILL BE IN THE CART!
        $this->assertEquals(1, $cart->allItems()->count());
        $this->assertDatabaseHas('cart_items', [
            'cart_id'    => $cart->id,
            'variant_id' => $this->variantA->id,
        ]);
        $this->assertDatabaseMissing('cart_items', [
            'cart_id'    => $cart->id,
            'variant_id' => $this->variantC->id,
        ]);
    }

    public function test_cart_page_renders_all_items_with_their_checklist_status(): void
    {
        $cart = $this->user->getOrCreateCart();

        $cart->allItems()->create([
            'product_id'  => $this->product->id,
            'variant_id'  => $this->variantA->id,
            'quantity'    => 1,
            'is_selected' => false,
        ]);

        $cart->allItems()->create([
            'product_id'  => $this->product->id,
            'variant_id'  => $this->variantB->id,
            'quantity'    => 1,
            'is_selected' => true,
        ]);

        $response = $this->actingAs($this->user)->get(route('cart.index'));

        $response->assertOk();
        $response->assertSee('Varian A');
        $response->assertSee('Varian B');
        $response->assertSee('Pilih Semua');
    }
}
