<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * OrderController — Riwayat & Detail Pesanan Pelanggan.
 */
class OrderController extends Controller
{
    /**
     * Tampilkan daftar pesanan milik pengguna yang sedang login,
     * diurutkan dari yang terbaru, dengan paginasi.
     */
    public function index()
    {
        $orders = Auth::user()
            ->orders()
            ->with(['items', 'payment'])
            ->latest()
            ->paginate(10);

        return view('orders.index', compact('orders'));
    }

    /**
     * Tampilkan detail satu pesanan.
     * Menggunakan scoping pada relasi user untuk mencegah IDOR.
     *
     * @param  string  $orderNumber
     */
    public function show(string $orderNumber)
    {
        $order = Auth::user()
            ->orders()
            ->with(['items.product', 'items.variant', 'address', 'payment', 'shipment', 'voucher'])
            ->where('order_number', $orderNumber)
            ->firstOrFail();

        return view('orders.show', compact('order'));
    }

    /**
     * Batalkan pesanan jika statusnya masih memungkinkan.
     * Hanya pesanan berstatus 'pending' yang dapat dibatalkan oleh pelanggan.
     *
     * @param  string  $orderNumber
     */
    public function cancel(string $orderNumber)
    {
        $order = Auth::user()
            ->orders()
            ->where('order_number', $orderNumber)
            ->firstOrFail();

        // Hanya izinkan pembatalan untuk pesanan yang belum diproses
        if (!in_array($order->status, ['pending'])) {
            return redirect()->route('orders.show', $orderNumber)
                ->with('error', 'Pesanan tidak dapat dibatalkan pada status saat ini.');
        }

        $order->update(['status' => 'dibatalkan']);

        // Tandai pembayaran sebagai gagal jika ada
        if ($order->payment) {
            $order->payment->update(['status' => 'failed']);
        }

        return redirect()->route('orders.show', $orderNumber)
            ->with('success', 'Pesanan berhasil dibatalkan.');
    }

    /**
     * Kembalikan snap_token untuk melanjutkan pembayaran pesanan yang tertunda.
     *
     * Dipanggil via fetch() dari halaman detail pesanan saat tombol
     * "Lanjutkan Pembayaran" diklik. Response JSON:
     *   { "expired": false, "snap_token": "..." }  — token masih valid
     *   { "expired": true }                        — token habis, arahkan ke checkout
     *
     * @param  string  $orderNumber
     * @return \Illuminate\Http\JsonResponse
     */
    public function resumePayment(string $orderNumber)
    {
        // Scoping melalui relasi user() sudah mencegah IDOR —
        // firstOrFail() akan 404 jika pesanan bukan milik user ini.
        $order = Auth::user()
            ->orders()
            ->with('payment')
            ->where('order_number', $orderNumber)
            ->firstOrFail();

        // Hanya pesanan berstatus 'pending' yang dapat dilanjutkan
        if ($order->status !== 'pending') {
            return response()->json(['expired' => true], 200);
        }

        $payment = $order->payment;

        // Tidak ada data pembayaran, atau token sudah melewati 24 jam TTL-nya
        if (!$payment || $payment->isExpired()) {
            return response()->json(['expired' => true], 200);
        }

        return response()->json([
            'expired'    => false,
            'snap_token' => $payment->snap_token,
        ], 200);
    }
}
