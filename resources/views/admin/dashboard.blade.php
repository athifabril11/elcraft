@extends('admin.layouts.admin')

@section('title', 'Dashboard Toko')

@section('content')

@php
    $orderStatusMap = [
        'pending'    => ['label' => 'Menunggu Bayar', 'color' => '#D58E82', 'bg' => '#fefce8', 'textColor' => '#854d0e', 'border' => '#fef08a'],
        'paid'       => ['label' => 'Sudah Dibayar',  'color' => '#8E4034', 'bg' => '#eef2ff', 'textColor' => '#3730a3', 'border' => '#c7d2fe'],
        'diproses'   => ['label' => 'Sedang Diproses', 'color' => '#E8B4AC', 'bg' => '#faf5ff', 'textColor' => '#6b21a8', 'border' => '#e9d5ff'],
        'dikirim'    => ['label' => 'Sedang Dikirim',  'color' => '#C17B6F', 'bg' => '#eff6ff', 'textColor' => '#1d4ed8', 'border' => '#bfdbfe'],
        'selesai'    => ['label' => 'Selesai',         'color' => '#10b981', 'bg' => '#f0fdf4', 'textColor' => '#166534', 'border' => '#bbf7d0'],
        'dibatalkan' => ['label' => 'Dibatalkan',      'color' => '#e2d5d0', 'bg' => '#fef2f2', 'textColor' => '#991b1b', 'border' => '#fecaca'],
    ];
    $selesaiPct = $totalOrders > 0 ? round(($orderCountsByStatus['selesai'] / $totalOrders) * 100) : 0;

    // Donut segments
    $donutColors = ['#C17B6F', '#8E4034', '#E8B4AC', '#D58E82', '#5F5E5A', '#E2D5D0'];
    $circumference = 2 * pi() * 38;
    $donutSegments = [];
    $offset = 0;
    $idx = 0;
    foreach ($orderCountsByStatus as $key => $count) {
        $pct = $totalOrders > 0 ? ($count / $totalOrders) : 0;
        $dash = $pct * $circumference;
        $donutSegments[] = ['color' => $donutColors[$idx % 6], 'dash' => $dash, 'gap' => $circumference - $dash, 'offset' => -$offset, 'key' => $key];
        $offset += $dash;
        $idx++;
    }

    // Range-aware chart path
    $maxRev = max(array_column($chartRevenue, 'revenue') ?: [0]);
    $maxRev = $maxRev > 0 ? $maxRev : 1;
    $chartH = 140; $padL = 20; $padR = 20; $padT = 15; $padB = 20;
    $points = [];
    foreach ($chartRevenue as $i => $day) {
        $x = $padL + ($i / max(count($chartRevenue) - 1, 1)) * (680 - $padL - $padR);
        $y = $padT + (1 - ($day['revenue'] / $maxRev)) * ($chartH - $padT - $padB);
        $points[] = compact('x', 'y');
    }
    $pathD = count($points) > 0 ? ('M ' . $points[0]['x'] . ',' . $points[0]['y']) : 'M 0,140';
    for ($i = 1; $i < count($points); $i++) {
        $cp1x = $points[$i-1]['x'] + ($points[$i]['x'] - $points[$i-1]['x']) / 2;
        $cp2x = $points[$i]['x'] - ($points[$i]['x'] - $points[$i-1]['x']) / 2;
        $pathD .= ' C ' . $cp1x . ',' . $points[$i-1]['y'] . ' ' . $cp2x . ',' . $points[$i]['y'] . ' ' . $points[$i]['x'] . ',' . $points[$i]['y'];
    }
    $lastPt = end($points) ?: ['x' => 0, 'y' => 140];
    $firstPt = reset($points) ?: ['x' => 0, 'y' => 140];
    $areaD = $pathD . ' L ' . $lastPt['x'] . ',' . $chartH . ' L ' . $firstPt['x'] . ',' . $chartH . ' Z';

    // Hero Sparkline (fixed 7-day indicator)
    $maxS = max(array_column($heroSparkline, 'revenue') ?: [1]);
    $maxS = $maxS > 0 ? $maxS : 1;
    $sparkPts = [];
    foreach ($heroSparkline as $si => $sd) {
        $sx = count($heroSparkline) > 1 ? ($si / (count($heroSparkline) - 1)) * 300 : 150;
        $sy = 55 - (($sd['revenue'] / $maxS) * 50);
        $sparkPts[] = $sx . ',' . $sy;
    }
    $sparkLine = implode(' ', $sparkPts);
    $sparkFirst = $sparkPts[0] ?? '0,55';
    $sparkLast = end($sparkPts) ?: '300,55';
    $sparkLastX = explode(',', $sparkLast)[0];
    $sparkLastY = explode(',', $sparkLast)[1];
    $avgDaily = count($chartRevenue) > 0 ? array_sum(array_column($chartRevenue, 'revenue')) / count($chartRevenue) : 0;
@endphp

{{-- PAGE HEADER --}}
<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <div>
        <div class="flex items-center gap-2">
            <span class="w-2.5 h-2.5 rounded-full bg-brand-500 ring-4 ring-brand-100"></span>
            <h1 class="text-2xl font-extrabold text-atelier-textDark tracking-tight font-sans">Ringkasan Toko el Craft</h1>
        </div>
        <p class="text-xs text-atelier-textMuted mt-1 flex items-center gap-2">
            <span>{{ \Carbon\Carbon::now()->translatedFormat('l, d F Y') }}</span>
            <span class="text-stone-300">&bull;</span>
            <span class="text-emerald-700 font-medium flex items-center gap-1">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Sinkronisasi Real-time
            </span>
            <span class="text-stone-300">&bull;</span>
            <span>{{ $originCity }}</span>
        </p>
    </div>
    <a href="{{ route('admin.dashboard') }}" class="px-3 py-2 bg-white hover:bg-brand-50 border border-atelier-border hover:border-brand-300 rounded-xl text-xs font-semibold text-atelier-textMuted hover:text-brand-700 transition flex items-center gap-1.5 shadow-sm">
        <svg class="w-3.5 h-3.5 text-brand-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38"/></svg>
        <span>Perbarui Data</span>
    </a>
</div>

{{-- NOTIFIKASI DANA TERTANGKAP (PESANAN BATAL DENGAN PEMBAYARAN SUKSES) --}}
@if(($capturedStuckCount ?? 0) > 0)
<div x-data="{ dismissed: false }" x-show="!dismissed" x-cloak class="mb-6 rounded-2xl bg-gradient-to-r from-amber-50 via-rose-50/40 to-amber-50 border border-amber-300/90 p-4 md:p-5 shadow-sm transition-all duration-300">
    <div class="flex items-start justify-between gap-3">
        <div class="flex items-start gap-3.5 min-w-0 flex-1">
            <div class="w-10 h-10 rounded-xl bg-amber-600 text-white flex items-center justify-center shrink-0 shadow-sm mt-0.5">
                <svg class="w-5 h-5 animate-pulse" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="8" x2="12" y2="12"></line>
                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                </svg>
            </div>
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <h2 class="text-sm font-bold text-amber-900 font-sans">Perhatian: Dana Tertangkap</h2>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-amber-600 text-white shadow-xs">{{ $capturedStuckCount }} Pesanan</span>
                </div>
                <p class="text-xs text-amber-900/80 mt-0.5 leading-relaxed">
                    Pesanan batal dengan dana tertangkap menunggu refund manual. Terdapat {{ $capturedStuckCount }} pesanan dibatalkan yang pembayarannya telah sukses.
                </p>
                <div class="mt-3">
                    <a href="{{ route('admin.orders.index', ['payment_captured' => 1]) }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-amber-600 hover:bg-amber-700 text-white font-bold text-xs shadow-xs transition">
                        <span>Lihat Pesanan Dana Tertangkap</span>
                        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                    </a>
                </div>
            </div>
        </div>

        <!-- Tombol X (Tutup Notif) -->
        <button type="button" @click="dismissed = true" class="w-8 h-8 rounded-xl bg-white/80 hover:bg-white text-stone-400 hover:text-stone-700 border border-amber-200/70 flex items-center justify-center shrink-0 transition-colors shadow-xs" title="Tutup Notifikasi">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <line x1="18" y1="6" x2="6" y2="18"></line>
                <line x1="6" y1="6" x2="18" y2="18"></line>
            </svg>
        </button>
    </div>
</div>
@endif

{{-- NOTIFIKASI STOK MENIPIS (DISMISSIBLE ALERT BANNER, NO AUTO-TIMER, DETAIL PRODUK) --}}
@if($lowStockCount > 0)
<div x-data="{ dismissed: false }" x-show="!dismissed" x-cloak class="mb-6 rounded-2xl bg-gradient-to-r from-rose-50 via-amber-50/40 to-rose-50 border border-rose-200/90 p-4 md:p-5 shadow-sm transition-all duration-300">
    <div class="flex items-start justify-between gap-3">
        <div class="flex items-start gap-3.5 min-w-0 flex-1">
            <div class="w-10 h-10 rounded-xl bg-rose-500 text-white flex items-center justify-center shrink-0 shadow-sm mt-0.5">
                <svg class="w-5 h-5 animate-pulse" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                    <line x1="12" y1="9" x2="12" y2="13"></line>
                    <line x1="12" y1="17" x2="12.01" y2="17"></line>
                </svg>
            </div>
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <h2 class="text-sm font-bold text-rose-900 font-sans">Peringatan Stok Menipis</h2>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-rose-600 text-white shadow-xs">{{ $lowStockCount }} SKU Kritis (&le; 3 pcs)</span>
                </div>
                <p class="text-xs text-rose-800/80 mt-0.5 leading-relaxed">
                    Produk di bawah ini berada pada atau di bawah ambang batas minimum. Segera lakukan restok agar pesanan pelanggan tidak terhambat.
                </p>

                <!-- Detail Produk yang Menipis -->
                <div class="mt-3.5 flex flex-wrap gap-2">
                    @foreach($lowStockProducts as $lp)
                    <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl bg-white/95 border border-rose-200 shadow-xs text-xs">
                        <div class="w-6 h-6 rounded-lg bg-stone-100 border border-stone-200 overflow-hidden shrink-0 flex items-center justify-center">
                            @if($lp->image)
                                <img src="{{ asset('storage/'.$lp->image) }}" alt="{{ $lp->name }}" class="w-full h-full object-cover">
                            @else
                                <svg class="w-3.5 h-3.5 text-stone-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><polygon points="6 3 18 3 22 9 12 22 2 9 6 3"></polygon></svg>
                            @endif
                        </div>
                        <span class="font-bold text-atelier-textDark max-w-[170px] truncate" title="{{ $lp->name }}">{{ $lp->name }}</span>
                        <span class="px-1.5 py-0.5 rounded-md text-[10px] font-black bg-rose-100 text-rose-800 border border-rose-200">Sisa {{ $lp->stock }}</span>
                        <a href="{{ Route::has('admin.products.edit') ? route('admin.products.edit', $lp) : route('admin.products.index') }}" class="font-bold text-brand-600 hover:text-brand-800 hover:underline text-[11px] ml-0.5">Restok &rarr;</a>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- Tombol X (Tutup Notif tanpa timer) -->
        <button type="button" @click="dismissed = true" class="w-8 h-8 rounded-xl bg-white/80 hover:bg-white text-stone-400 hover:text-stone-700 border border-rose-200/70 flex items-center justify-center shrink-0 transition-colors shadow-xs" title="Tutup Notifikasi">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <line x1="18" y1="6" x2="6" y2="18"></line>
                <line x1="6" y1="6" x2="18" y2="18"></line>
            </svg>
        </button>
    </div>
</div>
@endif

{{-- ROW 1: HERO CARDS --}}
<div class="grid grid-cols-1 md:grid-cols-3 gap-5 mb-5">

{{-- Card 1: Pendapatan --}}
<div class="rounded-2xl p-5 text-white relative overflow-hidden shadow-md flex flex-col justify-between" style="background:linear-gradient(135deg,#A8584B 0%,#8E4034 50%,#6E2D23 100%);min-height:190px;">
    <div class="absolute -right-8 -bottom-8 w-36 h-36 rounded-full border border-white/10 pointer-events-none"></div>
    <div class="absolute -right-2 -bottom-2 w-24 h-24 rounded-full border border-white/10 pointer-events-none"></div>
    <div>
        <div class="flex items-center justify-between text-[11px] font-bold uppercase tracking-wider text-white/80">
            <span>Pendapatan Hari Ini</span>
            <span class="px-2 py-0.5 rounded-full bg-white/20 text-white text-[10px] font-bold">Real-time</span>
        </div>
        <div class="mt-3">
            @php $todayRevenueFormatted = number_format($todayRevenue, 0, ',', '.'); $thisMonthRevenueFormatted = number_format($thisMonthRevenue, 0, ',', '.'); @endphp
            <div class="text-3xl font-extrabold tracking-tight font-sans">Rp {{ $todayRevenueFormatted }}</div>
            <div class="text-[11px] text-white/75 mt-0.5">Bulan ini: <strong class="text-white">Rp {{ $thisMonthRevenueFormatted }}</strong></div>
        </div>
    </div>
    <div class="my-3 relative">
        <svg class="w-full h-14 overflow-visible" viewBox="0 0 300 60" preserveAspectRatio="none">
            <defs><linearGradient id="sparkGrad" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#ffffff" stop-opacity="0.35"/><stop offset="100%" stop-color="#ffffff" stop-opacity="0.0"/></linearGradient></defs>
            <polygon points="{{ $sparkFirst }} {{ $sparkLine }} {{ $sparkLastX }},60 0,60" fill="url(#sparkGrad)"/>
            <polyline points="{{ $sparkLine }}" fill="none" stroke="#FFFFFF" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
            <circle cx="{{ $sparkLastX }}" cy="{{ $sparkLastY }}" r="3.5" fill="#FFFFFF"/>
        </svg>
        <div class="flex justify-between text-[9px] text-white/60 font-mono mt-1">
            @foreach($heroSparkline as $wd)<span>{{ $wd['label'] }}</span>@endforeach
        </div>
    </div>
    <div class="pt-2 border-t border-white/15 flex items-center justify-between text-[11px]">
        <span class="text-white/80">{{ $todayTransactionCount }} transaksi selesai</span>
        <a href="{{ route('admin.orders.index') }}" class="font-bold text-white hover:underline flex items-center gap-1">Lihat Pesanan <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"></polyline></svg></a>
    </div>
</div>

{{-- Card 2: Perlu Perhatian --}}
<div class="bg-white rounded-2xl p-5 border border-atelier-border shadow-sm flex flex-col justify-between" style="min-height:190px;">
    <div>
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="p-1.5 rounded-lg bg-rose-50 text-rose-600"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg></span>
                <span class="text-[11px] font-bold uppercase tracking-wider text-atelier-textLight">Perlu Perhatian</span>
            </div>
            <span class="w-6 h-6 rounded-full bg-brand-100 text-brand-700 text-xs font-bold flex items-center justify-center border border-brand-200">{{ $ordersNeedingAttention + $pendingReturnsCount }}</span>
        </div>
        <h3 class="text-base font-bold text-atelier-textDark mt-3">Pesanan Perlu Diproses</h3>
        <div class="mt-2.5 p-3 rounded-xl bg-[#FCFAF9] border border-atelier-border">
            <div class="text-2xl font-black text-brand-700 font-sans leading-none">{{ $ordersNeedingAttention }} <span class="text-xs font-semibold text-atelier-textMuted">paket tertunda</span></div>
            <p class="text-[11px] text-atelier-textMuted mt-1.5"><strong class="text-stone-800">{{ $paidOrdersCount }} baru dibayar</strong>, {{ $processingOrdersCount }} sedang dipacking.</p>
            @if($pendingReturnsCount > 0)
            <div class="border-t border-dashed border-atelier-border mt-2 pt-2 text-[11px] text-rose-600 font-semibold flex items-center gap-1">
                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/><path d="M8 16H3v5"/></svg>
                {{ $pendingReturnsCount }} retur menunggu keputusan
            </div>
            @endif
        </div>
    </div>
    <div class="pt-3 border-t border-atelier-border mt-4 flex items-center justify-between">
        <span class="text-[11px] text-atelier-textLight font-medium">Prioritas Tinggi</span>
        <a href="{{ route('admin.orders.index') }}" class="text-xs font-bold text-brand-600 hover:text-brand-700 flex items-center gap-1 group"><span>Proses Sekarang</span><svg class="w-3.5 h-3.5 group-hover:translate-x-1 transition-transform" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"></polyline></svg></a>
    </div>
</div>

{{-- Card 3: Stok Kritis --}}
<div class="bg-white rounded-2xl p-5 border border-atelier-border shadow-sm flex flex-col justify-between" style="min-height:190px;">
    <div>
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="p-1.5 rounded-lg bg-amber-50 text-amber-600">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                    </svg>
                </span>
                <span class="text-[11px] font-bold uppercase tracking-wider text-atelier-textLight">Stok Kritis</span>
            </div>
            @if($lowStockCriticalCount > 0)
                <span class="w-6 h-6 rounded-full bg-rose-100 text-rose-700 text-xs font-bold flex items-center justify-center border border-rose-200">{{ $lowStockCriticalCount }}</span>
            @else
                <span class="px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 text-[10px] font-bold border border-emerald-200 flex items-center gap-1">Aman</span>
            @endif
        </div>
        <h3 class="text-base font-bold text-atelier-textDark mt-3">Produk Stok Menipis</h3>
        <div class="mt-2.5 p-3 rounded-xl bg-[#FCFAF9] border border-atelier-border">
            @if($lowStockCriticalCount > 0)
                <div class="space-y-1">
                    @foreach($lowStockRanking as $idx => $item)
                        <div class="flex items-center justify-between text-[11px] leading-tight">
                            <span class="text-stone-700 truncate mr-2" title="{{ $item['name'] }}{{ $item['variant_name'] ? ' — ' . $item['variant_name'] : '' }}">
                                <span class="font-bold text-stone-400">#{{ $idx + 1 }}</span>
                                <span class="font-semibold text-stone-800">{{ $item['name'] }}</span>
                                @if($item['variant_name'])
                                    <span class="text-stone-500 font-normal">— {{ $item['variant_name'] }}</span>
                                @endif
                            </span>
                            <span class="font-bold {{ $item['stock'] == 0 ? 'text-rose-600' : 'text-amber-600' }} shrink-0 font-mono text-[10px]">
                                {{ $item['stock'] }} pcs
                            </span>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-sm font-bold text-emerald-700 flex items-center gap-1.5">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                        <polyline points="22 4 12 14.01 9 11.01"/>
                    </svg>
                    Semua Stok Aman
                </div>
                <p class="text-[11px] text-atelier-textMuted mt-1.5">{{ $totalActiveProducts }} produk aktif di atas batas minimum 3 pcs.</p>
            @endif
        </div>
    </div>
    <div class="pt-3 border-t border-atelier-border mt-4 flex items-center justify-between">
        <span class="text-[11px] text-atelier-textLight font-medium">Ambang batas 3 pcs</span>
        <a href="{{ route('admin.products.index') }}" class="text-xs font-bold text-brand-600 hover:text-brand-700 flex items-center gap-1 group">
            <span>Kelola Stok</span>
            <svg class="w-3.5 h-3.5 group-hover:translate-x-1 transition-transform" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <polyline points="9 18 15 12 9 6"></polyline>
            </svg>
        </a>
    </div>
</div>
</div>

{{-- ROW 2: SECONDARY METRICS --}}
<div class="grid grid-cols-1 md:grid-cols-3 gap-5 mb-5">
<div class="bg-white p-5 rounded-2xl border border-atelier-border shadow-sm flex flex-col justify-between">
    <div class="flex items-center justify-between">
        <span class="text-[11px] font-bold uppercase tracking-wider text-atelier-textLight">Pelanggan Terdaftar</span>
        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">+{{ $newCustomersThisWeek }} minggu ini</span>
    </div>
    <div class="mt-2">
        <div class="text-2xl font-extrabold text-atelier-textDark font-sans">{{ $totalCustomers }} <span class="text-xs font-semibold text-atelier-textMuted">Pelanggan</span></div>
        <div class="text-[11px] text-atelier-textMuted mt-0.5">Basis pembeli terdaftar toko el Craft</div>
    </div>
</div>

<div class="bg-white p-5 rounded-2xl border border-atelier-border shadow-sm flex flex-col justify-between">
    <div class="flex items-center justify-between">
        <span class="text-[11px] font-bold uppercase tracking-wider text-atelier-textLight">Total Semua Pesanan</span>
        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">{{ $selesaiPct }}% tuntas</span>
    </div>
    <div class="mt-2">
        <div class="text-2xl font-extrabold text-atelier-textDark font-sans">{{ $totalOrders }} <span class="text-xs font-semibold text-atelier-textMuted">Pesanan</span></div>
        <div class="text-[11px] text-atelier-textMuted mt-0.5">{{ $orderCountsByStatus['selesai'] }} pesanan selesai sukses</div>
    </div>
    <div class="mt-3">
        <div class="w-full bg-stone-100 rounded-full h-1.5 overflow-hidden"><div class="bg-brand-500 h-1.5 rounded-full" style="width:{{ $selesaiPct }}%"></div></div>
        <div class="text-[10px] text-atelier-textLight mt-1 text-right">{{ $orderCountsByStatus['dikirim'] }} sedang dalam pengiriman</div>
    </div>
</div>

<div class="bg-white p-5 rounded-2xl border border-atelier-border shadow-sm flex flex-col justify-between">
    <div class="flex items-center justify-between">
        <span class="text-[11px] font-bold uppercase tracking-wider text-atelier-textLight">Total Pendapatan</span>
        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-50 text-blue-700 border border-blue-200">All-time</span>
    </div>
    @php $totalRevenueFormatted = number_format($totalRevenue, 0, ',', '.'); $avgDailyFormatted = number_format($avgDaily, 0, ',', '.'); @endphp
    <div class="mt-2">
        <div class="text-2xl font-extrabold text-atelier-textDark font-sans">Rp {{ $totalRevenueFormatted }}</div>
        <div class="text-[11px] text-atelier-textMuted mt-0.5">{{ $completedOrdersCount }} pesanan berhasil diselesaikan</div>
    </div>
    <div class="mt-3 pt-2 border-t border-atelier-border flex gap-3">
        <a href="{{ route('admin.orders.index') }}" class="text-[11px] text-brand-600 font-semibold hover:underline">Lihat Pesanan</a>
        <a href="{{ route('admin.reports.index') }}" class="text-[11px] text-brand-600 font-semibold hover:underline">Laporan</a>
    </div>
</div>
</div>

{{-- FILTER BAR: PERIOD SELECTOR --}}
<div class="admin-card p-5 mb-5">
    <form method="GET" action="{{ route('admin.dashboard') }}" class="m-0" id="filterForm">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div class="flex items-center flex-wrap gap-2">
                <span class="text-xs font-bold text-atelier-textLight uppercase tracking-wider mr-1">Periode:</span>
                
                <a href="{{ route('admin.dashboard', ['period' => 'today']) }}"
                   class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-all {{ $period === 'today' ? 'bg-brand-500 text-white shadow-xs' : 'bg-brand-50/70 text-brand-800 hover:bg-brand-100' }}">
                    Hari Ini
                </a>
                <a href="{{ route('admin.dashboard', ['period' => '7days']) }}"
                   class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-all {{ $period === '7days' ? 'bg-brand-500 text-white shadow-xs' : 'bg-brand-50/70 text-brand-800 hover:bg-brand-100' }}">
                    7 Hari Terakhir
                </a>
                <a href="{{ route('admin.dashboard', ['period' => 'this_month']) }}"
                   class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-all {{ $period === 'this_month' ? 'bg-brand-500 text-white shadow-xs' : 'bg-brand-50/70 text-brand-800 hover:bg-brand-100' }}">
                    Bulan Ini
                </a>
                <a href="{{ route('admin.dashboard', ['period' => 'all']) }}"
                   class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-all {{ $period === 'all' ? 'bg-brand-500 text-white shadow-xs' : 'bg-brand-50/70 text-brand-800 hover:bg-brand-100' }}">
                    Semua
                </a>
            </div>
        </div>
    </form>
</div>

{{-- ROW 3: CHART + DONUT --}}
<div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-5">
<div class="lg:col-span-2 bg-white rounded-2xl p-6 border border-atelier-border shadow-sm">
    <div class="pb-4 border-b border-atelier-border mb-4">
        <h3 class="text-base font-bold text-atelier-textDark font-sans">
            Tren Penjualan
            @switch($period)
                @case('today') (Hari Ini) @break
                @case('7days') (7 Hari Terakhir) @break
                @case('this_month') (Bulan Ini) @break
                @case('all') (Semua Waktu) @break
                @default ({{ $startDate->format('d M Y') }} - {{ $endDate->format('d M Y') }})
            @endswitch
        </h3>
        <p class="text-xs text-atelier-textMuted mt-0.5">Pendapatan pesanan selesai per hari</p>
    </div>
    <div class="relative" style="min-height:200px;">
        <svg class="w-full h-40 overflow-visible" viewBox="0 0 700 160" preserveAspectRatio="none">
            <defs><linearGradient id="chartGrad" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#C17B6F" stop-opacity="0.22"/><stop offset="85%" stop-color="#C17B6F" stop-opacity="0.01"/></linearGradient></defs>
            <line x1="0" y1="20" x2="700" y2="20" stroke="#F1EAE6" stroke-dasharray="4 4" stroke-width="1"/>
            <line x1="0" y1="65" x2="700" y2="65" stroke="#F1EAE6" stroke-dasharray="4 4" stroke-width="1"/>
            <line x1="0" y1="110" x2="700" y2="110" stroke="#F1EAE6" stroke-dasharray="4 4" stroke-width="1"/>
            <line x1="0" y1="150" x2="700" y2="150" stroke="#EFE7E4" stroke-width="1"/>
            <path d="{{ $areaD }}" fill="url(#chartGrad)"/>
            <path d="{{ $pathD }}" fill="none" stroke="#C17B6F" stroke-width="2.5" stroke-linecap="round"/>
            @foreach($points as $pi => $pt)
                @php $ptFill = ($pi === count($points)-1) ? '#C17B6F' : '#FFFFFF'; $ptR = ($pi === count($points)-1) ? 5 : 3.5; @endphp
                <circle cx="{{ $pt['x'] }}" cy="{{ $pt['y'] }}" r="{{ $ptR }}" fill="{{ $ptFill }}" stroke="#C17B6F" stroke-width="2"/>
            @endforeach
        </svg>
        <div class="flex justify-between text-xs font-semibold text-atelier-textLight px-1 mt-2">
            @foreach($chartRevenue as $wd)<span>{{ $wd['label'] }}</span>@endforeach
        </div>
    </div>
    <div class="pt-3 border-t border-atelier-border flex items-center justify-between text-xs">
        <div class="flex items-center gap-2 text-atelier-textMuted"><span class="w-2.5 h-2.5 rounded-full bg-brand-500"></span><span>Pendapatan Pesanan Selesai</span></div>
        <div class="text-atelier-textMuted">Rata-rata Harian: <strong class="text-stone-800 font-sans">Rp {{ $avgDailyFormatted }}</strong></div>
    </div>
</div>

<div class="bg-white rounded-2xl p-6 border border-atelier-border shadow-sm flex flex-col">
    <h3 class="text-base font-bold text-atelier-textDark font-sans">Status Pesanan</h3>
    <p class="text-xs text-atelier-textMuted mt-0.5">Distribusi {{ $totalOrders }} pesanan ({{ $period === 'all' ? 'sepanjang waktu' : 'periode terpilih' }})</p>
    <div class="flex items-center justify-center relative my-6">
        <svg class="w-36 h-36 transform -rotate-90" viewBox="0 0 100 100">
            <circle cx="50" cy="50" r="38" fill="none" stroke="#F6EFEB" stroke-width="10"/>
            @foreach($donutSegments as $seg)
                @if($seg['dash'] > 0)
                    <circle cx="50" cy="50" r="38" fill="none" stroke="{{ $seg['color'] }}" stroke-width="10" stroke-dasharray="{{ round($seg['dash'],2) }} {{ round($seg['gap'],2) }}" stroke-dashoffset="{{ round($seg['offset'],2) }}"/>
                @endif
            @endforeach
        </svg>
        <div class="absolute inset-0 flex flex-col items-center justify-center text-center">
            <span class="text-2xl font-black text-atelier-textDark leading-none font-sans">{{ $totalOrders }}</span>
            <span class="text-[9px] font-bold text-atelier-textLight uppercase tracking-wider mt-0.5">TOTAL</span>
        </div>
    </div>
    <div class="grid grid-cols-2 gap-y-2 gap-x-3 pt-3 border-t border-atelier-border text-xs">
        @foreach($orderCountsByStatus as $statusKey => $count)
        @php $info=$orderStatusMap[$statusKey]??['label'=>ucfirst($statusKey),'color'=>'#78716C']; $pct=$totalOrders>0?round(($count/$totalOrders)*100):0; @endphp
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full shrink-0" style="background:{{ $info['color'] }}"></span><span class="text-atelier-textMuted text-[11px] truncate">{{ $info['label'] }}</span></div>
            <span class="font-bold text-stone-800 text-[11px] shrink-0">{{ $pct }}%</span>
        </div>
        @endforeach
    </div>
    <a href="{{ route('admin.orders.index') }}" class="mt-4 text-center text-xs font-bold text-brand-600 hover:text-brand-700">Kelola Semua Pesanan &rarr;</a>
</div>
</div>

{{-- ROW 4: RECENT ORDERS + NEW CUSTOMERS --}}
<div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-5">
<div class="lg:col-span-2 bg-white rounded-2xl border border-atelier-border shadow-sm overflow-hidden flex flex-col">
    <div class="p-5 border-b border-atelier-border flex items-center justify-between">
        <div><h3 class="text-base font-bold text-atelier-textDark font-sans">Pesanan Terbaru</h3><p class="text-xs text-atelier-textMuted mt-0.5">Pantauan transaksi masuk secara langsung</p></div>
        <a href="{{ route('admin.orders.index') }}" class="text-xs font-bold text-brand-600 hover:text-brand-700 flex items-center gap-1 group"><span>Lihat Semua ({{ $totalOrders }})</span><svg class="w-3.5 h-3.5 group-hover:translate-x-1 transition-transform" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"></polyline></svg></a>
    </div>
    <div class="overflow-x-auto flex-1">
        <table class="w-full text-left text-xs">
            <thead class="bg-stone-50/75 text-atelier-textLight uppercase tracking-wider font-semibold border-b border-atelier-border">
                <tr>
                    <th class="px-5 py-3">No. Pesanan</th>
                    <th class="px-5 py-3">Pelanggan &amp; Item</th>
                    <th class="px-5 py-3 text-right">Total</th>
                    <th class="px-5 py-3 text-center">Status</th>
                    <th class="px-5 py-3">Waktu</th>
                    <th class="px-5 py-3 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-atelier-border">
                @forelse($recentOrders as $order)
                @php
                    $st = $orderStatusMap[$order->status] ?? ['label' => ucfirst($order->status), 'bg' => '#f5f5f5', 'textColor' => '#78716C', 'border' => '#e7e5e4'];
                    $stStyle = 'background:' . $st['bg'] . ';color:' . $st['textColor'] . ';border:1px solid ' . $st['border'] . ';';
                    $orderAmt = number_format($order->total_amount, 0, ',', '.');
                    $orderDate = $order->created_at->format('d M, H:i');
                @endphp
                <tr class="hover:bg-brand-50/20 transition-colors">
                    <td class="px-5 py-3.5 font-mono font-bold text-brand-700 whitespace-nowrap">{{ $order->order_number }}</td>
                    <td class="px-5 py-3.5"><div class="font-bold text-atelier-textDark">{{ $order->user?->name ?? 'Guest' }}</div><div class="text-[11px] text-atelier-textMuted">{{ $order->items->first()?->product_name ?? 'Produk' }}@if($order->items->count()>1)<span class="text-stone-400">+{{ $order->items->count()-1 }} lainnya</span>@endif</div></td>
                    <td class="px-5 py-3.5 text-right font-bold text-stone-800 whitespace-nowrap">Rp {{ $orderAmt }}</td>
                    <td class="px-5 py-3.5 text-center whitespace-nowrap"><span class="px-2.5 py-1 rounded-full text-[10px] font-bold" style="{{ $stStyle }}">{{ $st['label'] }}</span></td>
                    <td class="px-5 py-3.5 text-atelier-textLight font-mono text-[11px] whitespace-nowrap">{{ $orderDate }}</td>
                    <td class="px-5 py-3.5 text-right whitespace-nowrap"><a href="{{ Route::has('admin.orders.show') ? route('admin.orders.show', $order) : route('admin.orders.index', ['q' => $order->order_number]) }}" class="text-xs font-bold text-brand-600 hover:text-brand-800 hover:underline">Detail</a></td>
                </tr>
                @empty
                <tr><td colspan="6" class="px-5 py-8 text-center text-xs text-atelier-textMuted">Belum ada pesanan masuk.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="p-3 bg-stone-50/60 border-t border-atelier-border text-center text-xs text-atelier-textLight">Menampilkan {{ $recentOrders->count() }} dari {{ $totalOrders }} pesanan</div>
</div>

<div class="bg-white rounded-2xl p-6 border border-atelier-border shadow-sm flex flex-col">
    <div class="flex items-center justify-between pb-4 border-b border-atelier-border">
        <div><h3 class="text-base font-bold text-atelier-textDark font-sans">Pelanggan Baru</h3><p class="text-xs text-atelier-textMuted mt-0.5">Pendaftaran terbaru</p></div>
        <span class="p-1.5 rounded-lg bg-brand-50 text-brand-600"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg></span>
    </div>
    <div class="divide-y divide-atelier-border flex-1">
        @forelse($recentCustomersFull as $rc)
        <div class="py-3.5 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-full bg-brand-100 text-brand-800 font-bold text-xs flex items-center justify-center">{{ strtoupper(substr($rc->name,0,2)) }}</div>
                <div><div class="text-xs font-bold text-atelier-textDark">{{ $rc->name }}</div><div class="text-[10px] text-atelier-textLight">Gabung {{ $rc->created_at->diffForHumans() }}</div></div>
            </div>
            @if($rc->has_ordered > 0)<span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-brand-700 text-white">Sudah Belanja</span>
            @else<span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-stone-100 text-stone-600">Belum Belanja</span>@endif
        </div>
        @empty<div class="py-6 text-center text-xs text-atelier-textMuted">Belum ada pelanggan terdaftar.</div>@endforelse
    </div>
    <div class="pt-4 border-t border-atelier-border mt-2">
        <a href="{{ route('admin.customers.index') }}" class="w-full py-2.5 rounded-xl bg-brand-50 hover:bg-brand-100/70 border border-brand-200 text-brand-700 text-xs font-bold transition flex items-center justify-center gap-2">Lihat Semua Pelanggan ({{ $totalCustomers }})</a>
    </div>
</div>
</div>

{{-- ROW 5: REVIEWS + TOP PRODUCTS --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
<div class="bg-white rounded-2xl p-6 border border-atelier-border shadow-sm flex flex-col">
    <div class="flex items-center justify-between pb-4 border-b border-atelier-border mb-1">
        <div class="flex items-center gap-2.5">
            <span class="p-1.5 rounded-lg bg-amber-50 text-amber-600">
                <svg class="w-4 h-4 fill-amber-400 text-amber-500" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                </svg>
            </span>
            <div>
                <h3 class="text-base font-bold text-atelier-textDark font-sans">Ulasan Terbaru</h3>
                <p class="text-xs text-atelier-textMuted">Feedback langsung dari pelanggan el Craft</p>
            </div>
        </div>
        <span class="px-2.5 py-1 text-[10px] font-bold bg-amber-50 text-amber-800 rounded-full border border-amber-200">{{ $totalReviewsCount }} Ulasan</span>
    </div>
    <div class="divide-y divide-atelier-border flex-1">
        @forelse($recentReviews as $rev)
        <div class="py-3.5 flex items-start gap-3">
            <div class="w-9 h-9 rounded-full bg-brand-100 text-brand-800 font-bold text-xs flex items-center justify-center shrink-0 mt-0.5">
                {{ strtoupper(substr($rev->user?->name ?? 'User', 0, 2)) }}
            </div>
            <div class="min-w-0 flex-1">
                <div class="flex items-center justify-between gap-2">
                    <div class="text-xs font-bold text-atelier-textDark truncate">{{ $rev->user?->name ?? 'Pelanggan' }}</div>
                    <div class="flex items-center text-amber-400">
                        @for($rs = 1; $rs <= 5; $rs++)
                            <svg class="w-3 h-3 {{ $rs <= $rev->rating ? 'fill-amber-400 text-amber-400' : 'text-stone-300 fill-stone-100' }}" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                            </svg>
                        @endfor
                    </div>
                </div>
                <div class="text-[10px] text-atelier-textLight mt-0.5 flex items-center gap-1.5">
                    <span class="font-medium text-brand-600 truncate">{{ $rev->product?->name ?? 'Produk' }}</span>
                    <span>&bull;</span>
                    <span>{{ $rev->created_at ? $rev->created_at->diffForHumans() : 'baru saja' }}</span>
                </div>
                @if($rev->comment)
                    <p class="text-xs text-atelier-textDark mt-1.5 italic bg-stone-50 p-2 rounded-lg border border-stone-100">
                        &ldquo;{{ \Illuminate\Support\Str::limit($rev->comment, 120) }}&rdquo;
                    </p>
                @endif
            </div>
        </div>
        @empty
        <div class="py-8 text-center">
            <div class="w-10 h-10 mx-auto mb-2 rounded-xl bg-amber-50 flex items-center justify-center text-amber-500">
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                </svg>
            </div>
            <div class="text-xs font-bold text-stone-700">Belum ada ulasan produk.</div>
            <div class="text-[11px] text-atelier-textMuted mt-1">Ulasan dari pelanggan yang telah menerima pesanan akan muncul di sini.</div>
        </div>
        @endforelse
    </div>
    <div class="pt-4 border-t border-atelier-border mt-2 text-center">
        <a href="{{ route('admin.products.index') }}" class="text-xs font-bold text-brand-600 hover:text-brand-700 inline-flex items-center gap-1 group">
            <span>Kelola Produk di Toko</span>
            <svg class="w-3.5 h-3.5 group-hover:translate-x-1 transition-transform" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <polyline points="9 18 15 12 9 6"></polyline>
            </svg>
        </a>
    </div>
</div>

<div class="bg-white rounded-2xl p-6 border border-atelier-border shadow-sm flex flex-col">
    <div class="flex items-center justify-between pb-4 border-b border-atelier-border mb-1">
        <div class="flex items-center gap-2.5">
            <span class="p-1.5 rounded-lg bg-amber-50 text-amber-600"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg></span>
            <div><h3 class="text-base font-bold text-atelier-textDark font-sans">Produk Terlaris</h3><p class="text-xs text-atelier-textMuted">Volume penjualan 30 hari terakhir</p></div>
        </div>
    </div>
    <div class="divide-y divide-atelier-border flex-1">
        @forelse($topProducts as $rank => $tp)
        @php
            $rankBg = $rank===0 ? '#A8584B' : ($rank===1 ? '#786F6B' : ($rank===2 ? '#b45309' : '#a8a29e'));
            $rankRevenue = number_format($tp->total_revenue, 0, ',', '.');
        @endphp
        <div class="py-3.5 flex items-center justify-between gap-3">
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-7 h-7 rounded-lg text-white font-bold text-xs flex items-center justify-center shrink-0" style="background:{{ $rankBg }}">{{ $rank+1 }}</div>
                <div class="min-w-0">
                    <div class="text-xs font-bold text-atelier-textDark truncate">{{ $tp->product_name }}</div>
                    <div class="text-[10px] text-atelier-textLight mt-0.5">{{ $tp->total_sold }} terjual &bull; Omzet: <strong class="text-stone-700 font-sans">Rp {{ $rankRevenue }}</strong></div>
                </div>
            </div>
            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200 shrink-0">#{{ $rank+1 }}</span>
        </div>
        @empty
        <div class="py-8 text-center">
            <svg class="w-10 h-10 mx-auto mb-2 text-stone-200" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
            <div class="text-xs text-atelier-textMuted">Belum ada data penjualan bulan ini.</div>
        </div>
        @endforelse
    </div>
    <div class="pt-4 border-t border-atelier-border mt-2 text-center">
        <a href="{{ route('admin.reports.index') }}" class="text-xs font-bold text-brand-600 hover:text-brand-700 inline-flex items-center gap-1 group"><span>Lihat Laporan Lengkap</span><svg class="w-3.5 h-3.5 group-hover:translate-x-1 transition-transform" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"></polyline></svg></a>
    </div>
</div>
</div>

@endsection
