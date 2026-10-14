<?php

namespace Tests\Feature\Admin;

use App\Mail\OrderCancelledMail;
use App\Models\Category;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherUsage;
use App\Services\MidtransService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Mockery;
use Tests\TestCase;

/**
 * AdminCancelPaidOrderTest
 *
 * Fitur admin "Batalkan Pesanan" (POST admin/orders/{order}/cancel-paid):
 * order paid/diproses -> dibatalkan, stok & kuota voucher dipulihkan,
 * payment TETAP 'success' (dana tertangkap -> alur refund refund-api /
 * refund-manual yang sudah ada), email merchant_cancel ke pelanggan.
 *
 * Catatan: lockForUpdate tidak benar-benar diuji (SQLite :memory:),
 * jadi tidak ada test yang mengklaim membuktikan perilaku locking.
 */
class AdminCancelPaidOrderTest extends TestCase
{
    use RefreshDatabase;

    private const REASON = 'Barang rusak saat pemeriksaan gudang.';

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
    }

    private function customer(): User
    {
        return User::factory()->create(['role' => 'customer', 'email_verified_at' => now()]);
    }

    private function makeProduct(int $stock): Product
    {
        $category = Category::create(['name' => 'Aksesori', 'slug' => 'aksesori-' . uniqid(), 'is_active' => true]);

        return Product::create([
            'category_id' => $category->id,
            'name'        => 'Kalung Perak',
            'slug'        => 'kalung-' . uniqid(),
            'price'       => 75000,
            'stock'       => $stock,
            'weight'      => 50,
            'is_active'   => true,
        ]);
    }

    private function makeOrder(string $status, array $overrides = []): Order
    {
        $user    = $this->customer();
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

        return Order::create(array_merge([
            'order_number' => 'ORD-ACP-' . uniqid(),
            'user_id'      => $user->id,
            'address_id'   => $address->id,
            'subtotal'     => 150000,
            'total_amount' => 165000,
            'status'       => $status,
        ], $overrides));
    }

    private function attachPayment(Order $order, string $method = 'gopay', string $status = 'success'): Payment
    {
        return Payment::create([
            'order_id'          => $order->id,
            'midtrans_order_id' => $order->order_number,
            'amount'            => $order->total_amount,
            'status'            => $status,
            'payment_method'    => $method,
            'snap_token'        => 'tok-' . uniqid(),
        ]);
    }

    private function attachItem(Order $order, Product $product, int $qty, ?ProductVariant $variant = null): void
    {
        $order->items()->create([
            'product_id'   => $product->id,
            'variant_id'   => $variant?->id,
            'variant_name' => $variant?->variant_name,
            'product_name' => $product->name,
            'quantity'     => $qty,
            'price'        => $product->price,
            'subtotal'     => $product->price * $qty,
        ]);
    }

    private function makeVoucher(int $usedCount = 0, int $quota = 10): Voucher
    {
        return Voucher::create([
            'code'           => 'ACP' . strtoupper(substr(uniqid(), -6)),
            'discount_type'  => 'percent',
            'discount_value' => 10,
            'quota'          => $quota,
            'used_count'     => $usedCount,
            'min_purchase'   => 0,
            'max_discount'   => null,
            'start_date'     => now()->subDay()->format('Y-m-d'),
            'end_date'       => now()->addDay()->format('Y-m-d'),
            'is_active'      => true,
        ]);
    }

    // ── 1–2. Pembatalan order paid / diproses ────────────────────────────────

    public function test_admin_cancels_paid_order(): void
    {
        Mail::fake();

        // Stok 8 = 10 - 2 (reservasi checkout masih dipegang order paid)
        $product = $this->makeProduct(8);
        $order   = $this->makeOrder('paid');
        $this->attachPayment($order);
        $this->attachItem($order, $product, 2);

        $this->actingAs($this->admin())
            ->post(route('admin.orders.cancel-paid', $order->id), ['reason' => self::REASON])
            ->assertRedirect(route('admin.orders.index', ['payment_captured' => 1]))
            ->assertSessionHas('success');

        $order->refresh();
        $this->assertSame('dibatalkan', $order->status);
        $this->assertStringContainsString('[DIBATALKAN ADMIN: ' . self::REASON, (string) $order->notes);

        // Payment sengaja TIDAK diubah — harus tetap 'success' agar masuk
        // daftar dana tertangkap dan bisa refund-api/refund-manual.
        $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'status' => 'success']);

        // Reservasi stok dipulihkan: 8 + 2 = 10
        $this->assertSame(10, (int) $product->fresh()->stock);
    }

    public function test_admin_cancels_diproses_order(): void
    {
        Mail::fake();

        $product = $this->makeProduct(8);
        $order   = $this->makeOrder('diproses');
        $this->attachPayment($order);
        $this->attachItem($order, $product, 2);

        $this->actingAs($this->admin())
            ->post(route('admin.orders.cancel-paid', $order->id), ['reason' => self::REASON])
            ->assertRedirect(route('admin.orders.index', ['payment_captured' => 1]))
            ->assertSessionHas('success');

        $order->refresh();
        $this->assertSame('dibatalkan', $order->status);
        $this->assertStringContainsString('[DIBATALKAN ADMIN: ' . self::REASON, (string) $order->notes);
        $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'status' => 'success']);
        $this->assertSame(10, (int) $product->fresh()->stock);
    }

    // ── 3. Order varian: stok varian pulih + parent di-sync ──────────────────

    public function test_variant_order_restores_variant_stock_and_resyncs_parent(): void
    {
        Mail::fake();

        $product = $this->makeProduct(7); // agregat varian aktif: 3 + 4
        $variantA = ProductVariant::create([
            'product_id'   => $product->id,
            'variant_type' => 'Ukuran',
            'variant_name' => 'Size 6',
            'sku'          => 'ACP-S6-' . uniqid(),
            'price_adjust' => 0,
            'stock'        => 3,
            'is_active'    => true,
        ]);
        $variantB = ProductVariant::create([
            'product_id'   => $product->id,
            'variant_type' => 'Ukuran',
            'variant_name' => 'Size 7',
            'sku'          => 'ACP-S7-' . uniqid(),
            'price_adjust' => 0,
            'stock'        => 4,
            'is_active'    => true,
        ]);

        $order = $this->makeOrder('paid');
        $this->attachPayment($order);
        $this->attachItem($order, $product, 2, $variantA);

        $this->actingAs($this->admin())
            ->post(route('admin.orders.cancel-paid', $order->id), ['reason' => self::REASON])
            ->assertSessionHas('success');

        $this->assertSame('dibatalkan', $order->fresh()->status);

        // Stok varian A pulih: 3 + 2 = 5; varian B tidak tersentuh
        $this->assertSame(5, (int) $variantA->fresh()->stock);
        $this->assertSame(4, (int) $variantB->fresh()->stock);

        // Parent di-sync dari varian AKTIF: 5 + 4 = 9
        $this->assertSame(9, (int) $product->fresh()->stock);
    }

    // ── 4–5. Voucher: dengan & tanpa rekaman VoucherUsage ────────────────────

    public function test_voucher_usage_is_released_and_used_count_decremented(): void
    {
        Mail::fake();

        $voucher = $this->makeVoucher(usedCount: 1);
        $order   = $this->makeOrder('paid', ['voucher_id' => $voucher->id, 'voucher_discount' => 15000]);
        $this->attachPayment($order);
        VoucherUsage::create([
            'voucher_id'        => $voucher->id,
            'user_id'           => $order->user_id,
            'order_id'          => $order->id,
            'discount_applied'  => 15000,
        ]);

        $this->actingAs($this->admin())
            ->post(route('admin.orders.cancel-paid', $order->id), ['reason' => self::REASON])
            ->assertSessionHas('success');

        $this->assertSame('dibatalkan', $order->fresh()->status);
        $this->assertDatabaseMissing('voucher_usages', ['order_id' => $order->id]);
        $this->assertSame(0, (int) $voucher->fresh()->used_count);
    }

    public function test_voucher_without_usage_record_leaves_used_count_unchanged(): void
    {
        Mail::fake();

        // Kasus kuota habis saat settlement: order punya voucher_id tapi
        // tidak ada baris VoucherUsage -> used_count TIDAK boleh dikurangi.
        $voucher = $this->makeVoucher(usedCount: 3);
        $order   = $this->makeOrder('paid', ['voucher_id' => $voucher->id, 'voucher_discount' => 15000]);
        $this->attachPayment($order);

        $this->actingAs($this->admin())
            ->post(route('admin.orders.cancel-paid', $order->id), ['reason' => self::REASON])
            ->assertSessionHas('success');

        $this->assertSame('dibatalkan', $order->fresh()->status);
        $this->assertDatabaseMissing('voucher_usages', ['order_id' => $order->id]);
        $this->assertSame(3, (int) $voucher->fresh()->used_count);
    }

    // ── 6. Status yang ditolak tidak disentuh ────────────────────────────────

    public function test_rejected_statuses_are_left_untouched(): void
    {
        Mail::fake();
        $admin = $this->admin();

        foreach (['pending', 'dikirim', 'selesai', 'dibatalkan'] as $status) {
            $product = $this->makeProduct(8);
            $order   = $this->makeOrder($status);
            $this->attachPayment($order);
            $this->attachItem($order, $product, 2);

            $this->actingAs($admin)
                ->post(route('admin.orders.cancel-paid', $order->id), ['reason' => self::REASON])
                ->assertRedirect(route('admin.orders.index'))
                ->assertSessionHas('error', fn ($message) => str_contains($message, 'Pesanan ini tidak dapat dibatalkan dari sini'));

            $order->refresh();
            $this->assertSame($status, $order->status, "Status {$status} tidak boleh berubah");
            $this->assertNull($order->notes, "Notes tidak boleh diubah untuk status {$status}");
            $this->assertSame(8, (int) $product->fresh()->stock, "Stok tidak boleh berubah untuk status {$status}");
            $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'status' => 'success']);
            Mail::assertNothingSent();
        }
    }

    // ── 7. Idempoten: dua kali panggil -> efek hanya sekali ──────────────────

    public function test_second_call_is_rejected_and_effects_appear_only_once(): void
    {
        Mail::fake();

        $product = $this->makeProduct(8);
        $voucher = $this->makeVoucher(usedCount: 1);
        $order   = $this->makeOrder('paid', ['voucher_id' => $voucher->id, 'voucher_discount' => 15000]);
        $this->attachPayment($order);
        $this->attachItem($order, $product, 2);
        VoucherUsage::create([
            'voucher_id'       => $voucher->id,
            'user_id'          => $order->user_id,
            'order_id'         => $order->id,
            'discount_applied' => 15000,
        ]);

        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.orders.cancel-paid', $order->id), ['reason' => self::REASON])
            ->assertSessionHas('success');

        $this->assertSame(10, (int) $product->fresh()->stock);
        $this->assertSame(0, (int) $voucher->fresh()->used_count);

        // Panggilan kedua: ditolak, tidak ada efek ganda
        $this->actingAs($admin)
            ->post(route('admin.orders.cancel-paid', $order->id), ['reason' => self::REASON])
            ->assertRedirect(route('admin.orders.index'))
            ->assertSessionHas('error');

        $this->assertSame('dibatalkan', $order->fresh()->status);
        $this->assertSame(10, (int) $product->fresh()->stock, 'Stok tidak boleh dipulihkan dua kali');
        $this->assertSame(0, (int) $voucher->fresh()->used_count, 'used_count tidak boleh dikurangi dua kali');
        $this->assertDatabaseMissing('voucher_usages', ['order_id' => $order->id]);
    }

    // ── 8. Validasi reason ───────────────────────────────────────────────────

    public function test_reason_validation_rejects_invalid_input(): void
    {
        Mail::fake();
        $admin  = $this->admin();
        $reasons = [
            'empty'      => '',
            'too_short'  => 'abc',
            'too_long'   => str_repeat('x', 501),
        ];

        foreach ($reasons as $label => $reason) {
            $product = $this->makeProduct(8);
            $order   = $this->makeOrder('paid');
            $this->attachPayment($order);
            $this->attachItem($order, $product, 2);

            $this->actingAs($admin)
                ->from(route('admin.orders.index'))
                ->post(route('admin.orders.cancel-paid', $order->id), ['reason' => $reason])
                ->assertSessionHasErrors('reason');

            $order->refresh();
            $this->assertSame('paid', $order->status, "Order tidak boleh berubah saat reason {$label} tidak valid");
            $this->assertNull($order->notes);
            $this->assertSame(8, (int) $product->fresh()->stock, "Stok tidak boleh berubah saat reason {$label} tidak valid");
            Mail::assertNothingSent();
        }
    }

    // ── 9. Akses: guest & non-admin ──────────────────────────────────────────

    public function test_guest_is_redirected_to_login_and_customer_gets_403(): void
    {
        Mail::fake();

        $product = $this->makeProduct(8);
        $order   = $this->makeOrder('paid');
        $this->attachPayment($order);
        $this->attachItem($order, $product, 2);

        // Guest -> redirect login
        $this->post(route('admin.orders.cancel-paid', $order->id), ['reason' => self::REASON])
            ->assertRedirect(route('login'));

        $this->assertSame('paid', $order->fresh()->status);
        $this->assertSame(8, (int) $product->fresh()->stock);

        // Customer (bukan admin) -> 403
        $this->actingAs($this->customer())
            ->post(route('admin.orders.cancel-paid', $order->id), ['reason' => self::REASON])
            ->assertForbidden();

        $this->assertSame('paid', $order->fresh()->status);
        $this->assertNull($order->fresh()->notes);
        $this->assertSame(8, (int) $product->fresh()->stock);
        Mail::assertNothingSent();
    }

    // ── 10. Email merchant_cancel ────────────────────────────────────────────

    public function test_merchant_cancel_mail_is_sent_once_with_reason(): void
    {
        Mail::fake();
        $admin = $this->admin();

        // Ditolak -> tidak ada email
        $rejected = $this->makeOrder('dikirim');
        $this->attachPayment($rejected);
        $this->actingAs($admin)
            ->post(route('admin.orders.cancel-paid', $rejected->id), ['reason' => self::REASON])
            ->assertSessionHas('error');
        Mail::assertNothingSent();

        // Berhasil -> tepat satu OrderCancelledMail merchant_cancel
        $product = $this->makeProduct(8);
        $order   = $this->makeOrder('paid');
        $this->attachPayment($order);
        $this->attachItem($order, $product, 2);
        $customerEmail = $order->user->email;

        $this->actingAs($admin)
            ->post(route('admin.orders.cancel-paid', $order->id), ['reason' => self::REASON])
            ->assertSessionHas('success');

        Mail::assertSentCount(1);
        Mail::assertSent(OrderCancelledMail::class, function (OrderCancelledMail $mail) use ($customerEmail) {
            return $mail->hasTo($customerEmail)
                && $mail->event === 'merchant_cancel'
                && str_contains($mail->envelope()->subject, 'Dibatalkan oleh Toko')
                && str_contains($mail->render(), self::REASON);
        });
    }

    // ── 11. Kegagalan email tidak membatalkan pembatalan ─────────────────────

    public function test_mail_failure_does_not_undo_cancellation(): void
    {
        $product = $this->makeProduct(8);
        $order   = $this->makeOrder('paid');
        $this->attachPayment($order);
        $this->attachItem($order, $product, 2);

        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP down'));

        $this->actingAs($this->admin())
            ->post(route('admin.orders.cancel-paid', $order->id), ['reason' => self::REASON])
            ->assertRedirect(route('admin.orders.index', ['payment_captured' => 1]))
            ->assertSessionHas('success', fn ($message) => str_contains($message, 'gagal terkirim'));

        // Pembatalan tetap tersimpan; stok tetap pulih; payment tetap success
        $this->assertSame('dibatalkan', $order->fresh()->status);
        $this->assertSame(10, (int) $product->fresh()->stock);
        $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'status' => 'success']);
    }

    // ── 12. Follow-on: masuk daftar dana tertangkap lalu refund-manual ────────

    public function test_cancelled_paid_order_appears_in_captured_funds_and_can_be_refunded_manually(): void
    {
        Mail::fake();
        Http::fake();

        $product = $this->makeProduct(8);
        $order   = $this->makeOrder('paid');
        $this->attachPayment($order, 'bank_transfer');
        $this->attachItem($order, $product, 2);

        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.orders.cancel-paid', $order->id), ['reason' => self::REASON])
            ->assertSessionHas('success');

        // Muncul di daftar dana tertangkap, lengkap dengan tombol refund
        $this->actingAs($admin)
            ->get(route('admin.orders.index', ['payment_captured' => 1]))
            ->assertOk()
            ->assertSee($order->order_number)
            ->assertSee(route('admin.orders.refund-manual', $order->id), false);

        // Refund manual sukses untuk pesanan ini
        $this->actingAs($admin)
            ->post(route('admin.orders.refund-manual', $order->id), [
                'manual_notes' => 'Transfer bank BCA a.n. pelanggan.',
            ])
            ->assertRedirect(route('admin.orders.index', ['payment_captured' => 1]))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'status' => 'refunded']);
        Http::assertNothingSent();
    }

    // ── 13. Regresi webhook: pelepasan voucher saat cancel dari paid ─────────

    public function test_webhook_settle_then_cancel_releases_voucher_exactly_once(): void
    {
        Mail::fake();

        $midtransMock = Mockery::mock(MidtransService::class);
        $midtransMock->shouldReceive('verifySignature')->andReturn(true);
        $this->app->instance(MidtransService::class, $midtransMock);

        $voucher = $this->makeVoucher(usedCount: 0, quota: 5);
        $order   = $this->makeOrder('pending', ['voucher_id' => $voucher->id, 'voucher_discount' => 10000]);
        $this->attachPayment($order, 'gopay', 'pending');

        $basePayload = [
            'order_id'      => $order->order_number,
            'payment_type'  => 'gopay',
            'status_code'   => '200',
            'gross_amount'  => (string) $order->total_amount,
            'signature_key' => 'dummy_signature',
            'fraud_status'  => 'accept',
        ];

        // pending -> settlement: pemakaian voucher tercatat tepat satu kali
        $this->postJson(route('midtrans.webhook'), array_merge($basePayload, ['transaction_status' => 'settlement']))
            ->assertOk();

        $this->assertSame('paid', $order->fresh()->status);
        $this->assertSame(1, (int) $voucher->fresh()->used_count);
        $this->assertSame(1, VoucherUsage::where('order_id', $order->id)->count());

        // paid -> cancel (webhook): usage dilepas, used_count turun tepat 1
        $this->postJson(route('midtrans.webhook'), array_merge($basePayload, ['transaction_status' => 'cancel']))
            ->assertOk();

        $this->assertSame('dibatalkan', $order->fresh()->status);
        $this->assertSame(0, (int) $voucher->fresh()->used_count);
        $this->assertSame(0, VoucherUsage::where('order_id', $order->id)->count());

        // Pengiriman webhook cancel yang diulang tidak boleh mengurangi lagi
        $this->postJson(route('midtrans.webhook'), array_merge($basePayload, ['transaction_status' => 'cancel']))
            ->assertOk();

        $this->assertSame('dibatalkan', $order->fresh()->status);
        $this->assertSame(0, (int) $voucher->fresh()->used_count, 'used_count tidak boleh kurang dari 0');
        $this->assertSame(0, VoucherUsage::where('order_id', $order->id)->count());
    }

    // ── UI: tombol hanya untuk paid / diproses ───────────────────────────────

    public function test_cancel_button_only_shown_for_paid_and_diproses_orders(): void
    {
        Mail::fake();

        $paid     = $this->makeOrder('paid');
        $diproses = $this->makeOrder('diproses');
        $dikirim  = $this->makeOrder('dikirim');

        $this->actingAs($this->admin())
            ->get(route('admin.orders.index'))
            ->assertOk()
            ->assertSee(route('admin.orders.cancel-paid', $paid->id), false)
            ->assertSee(route('admin.orders.cancel-paid', $diproses->id), false)
            ->assertDontSee(route('admin.orders.cancel-paid', $dikirim->id), false);
    }

    // ── Regresi: modal action bertahan setelah validasi gagal ────────────────

    public function test_failed_validation_rerenders_modal_with_correct_cancel_action_and_error(): void
    {
        Mail::fake();

        $product = $this->makeProduct(8);
        $order   = $this->makeOrder('paid');
        $this->attachPayment($order);
        $this->attachItem($order, $product, 2);

        $cancelUrl = route('admin.orders.cancel-paid', $order->id);

        $response = $this->actingAs($this->admin())
            ->from(route('admin.orders.index'))
            ->post($cancelUrl, [
                'reason'               => 'abc',
                '_cancel_action'       => $cancelUrl,
                '_cancel_order_number' => $order->order_number,
            ]);

        $response->assertSessionHasErrors('reason');
        // Ikuti redirect ke halaman index dengan cookie session (seperti browser)
        $page = $this->followRedirectWithSession($response);

        // Modal yang dibuka ulang tetap menunjuk ke URL cancel order ini —
        // periksa state awal Alpine di modal, bukan sekadar kemunculan URL
        // (URL yang sama juga ada di payload tombol $dispatch di baris tabel).
        $page->assertOk()
            ->assertSee('cancelAction: ' . \Illuminate\Support\Js::from($cancelUrl), false)
            ->assertSee('orderNumber: ' . \Illuminate\Support\Js::from($order->order_number), false)
            ->assertSee('Alasan pembatalan minimal 5 karakter.', false);

        // Server tidak memproses apapun: order & stok tidak berubah
        $this->assertSame('paid', $order->fresh()->status);
        $this->assertSame(8, (int) $product->fresh()->stock);
    }

    public function test_foreign_cancel_action_is_ignored_after_failed_validation(): void
    {
        Mail::fake();

        $order = $this->makeOrder('paid');

        $response = $this->actingAs($this->admin())
            ->from(route('admin.orders.index'))
            ->post(route('admin.orders.cancel-paid', $order->id), [
                'reason'               => 'abc',
                '_cancel_action'       => 'https://evil.example/steal',
                '_cancel_order_number' => $order->order_number,
            ]);

        $response->assertSessionHasErrors('reason');
        // Ikuti redirect ke halaman index dengan cookie session (seperti browser)
        $page = $this->followRedirectWithSession($response);

        // Nilai _cancel_action asing TIDAK boleh masuk ke modal; action awal kosong
        $page->assertOk();
        $page->assertDontSee('https://evil.example/steal', false);
        $page->assertSee("cancelAction: ''", false);

        $this->assertSame('paid', $order->fresh()->status);
    }

    public function test_index_modal_contains_cancel_action_hidden_fields(): void
    {
        Mail::fake();

        $this->makeOrder('paid');

        $this->actingAs($this->admin())
            ->get(route('admin.orders.index'))
            ->assertOk()
            ->assertSee('name="_cancel_action"', false)
            ->assertSee('name="_cancel_order_number"', false);
    }

    public function test_successful_cancel_still_works_when_hidden_cancel_fields_are_posted(): void
    {
        Mail::fake();

        $product = $this->makeProduct(8);
        $order   = $this->makeOrder('paid');
        $this->attachPayment($order);
        $this->attachItem($order, $product, 2);

        $this->actingAs($this->admin())
            ->post(route('admin.orders.cancel-paid', $order->id), [
                'reason'               => self::REASON,
                '_cancel_action'       => route('admin.orders.cancel-paid', $order->id),
                '_cancel_order_number' => $order->order_number,
            ])
            ->assertRedirect(route('admin.orders.index', ['payment_captured' => 1]))
            ->assertSessionHas('success');

        // Hasil identik dengan POST tanpa field tersembunyi: sekali efek saja
        $this->assertSame('dibatalkan', $order->fresh()->status);
        $this->assertSame(10, (int) $product->fresh()->stock);
        $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'status' => 'success']);
    }

    /**
     * Ikuti redirect ke halaman index dengan membawa cookie session dari
     * response POST — sama seperti browser asli. Tanpa cookie, store in-memory
     * mempertahankan objek ViewErrorBag lama dan marshalErrorBag (session
     * serialization=json) mengosongkannya, sehingga @error tidak tampil.
     */
    private function followRedirectWithSession(TestResponse $response): TestResponse
    {
        $sessionCookie = collect($response->headers->getCookies())
            ->first(fn ($cookie) => $cookie->getName() === config('session.cookie'));

        $this->withUnencryptedCookies([
            config('session.cookie') => $sessionCookie->getValue(),
        ]);

        return $this->get($response->headers->get('Location'));
    }
}
