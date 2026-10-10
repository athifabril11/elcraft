<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherUsage;
use App\Services\MidtransService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * WebhookOutOfOrderTest
 *
 * Covers out-of-order / late Midtrans notifications against MidtransWebhookController:
 *   - a late 'pending' notification must NOT regress a settled ('paid') order
 *   - a 'cancel' notification must NOT cancel an order already in fulfillment ('diproses')
 *   - a 'cancel' notification on a 'pending' order still cancels (behavior preservation)
 */
class WebhookOutOfOrderTest extends TestCase
{
    use RefreshDatabase;

    private function mockSignatureValid(): void
    {
        $mock = Mockery::mock(MidtransService::class);
        $mock->shouldReceive('verifySignature')->andReturn(true);
        $this->app->instance(MidtransService::class, $mock);
    }

    private function postWebhook(string $orderNumber, string $transactionStatus): \Illuminate\Testing\TestResponse
    {
        return $this->postJson(route('midtrans.webhook'), [
            'order_id'           => $orderNumber,
            'transaction_status' => $transactionStatus,
            'status_code'        => '200',
            'gross_amount'       => '100000.00',
            'signature_key'      => 'dummy_signature',
            'payment_type'       => 'gopay',
            'fraud_status'       => 'accept',
        ]);
    }

    /**
     * @return array{0: User, 1: Product, 2: Order}
     */
    private function makeOrder(string $orderStatus, string $paymentStatus, int $stock = 10): array
    {
        $user = User::factory()->create();
        $category = Category::create(['name' => 'Kalung', 'slug' => 'kalung-ooo-' . uniqid(), 'is_active' => true]);
        $product = Product::create([
            'category_id' => $category->id,
            'name'        => 'Kalung Perak',
            'slug'        => 'kalung-perak-ooo-' . uniqid(),
            'price'       => 50000,
            'stock'       => $stock,
            'weight'      => 5,
            'is_active'   => true,
        ]);

        $address = $user->addresses()->create([
            'label'          => 'Utama',
            'recipient_name' => $user->name,
            'phone'          => '081200000010',
            'province'       => 'Jawa Barat',
            'city'           => 'Bandung',
            'district'       => 'Coblong',
            'postal_code'    => '40135',
            'full_address'   => 'Jl. Webhook Out Of Order No. 1',
        ]);

        $order = Order::create([
            'order_number' => 'ELCRAFT-OOO-' . uniqid(),
            'user_id'      => $user->id,
            'address_id'   => $address->id,
            'subtotal'     => 100000,
            'total_amount' => 100000,
            'status'       => $orderStatus,
        ]);

        $order->items()->create([
            'product_id'   => $product->id,
            'product_name' => $product->name,
            'price'        => 50000,
            'quantity'     => 2,
            'subtotal'     => 100000,
        ]);

        Payment::create([
            'order_id'          => $order->id,
            'midtrans_order_id' => $order->order_number,
            'amount'            => 100000,
            'status'            => $paymentStatus,
        ]);

        return [$user, $product, $order];
    }

    /**
     * Notifikasi 'pending' yang telat tiba SETELAH settlement tidak boleh
     * menurunkan status pesanan yang sudah 'paid' kembali ke 'pending'
     * (pembayaran sudah tercatat, stok sudah dipotong).
     */
    public function test_pending_notification_after_settlement_is_ignored(): void
    {
        $this->mockSignatureValid();
        [, $product, $order] = $this->makeOrder('pending', 'pending');

        // Settlement nyata: order -> paid, payment -> success, stok 10 - 2 = 8
        $this->postWebhook($order->order_number, 'settlement')->assertOk();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'paid']);
        $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'status' => 'success']);
        $product->refresh();
        $this->assertSame(8, $product->stock);

        // Notifikasi pending telat untuk order yang sama
        $this->postWebhook($order->order_number, 'pending')->assertOk();

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'paid']);
        $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'status' => 'success']);
        $product->refresh();
        $this->assertSame(8, $product->stock, 'Stock must remain deducted after late pending notification');
    }

    /**
     * Notifikasi 'cancel' untuk pesanan yang sudah berjalan ('diproses')
     * tidak boleh membatalkannya — stok sudah dipotong saat settlement dan
     * TIDAK akan dipulihkan, sehingga pembatalan diam-diam menghilangkan inventaris.
     */
    public function test_cancel_notification_on_processing_order_is_ignored(): void
    {
        $this->mockSignatureValid();
        [, $product, $order] = $this->makeOrder('diproses', 'success', 8);

        $this->postWebhook($order->order_number, 'cancel')->assertOk();

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'diproses']);
        $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'status' => 'success']);
        $product->refresh();
        $this->assertSame(8, $product->stock, 'Stock must remain untouched when cancel is ignored');
    }

    /**
     * Perilaku yang sudah ada harus dipertahankan: pesanan 'pending' yang
     * menerima notifikasi 'cancel' tetap dibatalkan (order -> dibatalkan,
     * payment -> failed).
     */
    public function test_cancel_notification_on_pending_order_still_cancels(): void
    {
        $this->mockSignatureValid();
        [, , $order] = $this->makeOrder('pending', 'pending');

        $this->postWebhook($order->order_number, 'cancel')->assertOk();

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'dibatalkan']);
        $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'status' => 'failed']);
    }

    /**
     * Notifikasi 'expire'/'deny' yang telat tiba SETELAH settlement tidak
     * boleh membatalkan pesanan 'paid' — pembayaran sudah tertangkap di
     * Midtrans, membatalkannya membuat selisih uang (money gap).
     */
    public function test_expire_notification_on_paid_order_is_ignored(): void
    {
        $this->mockSignatureValid();
        [, $product, $order] = $this->makeOrder('paid', 'success', 8);

        $this->postWebhook($order->order_number, 'expire')->assertOk();

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'paid']);
        $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'status' => 'success']);
        $product->refresh();
        $this->assertSame(8, $product->stock, 'Stock must remain untouched when expire on paid order is ignored');
    }

    /**
     * Pembatalan merchant ('cancel') pada pesanan 'paid' tetap berlaku:
     * order -> dibatalkan, payment -> failed, stok yang sudah dipotong
     * saat settlement DIKEMBALIKAN (10 - 2 = 8, kembali ke 10).
     */
    public function test_cancel_notification_on_paid_order_restores_stock(): void
    {
        $this->mockSignatureValid();
        [, $product, $order] = $this->makeOrder('paid', 'success', 8);

        $this->postWebhook($order->order_number, 'cancel')->assertOk();

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'dibatalkan']);
        $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'status' => 'failed']);
        $product->refresh();
        $this->assertSame(10, $product->stock, 'Stock deducted at settlement must be restored on merchant cancel');
    }

    /**
     * Selain stok, pembatalan merchant pada pesanan 'paid' juga harus
     * mengembalikan kuota voucher (used_count) dan menghapus baris VoucherUsage.
     * Pengiriman notifikasi kedua (double delivery) tidak boleh mengurangi
     * used_count dua kali.
     */
    public function test_cancel_notification_on_paid_order_restores_voucher_quota_once(): void
    {
        $this->mockSignatureValid();
        [, , $order] = $this->makeOrder('paid', 'success', 8);

        $voucher = Voucher::create([
            'code'           => 'CXL' . strtoupper(substr(uniqid(), -6)),
            'discount_type'  => 'percent',
            'discount_value' => 10,
            'min_purchase'   => 0,
            'max_discount'   => null,
            'quota'          => 50,
            'used_count'     => 1,
            'start_date'     => now()->subDay(),
            'end_date'       => now()->addDay(),
            'is_active'      => true,
        ]);

        $order->update(['voucher_id' => $voucher->id, 'voucher_discount' => 10000]);

        VoucherUsage::create([
            'voucher_id'       => $voucher->id,
            'user_id'          => $order->user_id,
            'order_id'         => $order->id,
            'discount_applied' => 10000,
        ]);

        // First cancel: order cancelled, voucher quota restored.
        $this->postWebhook($order->order_number, 'cancel')->assertOk();

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'dibatalkan']);
        $voucher->refresh();
        $this->assertSame(0, $voucher->used_count, 'Voucher used_count must be restored on merchant cancel');
        $this->assertDatabaseMissing('voucher_usages', ['order_id' => $order->id]);

        // Double delivery: second cancel must NOT decrement again.
        $this->postWebhook($order->order_number, 'cancel')->assertOk();

        $voucher->refresh();
        $this->assertSame(0, $voucher->used_count, 'Voucher used_count must not be decremented twice');
        $this->assertDatabaseMissing('voucher_usages', ['order_id' => $order->id]);
    }
}
