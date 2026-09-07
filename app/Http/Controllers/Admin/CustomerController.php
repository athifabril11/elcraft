<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    /**
     * Tampilkan daftar pelanggan toko el Craft.
     */
    public function index(Request $request)
    {
        $search = $request->query('q');
        $status = $request->query('status', 'all');

        $query = User::where('role', 'customer');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        // Hitung statistik pelanggan
        $totalCustomers = User::where('role', 'customer')->count();
        $activeCustomers = User::where('role', 'customer')->where('is_active', true)->count();
        $newCustomersThisMonth = User::where('role', 'customer')
            ->where('created_at', '>=', Carbon::now()->startOfMonth())
            ->count();
        $payingCustomers = User::where('role', 'customer')
            ->whereHas('orders', function ($q) {
                $q->whereIn('status', ['paid', 'diproses', 'dikirim', 'selesai']);
            })->count();

        // Eager load aggregasi pesanan
        $customers = $query->withCount('orders')
            ->withSum([
                'orders as total_spent' => function ($q) {
                    $q->whereIn('status', ['paid', 'diproses', 'dikirim', 'selesai']);
                }
            ], 'total_amount')
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('admin.customers.index', compact(
            'customers',
            'search',
            'status',
            'totalCustomers',
            'activeCustomers',
            'newCustomersThisMonth',
            'payingCustomers'
        ));
    }

    /**
     * Tampilkan rincian profil, alamat, dan riwayat pesanan pelanggan.
     */
    public function show(User $customer)
    {
        if ($customer->role !== 'customer') {
            abort(404, 'Pengguna bukan pelanggan.');
        }

        $customer->load(['addresses']);

        // Riwayat pesanan pelanggan
        $orders = $customer->orders()
            ->with(['items', 'payment'])
            ->latest()
            ->paginate(10);

        // Agregasi metrik pelanggan
        $totalOrdersCount = $customer->orders()->count();
        $completedOrdersCount = $customer->orders()->where('status', 'selesai')->count();
        $totalSpent = (float) $customer->orders()
            ->whereIn('status', ['paid', 'diproses', 'dikirim', 'selesai'])
            ->sum('total_amount');

        return view('admin.customers.show', compact(
            'customer',
            'orders',
            'totalOrdersCount',
            'completedOrdersCount',
            'totalSpent'
        ));
    }

    /**
     * Ubah status aktif / nonaktif akun pelanggan (suspensi akun).
     */
    public function toggleStatus(User $customer)
    {
        if ($customer->role !== 'customer') {
            return redirect()->back()->with('error', 'Hanya akun pelanggan yang dapat diubah statusnya.');
        }

        $customer->is_active = !$customer->is_active;
        $customer->save();

        $message = $customer->is_active
            ? "Akun pelanggan {$customer->name} berhasil diaktifkan kembali."
            : "Akun pelanggan {$customer->name} berhasil dinonaktifkan (ditangguhkan).";

        return redirect()->back()->with('success', $message);
    }
}
