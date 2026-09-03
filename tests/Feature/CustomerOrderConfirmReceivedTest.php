<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * CustomerOrderConfirmReceivedTest
 *
 * Tests the customer "Konfirmasi Pesanan Diterima" feature:
 *   PATCH /orders/{orderNumber}/confirm-received  (orders.confirm-received)
 *
 * Covers:
 *  - Guest redirected to login
 *  - User cannot confirm another user's order (IDOR / 404)
 *  - dikirim → selesai succeeds, success flash shown
 *  - All other statuses are rejected (status unchanged, error flash)
 *  - Button visibility in show.blade.php for dikirim (assertSee) vs other statuses (assertDontSee)
 */
class CustomerOrderConfirmReceivedTest extends TestCase
{
    use RefreshDatabase;

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function makeUser(): User
    {
        return User::factory()->create(['email_verified_at' => now()]);
    }

    /**
     * Create a minimal order for a given user with the given status.
     * Matches the Order::create pattern used across the existing test suite.
     */
    private function makeOrder(User $user, string $status = 'dikirim'): Order
    {
        $address = $user->addresses()->create([
            'label'          => 'Utama',
            'recipient_name' => $user->name,
            'phone'          => '081200000000',
            'province'       => 'Jawa Timur',
            'city'           => 'Jember',
            'district'       => 'Sumbersari',
            'postal_code'    => '68121',
            'full_address'   => 'Jl. Test No. 1',
        ]);

        return Order::create([
            'order_number' => 'ORD-CONFIRMTEST-' . uniqid(),
            'user_id'      => $user->id,
            'address_id'   => $address->id,
            'subtotal'     => 100000,
            'total_amount' => 100000,
            'status'       => $status,
        ]);
    }

    // ── Access Control ───────────────────────────────────────────────────────

    public function test_guest_is_redirected_to_login(): void
    {
        $user  = $this->makeUser();
        $order = $this->makeOrder($user, 'dikirim');

        $this->patch("/orders/{$order->order_number}/confirm-received")
             ->assertRedirect('/login');
    }

    public function test_user_cannot_confirm_another_users_order(): void
    {
        $owner   = $this->makeUser();
        $other   = $this->makeUser();
        $order   = $this->makeOrder($owner, 'dikirim');

        // $other tries to confirm $owner's order — ownership scoping returns 404
        $this->actingAs($other)
             ->patch("/orders/{$order->order_number}/confirm-received")
             ->assertNotFound();

        $this->assertEquals('dikirim', $order->fresh()->status);
    }

    // ── Successful Confirmation ───────────────────────────────────────────────

    public function test_user_can_confirm_their_dikirim_order(): void
    {
        $user  = $this->makeUser();
        $order = $this->makeOrder($user, 'dikirim');

        $this->actingAs($user)
             ->patch("/orders/{$order->order_number}/confirm-received")
             ->assertRedirect(route('orders.show', $order->order_number))
             ->assertSessionHas('success');

        $this->assertEquals('selesai', $order->fresh()->status);
    }

    // ── Rejected Statuses ────────────────────────────────────────────────────

    /**
     * @param  string  $status
     */
    #[DataProvider('nonDikirimStatuses')]
    public function test_user_cannot_confirm_order_with_non_dikirim_status(string $status): void
    {
        $user  = $this->makeUser();
        $order = $this->makeOrder($user, $status);

        $this->actingAs($user)
             ->patch("/orders/{$order->order_number}/confirm-received")
             ->assertRedirect(route('orders.show', $order->order_number))
             ->assertSessionHas('error');

        $this->assertEquals($status, $order->fresh()->status);
    }

    public static function nonDikirimStatuses(): array
    {
        return [
            'pending'    => ['pending'],
            'paid'       => ['paid'],
            'diproses'   => ['diproses'],
            'selesai'    => ['selesai'],
            'dibatalkan' => ['dibatalkan'],
        ];
    }

    // ── Blade View Button Visibility ─────────────────────────────────────────

    public function test_confirm_button_is_visible_when_status_is_dikirim(): void
    {
        $user  = $this->makeUser();
        $order = $this->makeOrder($user, 'dikirim');

        $this->actingAs($user)
             ->get("/orders/{$order->order_number}")
             ->assertOk()
             ->assertSee('Konfirmasi Pesanan Diterima');
    }

    public function test_confirm_button_appears_exactly_once_when_status_is_dikirim(): void
    {
        $user  = $this->makeUser();
        $order = $this->makeOrder($user, 'dikirim');

        $response = $this->actingAs($user)
                         ->get("/orders/{$order->order_number}")
                         ->assertOk();

        $count = substr_count($response->getContent(), 'Konfirmasi Pesanan Diterima');

        $this->assertSame(1, $count, "Expected 'Konfirmasi Pesanan Diterima' to appear exactly once, found {$count} time(s).");
    }

    public function test_confirm_button_is_not_visible_when_status_is_pending(): void
    {
        $user  = $this->makeUser();
        $order = $this->makeOrder($user, 'pending');

        $this->actingAs($user)
             ->get("/orders/{$order->order_number}")
             ->assertOk()
             ->assertDontSee('Konfirmasi Pesanan Diterima');
    }

    public function test_confirm_button_is_not_visible_when_status_is_selesai(): void
    {
        $user  = $this->makeUser();
        $order = $this->makeOrder($user, 'selesai');

        $this->actingAs($user)
             ->get("/orders/{$order->order_number}")
             ->assertOk()
             ->assertDontSee('Konfirmasi Pesanan Diterima');
    }

    public function test_confirm_button_is_not_visible_when_status_is_paid(): void
    {
        $user  = $this->makeUser();
        $order = $this->makeOrder($user, 'paid');

        $this->actingAs($user)
             ->get("/orders/{$order->order_number}")
             ->assertOk()
             ->assertDontSee('Konfirmasi Pesanan Diterima');
    }

    public function test_confirm_button_is_not_visible_when_status_is_dibatalkan(): void
    {
        $user  = $this->makeUser();
        $order = $this->makeOrder($user, 'dibatalkan');

        $this->actingAs($user)
             ->get("/orders/{$order->order_number}")
             ->assertOk()
             ->assertDontSee('Konfirmasi Pesanan Diterima');
    }
}
