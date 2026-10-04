@extends('admin.layouts.admin')

@section('title', 'Kelola Pelanggan')
@section('page-title', 'Kelola Pelanggan')
@section('page-subtitle', 'Daftar akun pelanggan toko, riwayat belanja, dan manajemen akses akun')

@section('content')

<!-- ========================================================= -->
<!-- SUMMARY STATS -->
<!-- ========================================================= -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="admin-card p-5">
        <div class="flex items-center justify-between mb-2">
            <span class="text-xs font-bold text-atelier-textLight uppercase tracking-wider">Total Pelanggan</span>
            <span class="material-symbols-outlined text-brand-600 text-lg">group</span>
        </div>
        <div class="text-2xl font-extrabold text-atelier-textDark font-sans">
            {{ number_format($totalCustomers) }}
        </div>
        <div class="text-[11px] text-atelier-textMuted mt-1">Akun terdaftar</div>
    </div>

    <div class="admin-card p-5">
        <div class="flex items-center justify-between mb-2">
            <span class="text-xs font-bold text-atelier-textLight uppercase tracking-wider">Akun Aktif</span>
            <span class="material-symbols-outlined text-emerald-600 text-lg">verified_user</span>
        </div>
        <div class="text-2xl font-extrabold text-emerald-700 font-sans">
            {{ number_format($activeCustomers) }}
        </div>
        <div class="text-[11px] text-atelier-textMuted mt-1">Bisa login & belanja</div>
    </div>

    <div class="admin-card p-5">
        <div class="flex items-center justify-between mb-2">
            <span class="text-xs font-bold text-atelier-textLight uppercase tracking-wider">Pelanggan Baru</span>
            <span class="material-symbols-outlined text-purple-600 text-lg">person_add</span>
        </div>
        <div class="text-2xl font-extrabold text-purple-800 font-sans">
            {{ number_format($newCustomersThisMonth) }}
        </div>
        <div class="text-[11px] text-atelier-textMuted mt-1">Gabung bulan ini</div>
    </div>

    <div class="admin-card p-5">
        <div class="flex items-center justify-between mb-2">
            <span class="text-xs font-bold text-atelier-textLight uppercase tracking-wider">Pernah Belanja</span>
            <span class="material-symbols-outlined text-amber-600 text-lg">shopping_basket</span>
        </div>
        <div class="text-2xl font-extrabold text-amber-800 font-sans">
            {{ number_format($payingCustomers) }}
        </div>
        <div class="text-[11px] text-atelier-textMuted mt-1">Punya transaksi sukses</div>
    </div>
</div>

<!-- ========================================================= -->
<!-- FILTER & SEARCH BAR -->
<!-- ========================================================= -->
<div class="admin-card p-5 mb-6">
    <form method="GET" action="{{ route('admin.customers.index') }}" class="m-0">
        <div class="flex flex-col sm:flex-row items-center justify-between gap-3">
            
            <div class="flex-1 w-full sm:w-auto relative">
                <span class="material-symbols-outlined absolute left-3 top-2.5 text-atelier-textLight text-sm">search</span>
                <input type="text" name="q" value="{{ $search }}"
                       placeholder="Cari nama, email, atau no. telepon..."
                       class="admin-input !pl-9 text-xs">
            </div>

            <div class="flex items-center gap-2.5 w-full sm:w-auto">
                <select name="status" class="admin-input !w-auto text-xs">
                    <option value="all" {{ $status === 'all' ? 'selected' : '' }}>Semua Status</option>
                    <option value="active" {{ $status === 'active' ? 'selected' : '' }}>Akun Aktif</option>
                    <option value="inactive" {{ $status === 'inactive' ? 'selected' : '' }}>Akun Dinonaktifkan</option>
                </select>

                <button type="submit" class="admin-btn-primary text-xs !py-2 shrink-0">
                    <span class="material-symbols-outlined !text-sm">filter_alt</span>
                    <span>Filter</span>
                </button>

                @if($search || $status !== 'all')
                    <a href="{{ route('admin.customers.index') }}" class="admin-btn-ghost text-xs !py-2 shrink-0" title="Reset filter">
                        <span class="material-symbols-outlined !text-sm">restart_alt</span>
                    </a>
                @endif
            </div>

        </div>
    </form>
</div>

<!-- ========================================================= -->
<!-- CUSTOMER TABLE -->
<!-- ========================================================= -->
<div class="admin-card overflow-hidden">
    <div class="p-5 border-b border-atelier-border flex items-center justify-between">
        <div>
            <h3 class="text-xs font-bold uppercase tracking-wider text-atelier-textDark m-0">Daftar Pelanggan</h3>
            <p class="text-[11px] text-atelier-textMuted mt-0.5 m-0">Kelola informasi dan status hak akses pelanggan</p>
        </div>
        <span class="text-xs font-semibold text-atelier-textMuted">
            Total: {{ $customers->total() }} akun
        </span>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse text-xs">
            <thead>
                <tr class="border-b border-atelier-border bg-atelier-cream/40 text-[10px] uppercase font-bold text-atelier-textLight tracking-wider">
                    <th class="py-3 px-4">Pelanggan</th>
                    <th class="py-3 px-4">Kontak</th>
                    <th class="py-3 px-4">Bergabung</th>
                    <th class="py-3 px-4 text-center">Status</th>
                    <th class="py-3 px-4 text-center">Total Pesanan</th>
                    <th class="py-3 px-4 text-right">Total Belanja</th>
                    <th class="py-3 px-4 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-atelier-border">
                @forelse($customers as $cust)
                    <tr class="hover:bg-brand-50/20 transition">
                        <td class="py-3 px-4">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-full bg-gradient-to-br from-brand-300 to-brand-500 text-white flex items-center justify-center font-bold text-xs shrink-0">
                                    {{ strtoupper(substr($cust->name, 0, 1)) }}
                                </div>
                                <div class="min-w-0">
                                    <div class="font-bold text-atelier-textDark truncate">{{ $cust->name }}</div>
                                    <div class="text-[10px] text-atelier-textMuted truncate">ID #{{ $cust->id }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="py-3 px-4">
                            <div class="text-atelier-textDark">{{ $cust->email }}</div>
                            <div class="text-[10px] text-atelier-textMuted">{{ $cust->phone ?? 'Belum ada no. HP' }}</div>
                        </td>
                        <td class="py-3 px-4 text-atelier-textMuted">
                            {{ $cust->created_at->translatedFormat('d M Y') }}
                        </td>
                        <td class="py-3 px-4 text-center">
                            @if($cust->is_active)
                                <span class="badge-active">Aktif</span>
                            @else
                                <span class="badge-inactive !bg-rose-100 !text-rose-700">Nonaktif</span>
                            @endif
                        </td>
                        <td class="py-3 px-4 text-center">
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-brand-50 text-brand-700 border border-brand-200">
                                {{ $cust->orders_count }} pesanan
                            </span>
                        </td>
                        <td class="py-3 px-4 text-right font-bold text-atelier-textDark font-sans">
                            Rp {{ number_format($cust->total_spent ?? 0, 0, ',', '.') }}
                        </td>
                        <td class="py-3 px-4 text-right">
                            <div class="flex items-center justify-end gap-1.5">
                                <a href="{{ route('admin.customers.show', $cust->id) }}"
                                   class="admin-btn-ghost !py-1 !px-2.5 text-[11px]" title="Lihat Profil & Riwayat">
                                    <span class="material-symbols-outlined !text-xs">visibility</span>
                                    <span>Detail</span>
                                </a>

                                <form method="POST" action="{{ route('admin.customers.toggle-status', $cust->id) }}"
                                      onsubmit="return confirm('Apakah Anda yakin ingin {{ $cust->is_active ? 'menonaktifkan' : 'mengaktifkan' }} akun ini?')"
                                      class="inline m-0">
                                    @csrf
                                    @method('PATCH')
                                    @if($cust->is_active)
                                        <button type="submit" class="admin-btn-danger !py-1 !px-2 text-[11px]" title="Tangguhkan / Nonaktifkan Akun">
                                            <span class="material-symbols-outlined !text-xs">block</span>
                                        </button>
                                    @else
                                        <button type="submit" class="admin-btn-ghost !py-1 !px-2 text-[11px] !border-emerald-200 !text-emerald-700 hover:!bg-emerald-50" title="Aktifkan Kembali Akun">
                                            <span class="material-symbols-outlined !text-xs">check_circle</span>
                                        </button>
                                    @endif
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="py-8 text-center text-atelier-textMuted text-xs">
                            Tidak ada data pelanggan yang sesuai dengan pencarian atau filter.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($customers->hasPages())
        <div class="p-4 border-t border-atelier-border bg-white flex justify-end">
            {{ $customers->links() }}
        </div>
    @endif
</div>

@endsection
