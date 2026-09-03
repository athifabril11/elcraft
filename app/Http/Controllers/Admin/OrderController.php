<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

/**
 * Admin\OrderController — Minimal order status advancement.
 *
 * Scope: list all orders (paginated) and advance status through the
 * paid → diproses → dikirim pipeline only. No other mutations.
 */
class OrderController extends Controller
{
    /**
     * List all orders, newest first, paginated 15 per page.
     */
    public function index()
    {
        $orders = Order::with(['user', 'items'])
            ->latest()
            ->paginate(15);

        return view('admin.orders.index', compact('orders'));
    }

    /**
     * Advance an order's status one step forward through the pipeline:
     *   paid → diproses → dikirim
     *
     * Any other current status is rejected with an error redirect.
     * Uses a DB transaction with a row-level lock to prevent races.
     */
    public function advanceStatus(Order $order): RedirectResponse
    {
        $statusLabels = [
            'diproses' => 'Sedang Diproses',
            'dikirim'  => 'Sedang Dikirim',
        ];

        $next = DB::transaction(function () use ($order) {
            // Re-read the row inside the transaction with a lock
            $locked = Order::lockForUpdate()->findOrFail($order->id);

            $map = [
                'paid'     => 'diproses',
                'diproses' => 'dikirim',
            ];

            if (!array_key_exists($locked->status, $map)) {
                return null; // signal rejection
            }

            $nextStatus = $map[$locked->status];
            $locked->update(['status' => $nextStatus]);

            return $nextStatus;
        });

        if ($next === null) {
            return redirect()
                ->route('admin.orders.index')
                ->with('error', 'Status pesanan ini tidak bisa dimajukan dari halaman ini.');
        }

        $label = $statusLabels[$next] ?? $next;

        return redirect()
            ->route('admin.orders.index')
            ->with('success', "Pesanan #{$order->order_number} berhasil diperbarui ke status \"{$label}\".");
    }
}
