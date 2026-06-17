<?php

namespace App\Http\Controllers;

use App\Services\MidtransService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * CheckoutController — Pengelola halaman checkout dan pembayaran
 *
 * Alur pembayaran yang benar (PCI-DSS compliant):
 * 1. Pengguna menekan tombol "Bayar Sekarang"
 * 2. Controller membuat Snap Token via MidtransService (server-side)
 * 3. Snap Token dikirim ke frontend
 * 4. Frontend membuka popup Midtrans Snap menggunakan token tersebut
 * 5. Pengguna mengisi data kartu LANGSUNG di popup Midtrans (server kita tidak pernah melihatnya)
 * 6. Midtrans mengirim notifikasi ke webhook endpoint kita
 */
class CheckoutController extends Controller
{
    public function __construct(private readonly MidtransService $midtrans) {}

    /**
     * Tampilkan halaman checkout dengan ringkasan pesanan.
     * Memerlukan autentikasi pengguna.
     */
    public function index(Request $request)
    {
        // TODO: Ambil data keranjang belanja dari database (sesi ini masih mock)
        // Contoh struktur data yang akan digunakan:
        $cartItems = [
            // Diisi dari database keranjang pengguna yang login
        ];

        $subtotal = collect($cartItems)->sum(fn ($item) => $item['price'] * $item['quantity']);
        $shipping = 0; // TODO: Integrasi RajaOngkir untuk kalkulasi ongkir
        $total    = $subtotal + $shipping;

        return view('checkout.index', compact('cartItems', 'subtotal', 'shipping', 'total'));
    }

    /**
     * Buat Snap Token dan kembalikan sebagai JSON ke frontend.
     *
     * Dipanggil via AJAX/fetch dari halaman checkout saat pengguna menekan "Bayar".
     */
    public function createSnapToken(Request $request)
    {
        $this->middleware('auth');

        $user = $request->user();

        // ── Susun Data Pesanan ───────────────────────────────────────
        // Gunakan nano ID atau UUID agar tidak bisa ditebak
        $orderId = 'ELCRAFT-' . strtoupper(Str::random(8)) . '-' . time();

        // TODO: Ambil dari keranjang belanja yang tersimpan di database
        $order = [
            'id'    => $orderId,
            'total' => (int) $request->input('total', 0),
            'items' => $request->input('items', []),
        ];

        $customer = [
            'name'  => $user->name,
            'email' => $user->email,
            'phone' => $user->phone ?? '',
        ];

        // ── Buat Snap Token (server-side, tidak menyentuh data kartu) ─
        $snapData = $this->midtrans->createSnapToken($order, $customer);

        return response()->json([
            'snap_token' => $snapData['token'],
            'order_id'   => $orderId,
        ]);
    }

    /**
     * Halaman setelah pembayaran berhasil diselesaikan.
     */
    public function finish(Request $request)
    {
        return view('checkout.finish', [
            'order_id' => $request->input('order_id'),
            'status'   => $request->input('transaction_status'),
        ]);
    }

    /**
     * Halaman ketika pengguna menutup popup sebelum menyelesaikan pembayaran.
     */
    public function unfinish(Request $request)
    {
        return redirect()->route('checkout.index')
            ->with('info', 'Pembayaran belum selesai. Silakan coba lagi.');
    }

    /**
     * Halaman ketika pembayaran mengalami error.
     */
    public function error(Request $request)
    {
        return redirect()->route('checkout.index')
            ->with('error', 'Terjadi kesalahan saat memproses pembayaran. Silakan hubungi kami.');
    }
}
