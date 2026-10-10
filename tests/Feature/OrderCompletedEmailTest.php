<?php

namespace Tests\Feature;

use App\Mail\OrderCompletedMail;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ShopSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class OrderCompletedEmailTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $email = 'customer@example.com'): User
    {
        return User::factory()->create([
            'name'              => 'Budi Customer',
            'email'             => $email,
            'email_verified_at' => now(),
        ]);
    }

    private function makeOrder(User $user, string $status = 'dikirim', int $daysOld = 0): Order
    {
        $address = $user->addresses()->create([
            'label'          => 'Rumah',
            'recipient_name' => $user->name,
            'phone'          => '081234567890',
            'province'       => 'Jawa Timur',
            'city'           => 'Jember',
            'district'       => 'Sumbersari',
            'postal_code'    => '68121',
            'full_address'   => 'Jl. Kalimantan No. 10',
        ]);

        $category = Category::firstOrCreate(
            ['slug' => 'aksesoris'],
            ['name' => 'Aksesoris', 'is_active' => true]
        );

        $product = Product::create([
            'category_id' => $category->id,
            'name'        => 'Tas Anyaman Bambu',
            'slug'        => 'tas-anyaman-bambu-' . uniqid(),
            'price'       => 150000,
            'stock'       => 10,
            'weight'      => 500,
            'is_active'   => true,
        ]);

        $order = Order::create([
            'order_number' => 'ORD-TEST-' . uniqid(),
            'user_id'      => $user->id,
            'address_id'   => $address->id,
            'subtotal'     => 150000,
            'total_amount' => 150000,
            'status'       => $status,
            'courier'      => 'jne',
        ]);

        OrderItem::create([
            'order_id'     => $order->id,
            'product_id'   => $product->id,
            'product_name' => $product->name,
            'variant_name' => 'Natural Medium',
            'price'        => 150000,
            'quantity'     => 1,
            'subtotal'     => 150000,
        ]);

        if ($daysOld > 0) {
            DB::table('orders')
                ->where('id', $order->id)
                ->update(['updated_at' => now()->subDays($daysOld)->toDateTimeString()]);
        }

        return $order->fresh(['address', 'items', 'user']);
    }

    // ── 1. Customer Confirmation Flow ────────────────────────────────────────

    public function test_customer_confirm_received_sends_completion_email(): void
    {
        Mail::fake();

        $user  = $this->makeUser('buyer@example.com');
        $order = $this->makeOrder($user, 'dikirim');

        $response = $this->actingAs($user)
            ->patch("/orders/{$order->order_number}/confirm-received");

        $response->assertRedirect(route('orders.show', $order->order_number))
            ->assertSessionHas('success');

        $this->assertEquals('selesai', $order->fresh()->status);

        Mail::assertSent(OrderCompletedMail::class, function (OrderCompletedMail $mail) use ($user, $order) {
            return $mail->hasTo($user->email)
                && $mail->order->id === $order->id;
        });
    }

    public function test_customer_confirm_received_does_not_send_mail_if_status_is_not_dikirim(): void
    {
        Mail::fake();

        $user  = $this->makeUser('buyer2@example.com');
        $order = $this->makeOrder($user, 'diproses');

        $response = $this->actingAs($user)
            ->patch("/orders/{$order->order_number}/confirm-received");

        $response->assertRedirect(route('orders.show', $order->order_number))
            ->assertSessionHas('error');

        $this->assertEquals('diproses', $order->fresh()->status);

        Mail::assertNotSent(OrderCompletedMail::class);
    }

    // ── 2. Auto Complete Command Flow ────────────────────────────────────────

    public function test_auto_complete_command_sends_completion_email(): void
    {
        Mail::fake();

        ShopSetting::set('order_auto_complete_days', '7');

        $user  = $this->makeUser('autobuyer@example.com');
        $order = $this->makeOrder($user, 'dikirim', 8);

        $this->artisan('orders:auto-complete')
            ->assertSuccessful();

        $this->assertEquals('selesai', $order->fresh()->status);

        Mail::assertSent(OrderCompletedMail::class, function (OrderCompletedMail $mail) use ($user, $order) {
            return $mail->hasTo($user->email)
                && $mail->order->id === $order->id;
        });
    }

    public function test_auto_complete_command_does_not_send_email_when_within_threshold(): void
    {
        Mail::fake();

        ShopSetting::set('order_auto_complete_days', '7');

        $user  = $this->makeUser('recentbuyer@example.com');
        $order = $this->makeOrder($user, 'dikirim', 3);

        $this->artisan('orders:auto-complete')
            ->assertSuccessful();

        $this->assertEquals('dikirim', $order->fresh()->status);

        Mail::assertNotSent(OrderCompletedMail::class);
    }

    // ── 3. Resilience: Email Failure Does Not Break Flows ─────────────────────

    public function test_confirm_received_email_failure_does_not_break_customer_redirect(): void
    {
        Mail::shouldReceive('to')
            ->once()
            ->andThrow(new \Exception('SMTP connection failure'));

        $user  = $this->makeUser('resilient@example.com');
        $order = $this->makeOrder($user, 'dikirim');

        $response = $this->actingAs($user)
            ->patch("/orders/{$order->order_number}/confirm-received");

        $response->assertRedirect(route('orders.show', $order->order_number))
            ->assertSessionHas('success');

        $this->assertEquals('selesai', $order->fresh()->status);
    }

    public function test_auto_complete_email_failure_does_not_abort_loop(): void
    {
        Mail::shouldReceive('to')
            ->andThrow(new \Exception('Mail transport down'));

        ShopSetting::set('order_auto_complete_days', '7');

        $user1  = $this->makeUser('order1@example.com');
        $order1 = $this->makeOrder($user1, 'dikirim', 8);

        $user2  = $this->makeUser('order2@example.com');
        $order2 = $this->makeOrder($user2, 'dikirim', 9);

        $this->artisan('orders:auto-complete')
            ->expectsOutput('Auto-completed 2 order(s).')
            ->assertSuccessful();

        $this->assertEquals('selesai', $order1->fresh()->status);
        $this->assertEquals('selesai', $order2->fresh()->status);
    }

    // ── 4. Mailable Envelope and Content ──────────────────────────────────────

    public function test_order_completed_mail_renders_subject_and_content(): void
    {
        $user  = $this->makeUser('content@example.com');
        $order = $this->makeOrder($user, 'selesai');

        $mailable = new OrderCompletedMail($order);

        $mailable->assertHasSubject("Pesanan #{$order->order_number} Selesai - el Craft");
        $mailable->assertSeeInHtml($order->order_number);
        $mailable->assertSeeInHtml('Tas Anyaman Bambu');
        $mailable->assertSeeInHtml('Budi Customer');
    }
}
