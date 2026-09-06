<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ReturnItem;
use App\Models\ReturnRequest;
use App\Models\ShopSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * ReturnSubmissionTest
 *
 * Tests the customer "Ajukan Retur" submission flow:
 *   GET  /orders/{orderNumber}/returns/create  (orders.returns.create)
 *   POST /orders/{orderNumber}/returns          (orders.returns.store)
 *   GET  /orders/{orderNumber}/returns          (orders.returns.show)
 */
class ReturnSubmissionTest extends TestCase
{
    use RefreshDatabase;

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function makeUser(): User
    {
        return User::factory()->create(['email_verified_at' => now()]);
    }

    private function makeAddress(User $user): Address
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

    private function makeProduct(): Product
    {
        $category = Category::create([
            'name'      => 'Gelang',
            'slug'      => 'gelang-' . uniqid(),
            'is_active' => true,
        ]);

        return Product::create([
            'category_id' => $category->id,
            'name'        => 'Gelang Emas Test',
            'slug'        => 'gelang-emas-test-' . uniqid(),
            'price'       => 150000,
            'stock'       => 20,
            'weight'      => 10,
            'is_active'   => true,
        ]);
    }

    /**
     * Create an order with one order item (qty 3 by default).
     */
    private function makeOrderWithItem(User $user, string $status = 'selesai', int $qty = 3): array
    {
        $address = $this->makeAddress($user);
        $product = $this->makeProduct();

        $order = Order::create([
            'order_number' => 'ORD-RETTEST-' . uniqid(),
            'user_id'      => $user->id,
            'address_id'   => $address->id,
            'subtotal'     => $product->price * $qty,
            'total_amount' => $product->price * $qty,
            'status'       => $status,
        ]);

        $item = OrderItem::create([
            'order_id'     => $order->id,
            'product_id'   => $product->id,
            'product_name' => $product->name,
            'price'        => $product->price,
            'quantity'     => $qty,
            'subtotal'     => $product->price * $qty,
        ]);

        return [$order, $item, $product];
    }

    private function validPayload(OrderItem $item, int $qty = 1): array
    {
        return [
            'resolution_type' => 'refund',
            'reason'          => 'Produk tidak sesuai deskripsi.',
            'items'           => [$item->id => $qty],
        ];
    }

    // ── Access Control ───────────────────────────────────────────────────────

    public function test_guest_is_redirected_to_login_for_create(): void
    {
        [$order] = $this->makeOrderWithItem($this->makeUser(), 'selesai');

        $this->get(route('orders.returns.create', $order->order_number))
             ->assertRedirect('/login');
    }

    public function test_guest_is_redirected_to_login_for_store(): void
    {
        [$order, $item] = $this->makeOrderWithItem($this->makeUser(), 'selesai');

        $this->post(route('orders.returns.store', $order->order_number), $this->validPayload($item))
             ->assertRedirect('/login');
    }

    public function test_guest_is_redirected_to_login_for_show(): void
    {
        [$order] = $this->makeOrderWithItem($this->makeUser(), 'selesai');

        $this->get(route('orders.returns.show', $order->order_number))
             ->assertRedirect('/login');
    }

    // ── IDOR / Ownership ─────────────────────────────────────────────────────

    public function test_user_cannot_access_return_form_for_another_users_order(): void
    {
        $owner = $this->makeUser();
        $other = $this->makeUser();
        [$order] = $this->makeOrderWithItem($owner, 'selesai');

        $this->actingAs($other)
             ->get(route('orders.returns.create', $order->order_number))
             ->assertNotFound();
    }

    public function test_user_cannot_submit_return_for_another_users_order(): void
    {
        $owner = $this->makeUser();
        $other = $this->makeUser();
        [$order, $item] = $this->makeOrderWithItem($owner, 'selesai');

        $this->actingAs($other)
             ->post(route('orders.returns.store', $order->order_number), $this->validPayload($item))
             ->assertNotFound();
    }

    // ── Status Eligibility ───────────────────────────────────────────────────

    #[DataProvider('ineligibleStatuses')]
    public function test_return_form_is_not_accessible_for_ineligible_status(string $status): void
    {
        $user = $this->makeUser();
        [$order] = $this->makeOrderWithItem($user, $status);

        $this->actingAs($user)
             ->get(route('orders.returns.create', $order->order_number))
             ->assertRedirect(route('orders.show', $order->order_number))
             ->assertSessionHas('error');
    }

    public static function ineligibleStatuses(): array
    {
        return [
            'pending'    => ['pending'],
            'paid'       => ['paid'],
            'diproses'   => ['diproses'],
            'dibatalkan' => ['dibatalkan'],
        ];
    }

    public function test_return_form_is_accessible_when_status_is_dikirim(): void
    {
        $user = $this->makeUser();
        [$order] = $this->makeOrderWithItem($user, 'dikirim');

        $this->actingAs($user)
             ->get(route('orders.returns.create', $order->order_number))
             ->assertOk()
             ->assertViewIs('orders.returns.create');
    }

    public function test_return_form_is_accessible_when_status_is_selesai(): void
    {
        $user = $this->makeUser();
        [$order] = $this->makeOrderWithItem($user, 'selesai');

        $this->actingAs($user)
             ->get(route('orders.returns.create', $order->order_number))
             ->assertOk()
             ->assertViewIs('orders.returns.create');
    }

    // ── Duplicate Return Guard ────────────────────────────────────────────────

    public function test_cannot_submit_second_return_for_order_that_already_has_one(): void
    {
        $user = $this->makeUser();
        [$order, $item] = $this->makeOrderWithItem($user, 'selesai');

        // Create existing return
        ReturnRequest::create([
            'order_id'        => $order->id,
            'user_id'         => $user->id,
            'return_number'   => 'RET-20260101-XXXX',
            'resolution_type' => 'refund',
            'reason'          => 'Sudah ada.',
            'status'          => 'diajukan',
        ]);

        // Attempting to access create form should redirect
        $this->actingAs($user)
             ->get(route('orders.returns.create', $order->order_number))
             ->assertRedirect(route('orders.returns.show', $order->order_number))
             ->assertSessionHas('error');

        // Attempting to submit again should also redirect
        $this->actingAs($user)
             ->post(route('orders.returns.store', $order->order_number), $this->validPayload($item))
             ->assertRedirect(route('orders.returns.show', $order->order_number))
             ->assertSessionHas('error');
    }

    // ── Quantity Validation ──────────────────────────────────────────────────

    public function test_cannot_request_more_quantity_than_originally_ordered(): void
    {
        $user = $this->makeUser();
        [$order, $item] = $this->makeOrderWithItem($user, 'selesai', 3);

        $payload = $this->validPayload($item, 99); // 99 > 3 ordered

        $this->actingAs($user)
             ->post(route('orders.returns.store', $order->order_number), $payload)
             ->assertRedirect()
             ->assertSessionHas('error');

        $this->assertDatabaseCount('returns', 0);
    }

    public function test_cannot_request_zero_quantity(): void
    {
        $user = $this->makeUser();
        [$order, $item] = $this->makeOrderWithItem($user, 'selesai', 2);

        $payload = $this->validPayload($item, 0);

        // qty=0 fails the items.* min:1 validation rule — Laravel redirects back
        // with errors in the $errors bag (not the flash 'error' key).
        $this->actingAs($user)
             ->post(route('orders.returns.store', $order->order_number), $payload)
             ->assertRedirect()
             ->assertSessionHasErrors(['items.' . $item->id]);

        $this->assertDatabaseCount('returns', 0);
    }

    // ── Successful Submission ────────────────────────────────────────────────

    public function test_valid_submission_creates_return_request_and_items(): void
    {
        $user = $this->makeUser();
        [$order, $item] = $this->makeOrderWithItem($user, 'selesai', 3);

        $response = $this->actingAs($user)
            ->post(route('orders.returns.store', $order->order_number), [
                'resolution_type' => 'tukar_barang',
                'reason'          => 'Produk cacat saat diterima.',
                'items'           => [$item->id => 2],
            ])
            ->assertRedirect(route('orders.returns.show', $order->order_number))
            ->assertSessionHas('success');

        // ReturnRequest row exists
        $this->assertDatabaseHas('returns', [
            'order_id'        => $order->id,
            'user_id'         => $user->id,
            'resolution_type' => 'tukar_barang',
            'reason'          => 'Produk cacat saat diterima.',
            'status'          => 'diajukan',
        ]);

        // ReturnItem row exists with correct quantity
        $returnRequest = ReturnRequest::where('order_id', $order->id)->firstOrFail();
        $this->assertDatabaseHas('return_items', [
            'return_id'     => $returnRequest->id,
            'order_item_id' => $item->id,
            'quantity'      => 2,
        ]);
    }

    public function test_status_defaults_to_diajukan(): void
    {
        $user = $this->makeUser();
        [$order, $item] = $this->makeOrderWithItem($user, 'selesai');

        $this->actingAs($user)
             ->post(route('orders.returns.store', $order->order_number), $this->validPayload($item));

        $this->assertDatabaseHas('returns', [
            'order_id' => $order->id,
            'status'   => 'diajukan',
        ]);
    }

    // ── Return Number Uniqueness ─────────────────────────────────────────────

    public function test_return_number_is_generated_and_has_correct_format(): void
    {
        $user = $this->makeUser();
        [$order, $item] = $this->makeOrderWithItem($user, 'selesai');

        $this->actingAs($user)
             ->post(route('orders.returns.store', $order->order_number), $this->validPayload($item));

        $returnRequest = ReturnRequest::where('order_id', $order->id)->firstOrFail();

        // Format: RET-YYYYMMDD-XXXX
        $this->assertMatchesRegularExpression('/^RET-\d{8}-[A-Z0-9]{4}$/', $returnRequest->return_number);
    }

    public function test_return_numbers_are_unique_across_submissions(): void
    {
        // Two separate users/orders — each gets a unique return_number
        $user1 = $this->makeUser();
        $user2 = $this->makeUser();
        [$order1, $item1] = $this->makeOrderWithItem($user1, 'selesai');
        [$order2, $item2] = $this->makeOrderWithItem($user2, 'selesai');

        $this->actingAs($user1)
             ->post(route('orders.returns.store', $order1->order_number), $this->validPayload($item1));

        $this->actingAs($user2)
             ->post(route('orders.returns.store', $order2->order_number), $this->validPayload($item2));

        $numbers = ReturnRequest::pluck('return_number');
        $this->assertCount(2, $numbers->unique());
    }

    // ── Evidence Photo Upload ────────────────────────────────────────────────

    public function test_evidence_photo_upload_is_stored_correctly(): void
    {
        Storage::fake('public');

        $user = $this->makeUser();
        [$order, $item] = $this->makeOrderWithItem($user, 'selesai');

        $photo = UploadedFile::fake()->image('evidence.jpg', 100, 100);

        $this->actingAs($user)
             ->post(route('orders.returns.store', $order->order_number), [
                 'resolution_type' => 'refund',
                 'reason'          => 'Produk rusak, terlampir bukti foto.',
                 'items'           => [$item->id => 1],
                 'evidence_photo'  => $photo,
             ])
             ->assertRedirect(route('orders.returns.show', $order->order_number));

        $returnRequest = ReturnRequest::where('order_id', $order->id)->firstOrFail();

        $this->assertNotNull($returnRequest->evidence_photo_path);
        $this->assertStringStartsWith('returns/', $returnRequest->evidence_photo_path);
        Storage::disk('public')->assertExists($returnRequest->evidence_photo_path);
    }

    public function test_submission_succeeds_without_evidence_photo(): void
    {
        $user = $this->makeUser();
        [$order, $item] = $this->makeOrderWithItem($user, 'selesai');

        $this->actingAs($user)
             ->post(route('orders.returns.store', $order->order_number), $this->validPayload($item))
             ->assertRedirect(route('orders.returns.show', $order->order_number));

        $this->assertDatabaseHas('returns', [
            'order_id'            => $order->id,
            'evidence_photo_path' => null,
        ]);
    }

    // ── WhatsApp Link on Confirmation Page ───────────────────────────────────

    public function test_whatsapp_link_contains_correct_order_and_return_number(): void
    {
        // Seed the admin WhatsApp number
        ShopSetting::set('admin_whatsapp_number', '6281234567890');

        $user = $this->makeUser();
        [$order, $item] = $this->makeOrderWithItem($user, 'selesai');

        $this->actingAs($user)
             ->post(route('orders.returns.store', $order->order_number), $this->validPayload($item));

        $returnRequest = ReturnRequest::where('order_id', $order->id)->firstOrFail();

        $response = $this->actingAs($user)
             ->get(route('orders.returns.show', $order->order_number))
             ->assertOk();

        // The page should contain both numbers somewhere (in the wa.me link or message text)
        $response->assertSee($order->order_number, false);
        $response->assertSee($returnRequest->return_number, false);
        $response->assertSee('wa.me/6281234567890', false);
    }

    public function test_whatsapp_button_does_not_render_when_admin_number_is_placeholder(): void
    {
        // '62XXXXXXXXXX' is the seeder default placeholder. After stripping non-digits
        // it becomes '62' (2 chars) — well below the 10-digit minimum — so $waLink
        // must be null and the wa.me button must not appear in the HTML.
        ShopSetting::set('admin_whatsapp_number', '62XXXXXXXXXX');

        $user = $this->makeUser();
        [$order, $item] = $this->makeOrderWithItem($user, 'selesai');

        $this->actingAs($user)
             ->post(route('orders.returns.store', $order->order_number), $this->validPayload($item));

        $this->actingAs($user)
             ->get(route('orders.returns.show', $order->order_number))
             ->assertOk()
             ->assertDontSee('wa.me/', false);
    }

    // ── Button Visibility on Order Detail Page ────────────────────────────────

    public function test_ajukan_retur_button_visible_for_dikirim_without_existing_return(): void
    {
        $user = $this->makeUser();
        [$order] = $this->makeOrderWithItem($user, 'dikirim');

        $this->actingAs($user)
             ->get(route('orders.show', $order->order_number))
             ->assertOk()
             ->assertSee('Ajukan Retur');
    }

    public function test_ajukan_retur_button_visible_for_selesai_without_existing_return(): void
    {
        $user = $this->makeUser();
        [$order] = $this->makeOrderWithItem($user, 'selesai');

        $this->actingAs($user)
             ->get(route('orders.show', $order->order_number))
             ->assertOk()
             ->assertSee('Ajukan Retur');
    }

    public function test_ajukan_retur_button_not_visible_when_return_already_exists(): void
    {
        $user = $this->makeUser();
        [$order, $item] = $this->makeOrderWithItem($user, 'selesai');

        ReturnRequest::create([
            'order_id'        => $order->id,
            'user_id'         => $user->id,
            'return_number'   => 'RET-20260101-ABCD',
            'resolution_type' => 'refund',
            'reason'          => 'Sudah ada.',
            'status'          => 'diajukan',
        ]);

        $this->actingAs($user)
             ->get(route('orders.show', $order->order_number))
             ->assertOk()
             ->assertDontSee('Ajukan Retur')
             ->assertSee('Lihat Detail Retur');
    }

    public function test_ajukan_retur_button_not_visible_for_pending_order(): void
    {
        $user = $this->makeUser();
        [$order] = $this->makeOrderWithItem($user, 'pending');

        $this->actingAs($user)
             ->get(route('orders.show', $order->order_number))
             ->assertOk()
             ->assertDontSee('Ajukan Retur');
    }

    public function test_ajukan_retur_button_not_visible_for_dibatalkan_order(): void
    {
        $user = $this->makeUser();
        [$order] = $this->makeOrderWithItem($user, 'dibatalkan');

        $this->actingAs($user)
             ->get(route('orders.show', $order->order_number))
             ->assertOk()
             ->assertDontSee('Ajukan Retur');
    }
}
