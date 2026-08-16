<?php

namespace App\Http\Controllers;

use App\Services\MidtransService;
use App\Services\ShippingService;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Address;
use App\Models\Payment;
use App\Models\Voucher;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Class CheckoutController
 *
 * Pengelola halaman checkout dan pembuatan token pembayaran Midtrans Snap.
 */
class CheckoutController extends Controller
{
    /**
     * CheckoutController constructor.
     *
     * @param \App\Services\MidtransService $midtrans
     * @param \App\Services\ShippingService $shipping
     */
    public function __construct(
        private readonly MidtransService $midtrans,
        private readonly ShippingService $shipping,
    ) {}

    /**
     * Tampilkan halaman checkout dengan ringkasan pesanan.
     * Memerlukan autentikasi pengguna.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Contracts\View\View|\Illuminate\Http\RedirectResponse
     */
    public function index(Request $request)
    {
        $cart = $request->user()->getOrCreateCart();

        // Alihkan jika keranjang belanja kosong
        if ($cart->items->isEmpty()) {
            return redirect()->route('cart.index')
                ->with('error', 'Keranjang belanja Anda kosong. Silakan tambahkan produk terlebih dahulu.');
        }

        // Petakan item keranjang belanja ke format array yang diharapkan oleh view
        $cartItems = $cart->items->map(function ($item) {
            return [
                'name' => $item->variant 
                    ? $item->product->name . ' - ' . $item->variant->variant_name 
                    : $item->product->name,
                'price' => $item->getUnitPrice(),
                'quantity' => $item->quantity,
                'image' => $item->getPrimaryImage(),
            ];
        })->toArray();

        // Hitung total berat asli produk dalam gram
        $totalWeight = $cart->items->sum(function ($item) {
            $weight = $item->product->weight ?? 100;
            return max(1, (int) $weight) * $item->quantity;
        });
        $totalWeight = max(100, $totalWeight);

        $subtotal  = $cart->getTotalPrice();
        $shipping  = 0; // Ongkos Kirim awal
        $total     = $subtotal + $shipping;
        $addresses = $request->user()->addresses()->orderByDesc('is_default')->get();

        return view('checkout.index', compact('cartItems', 'subtotal', 'shipping', 'total', 'addresses', 'totalWeight'));
    }

    /**
     * Buat Snap Token dan kembalikan sebagai JSON ke frontend.
     *
     * Keamanan: stok dan ongkos kirim SELALU divalidasi ulang server-side.
     * Nilai shipping_cost dari client TIDAK digunakan untuk perhitungan total —
     * ongkir di-fetch ulang dari API RajaOngkir dalam request ini menggunakan
     * city_id, courier, courier_service, dan berat aktual keranjang dari database.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function createSnapToken(Request $request): JsonResponse
    {
        $request->validate([
            'recipient_name'  => 'required|string|max:255',
            'recipient_phone' => 'required|string|max:50',
            'full_address'    => 'required|string',
            'city'            => 'required|string|max:100',
            'city_id'         => 'required|integer|min:1',
            'province_id'     => 'nullable|integer|min:1',
            'postal_code'     => 'nullable|string|max:10',
            'courier'         => 'required|string|in:jne,pos,tiki,jnt',
            'courier_service' => 'required|string|max:50',
            'payment_method'  => 'required|string|in:credit_card,bca_va,bni_va,bri_va,other_va,gopay,shopeepay,qris,indomaret,alfamart',
            'notes'           => 'nullable|string',
            'voucher_code'    => 'nullable|string|max:50',
        ]);

        $user = $request->user();
        $cart = $user->getOrCreateCart();

        // 1. Validasi keranjang kosong
        if ($cart->items->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Keranjang belanja Anda kosong.',
            ], 422);
        }

        // 2. Validasi stok sebelum meminta token transaksi ke Midtrans
        foreach ($cart->items as $item) {
            $stock = $item->variant ? $item->variant->stock : $item->product->stock;
            if ($item->quantity > $stock) {
                return response()->json([
                    'success' => false,
                    'message' => 'Stok produk "' . $item->product->name . '" tidak mencukupi. Tersedia: ' . $stock,
                ], 422);
            }
        }

        // 3. Hitung ulang berat aktual dari database — jangan percaya nilai dari client
        $totalWeight = $cart->items->sum(function ($item) {
            return max(1, (int) ($item->product->weight ?? 0)) * $item->quantity;
        });
        $totalWeight = max(100, $totalWeight);

        // 4. Verifikasi ongkos kirim server-side dari RajaOngkir
        //    Nilai shipping_cost dari client DIABAIKAN sepenuhnya.
        //    Ongkir di-fetch ulang menggunakan city_id, berat, dan kurir dari request yang sudah divalidasi.
        $verifiedShipping = $this->resolveVerifiedShippingCost(
            cityId:         (int) $request->city_id,
            weightGrams:    $totalWeight,
            courier:        $request->courier,
            courierService: $request->courier_service,
        );
        // resolveVerifiedShippingCost() melempar ValidationException jika ongkir tidak dapat diverifikasi,
        // sehingga eksekusi tidak akan sampai ke baris berikutnya jika terjadi kegagalan.

        // Log jika client mengirim nilai ongkir yang berbeda (anomaly detection)
        $clientSubmittedCost = (int) $request->input('shipping_cost', 0);
        if ($clientSubmittedCost !== $verifiedShipping) {
            Log::warning('Checkout: perbedaan ongkir client vs server', [
                'user_id'         => $user->id,
                'city_id'         => $request->city_id,
                'courier'         => $request->courier,
                'courier_service' => $request->courier_service,
                'client_cost'     => $clientSubmittedCost,
                'server_cost'     => $verifiedShipping,
            ]);
        }

        try {
            return DB::transaction(function () use ($request, $user, $cart, $verifiedShipping) {
                // 5. Simpan atau perbarui alamat pengiriman
                $address = $user->addresses()->updateOrCreate(
                    [
                        'recipient_name' => $request->recipient_name,
                        'phone'          => $request->recipient_phone,
                        'full_address'   => $request->full_address,
                        'city'           => $request->city,
                    ],
                    [
                        'label'       => 'Utama',
                        'province'    => 'Jawa Barat',
                        'province_id' => $request->province_id,
                        'city_id'     => $request->city_id,
                        'district'    => 'Kecamatan',
                        'postal_code' => $request->postal_code ?? '',
                        'is_default'  => $user->addresses()->count() === 0,
                    ]
                );

                // 6. Susun data pesanan menggunakan nilai dari database dan API
                $orderId  = 'ELCRAFT-' . strtoupper(Str::random(8)) . '-' . time();
                $subtotal = $cart->getTotalPrice();

                // Ongkos kirim yang digunakan adalah nilai yang sudah diverifikasi server-side
                $shipping = $verifiedShipping;

                // Terapkan voucher jika ada
                $voucherDiscount = 0;
                $voucherId       = null;
                if ($request->filled('voucher_code')) {
                    $voucher = Voucher::where('code', strtoupper(trim($request->voucher_code)))->first();
                    if ($voucher && $voucher->isValid((float) $subtotal)) {
                        $voucherDiscount = $voucher->calculateDiscount((float) $subtotal);
                        $voucherId       = $voucher->id;
                    }
                }

                $total = $subtotal + $shipping - $voucherDiscount;

                // 6a. Batalkan pesanan pending lama milik pengguna ini (jika ada).
                //     Pesanan pending sebelumnya adalah sisa sesi Snap yang ditinggalkan
                //     (popup ditutup, tab ditutup, dll). Stok TIDAK perlu dikembalikan
                //     karena stok hanya dikurangi saat pembayaran berhasil (markAsPaid),
                //     bukan saat pesanan dibuat.
                $abandonedOrders = Order::where('user_id', $user->id)
                    ->where('status', 'pending')
                    ->with('payment')
                    ->get();

                foreach ($abandonedOrders as $abandoned) {
                    $abandoned->update(['status' => 'dibatalkan']);
                    if ($abandoned->payment) {
                        $abandoned->payment->update(['status' => 'failed']);
                    }
                }

                // Simpan Order
                $order = Order::create([
                    'order_number'     => $orderId,
                    'user_id'          => $user->id,
                    'address_id'       => $address->id,
                    'voucher_id'       => $voucherId,
                    'subtotal'         => $subtotal,
                    'discount_amount'  => 0,
                    'voucher_discount' => $voucherDiscount,
                    'shipping_cost'    => $shipping,
                    'courier'          => $request->courier,
                    'courier_service'  => $request->courier_service,
                    'total_amount'     => max(0, $total),
                    'status'           => 'pending',
                    'notes'            => $request->notes,
                ]);

                // Simpan Order Items
                $items = [];
                foreach ($cart->items as $item) {
                    $order->items()->create([
                        'product_id'      => $item->product_id,
                        'variant_id'      => $item->variant_id,
                        'product_name'    => $item->product->name,
                        'variant_name'    => $item->variant?->variant_name,
                        'price'           => $item->getUnitPrice(),
                        'discount_amount' => 0,
                        'quantity'        => $item->quantity,
                        'subtotal'        => $item->getSubtotal(),
                    ]);

                    $items[] = [
                        'id'       => $item->variant ? $item->product_id . '-' . $item->variant_id : (string) $item->product_id,
                        'price'    => (int) $item->getUnitPrice(),
                        'quantity' => $item->quantity,
                        'name'     => $item->variant 
                            ? substr($item->product->name . ' (' . $item->variant->variant_name . ')', 0, 50) 
                            : substr($item->product->name, 0, 50),
                    ];
                }

                // Tambahkan baris ongkos kirim ke item_details agar
                // sum(price * qty) == gross_amount (wajib oleh Midtrans).
                if ($shipping > 0) {
                    $items[] = [
                        'id'       => 'SHIPPING',
                        'price'    => (int) $shipping,
                        'quantity' => 1,
                        'name'     => 'Ongkos Kirim',
                    ];
                }

                // Tambahkan baris diskon voucher (nilai negatif) ke item_details
                // agar total tetap seimbang saat ada potongan harga.
                if ($voucherDiscount > 0) {
                    $items[] = [
                        'id'       => 'DISCOUNT',
                        'price'    => -(int) $voucherDiscount,
                        'quantity' => 1,
                        'name'     => 'Diskon Voucher',
                    ];
                }

                $orderData = [
                    'id'             => $orderId,
                    'total'          => (int) $total,
                    'items'          => $items,
                    'payment_method' => $request->payment_method,
                ];

                $customer = [
                    'name'  => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone ?? $request->recipient_phone,
                ];

                // 7. Request token dari Midtrans
                $snapData = $this->midtrans->createSnapToken($orderData, $customer);

                // Simpan data pembayaran
                // expired_at = 24 jam dari sekarang (sesuai TTL default Midtrans Snap token)
                Payment::create([
                    'order_id'          => $order->id,
                    'midtrans_order_id' => $orderId,
                    'payment_method'    => $request->payment_method,
                    'amount'            => $total,
                    'status'            => 'pending',
                    'snap_token'        => $snapData['token'],
                    'expired_at'        => now()->addHours(24),
                ]);

                return response()->json([
                    'snap_token' => $snapData['token'],
                    'order_id'   => $orderId,
                ]);
            });
        } catch (\Exception $e) {
            Log::error('Checkout Snap Token Error: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'trace'   => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal memproses pembayaran melalui Midtrans. Silakan coba kembali.',
            ], 500);
        }
    }

    /**
     * Re-fetch dan verifikasi ongkos kirim dari API RajaOngkir server-side.
     *
     * Method ini memanggil ShippingService::calculateCost() dalam request cycle ini
     * untuk mendapatkan biaya yang sahih dari API, kemudian mencocokkannya dengan
     * layanan kurir yang dipilih pelanggan (courier_service).
     *
     * @param  int     $cityId         ID kota tujuan dari RajaOngkir
     * @param  int     $weightGrams    Berat total keranjang dalam gram (dihitung server-side)
     * @param  string  $courier        Kode kurir (jne, pos, tiki, jnt)
     * @param  string  $courierService Kode layanan (misal: jne-REG, pos-Paket Biasa)
     * @return int                     Ongkos kirim yang sudah diverifikasi (dalam Rupiah)
     *
     * @throws \Illuminate\Validation\ValidationException jika ongkir tidak dapat diverifikasi
     */
    private function resolveVerifiedShippingCost(
        int $cityId,
        int $weightGrams,
        string $courier,
        string $courierService,
    ): int {
        $services = $this->shipping->calculateCost($cityId, $weightGrams, $courier);

        if (empty($services)) {
            Log::error('Checkout: RajaOngkir tidak mengembalikan data ongkir', [
                'city_id'      => $cityId,
                'weight'       => $weightGrams,
                'courier'      => $courier,
            ]);

            throw ValidationException::withMessages([
                'courier_service' => 'Gagal memvalidasi ongkos kirim dari API. Silakan coba lagi atau pilih kurir lain.',
            ]);
        }

        // Temukan layanan yang dipilih pelanggan dari hasil API.
        // courier_service dikirim frontend sebagai "{code}-{service}" (misal: "jne-REG").
        // ShippingController menyusunnya sebagai: ($item['code'] ?? '') . '-' . ($item['service'] ?? '')
        $matched = null;
        foreach ($services as $svc) {
            $serviceKey = ($svc['code'] ?? '') . '-' . ($svc['service'] ?? '');
            if (strcasecmp($serviceKey, $courierService) === 0) {
                $matched = $svc;
                break;
            }
        }

        if ($matched === null) {
            Log::warning('Checkout: layanan kurir tidak ditemukan dalam respons API', [
                'city_id'         => $cityId,
                'courier'         => $courier,
                'courier_service' => $courierService,
                'available'       => array_column($services, 'service'),
            ]);

            throw ValidationException::withMessages([
                'courier_service' => 'Layanan kurir yang dipilih tidak tersedia untuk tujuan ini. Silakan pilih layanan lain.',
            ]);
        }

        return (int) ($matched['cost'] ?? 0);
    }

    /**
     * Halaman setelah pembayaran selesai.
     * Membersihkan keranjang belanja setelah pembayaran terinisiasi.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Contracts\View\View
     */
    public function finish(Request $request)
    {
        $cart = $request->user()->cart;
        if ($cart) {
            $cart->items()->delete();
        }

        return view('checkout.finish', [
            'order_id' => $request->input('order_id'),
            'status'   => $request->input('transaction_status'),
        ]);
    }

    /**
     * Halaman ketika pengguna membatalkan transaksi.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function unfinish(Request $request)
    {
        return redirect()->route('checkout.index')
            ->with('info', 'Pembayaran dibatalkan. Silakan lakukan proses pembayaran kembali.');
    }

    /**
     * Halaman ketika pembayaran mengalami error.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function error(Request $request)
    {
        return redirect()->route('checkout.index')
            ->with('error', 'Terjadi kesalahan saat memproses transaksi pembayaran Anda.');
    }
}
