<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Models\Voucher;
use App\Services\MidtransService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * ConcurrencyRaceConditionAuditTest
 *
 * Mendemonstrasikan dan membuktikan secara empiris kerentanan Race Condition
 * dan Lost Update pada skenario nyata di sistem elCraft.
 */
class ConcurrencyRaceConditionAuditTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $customer;
    private Category $category;
    private Address $address;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role'  => 'admin',
            'email' => 'admin_audit@elcraft.test',
        ]);

        $this->customer = User::factory()->create([
            'role'  => 'customer',
            'email' => 'customer_audit@elcraft.test',
        ]);

        $this->category = Category::create([
            'name'        => 'Aksesoris Perhiasan',
            'slug'        => 'aksesoris-perhiasan',
            'description' => 'Kategori uji race condition',
        ]);

        $this->address = Address::create([
            'user_id'        => $this->customer->id,
            'label'          => 'Rumah',
            'recipient_name' => 'Customer Audit',
            'phone'          => '08123456789',
            'full_address'   => 'Jl. Uji Concurrency No. 1',
            'province'       => 'Jawa Barat',
            'city'           => 'Bandung',
            'city_id'        => 23,
            'district'       => 'Coblong',
            'postal_code'    => '40111',
            'is_default'     => true,
        ]);
    }

    /**
     * SKENARIO 1: Lost Update pada Edit Stok Produk oleh Admin
     *
     * Saat admin membuka form edit produk (stok terbaca 50), lalu terjadi transaksi
     * pembelian bersamaan yang mengurangi stok sebesar 5 pcs (stok di DB menjadi 45).
     * Ketika admin menekan simpan tanpa mengubah input stok, data stok di DB tertimpa
     * kembali menjadi 50 (5 pcs yang terjual hilang dari kalkulasi / lost update).
     */
    public function test_skenario_1_lost_update_pada_edit_stok_produk_admin(): void
    {
        // 1. Setup produk dengan stok awal 50
        $product = Product::create([
            'category_id' => $this->category->id,
            'name'        => 'Cincin Perak Audit',
            'slug'        => 'cincin-perak-audit',
            'price'       => 150000,
            'weight'      => 50,
            'stock'       => 50,
            'is_active'   => true,
        ]);

        // 2. Admin membuka form edit dan membaca data produk (stok = 50)
        $adminReadStock = $product->stock; // 50

        // 3. Terjadi event concurrent (misal: webhook settlement pembelian 5 pcs)
        //    mengurangi stok langsung di database
        $product->decrement('stock', 5);
        $this->assertEquals(45, $product->fresh()->stock, 'Stok di DB saat ini 45 karena ada pembelian 5 pcs');

        // 4. Admin menekan tombol simpan dengan payload form lama (stok = 50)
        $response = $this->actingAs($this->admin)->put(route('admin.products.update', $product), [
            'name'        => $product->name,
            'category_id' => $this->category->id,
            'price'       => 150000,
            'weight'      => 50,
            'stock'       => $adminReadStock, // Nilai 50 dari form yang belum diperbarui
            'description' => 'Update deskripsi oleh admin',
        ]);

        $response->assertRedirect(route('admin.products.index'));

        // 5. Bukti kerentanan Lost Update:
        //    Stok di DB yang seharusnya 45 tertimpa kembali menjadi 50!
        //    Pengurangan 5 pcs dari pembelian concurrent hilang tanpa jejak.
        $actualStock = $product->fresh()->stock;
        $this->assertEquals(50, $actualStock, 'BUKTI BOLONG: Nilai stok tertimpa kembali ke 50 dan mengabaikan pengurangan 5 pcs sebelumnya');
    }

    /**
     * SKENARIO 2: Webhook Midtrans Mengubah Order Dibatalkan Menjadi Paid & Memotong Stok Ulang
     *
     * Jika pesanan telah dibatalkan (oleh admin / customer / timeout), lalu notifikasi
     * settlement Midtrans tiba karena delay jaringan. Karena MidtransWebhookController
     * hanya mengecek status === 'paid', order yang 'dibatalkan' tetap diubah menjadi 'paid'
     * dan stok produk dipotong kembali.
     */
    public function test_skenario_2_webhook_settlement_menimpa_pesanan_yang_sudah_dibatalkan(): void
    {
        // Mocking Midtrans signature verification
        $mock = Mockery::mock(MidtransService::class);
        $mock->shouldReceive('verifySignature')->andReturn(true);
        $this->app->instance(MidtransService::class, $mock);

        // 1. Buat produk dengan stok awal 10
        $product = Product::create([
            'category_id' => $this->category->id,
            'name'        => 'Kalung Emas Audit',
            'slug'        => 'kalung-emas-audit',
            'price'       => 200000,
            'weight'      => 10,
            'stock'       => 10,
            'is_active'   => true,
        ]);

        // 2. Buat pesanan dengan status 'dibatalkan' (misal dibatalkan admin)
        $order = Order::create([
            'order_number'     => 'ELCRAFT-AUDIT-001',
            'user_id'          => $this->customer->id,
            'address_id'       => $this->address->id,
            'total_amount'     => 200000,
            'subtotal'         => 200000,
            'shipping_cost'    => 0,
            'discount_amount'  => 0,
            'voucher_discount' => 0,
            'status'           => 'dibatalkan', // SUDAH DIBATALKAN
        ]);

        OrderItem::create([
            'order_id'     => $order->id,
            'product_id'   => $product->id,
            'product_name' => $product->name,
            'price'        => 200000,
            'quantity'     => 2,
            'subtotal'     => 200000,
        ]);

        Payment::create([
            'order_id'          => $order->id,
            'midtrans_order_id' => $order->order_number,
            'amount'            => 200000,
            'status'            => 'failed',
            'gross_amount'      => 200000,
        ]);

        $this->assertEquals(10, $product->fresh()->stock);
        $this->assertEquals('dibatalkan', $order->fresh()->status);

        // 3. Kirim webhook settlement Midtrans untuk pesanan yang sudah dibatalkan tersebut
        $response = $this->postJson(route('midtrans.webhook'), [
            'order_id'           => $order->order_number,
            'transaction_status' => 'settlement',
            'status_code'        => '200',
            'gross_amount'       => '200000.00',
            'signature_key'      => 'dummy_signature',
            'payment_type'       => 'qris',
            'fraud_status'       => 'accept',
        ]);

        $response->assertStatus(200);

        // 4. Verifikasi perilaku yang benar (FIX BUG #1):
        //    Order yang sudah 'dibatalkan' TIDAK BOLEH ditransisikan ke 'paid' oleh webhook,
        //    dan stok produk TIDAK BOLEH dipotong kembali (tetap 10, bukan 8).
        $this->assertEquals('dibatalkan', $order->fresh()->status, 'Order tetap berstatus dibatalkan');
        $this->assertEquals(10, $product->fresh()->stock, 'Stok produk tidak berkurang lagi (tetap 10)');
    }

    /**
     * SKENARIO 3: Race Condition Kuota Voucher Melebihi Batas (Over-redemption)
     *
     * Voucher memiliki kuota = 1. Dua customer melakukan checkout secara bersamaan.
     * Keduanya lolos validasi kuota (karena used_count masih 0 saat pesanan dibuat pending).
     * Saat kedua transaksi dibayar dan webhook memproses keduanya melalui HTTP route resmi,
     * used_count naik menjadi 2 (melebihi kuota 1).
     */
    public function test_skenario_3_voucher_quota_over_redemption_race_condition(): void
    {
        // Mocking Midtrans signature verification
        $mock = Mockery::mock(MidtransService::class);
        $mock->shouldReceive('verifySignature')->andReturn(true);
        $this->app->instance(MidtransService::class, $mock);

        // 1. Setup produk
        $product = Product::create([
            'category_id' => $this->category->id,
            'name'        => 'Gelang Emas Promo',
            'slug'        => 'gelang-emas-promo',
            'price'       => 150000,
            'weight'      => 10,
            'stock'       => 10,
            'is_active'   => true,
        ]);

        // 2. Voucher dengan kuota hanya 1
        $voucher = Voucher::create([
            'code'           => 'RACE1KOTA',
            'discount_type'  => 'nominal',
            'discount_value' => 50000,
            'min_purchase'   => 100000,
            'quota'          => 1,
            'used_count'     => 0,
            'start_date'     => now()->subDay()->toDateString(),
            'end_date'       => now()->addDay()->toDateString(),
            'is_active'      => true,
        ]);

        // 3. Customer A & Customer B membaca validitas voucher pada saat yang sama sebelum bayar
        $this->assertTrue($voucher->isValid(150000), 'Customer A cek kuota: valid');
        $this->assertTrue($voucher->isValid(150000), 'Customer B cek kuota: valid (karena used_count masih 0)');

        // 4. Keduanya membuat pesanan pending dengan voucher_id tersebut
        $orderA = Order::create([
            'order_number'     => 'ELCRAFT-VOUCHER-001',
            'user_id'          => $this->customer->id,
            'address_id'       => $this->address->id,
            'voucher_id'       => $voucher->id,
            'total_amount'     => 100000,
            'subtotal'         => 150000,
            'shipping_cost'    => 0,
            'discount_amount'  => 0,
            'voucher_discount' => 50000,
            'status'           => 'pending',
        ]);
        OrderItem::create([
            'order_id'     => $orderA->id,
            'product_id'   => $product->id,
            'product_name' => $product->name,
            'price'        => 150000,
            'quantity'     => 1,
            'subtotal'     => 150000,
        ]);
        Payment::create([
            'order_id'          => $orderA->id,
            'midtrans_order_id' => $orderA->order_number,
            'amount'            => 100000,
            'status'            => 'pending',
            'gross_amount'      => 100000,
        ]);

        $orderB = Order::create([
            'order_number'     => 'ELCRAFT-VOUCHER-002',
            'user_id'          => $this->customer->id,
            'address_id'       => $this->address->id,
            'voucher_id'       => $voucher->id,
            'total_amount'     => 100000,
            'subtotal'         => 150000,
            'shipping_cost'    => 0,
            'discount_amount'  => 0,
            'voucher_discount' => 50000,
            'status'           => 'pending',
        ]);
        OrderItem::create([
            'order_id'     => $orderB->id,
            'product_id'   => $product->id,
            'product_name' => $product->name,
            'price'        => 150000,
            'quantity'     => 1,
            'subtotal'     => 150000,
        ]);
        Payment::create([
            'order_id'          => $orderB->id,
            'midtrans_order_id' => $orderB->order_number,
            'amount'            => 100000,
            'status'            => 'pending',
            'gross_amount'      => 100000,
        ]);

        // 5. Kirim webhook settlement untuk Order A
        $this->postJson(route('midtrans.webhook'), [
            'order_id'           => $orderA->order_number,
            'transaction_status' => 'settlement',
            'status_code'        => '200',
            'gross_amount'       => '100000.00',
            'signature_key'      => 'dummy_signature',
            'payment_type'       => 'qris',
            'fraud_status'       => 'accept',
        ])->assertStatus(200);

        // 6. Kirim webhook settlement untuk Order B
        $this->postJson(route('midtrans.webhook'), [
            'order_id'           => $orderB->order_number,
            'transaction_status' => 'settlement',
            'status_code'        => '200',
            'gross_amount'       => '100000.00',
            'signature_key'      => 'dummy_signature',
            'payment_type'       => 'qris',
            'fraud_status'       => 'accept',
        ])->assertStatus(200);

        // 7. Verifikasi perilaku yang benar (FIX BUG #2):
        //    used_count TIDAK BOLEH melebihi kuota (tetap 1, BUKAN 2).
        //    Order A mendapatkan pemakaian voucher resmi, sedangkan Order B
        //    tetap 'paid' tetapi tanpa mencatat VoucherUsage dan diberi catatan peringatan sistem.
        $this->assertEquals(1, $voucher->fresh()->used_count, 'used_count tetap 1 sesuai batas kuota');
        $this->assertLessThanOrEqual($voucher->quota, $voucher->fresh()->used_count, 'used_count tidak melampaui quota');

        $this->assertEquals('paid', $orderA->fresh()->status);
        $this->assertEquals('paid', $orderB->fresh()->status);

        // Order A tercatat di tabel voucher_usages
        $this->assertDatabaseHas('voucher_usages', [
            'order_id'   => $orderA->id,
            'voucher_id' => $voucher->id,
        ]);

        // Order B TIDAK tercatat di tabel voucher_usages (karena kuota sudah habis)
        $this->assertDatabaseMissing('voucher_usages', [
            'order_id'   => $orderB->id,
            'voucher_id' => $voucher->id,
        ]);

        // Order B memiliki catatan peringatan sistem pada kolom notes
        $this->assertStringContainsString('PERINGATAN SISTEM: Kuota voucher', (string) $orderB->fresh()->notes);
    }
}
