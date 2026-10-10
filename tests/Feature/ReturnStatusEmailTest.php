<?php

namespace Tests\Feature;

use App\Mail\ReturnStatusMail;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ReturnItem;
use App\Models\ReturnRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ReturnStatusEmailTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
    }

    private function customer(): User
    {
        return User::factory()->create(['role' => 'customer', 'email_verified_at' => now()]);
    }

    private function makeReturn(User $user, string $status = 'diajukan', string $resolution = 'refund'): ReturnRequest
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

        $order = Order::create([
            'order_number' => 'ORD-RSE-' . uniqid(),
            'user_id'      => $user->id,
            'address_id'   => $address->id,
            'subtotal'     => 150000,
            'total_amount' => 165000,
            'status'       => 'selesai',
        ]);

        Payment::create([
            'order_id'          => $order->id,
            'midtrans_order_id' => $order->order_number,
            'amount'            => 165000,
            'status'            => 'success',
            'payment_method'    => 'gopay',
        ]);

        $category = \App\Models\Category::create(['name' => 'Lilin', 'slug' => 'lilin-rse-' . uniqid(), 'is_active' => true]);
        $product  = \App\Models\Product::create([
            'category_id' => $category->id,
            'name'        => 'Lilin Aromaterapi',
            'slug'        => 'lilin-rse-' . uniqid(),
            'price'       => 150000,
            'stock'       => 10,
            'weight'      => 50,
            'is_active'   => true,
        ]);
        $item = $order->items()->create([
            'product_id'   => $product->id,
            'product_name' => 'Lilin Aromaterapi',
            'quantity'     => 1,
            'price'        => 150000,
            'subtotal'     => 150000,
        ]);

        $ret = ReturnRequest::create([
            'order_id'        => $order->id,
            'user_id'         => $user->id,
            'return_number'   => 'RET-RSE-' . uniqid(),
            'resolution_type' => $resolution,
            'reason'          => 'Produk cacat.',
            'status'          => $status,
            'refund_amount'   => $resolution === 'refund' ? 150000 : null,
        ]);

        ReturnItem::create([
            'return_id'     => $ret->id,
            'order_item_id' => $item->id,
            'quantity'      => 1,
        ]);

        return $ret;
    }

    public function test_approving_refund_return_sends_approved_email(): void
    {
        Mail::fake();
        $customer = $this->customer();
        $ret      = $this->makeReturn($customer, 'diajukan', 'refund');

        $this->actingAs($this->admin())
            ->patch(route('admin.returns.approve', $ret->id), ['refund_amount' => 150000])
            ->assertSessionHas('success');

        Mail::assertSent(ReturnStatusMail::class, function (ReturnStatusMail $mail) use ($customer, $ret) {
            return $mail->hasTo($customer->email)
                && $mail->event === 'approved'
                && str_contains($mail->envelope()->subject, $ret->return_number);
        });
    }

    public function test_approving_exchange_return_sends_approved_email(): void
    {
        Mail::fake();
        $customer = $this->customer();
        $ret      = $this->makeReturn($customer, 'diajukan', 'tukar_barang');

        $this->actingAs($this->admin())
            ->patch(route('admin.returns.approve', $ret->id))
            ->assertSessionHas('success');

        Mail::assertSent(ReturnStatusMail::class, fn (ReturnStatusMail $mail) => $mail->event === 'approved' && $mail->hasTo($customer->email));
    }

    public function test_rejecting_return_sends_rejected_email(): void
    {
        Mail::fake();
        $customer = $this->customer();
        $ret      = $this->makeReturn($customer);

        $this->actingAs($this->admin())
            ->patch(route('admin.returns.reject', $ret->id), ['admin_notes' => 'Bukti foto tidak jelas.'])
            ->assertSessionHas('success');

        Mail::assertSent(ReturnStatusMail::class, fn (ReturnStatusMail $mail) => $mail->event === 'rejected' && $mail->hasTo($customer->email));
    }

    public function test_api_refund_sends_refund_completed_email(): void
    {
        Http::fake([
            '*/refund' => Http::response([
                'status_code'        => '200',
                'status_message'     => 'Success, refund request is approved',
                'transaction_status' => 'refund',
            ], 200),
        ]);
        Mail::fake();
        $customer = $this->customer();
        $ret      = $this->makeReturn($customer, 'disetujui', 'refund');

        $this->actingAs($this->admin())
            ->patch(route('admin.returns.refund', $ret->id))
            ->assertSessionHas('success');

        Mail::assertSent(ReturnStatusMail::class, fn (ReturnStatusMail $mail) => $mail->event === 'refund_completed' && $mail->hasTo($customer->email));
    }

    public function test_manual_refund_sends_refund_completed_email(): void
    {
        Http::fake();
        Mail::fake();
        $customer = $this->customer();
        $ret      = $this->makeReturn($customer, 'disetujui', 'refund');

        $this->actingAs($this->admin())
            ->patch(route('admin.returns.refund_manual', $ret->id), ['manual_notes' => 'Transfer bank.'])
            ->assertSessionHas('success');

        Mail::assertSent(ReturnStatusMail::class, fn (ReturnStatusMail $mail) => $mail->event === 'refund_completed' && $mail->hasTo($customer->email));
    }

    public function test_email_failure_does_not_break_approve_redirect(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP down'));
        $customer = $this->customer();
        $ret      = $this->makeReturn($customer, 'diajukan', 'tukar_barang');

        $this->actingAs($this->admin())
            ->patch(route('admin.returns.approve', $ret->id))
            ->assertSessionHas('success');

        $this->assertSame('disetujui', $ret->fresh()->status);
    }

    public function test_email_view_renders_with_return_details(): void
    {
        $customer = $this->customer();
        $ret      = $this->makeReturn($customer, 'disetujui', 'refund');

        $html = (new ReturnStatusMail($ret, 'refund_completed'))->render();

        $this->assertStringContainsString($ret->return_number, $html);
        $this->assertStringContainsString('150.000', $html);
    }
}
