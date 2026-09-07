<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Admin\OrderController — Manajemen pesanan & pembaruan status di panel admin.
 *
 * Scope: list all orders (paginated, with status filter & search)
 * and advance status through the paid → diproses → dikirim pipeline.
 */
class OrderController extends Controller
{
    private const STATUSES = ['pending', 'paid', 'diproses', 'dikirim', 'selesai', 'dibatalkan'];

    /**
     * List all orders, newest first, with optional status filter and search.
     */
    public function index(Request $request)
    {
        $filter = $request->query('status');
        $search = trim((string) $request->query('q', ''));

        $query = Order::with(['user', 'items', 'address'])->latest();

        if ($filter && in_array($filter, self::STATUSES, true)) {
            $query->where('status', $filter);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($uq) use ($search) {
                      $uq->where('name', 'like', "%{$search}%")
                         ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        $orders = $query->paginate(15)->withQueryString();

        // Per-status counts for filter tabs
        $counts = Order::selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('admin.orders.index', compact('orders', 'counts', 'filter', 'search'));
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
