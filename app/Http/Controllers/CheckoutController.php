<?php

namespace App\Http\Controllers;

use App\Services\MidtransService;
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
     */
    public function __construct(private readonly MidtransService $midtrans) {}

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

        $subtotal  = $cart->getTotalPrice();
        $shipping  = 0; // Ongkos Kirim gratis
        $total     = $subtotal + $shipping;
        $addresses = $request->user()->addresses()->orderByDesc('is_default')->get();

        return view('checkout.index', compact('cartItems', 'subtotal', 'shipping', 'total', 'addresses'));
    }

    /**
     * Buat Snap Token dan kembalikan sebagai JSON ke frontend.
     * Validasi ketersediaan stok dan harga dilakukan server-side demi keamanan.
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
            'city_id'         => 'nullable|integer|min:1',
            'province_id'     => 'nullable|integer|min:1',
            'postal_code'     => 'nullable|string|max:10',
            'shipping_cost'   => 'nullable|integer|min:0',
            'courier'         => 'nullable|string|in:jne,pos,tiki',
            'courier_service' => 'nullable|string|max:50',
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

        try {
            return DB::transaction(function () use ($request, $user, $cart) {
                // 3. Simpan atau perbarui alamat pengiriman (termasuk city_id & province_id untuk ongkir)
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

                // 4. Susun data pesanan menggunakan data valid dari database
                $orderId  = 'ELCRAFT-' . strtoupper(Str::random(8)) . '-' . time();
                $subtotal = $cart->getTotalPrice();

                // Ambil ongkos kirim dari pilihan kurir yang sudah divalidasi RajaOngkir
                // Nilai ini sudah di-fetch langsung dari API, sehingga aman digunakan.
                $shipping = max(0, (int) $request->input('shipping_cost', 0));

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
                    'total_amount'     => max(0, $total),
                    'status'           => 'pending',
                    'notes'            => $request->notes,
                ]);

                // Simpan Order Items
                $items = [];
                foreach ($cart->items as $item) {
                    $order->items()->create([
                        'product_id' => $item->product_id,
                        'variant_id' => $item->variant_id,
                        'product_name' => $item->product->name,
                        'variant_name' => $item->variant?->variant_name,
                        'price' => $item->getUnitPrice(),
                        'discount_amount' => 0,
                        'quantity' => $item->quantity,
                        'subtotal' => $item->getSubtotal(),
                    ]);

                    $items[] = [
                        'id' => $item->variant ? $item->product_id . '-' . $item->variant_id : (string) $item->product_id,
                        'price' => (int) $item->getUnitPrice(),
                        'quantity' => $item->quantity,
                        'name' => $item->variant 
                            ? substr($item->product->name . ' (' . $item->variant->variant_name . ')', 0, 50) 
                            : substr($item->product->name, 0, 50),
                    ];
                }

                $orderData = [
                    'id'    => $orderId,
                    'total' => (int) $total,
                    'items' => $items,
                ];

                $customer = [
                    'name'  => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone ?? $request->recipient_phone,
                ];

                // 5. Request token dari Midtrans
                $snapData = $this->midtrans->createSnapToken($orderData, $customer);

                // Simpan data pembayaran
                Payment::create([
                    'order_id' => $order->id,
                    'midtrans_order_id' => $orderId,
                    'amount' => $total,
                    'status' => 'pending',
                    'snap_token' => $snapData['token'],
                ]);

                return response()->json([
                    'snap_token' => $snapData['token'],
                    'order_id'   => $orderId,
                ]);
            });
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Checkout Snap Token Error: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal memproses pembayaran melalui Midtrans. Silakan coba kembali.',
            ], 500);
        }
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
