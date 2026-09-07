@extends('admin.layouts.admin')

@section('title', 'Detail Pelanggan — ' . $customer->name)
@section('page-title', 'Detail Pelanggan')
@section('page-subtitle', 'Informasi akun, alamat tersimpan, dan riwayat pesanan pelanggan')

@section('content')

@if(session('success'))
    <div class="mb-4 p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-semibold flex items-center gap-2">
        <span class="material-symbols-outlined text-base text-emerald-600">check_circle</span>
        <span>{{ session('success') }}</span>
    </div>
@endif

@if(session('error'))
    <div class="mb-4 p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs font-semibold flex items-center gap-2">
        <span class="material-symbols-outlined text-base text-rose-600">error</span>
        <span>{{ session('error') }}</span>
    </div>
@endif

<!-- Back Button & Quick Actions Header -->
<div class="flex items-center justify-between gap-4 mb-6">
    <a href="{{ route('admin.customers.index') }}" class="admin-btn-ghost text-xs">
        <span class="material-symbols-outlined !text-sm">arrow_back</span>
        <span>Kembali ke Daftar Pelanggan</span>
    </a>

    <form method="POST" action="{{ route('admin.customers.toggle-status', $customer->id) }}"
          onsubmit="return confirm('Apakah Anda yakin ingin {{ $customer->is_active ? 'menonaktifkan' : 'mengaktifkan' }} akun ini?')"
          class="m-0">
        @csrf
        @method('PATCH')
        @if($customer->is_active)
            <button type="submit" class="admin-btn-danger text-xs">
                <span class="material-symbols-outlined !text-sm">block</span>
                <span>Nonaktifkan Akun (Tangguhkan)</span>
            </button>
        @else
            <button type="submit" class="admin-btn-primary text-xs !bg-emerald-600 hover:!bg-emerald-700">
                <span class="material-symbols-outlined !text-sm">check_circle</span>
                <span>Aktifkan Kembali Akun</span>
            </button>
        @endif
    </form>
</div>

<!-- ========================================================= -->
<!-- PROFILE & LIFETIME VALUE CARDS -->
<!-- ========================================================= -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">

    <!-- Profile Overview Card (1 Kolom) -->
    <div class="admin-card p-6 flex flex-col justify-between">
        <div>
            <div class="flex items-center gap-4 mb-4">
                <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-brand-300 via-brand-500 to-brand-700 text-white flex items-center justify-center font-extrabold text-xl shadow-xs">
                    {{ strtoupper(substr($customer->name, 0, 1)) }}
                </div>
                <div>
                    <h2 class="text-base font-extrabold text-atelier-textDark m-0">{{ $customer->name }}</h2>
                    <div class="text-xs text-atelier-textMuted mt-0.5">{{ $customer->email }}</div>
                    <div class="mt-2">
                        @if($customer->is_active)
                            <span class="badge-active">Akun Aktif</span>
                        @else
                            <span class="badge-inactive !bg-rose-100 !text-rose-700">Akun Dinonaktifkan</span>
                        @endif
                    </div>
                </div>
            </div>

            <div class="space-y-2.5 pt-4 border-t border-atelier-border text-xs">
                <div class="flex justify-between">
                    <span class="text-atelier-textLight">No. Telepon:</span>
                    <span class="font-semibold text-atelier-textDark">{{ $customer->phone ?? 'Belum diisi' }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-atelier-textLight">Status Email:</span>
                    <span class="font-semibold {{ $customer->email_verified_at ? 'text-emerald-700' : 'text-amber-700' }}">
                        {{ $customer->email_verified_at ? 'Terverifikasi' : 'Belum Verifikasi' }}
                    </span>
                </div>
                <div class="flex justify-between">
                    <span class="text-atelier-textLight">Terdaftar Sejak:</span>
                    <span class="font-semibold text-atelier-textDark">{{ $customer->created_at->translatedFormat('d F Y, H:i') }}</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Lifetime Stats Card (2 Kolom) -->
    <div class="lg:col-span-2 admin-card p-6">
        <h3 class="text-xs font-bold uppercase tracking-wider text-atelier-textLight mb-4">Ringkasan Nilai Pelanggan (Lifetime Value)</h3>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="p-4 rounded-xl bg-atelier-cream/60 border border-atelier-border">
                <div class="text-[11px] font-bold text-atelier-textLight uppercase tracking-wider mb-1">Total Belanja</div>
                <div class="text-xl font-extrabold text-brand-700 font-sans">
                    Rp {{ number_format($totalSpent, 0, ',', '.') }}
                </div>
                <div class="text-[10px] text-atelier-textMuted mt-1">Akumulasi pesanan lunas</div>
            </div>

            <div class="p-4 rounded-xl bg-atelier-cream/60 border border-atelier-border">
                <div class="text-[11px] font-bold text-atelier-textLight uppercase tracking-wider mb-1">Total Pesanan</div>
                <div class="text-xl font-extrabold text-atelier-textDark font-sans">
                    {{ $totalOrdersCount }}
                </div>
                <div class="text-[10px] text-atelier-textMuted mt-1">{{ $completedOrdersCount }} pesanan selesai</div>
            </div>

            <div class="p-4 rounded-xl bg-atelier-cream/60 border border-atelier-border">
                <div class="text-[11px] font-bold text-atelier-textLight uppercase tracking-wider mb-1">Rata-rata Order</div>
                @php
                    $aov = $totalOrdersCount > 0 ? $totalSpent / max(1, $completedOrdersCount ?: $totalOrdersCount) : 0;
                @endphp
                <div class="text-xl font-extrabold text-atelier-textDark font-sans">
                    Rp {{ number_format($aov, 0, ',', '.') }}
                </div>
                <div class="text-[10px] text-atelier-textMuted mt-1">Per transaksi lunas</div>
            </div>
        </div>

        <!-- Alamat Pengiriman Tersimpan -->
        <div class="mt-6 pt-5 border-t border-atelier-border">
            <h4 class="text-xs font-bold uppercase tracking-wider text-atelier-textLight mb-3">Alamat Pengiriman Tersimpan</h4>
            @if($customer->addresses->isEmpty())
                <p class="text-xs text-atelier-textMuted">Pelanggan belum menyimpan alamat pengiriman.</p>
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    @foreach($customer->addresses as $addr)
                        <div class="p-3 rounded-xl border {{ $addr->is_default ? 'border-brand-300 bg-brand-50/30' : 'border-atelier-border bg-white' }} text-xs">
                            <div class="flex items-center justify-between mb-1.5">
                                <span class="font-bold text-atelier-textDark">{{ $addr->recipient_name }}</span>
                                @if($addr->is_default)
                                    <span class="badge-active !text-[9px]">Alamat Utama</span>
                                @endif
                            </div>
                            <div class="text-atelier-textMuted text-[11px] leading-relaxed">
                                {{ $addr->full_address }}<br>
                                {{ $addr->city }}, {{ $addr->province }} {{ $addr->postal_code }}
                            </div>
                            <div class="text-atelier-textDark text-[11px] font-medium mt-1">
                                No. HP: {{ $addr->phone }}
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

</div>

<!-- ========================================================= -->
<!-- ORDER HISTORY TABLE -->
<!-- ========================================================= -->
<div class="admin-card overflow-hidden">
    <div class="p-5 border-b border-atelier-border flex items-center justify-between">
        <div>
            <h3 class="text-xs font-bold uppercase tracking-wider text-atelier-textDark m-0">Riwayat Pesanan Pelanggan</h3>
            <p class="text-[11px] text-atelier-textMuted mt-0.5 m-0">Daftar transaksi yang pernah dilakukan oleh {{ $customer->name }}</p>
        </div>
        <span class="text-xs font-semibold text-atelier-textMuted">
            {{ $orders->total() }} total transaksi
        </span>
    </div>

    @php
        $orderStatusMap = [
            'pending'    => ['label' => 'Menunggu Bayar', 'bg' => '#fefce8', 'color' => '#854d0e', 'border' => '#fef08a'],
            'paid'       => ['label' => 'Sudah Dibayar',  'bg' => '#eef2ff', 'color' => '#3730a3', 'border' => '#c7d2fe'],
            'diproses'   => ['label' => 'Sedang Diproses', 'bg' => '#faf5ff', 'color' => '#6b21a8', 'border' => '#e9d5ff'],
            'dikirim'    => ['label' => 'Sedang Dikirim',  'bg' => '#eff6ff', 'color' => '#1d4ed8', 'border' => '#bfdbfe'],
            'selesai'    => ['label' => 'Selesai',         'bg' => '#f0fdf4', 'color' => '#166534', 'border' => '#bbf7d0'],
            'dibatalkan' => ['label' => 'Dibatalkan',      'bg' => '#fef2f2', 'color' => '#991b1b', 'border' => '#fecaca'],
        ];
    @endphp

    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse text-xs">
            <thead>
                <tr class="border-b border-atelier-border bg-atelier-cream/40 text-[10px] uppercase font-bold text-atelier-textLight tracking-wider">
                    <th class="py-3 px-4">No. Pesanan</th>
                    <th class="py-3 px-4">Tanggal</th>
                    <th class="py-3 px-4">Status</th>
                    <th class="py-3 px-4">Produk yang Dibeli</th>
                    <th class="py-3 px-4 text-right">Ongkir</th>
                    <th class="py-3 px-4 text-right">Total Akhir</th>
                    <th class="py-3 px-4 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-atelier-border">
                @forelse($orders as $order)
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
                                Kelola Pesanan
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="py-8 text-center text-atelier-textMuted text-xs">
                            Pelanggan ini belum pernah melakukan pemesanan.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($orders->hasPages())
        <div class="p-4 border-t border-atelier-border bg-white flex justify-end">
            {{ $orders->links() }}
        </div>
    @endif
</div>

@endsection
