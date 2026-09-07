<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Voucher;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VoucherController extends Controller
{
    /**
     * Tampilkan daftar voucher diskon toko el Craft.
     */
    public function index(Request $request)
    {
        $search = $request->query('q');
        $status = $request->query('status', 'all');

        $query = Voucher::query();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($status === 'active') {
            $query->where('is_active', true)
                  ->where('end_date', '>=', Carbon::today());
        } elseif ($status === 'inactive') {
            $query->where(function ($q) {
                $q->where('is_active', false)
                  ->orWhere('end_date', '<', Carbon::today());
            });
        }

        // Metrik Ringkasan Voucher
        $totalActiveVouchers = Voucher::where('is_active', true)
            ->where('end_date', '>=', Carbon::today())
            ->count();
        $totalUsagesCount = (int) Voucher::sum('used_count');
        $expiringOrLowQuotaCount = Voucher::where('is_active', true)
            ->where(function ($q) {
                $q->whereRaw('quota - used_count <= 10')
                  ->orWhere('end_date', '<=', Carbon::now()->addDays(7));
            })->count();

        $vouchers = $query->orderByDesc('created_at')->paginate(10)->withQueryString();

        return view('admin.vouchers.index', compact(
            'vouchers',
            'search',
            'status',
            'totalActiveVouchers',
            'totalUsagesCount',
            'expiringOrLowQuotaCount'
        ));
    }

    /**
     * Simpan voucher diskon baru.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'code'           => ['required', 'string', 'max:50', 'unique:vouchers,code'],
            'description'    => ['nullable', 'string', 'max:255'],
            'discount_type'  => ['required', 'in:percent,nominal'],
            'discount_value' => ['required', 'numeric', 'min:1', function ($attribute, $value, $fail) use ($request) {
                if ($request->input('discount_type') === 'percent' && $value > 100) {
                    $fail('Nilai diskon persentase tidak boleh lebih dari 100%.');
                }
            }],
            'min_purchase'   => ['nullable', 'numeric', 'min:0'],
            'max_discount'   => ['nullable', 'numeric', 'min:0'],
            'quota'          => ['required', 'integer', 'min:1'],
            'start_date'     => ['required', 'date'],
            'end_date'       => ['required', 'date', 'after_or_equal:start_date'],
            'is_active'      => ['nullable', 'boolean'],
        ]);

        $validated['code'] = strtoupper(trim($validated['code']));
        $validated['min_purchase'] = $validated['min_purchase'] ?? 0;
        $validated['is_active'] = $request->has('is_active');

        Voucher::create($validated);

        return redirect()->route('admin.vouchers.index')->with('success', "Voucher {$validated['code']} berhasil dibuat.");
    }

    /**
     * Perbarui data voucher diskon.
     */
    public function update(Request $request, Voucher $voucher)
    {
        $validated = $request->validate([
            'code'           => ['required', 'string', 'max:50', Rule::unique('vouchers', 'code')->ignore($voucher->id)],
            'description'    => ['nullable', 'string', 'max:255'],
            'discount_type'  => ['required', 'in:percent,nominal'],
            'discount_value' => ['required', 'numeric', 'min:1', function ($attribute, $value, $fail) use ($request) {
                if ($request->input('discount_type') === 'percent' && $value > 100) {
                    $fail('Nilai diskon persentase tidak boleh lebih dari 100%.');
                }
            }],
            'min_purchase'   => ['nullable', 'numeric', 'min:0'],
            'max_discount'   => ['nullable', 'numeric', 'min:0'],
            'quota'          => ['required', 'integer', 'min:' . max(1, $voucher->used_count)],
            'start_date'     => ['required', 'date'],
            'end_date'       => ['required', 'date', 'after_or_equal:start_date'],
            'is_active'      => ['nullable', 'boolean'],
        ]);

        $validated['code'] = strtoupper(trim($validated['code']));
        $validated['min_purchase'] = $validated['min_purchase'] ?? 0;
        $validated['is_active'] = $request->has('is_active');

        $voucher->update($validated);

        return redirect()->route('admin.vouchers.index')->with('success', "Voucher {$voucher->code} berhasil diperbarui.");
    }

    /**
     * Toggle status aktif / nonaktif voucher.
     */
    public function toggle(Voucher $voucher)
    {
        $voucher->update(['is_active' => !$voucher->is_active]);

        $status = $voucher->is_active ? 'diaktifkan' : 'dinonaktifkan';
        return redirect()->back()->with('success', "Voucher {$voucher->code} berhasil {$status}.");
    }

    /**
     * Hapus voucher diskon (atau nonaktifkan jika sudah memiliki histori penggunaan).
     */
    public function destroy(Voucher $voucher)
    {
        if ($voucher->used_count > 0 || $voucher->usages()->exists() || $voucher->orders()->exists()) {
            $voucher->update(['is_active' => false]);
            return redirect()->back()->with('success', "Voucher {$voucher->code} telah memiliki riwayat transaksi, sehingga dinonaktifkan demi integritas data laporan.");
        }

        $code = $voucher->code;
        $voucher->delete();

        return redirect()->back()->with('success', "Voucher {$code} berhasil dihapus.");
    }
}
