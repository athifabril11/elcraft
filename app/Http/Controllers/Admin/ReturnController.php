<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ReturnRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Admin\ReturnController - Pengelolaan pengajuan retur pelanggan.
 *
 * Pipeline: diajukan -> disetujui -> diproses -> selesai
 *                     -> ditolak
 *
 * approve()       - diajukan  -> disetujui
 * reject()        - diajukan  -> ditolak (wajib admin_notes)
 * advanceStatus() - disetujui -> diproses -> selesai
 */
class ReturnController extends Controller
{
    private const STATUSES = ['diajukan', 'disetujui', 'diproses', 'selesai', 'ditolak'];

    /**
     * List return requests with optional status filter and per-status counts.
     */
    public function index(Request $request)
    {
        $filter = $request->query('status');

        $query = ReturnRequest::with(['order', 'user', 'items.orderItem'])->latest();

        if ($filter && in_array($filter, self::STATUSES, true)) {
            $query->where('status', $filter);
        }

        $returns = $query->paginate(20)->withQueryString();

        // Per-status counts for the filter tabs
        $counts = ReturnRequest::selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('admin.returns.index', compact('returns', 'counts', 'filter'));
    }

    /**
     * Approve a diajukan return.
     * refund_amount required only when resolution_type is 'refund'.
     */
    public function approve(Request $request, ReturnRequest $return): RedirectResponse
    {
        $return->loadMissing('order');

        $request->validate([
            'admin_notes'   => 'nullable|string|max:2000',
            'refund_amount' => $return->resolution_type === 'refund'
                ? ['required', 'numeric', 'min:0', function ($attribute, $value, $fail) use ($return) {
                    $orderTotal = $return->order->total_amount ?? 0;
                    if ($value > $orderTotal) {
                        $fail('Nominal refund tidak boleh melebihi total pesanan (Rp ' . number_format($orderTotal, 0, ',', '.') . ').');
                    }
                }]
                : 'nullable|numeric|min:0',
        ]);

        $result = DB::transaction(function () use ($return, $request) {
            $locked = ReturnRequest::lockForUpdate()->findOrFail($return->id);

            if ($locked->status !== 'diajukan') {
                return null;
            }

            $locked->update([
                'status'        => 'disetujui',
                'refund_amount' => $request->input('refund_amount'),
                'admin_notes'   => $request->input('admin_notes'),
                'resolved_at'   => now(),
            ]);

            return $locked;
        });

        if ($result === null) {
            return redirect()->route('admin.returns.index')
                ->with('error', 'Retur ini tidak dalam status Diajukan dan tidak dapat disetujui.');
        }

        return redirect()->route('admin.returns.index')
            ->with('success', 'Retur #' . $return->return_number . ' telah disetujui.');
    }

    /**
     * Reject a diajukan return.
     * admin_notes is required so the reason can be communicated.
     */
    public function reject(Request $request, ReturnRequest $return): RedirectResponse
    {
        $request->validate([
            'admin_notes' => 'required|string|min:5|max:2000',
        ]);

        $result = DB::transaction(function () use ($return, $request) {
            $locked = ReturnRequest::lockForUpdate()->findOrFail($return->id);

            if ($locked->status !== 'diajukan') {
                return null;
            }

            $locked->update([
                'status'      => 'ditolak',
                'admin_notes' => $request->admin_notes,
                'resolved_at' => now(),
            ]);

            return $locked;
        });

        if ($result === null) {
            return redirect()->route('admin.returns.index')
                ->with('error', 'Retur ini tidak dalam status Diajukan dan tidak dapat ditolak.');
        }

        return redirect()->route('admin.returns.index')
            ->with('success', 'Retur #' . $return->return_number . ' telah ditolak.');
    }

    /**
     * Advance status through the post-approval pipeline:
     *   disetujui -> diproses -> selesai
     *
     * diajukan/ditolak are blocked here (must use approve/reject first).
     * Uses DB transaction with row lock to prevent races.
     */
    public function advanceStatus(ReturnRequest $return): RedirectResponse
    {
        $map = [
            'disetujui' => 'diproses',
            'diproses'  => 'selesai',
        ];

        $next = DB::transaction(function () use ($return, $map) {
            $locked = ReturnRequest::lockForUpdate()->findOrFail($return->id);

            if (!array_key_exists($locked->status, $map)) {
                return null;
            }

            $next = $map[$locked->status];
            $locked->update(['status' => $next]);
            return $next;
        });

        if ($next === null) {
            return redirect()->route('admin.returns.index')
                ->with('error', 'Status retur ini tidak dapat dimajukan dari sini.');
        }

        $labels = ['diproses' => 'Diproses', 'selesai' => 'Selesai'];
        $label  = $labels[$next] ?? $next;

        return redirect()->route('admin.returns.index')
            ->with('success', 'Retur #' . $return->return_number . ' diperbarui ke status "' . $label . '".');
    }
}