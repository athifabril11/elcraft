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
}
