@extends('admin.layouts.admin')

@section('title', 'Laporan & Analitik Penjualan')
@section('page-title', 'Laporan & Analitik Penjualan')
@section('page-subtitle', 'Analisis performa omset, produk terlaris, kontribusi kategori, dan data transaksi toko el Craft')

@section('content')

@php
    $orderStatusMap = [
        'pending'    => ['label' => 'Menunggu Bayar', 'bg' => '#fefce8', 'color' => '#854d0e', 'border' => '#fef08a'],
        'paid'       => ['label' => 'Sudah Dibayar',  'bg' => '#eef2ff', 'color' => '#3730a3', 'border' => '#c7d2fe'],
        'diproses'   => ['label' => 'Sedang Diproses', 'bg' => '#faf5ff', 'color' => '#6b21a8', 'border' => '#e9d5ff'],
        'dikirim'    => ['label' => 'Sedang Dikirim',  'bg' => '#eff6ff', 'color' => '#1d4ed8', 'border' => '#bfdbfe'],
        'selesai'    => ['label' => 'Selesai',         'bg' => '#f0fdf4', 'color' => '#166534', 'border' => '#bbf7d0'],
        'dibatalkan' => ['label' => 'Dibatalkan',      'bg' => '#fef2f2', 'color' => '#991b1b', 'border' => '#fecaca'],
    ];

    $totalCategoryRev = $categorySales->sum('total_revenue');
@endphp

<!-- ========================================================= -->
<!-- FILTER BAR & EXPORT ACTION -->
<!-- ========================================================= -->
<div class="admin-card p-5 mb-6">
    <form method="GET" action="{{ route('admin.reports.index') }}" class="m-0" id="filterForm">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            
            <!-- Quick Period Pills -->
            <div class="flex items-center flex-wrap gap-2">
                <span class="text-xs font-bold text-atelier-textLight uppercase tracking-wider mr-1">Periode:</span>
                
                <a href="{{ route('admin.reports.index', ['period' => 'today']) }}"
                   class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-all {{ $period === 'today' ? 'bg-brand-500 text-white shadow-xs' : 'bg-brand-50/70 text-brand-800 hover:bg-brand-100' }}">
                    Hari Ini
                </a>
                <a href="{{ route('admin.reports.index', ['period' => '7days']) }}"
                   class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-all {{ $period === '7days' ? 'bg-brand-500 text-white shadow-xs' : 'bg-brand-50/70 text-brand-800 hover:bg-brand-100' }}">
                    7 Hari Terakhir
                </a>
                <a href="{{ route('admin.reports.index', ['period' => '30days']) }}"
                   class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-all {{ $period === '30days' ? 'bg-brand-500 text-white shadow-xs' : 'bg-brand-50/70 text-brand-800 hover:bg-brand-100' }}">
                    30 Hari Terakhir
                </a>
                <a href="{{ route('admin.reports.index', ['period' => 'this_month']) }}"
                   class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-all {{ $period === 'this_month' ? 'bg-brand-500 text-white shadow-xs' : 'bg-brand-50/70 text-brand-800 hover:bg-brand-100' }}">
                    Bulan Ini
                </a>
            </div>

            <!-- Custom Date Range & Status Filters -->
            <div class="flex flex-wrap items-center gap-2.5">
                <input type="hidden" name="period" value="custom">

                <div class="flex items-center gap-1.5">
                    <input type="date" name="start_date" value="{{ $startDate->format('Y-m-d') }}"
                           class="admin-input !w-auto !py-1.5 text-xs text-atelier-textDark" title="Tanggal Mulai">
                    <span class="text-xs text-atelier-textMuted">s/d</span>
                    <input type="date" name="end_date" value="{{ $endDate->format('Y-m-d') }}"
                           class="admin-input !w-auto !py-1.5 text-xs text-atelier-textDark" title="Tanggal Akhir">
                </div>

                <select name="status" class="admin-input !w-auto !py-1.5 text-xs text-atelier-textDark">
                    <option value="all" {{ ($statusFilter ?? 'all') === 'all' ? 'selected' : '' }}>Semua Status</option>
                    <option value="selesai" {{ ($statusFilter ?? '') === 'selesai' ? 'selected' : '' }}>Selesai</option>
                    <option value="diproses" {{ ($statusFilter ?? '') === 'diproses' ? 'selected' : '' }}>Diproses</option>
                    <option value="dikirim" {{ ($statusFilter ?? '') === 'dikirim' ? 'selected' : '' }}>Dikirim</option>
                    <option value="paid" {{ ($statusFilter ?? '') === 'paid' ? 'selected' : '' }}>Sudah Bayar</option>
                    <option value="pending" {{ ($statusFilter ?? '') === 'pending' ? 'selected' : '' }}>Menunggu Bayar</option>
                    <option value="dibatalkan" {{ ($statusFilter ?? '') === 'dibatalkan' ? 'selected' : '' }}>Dibatalkan</option>
                </select>

                <button type="submit" class="admin-btn-primary !py-1.5 !px-3 text-xs">
                    <span class="material-symbols-outlined !text-sm">filter_alt</span>
                    <span>Terapkan</span>
                </button>

                <!-- Export CSV Button -->
                <a href="{{ route('admin.reports.export', request()->query()) }}"
                   class="admin-btn-ghost !py-1.5 !px-3 text-xs !border-emerald-200 !text-emerald-700 hover:!bg-emerald-50 hover:!border-emerald-400"
                   title="Unduh laporan transaksi dalam format CSV/Excel">
                    <span class="material-symbols-outlined !text-sm">download</span>
                    <span>Export CSV</span>
                </a>
            </div>
        </div>
    </form>
</div>

<!-- ========================================================= -->
<!-- METRIC STATS CARDS -->
<!-- ========================================================= -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">

    <!-- Card 1: Total Omset (Gross) -->
    <div class="admin-card p-5 bg-gradient-to-br from-white via-white to-brand-50/20">
        <div class="flex items-center justify-between mb-3">
            <span class="text-xs font-bold text-atelier-textLight uppercase tracking-wider">Omset Bruto</span>
            <div class="w-8 h-8 rounded-lg bg-brand-100 text-brand-700 flex items-center justify-center">
                <span class="material-symbols-outlined text-base">payments</span>
            </div>
        </div>
        <div class="text-xl font-extrabold text-atelier-textDark font-sans">
            Rp {{ number_format($grossSales, 0, ',', '.') }}
        </div>
        <div class="mt-2 text-[11px] text-atelier-textMuted flex items-center gap-1">
            <span>Dari {{ number_format($totalPaidOrders) }} pesanan lunas</span>
        </div>
    </div>

    <!-- Card 2: Pendapatan Bersih (Net) -->
    <div class="admin-card p-5 bg-gradient-to-br from-white via-white to-emerald-50/20">
        <div class="flex items-center justify-between mb-3">
            <span class="text-xs font-bold text-atelier-textLight uppercase tracking-wider">Pendapatan Bersih</span>
            <div class="w-8 h-8 rounded-lg bg-emerald-100 text-emerald-700 flex items-center justify-center">
                <span class="material-symbols-outlined text-base">account_balance_wallet</span>
            </div>
        </div>
        <div class="text-xl font-extrabold text-emerald-800 font-sans">
            Rp {{ number_format($netSales, 0, ',', '.') }}
        </div>
        <div class="mt-2 text-[11px] text-atelier-textMuted">
            <span>Setelah potongan refund (Rp {{ number_format($totalRefunds, 0, ',', '.') }})</span>
        </div>
    </div>

    <!-- Card 3: Unit Aksesoris Terjual -->
    <div class="admin-card p-5">
        <div class="flex items-center justify-between mb-3">
            <span class="text-xs font-bold text-atelier-textLight uppercase tracking-wider">Unit Terjual</span>
            <div class="w-8 h-8 rounded-lg bg-purple-100 text-purple-700 flex items-center justify-center">
                <span class="material-symbols-outlined text-base">inventory_2</span>
            </div>
        </div>
        <div class="text-xl font-extrabold text-atelier-textDark font-sans">
            {{ number_format($totalUnitsSold) }} <span class="text-xs font-semibold text-atelier-textMuted">pcs</span>
        </div>
        <div class="mt-2 text-[11px] text-atelier-textMuted">
            <span>Produk aksesoris terkirim/lunas</span>
        </div>
    </div>

    <!-- Card 4: Diskon Voucher Diberikan -->
    <div class="admin-card p-5">
        <div class="flex items-center justify-between mb-3">
            <span class="text-xs font-bold text-atelier-textLight uppercase tracking-wider">Diskon Promo</span>
            <div class="w-8 h-8 rounded-lg bg-amber-100 text-amber-700 flex items-center justify-center">
                <span class="material-symbols-outlined text-base">loyalty</span>
            </div>
        </div>
        <div class="text-xl font-extrabold text-amber-800 font-sans">
            Rp {{ number_format($voucherDiscounts, 0, ',', '.') }}
        </div>
        <div class="mt-2 text-[11px] text-atelier-textMuted">
            <span>Total subsidi promo voucher</span>
        </div>
    </div>

</div>

<!-- ========================================================= -->
<!-- 2-COLUMN ANALYTICS: BEST SELLERS & CATEGORY BREAKDOWN -->
<!-- ========================================================= -->
<div class="grid grid-cols-1 lg:grid-cols-12 gap-6 mb-6">

    <!-- Left: Top 5 Produk Terlaris (7 Kolom) -->
    <div class="lg:col-span-7 admin-card p-5">
        <div class="flex items-center justify-between pb-4 border-b border-atelier-border mb-4">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-brand-600 text-lg">star</span>
                <h3 class="text-xs font-bold uppercase tracking-wider text-atelier-textDark m-0">5 Produk Terlaris (Top Selling)</h3>
            </div>
            <span class="text-[11px] text-atelier-textMuted">Berdasarkan unit terjual</span>
        </div>

        @if($topProducts->isEmpty())
            <div class="py-12 text-center text-atelier-textMuted">
                <span class="material-symbols-outlined text-3xl text-atelier-textLight mb-2">shopping_bag</span>
                <p class="text-xs">Belum ada transaksi produk pada periode ini.</p>
            </div>
        @else
            <div class="space-y-3">
                @foreach($topProducts as $index => $item)
                    <div class="flex items-center justify-between p-3 rounded-xl bg-atelier-cream/50 border border-atelier-border/80 hover:bg-brand-50/30 transition">
                        <div class="flex items-center gap-3 min-w-0">
                            <!-- Rank Badge -->
                            <div class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold {{ $index === 0 ? 'bg-amber-400 text-amber-950 shadow-xs' : ($index === 1 ? 'bg-slate-300 text-slate-800' : ($index === 2 ? 'bg-amber-600 text-white' : 'bg-atelier-border text-atelier-textMuted')) }}">
                                {{ $index + 1 }}
                            </div>

                            <!-- Product Thumbnail -->
                            @php
                                $thumb = $item->product?->images->first()?->image_path;
                            @endphp
                            <div class="w-10 h-10 rounded-lg bg-white border border-atelier-border overflow-hidden shrink-0 flex items-center justify-center">
                                @if($thumb)
                                    <img src="{{ asset('storage/' . $thumb) }}" alt="{{ $item->product_name }}" class="w-full h-full object-cover">
                                @else
                                    <span class="material-symbols-outlined text-brand-400 text-sm">diamond</span>
                                @endif
                            </div>

                            <div class="min-w-0">
                                <div class="text-xs font-bold text-atelier-textDark truncate">{{ $item->product_name }}</div>
                                <div class="text-[10px] text-atelier-textMuted">
                                    {{ $item->product?->category?->name ?? 'Aksesoris' }}
                                </div>
                            </div>
                        </div>

                        <div class="text-right shrink-0">
                            <div class="text-xs font-bold text-brand-700 font-sans">
                                {{ number_format($item->total_qty) }} pcs
                            </div>
                            <div class="text-[10px] text-atelier-textMuted font-medium">
                                Rp {{ number_format($item->total_sales, 0, ',', '.') }}
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <!-- Right: Kontribusi Kategori Aksesoris (5 Kolom) -->
    <div class="lg:col-span-5 admin-card p-5">
        <div class="flex items-center justify-between pb-4 border-b border-atelier-border mb-4">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-brand-600 text-lg">pie_chart</span>
                <h3 class="text-xs font-bold uppercase tracking-wider text-atelier-textDark m-0">Kontribusi Kategori</h3>
            </div>
            <span class="text-[11px] text-atelier-textMuted">Pangsa Omset</span>
        </div>

        @if($categorySales->isEmpty())
            <div class="py-12 text-center text-atelier-textMuted">
                <span class="material-symbols-outlined text-3xl text-atelier-textLight mb-2">category</span>
                <p class="text-xs">Belum ada data penjualan per kategori.</p>
            </div>
        @else
            <div class="space-y-4">
                @foreach($categorySales as $cat)
                    @php
                        $percentage = $totalCategoryRev > 0 ? round(($cat->total_revenue / $totalCategoryRev) * 100, 1) : 0;
                    @endphp
                    <div>
                        <div class="flex items-center justify-between text-xs mb-1.5">
                            <span class="font-bold text-atelier-textDark">{{ $cat->category_name }}</span>
                            <span class="text-[11px] text-brand-700 font-semibold">
                                Rp {{ number_format($cat->total_revenue, 0, ',', '.') }} ({{ $percentage }}%)
                            </span>
                        </div>
                        <div class="w-full h-2 bg-atelier-border/80 rounded-full overflow-hidden">
                            <div class="h-full bg-gradient-to-r from-brand-400 to-brand-600 rounded-full" style="width: {{ $percentage }}%"></div>
                        </div>
                        <div class="text-[10px] text-atelier-textMuted mt-0.5">
                            {{ number_format($cat->total_qty) }} unit terjual
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

</div>

<!-- ========================================================= -->
<!-- DETAIL TRANSAKSI TABLE -->
<!-- ========================================================= -->
<div class="admin-card overflow-hidden">
    <div class="p-5 border-b border-atelier-border flex items-center justify-between">
        <div>
            <h3 class="text-xs font-bold uppercase tracking-wider text-atelier-textDark m-0">Daftar Transaksi Pesanan</h3>
            <p class="text-[11px] text-atelier-textMuted mt-0.5 m-0">Menampilkan pesanan yang tercatat dalam periode filter</p>
        </div>
        <div class="text-xs font-semibold text-atelier-textMuted">
            Total: {{ $transactions->total() }} transaksi
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse text-xs">
            <thead>
                <tr class="border-b border-atelier-border bg-atelier-cream/40 text-[10px] uppercase font-bold text-atelier-textLight tracking-wider">
                    <th class="py-3 px-4">No. Pesanan</th>
                    <th class="py-3 px-4">Tanggal & Waktu</th>
                    <th class="py-3 px-4">Pelanggan</th>
                    <th class="py-3 px-4">Status</th>
                    <th class="py-3 px-4">Item Produk</th>
                    <th class="py-3 px-4 text-right">Ongkir</th>
                    <th class="py-3 px-4 text-right">Total Akhir</th>
                    <th class="py-3 px-4 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-atelier-border">
                @forelse($transactions as $order)
                    @php
                        $cfg = $orderStatusMap[$order->status] ?? ['label' => ucfirst($order->status), 'bg' => '#f3f4f6', 'color' => '#374151', 'border' => '#e5e7eb'];
                    @endphp
                    <tr class="hover:bg-brand-50/20 transition">
                        <td class="py-3 px-4 font-mono font-bold text-atelier-textDark">
                            {{ $order->order_number }}
                        </td>
                        <td class="py-3 px-4 text-atelier-textMuted">
                            {{ $order->created_at->translatedFormat('d M Y, H:i') }}
                        </td>
                        <td class="py-3 px-4">
                            <div class="font-bold text-atelier-textDark">{{ $order->user?->name ?? 'Guest/User Dihapus' }}</div>
                            <div class="text-[10px] text-atelier-textMuted">{{ $order->user?->email ?? '-' }}</div>
                        </td>
                        <td class="py-3 px-4">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold"
                                  style="background: {{ $cfg['bg'] }}; color: {{ $cfg['color'] }}; border: 1px solid {{ $cfg['border'] }};">
                                {{ $cfg['label'] }}
                            </span>
                        </td>
                        <td class="py-3 px-4 text-atelier-textMuted max-w-xs truncate">
                            {{ $order->items->pluck('product_name')->implode(', ') }}
                        </td>
                        <td class="py-3 px-4 text-right text-atelier-textMuted">
                            Rp {{ number_format($order->shipping_cost, 0, ',', '.') }}
                        </td>
                        <td class="py-3 px-4 text-right font-bold text-atelier-textDark">
                            Rp {{ number_format($order->total_amount, 0, ',', '.') }}
                        </td>
                        <td class="py-3 px-4 text-center">
                            <a href="{{ route('admin.orders.index') }}"
                               class="text-brand-600 hover:text-brand-800 font-semibold text-[11px] underline">
                                Detail
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="py-8 text-center text-atelier-textMuted text-xs">
                            Tidak ada data transaksi yang sesuai dengan filter.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($transactions->hasPages())
        <div class="p-4 border-t border-atelier-border bg-white flex justify-end">
            {{ $transactions->links() }}
        </div>
    @endif
</div>

@endsection
