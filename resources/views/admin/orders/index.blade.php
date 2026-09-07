@extends('admin.layouts.admin')

@section('title', 'Kelola Pesanan')
@section('page-title', 'Kelola Pesanan Pelanggan')
@section('page-subtitle', 'Pantau transaksi masuk, verifikasi pembayaran, dan majukan status pengiriman')

@section('content')

@php
    $statusMap = [
        'pending'    => ['label' => 'Menunggu Bayar',  'style' => 'background:#fefce8; color:#854d0e; border:1px solid #fef08a;'],
        'paid'       => ['label' => 'Sudah Dibayar',   'style' => 'background:#eef2ff; color:#3730a3; border:1px solid #c7d2fe;'],
        'diproses'   => ['label' => 'Sedang Diproses', 'style' => 'background:#faf5ff; color:#6b21a8; border:1px solid #e9d5ff;'],
        'dikirim'    => ['label' => 'Sedang Dikirim',  'style' => 'background:#eff6ff; color:#1d4ed8; border:1px solid #bfdbfe;'],
        'selesai'    => ['label' => 'Selesai',         'style' => 'background:#f0fdf4; color:#166534; border:1px solid #bbf7d0;'],
        'dibatalkan' => ['label' => 'Dibatalkan',      'style' => 'background:#fef2f2; color:#991b1b; border:1px solid #fecaca;'],
    ];

    $tabStatuses = [
        ''           => 'Semua',
        'pending'    => 'Menunggu Bayar',
        'paid'       => 'Sudah Dibayar',
        'diproses'   => 'Sedang Diproses',
        'dikirim'    => 'Sedang Dikirim',
        'selesai'    => 'Selesai',
        'dibatalkan' => 'Dibatalkan',
    ];
@endphp

<!-- Status Filter Tabs & Search Bar -->
<div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-5">
    
    <!-- Status Filter Tabs -->
    <div class="flex items-center gap-1.5 overflow-x-auto pb-1 max-w-full">
        @foreach($tabStatuses as $key => $label)
            @php
                $isActive = ($filter ?? '') === $key;
                $count = $key === '' ? $counts->sum() : ($counts[$key] ?? 0);
            @endphp
            <a href="{{ route('admin.orders.index', array_filter(['status' => $key, 'q' => $search])) }}"
               class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full text-xs font-semibold whitespace-nowrap transition-all text-decoration-none {{ $isActive ? 'bg-brand-600 text-white shadow-xs font-bold' : 'bg-white text-stone-600 border border-stone-200/80 hover:bg-stone-50 hover:text-stone-900' }}">
                <span>{{ $label }}</span>
                <span class="px-1.5 py-0.2 rounded-full text-[10px] font-bold {{ $isActive ? 'bg-white/25 text-white' : 'bg-stone-100 text-stone-500' }}">
                    {{ $count }}
                </span>
            </a>
        @endforeach
    </div>

    <!-- Search Box -->
    <form method="GET" action="{{ route('admin.orders.index') }}" class="flex items-center gap-2 m-0 shrink-0">
        @if($filter)
            <input type="hidden" name="status" value="{{ $filter }}">
        @endif
        <div class="relative">
            <span class="material-symbols-outlined absolute left-2.5 top-2 text-stone-400" style="font-size:16px;">search</span>
            <input type="text"
                   name="q"
                   value="{{ $search }}"
                   placeholder="Cari no. pesanan / nama..."
                   class="pl-8 pr-3 py-1.5 text-xs bg-white border border-stone-200 rounded-xl outline-none focus:border-brand-500 w-48 md:w-56 transition">
        </div>
        <button type="submit" class="admin-btn-primary" style="font-size:0.75rem; padding:0.4rem 0.8rem;">
            Cari
        </button>
        @if($search !== '')
            <a href="{{ route('admin.orders.index', array_filter(['status' => $filter])) }}"
               class="admin-btn-ghost" style="font-size:0.75rem; padding:0.4rem 0.6rem;" title="Reset pencarian">
                Reset
            </a>
        @endif
    </form>
</div>

<!-- Main Table Card -->
<div class="admin-card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse text-xs">
            <thead>
                <tr class="bg-stone-50/75 border-b border-stone-200/80 text-[11px] font-bold text-stone-500 uppercase tracking-wider">
                    <th class="px-5 py-3.5 whitespace-nowrap">Nomor Pesanan</th>
                    <th class="px-5 py-3.5">Pelanggan</th>
                    <th class="px-5 py-3.5">Item Pembelian</th>
                    <th class="px-5 py-3.5">Pengiriman</th>
                    <th class="px-5 py-3.5">Total Belanja</th>
                    <th class="px-5 py-3.5">Status</th>
                    <th class="px-5 py-3.5 whitespace-nowrap">Tanggal</th>
                    <th class="px-5 py-3.5 text-right whitespace-nowrap">Aksi Cepat</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @forelse($orders as $order)
                    @php
                        $st = $statusMap[$order->status] ?? ['label' => ucfirst($order->status), 'style' => 'background:#f5f5f5; color:#78716C; border:1px solid #e5e5e5;'];
                    @endphp
                    <tr class="hover:bg-brand-50/20 transition-colors">
                        <!-- Nomor Pesanan -->
                        <td class="px-5 py-4 whitespace-nowrap">
                            <span class="font-mono font-bold text-brand-700 tracking-tight text-xs block">
                                {{ $order->order_number }}
                            </span>
                        </td>

                        <!-- Pelanggan -->
                        <td class="px-5 py-4">
                            <div class="font-semibold text-stone-900">{{ $order->user?->name ?? '—' }}</div>
                            <div class="text-[11px] text-stone-500">{{ $order->user?->email ?? '' }}</div>
                        </td>

                        <!-- Item Pembelian -->
                        <td class="px-5 py-4">
                            <div class="space-y-1">
                                @forelse($order->items->take(2) as $item)
                                    <div class="text-[11px] text-stone-800">
                                        {{ $item->product_name ?? 'Item Produk' }}
                                        <span class="text-stone-400 font-semibold">×{{ $item->quantity }}</span>
                                    </div>
                                @empty
                                    <span class="text-stone-400">—</span>
                                @endforelse
                                @if($order->items->count() > 2)
                                    <div class="text-[10px] text-brand-600 font-semibold">
                                        +{{ $order->items->count() - 2 }} item lainnya
                                    </div>
                                @endif
                            </div>
                        </td>

                        <!-- Pengiriman -->
                        <td class="px-5 py-4 text-[11px] text-stone-600">
                            @if($order->address)
                                <div class="font-medium text-stone-800">{{ $order->address->city }}, {{ $order->address->province }}</div>
                                <div class="text-[10px] text-stone-400">{{ $order->shipping_courier ? strtoupper($order->shipping_courier) : 'Kurir Standard' }}</div>
                            @else
                                <span class="text-stone-400">—</span>
                            @endif
                        </td>

                        <!-- Total Belanja -->
                        <td class="px-5 py-4 whitespace-nowrap">
                            <span class="font-sans font-bold text-stone-900 text-xs">
                                Rp {{ number_format($order->total_amount, 0, ',', '.') }}
                            </span>
                        </td>

                        <!-- Status Badge -->
                        <td class="px-5 py-4 whitespace-nowrap">
                            <span class="inline-block px-2.5 py-0.5 rounded-full text-[10px] font-bold" style="{{ $st['style'] }}">
                                {{ $st['label'] }}
                            </span>
                        </td>

                        <!-- Tanggal -->
                        <td class="px-5 py-4 text-stone-500 whitespace-nowrap text-[11px]">
                            {{ $order->created_at->format('d M Y, H:i') }}
                        </td>

                        <!-- Aksi Pipeline -->
                        <td class="px-5 py-4 text-right whitespace-nowrap">
                            @if($order->status === 'paid')
                                <form method="POST" action="{{ route('admin.orders.advance-status', $order) }}" class="inline m-0">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="admin-btn-primary" style="font-size:0.75rem; padding:0.35rem 0.85rem;">
                                        <span class="material-symbols-outlined" style="font-size:14px;">inbox</span>
                                        Tandai Diproses
                                    </button>
                                </form>
                            @elseif($order->status === 'diproses')
                                <form method="POST" action="{{ route('admin.orders.advance-status', $order) }}" class="inline m-0">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="admin-btn-primary" style="font-size:0.75rem; padding:0.35rem 0.85rem;">
                                        <span class="material-symbols-outlined" style="font-size:14px;">local_shipping</span>
                                        Tandai Dikirim
                                    </button>
                                </form>
                            @else
                                <span class="text-stone-400 text-xs">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="p-12 text-center text-stone-500 text-xs">
                            <span class="material-symbols-outlined text-stone-300 block mb-2" style="font-size:36px;">receipt_long</span>
                            Belum ada pesanan{{ $filter ? ' dengan status "' . ($tabStatuses[$filter] ?? $filter) . '"' : '' }}{{ $search ? ' untuk pencarian "' . $search . '"' : '' }}.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    @if($orders->hasPages())
        <div class="px-5 py-3.5 border-t border-stone-200/80 bg-stone-50/50">
            {{ $orders->links() }}
        </div>
    @endif
</div>

@endsection
