<?php

namespace Tests\Feature;

use App\Mail\PaymentSuccessMail;
use App\Models\Address;
use App\Models\Category;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Services\MidtransService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Mockery;
use Tests\TestCase;

class PaymentSuccessMailTest extends TestCase
{
    use RefreshDatabase;

    private function postSettlementWebhook(string $orderNumber): \Illuminate\Testing\TestResponse
    {
        $mock = Mockery::mock(MidtransService::class);
        $mock->shouldReceive('verifySignature')->andReturn(true);
        $this->app->instance(MidtransService::class, $mock);

        return $this->postJson(route('midtrans.webhook'), [
            'order_id'           => $orderNumber,
            'transaction_status' => 'settlement',
            'status_code'        => '200',
            // Data uji: nominal selalu sama dengan total order masing-masing test
            // (total berbeda: 115000 dengan ongkir, 50000 tanpa ongkir).
            'gross_amount'       => (string) Order::where('order_number', $orderNumber)->value('total_amount'),
            'signature_key'      => 'dummy_signature',
            'payment_type'       => 'gopay',
            'fraud_status'       => 'accept',
        ]);
    }

    public function test_payment_success_email_is_sent_to_customer_upon_settlement(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'name'  => 'Siti Rahma',
            'email' => 'siti@example.com',
        ]);

        $category = Category::create([
            'name'      => 'Cincin',
            'slug'      => 'cincin',
            'is_active' => true,
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'name'        => 'Cincin Mutiara',
            'slug'        => 'cincin-mutiara',
            'price'       => 100000,
            'stock'       => 5,
            'weight'      => 10,
            'is_active'   => true,
        ]);

        $address = Address::create([
            'user_id'        => $user->id,
            'label'          => 'Rumah',
            'recipient_name' => 'Siti Rahma',
            'phone'          => '081234567890',
            'province'       => 'Jawa Barat',
            'city'           => 'Bandung',
            'district'       => 'Coblong',
            'postal_code'    => '40115',
            'full_address'   => 'Jl. Anggrek No. 12',
            'is_default'     => true,
        ]);

        $order = Order::create([
            'order_number'    => 'ELCRAFT-EMAIL-001',
            'user_id'         => $user->id,
            'address_id'      => $address->id,
            'subtotal'        => 100000,
            'shipping_cost'   => 15000,
            'courier'         => 'jne',
            'courier_service' => 'REG',
            'total_amount'    => 115000,
            'status'          => 'pending',
        ]);

        $order->items()->create([
            'product_id'   => $product->id,
            'product_name' => $product->name,
            'price'        => 100000,
            'quantity'     => 1,
            'subtotal'     => 100000,
        ]);

        Payment::create([
            'order_id'          => $order->id,
            'midtrans_order_id' => $order->order_number,
            'amount'            => 115000,
            'status'            => 'pending',
            'payment_method'    => 'gopay',
        ]);

        $response = $this->postSettlementWebhook($order->order_number);
        $response->assertOk();

        // Verifikasi email PaymentSuccessMail dikirim ke email pembeli
        Mail::assertSent(PaymentSuccessMail::class, function (PaymentSuccessMail $mail) use ($user, $order) {
            return $mail->hasTo($user->email) &&
                   $mail->order->id === $order->id &&
                   str_contains($mail->envelope()->subject, $order->order_number);
        });
    }

    public function test_duplicate_webhook_settlement_does_not_resend_email(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'email' => 'duplicate@example.com',
        ]);

        $address = Address::create([
            'user_id'        => $user->id,
            'label'          => 'Kantor',
            'recipient_name' => 'Budi',
            'phone'          => '081234567891',
            'province'       => 'DKI Jakarta',
            'city'           => 'Jakarta Pusat',
            'district'       => 'Gambir',
            'postal_code'    => '10110',
            'full_address'   => 'Jl. Thamrin No. 1',
            'is_default'     => true,
        ]);

        $order = Order::create([
            'order_number' => 'ELCRAFT-EMAIL-DUP',
            'user_id'      => $user->id,
            'address_id'   => $address->id,
            'subtotal'     => 50000,
            'total_amount' => 50000,
            'status'       => 'paid', // sudah lunas sebelumnya
        ]);

        Payment::create([
            'order_id'          => $order->id,
            'midtrans_order_id' => $order->order_number,
            'amount'            => 50000,
            'status'            => 'success',
        ]);

        $response = $this->postSettlementWebhook($order->order_number);
        $response->assertOk();

        // Tidak boleh mengirim email kedua jika webhook duplikat
        Mail::assertNothingSent();
    }

    public function test_payment_success_mail_renders_properly(): void
    {
        $user = User::factory()->create(['name' => 'Budi Santoso']);

        $address = Address::create([
            'user_id'        => $user->id,
            'label'          => 'Rumah',
            'recipient_name' => 'Budi Santoso',
            'phone'          => '081234567892',
            'province'       => 'Jawa Timur',
            'city'           => 'Surabaya',
            'district'       => 'Genteng',
            'postal_code'    => '60111',
            'full_address'   => 'Jl. Pemuda No. 45',
            'is_default'     => true,
        ]);

        $order = Order::create([
            'order_number'    => 'ELCRAFT-RENDER-001',
            'user_id'         => $user->id,
            'address_id'      => $address->id,
            'subtotal'        => 100000,
            'shipping_cost'   => 10000,
            'courier'         => 'jne',
            'courier_service' => 'REG',
            'total_amount'    => 110000,
            'status'          => 'paid',
        ]);

        $category = Category::create([
            'name'      => 'Kalung',
            'slug'      => 'kalung',
            'is_active' => true,
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'name'        => 'Kalung Berlian',
            'slug'        => 'kalung-berlian',
            'price'       => 100000,
            'stock'       => 5,
            'weight'      => 10,
            'is_active'   => true,
        ]);

        $order->items()->create([
            'product_id'   => $product->id,
            'product_name' => 'Kalung Berlian',
            'variant_name' => 'Silver',
            'price'        => 100000,
            'quantity'     => 1,
            'subtotal'     => 100000,
        ]);

        $mailable = new PaymentSuccessMail($order);

        $mailable->assertSeeInHtml('ELCRAFT-RENDER-001');
        $mailable->assertSeeInHtml('Kalung Berlian');
        $mailable->assertSeeInHtml('Silver');
        $mailable->assertSeeInHtml('Pembayaran Berhasil');
    }

    public function test_payment_success_mail_links_to_order_show_route(): void
    {
        $user = User::factory()->create(['name' => 'Rina Wijaya']);

        $address = Address::create([
            'user_id'        => $user->id,
            'label'          => 'Rumah',
            'recipient_name' => 'Rina Wijaya',
            'phone'          => '081234567893',
            'province'       => 'Jawa Barat',
            'city'           => 'Bandung',
            'district'       => 'Coblong',
            'postal_code'    => '40115',
            'full_address'   => 'Jl. Melati No. 7',
            'is_default'     => true,
        ]);

        $order = Order::create([
            'order_number'    => 'ELCRAFT-ROUTE-001',
            'user_id'         => $user->id,
            'address_id'      => $address->id,
            'subtotal'        => 100000,
            'shipping_cost'   => 10000,
            'courier'         => 'jne',
            'courier_service' => 'REG',
            'total_amount'    => 110000,
            'status'          => 'paid',
        ]);

        $category = Category::create([
            'name'      => 'Kalung',
            'slug'      => 'kalung-route',
            'is_active' => true,
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'name'        => 'Kalung Perak',
            'slug'        => 'kalung-perak-route',
            'price'       => 100000,
            'stock'       => 5,
            'weight'      => 10,
            'is_active'   => true,
        ]);

        $order->items()->create([
            'product_id'   => $product->id,
            'product_name' => $product->name,
            'price'        => 100000,
            'quantity'     => 1,
            'subtotal'     => 100000,
        ]);

        // Diskriminan anti-vacuous: paksa router memakai root yang berbeda dari
        // config('app.url') sehingga tautan manual config('app.url')/orders/...
        // TIDAK AKAN PERNAH cocok dengan route('orders.show', ...) — RED hanya
        // jika view masih menempel config('app.url').
        URL::forceRootUrl('https://forced.example.test');

        $mailable = new PaymentSuccessMail($order);
        $mailable->assertSeeInHtml(route('orders.show', $order->order_number));
    }

    public function test_settlement_without_payment_type_still_pays_order_and_sends_email_once(): void
    {
        Mail::fake();

        $user = User::factory()->create(['email' => 'notype@example.com']);

        $address = Address::create([
            'user_id'        => $user->id,
            'label'          => 'Rumah',
            'recipient_name' => 'Andi',
            'phone'          => '081234567894',
            'province'       => 'Jawa Timur',
            'city'           => 'Surabaya',
            'district'       => 'Genteng',
            'postal_code'    => '60111',
            'full_address'   => 'Jl. Pemuda No. 1',
            'is_default'     => true,
        ]);

        $order = Order::create([
            'order_number'    => 'ELCRAFT-NOTYPE-001',
            'user_id'         => $user->id,
            'address_id'      => $address->id,
            'subtotal'        => 115000,
            'shipping_cost'   => 0,
            'total_amount'    => 115000,
            'status'          => 'pending',
        ]);

        Payment::create([
            'order_id'          => $order->id,
            'midtrans_order_id' => $order->order_number,
            'amount'            => 115000,
            'status'            => 'pending',
            'payment_method'    => 'gopay',
        ]);

        $mock = Mockery::mock(MidtransService::class);
        $mock->shouldReceive('verifySignature')->andReturn(true);
        $this->app->instance(MidtransService::class, $mock);

        // Notifikasi settlement TANPA 'payment_type' — webhook harus tetap
        // 200, order lunas, dan email PaymentSuccessMail terkirim tepat 1x
        // (tanpa 500 dari akses key yang tidak ada).
        $response = $this->postJson(route('midtrans.webhook'), [
            'order_id'           => $order->order_number,
            'transaction_status' => 'settlement',
            'status_code'        => '200',
            'gross_amount'       => '115000',
            'signature_key'      => 'dummy_signature',
            'fraud_status'       => 'accept',
        ]);

        $response->assertOk();
        $this->assertSame('paid', $order->refresh()->status, 'Order tetap lunas walau payment_type tidak dikirim');
        Mail::assertSent(PaymentSuccessMail::class, 1);
    }
}
