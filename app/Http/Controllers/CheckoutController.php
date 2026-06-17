<?php

namespace App\Http\Controllers;

use App\Services\MidtransService;
use App\Models\Cart;
use App\Models\CartItem;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

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

        $subtotal = $cart->getTotalPrice();
        $shipping = 0; // Ongkos Kirim gratis
        $total    = $subtotal + $shipping;

        return view('checkout.index', compact('cartItems', 'subtotal', 'shipping', 'total'));
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

        // 3. Susun data pesanan menggunakan data valid dari database (bukan request payload)
        $orderId = 'ELCRAFT-' . strtoupper(Str::random(8)) . '-' . time();
        
        $items = [];
        foreach ($cart->items as $item) {
            $items[] = [
                'id' => $item->variant ? $item->product_id . '-' . $item->variant_id : (string) $item->product_id,
                'price' => (int) $item->getUnitPrice(),
                'quantity' => $item->quantity,
                'name' => $item->variant 
                    ? substr($item->product->name . ' (' . $item->variant->variant_name . ')', 0, 50) 
                    : substr($item->product->name, 0, 50),
            ];
        }

        $subtotal = $cart->getTotalPrice();
        $shipping = 0;
        $total = $subtotal + $shipping;

        $order = [
            'id'    => $orderId,
            'total' => (int) $total,
            'items' => $items,
        ];

        $customer = [
            'name'  => $user->name,
            'email' => $user->email,
            'phone' => $user->phone ?? '',
        ];

        // 4. Request token dari Midtrans
        try {
            $snapData = $this->midtrans->createSnapToken($order, $customer);
            
            return response()->json([
                'snap_token' => $snapData['token'],
                'order_id'   => $orderId,
            ]);
        } catch (\Exception $e) {
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
