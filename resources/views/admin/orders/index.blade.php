@extends('admin.layouts.admin')

@section('title', 'Kelola Pesanan')
@section('page-title', 'Kelola Pesanan')
@section('page-subtitle', 'Daftar semua pesanan pelanggan — majukan status paid → diproses → dikirim')

@section('content')

@php
    $statusMap = [
        'pending'    => ['label' => 'Menunggu Pembayaran', 'style' => 'background:#fefce8; color:#854d0e; border:1px solid #fef08a;'],
        'paid'       => ['label' => 'Pembayaran Berhasil', 'style' => 'background:#f0fdf4; color:#166534; border:1px solid #bbf7d0;'],
        'diproses'   => ['label' => 'Sedang Diproses',    'style' => 'background:#faf5ff; color:#6b21a8; border:1px solid #e9d5ff;'],
        'dikirim'    => ['label' => 'Sedang Dikirim',     'style' => 'background:#eef2ff; color:#3730a3; border:1px solid #c7d2fe;'],
        'selesai'    => ['label' => 'Pesanan Diterima',   'style' => 'background:#f0fdf4; color:#166534; border:1px solid #bbf7d0;'],
        'dibatalkan' => ['label' => 'Dibatalkan',         'style' => 'background:#fef2f2; color:#991b1b; border:1px solid #fecaca;'],
    ];
@endphp

<div class="admin-card" style="overflow:hidden;">

    {{-- Table --}}
    <div style="overflow-x:auto;">
        <table style="width:100%; border-collapse:collapse; font-size:0.8125rem;">
            <thead>
                <tr style="background:#F9F6F4; border-bottom:1px solid #EDE8E4;">
                    <th style="padding:0.75rem 1.25rem; text-align:left; font-size:0.7rem; font-weight:700; color:#78716C; text-transform:uppercase; letter-spacing:0.05em; white-space:nowrap;">Nomor Pesanan</th>
                    <th style="padding:0.75rem 1.25rem; text-align:left; font-size:0.7rem; font-weight:700; color:#78716C; text-transform:uppercase; letter-spacing:0.05em;">Pelanggan</th>
                    <th style="padding:0.75rem 1.25rem; text-align:left; font-size:0.7rem; font-weight:700; color:#78716C; text-transform:uppercase; letter-spacing:0.05em;">Total</th>
                    <th style="padding:0.75rem 1.25rem; text-align:left; font-size:0.7rem; font-weight:700; color:#78716C; text-transform:uppercase; letter-spacing:0.05em;">Status</th>
                    <th style="padding:0.75rem 1.25rem; text-align:left; font-size:0.7rem; font-weight:700; color:#78716C; text-transform:uppercase; letter-spacing:0.05em; white-space:nowrap;">Tanggal</th>
                    <th style="padding:0.75rem 1.25rem; text-align:right; font-size:0.7rem; font-weight:700; color:#78716C; text-transform:uppercase; letter-spacing:0.05em;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($orders as $order)
                    @php
                        $s = $statusMap[$order->status] ?? ['label' => ucfirst($order->status), 'style' => 'background:#f5f5f5; color:#78716C; border:1px solid #e5e5e5;'];
                    @endphp
                    <tr style="border-bottom:1px solid #EDE8E4;" onmouseover="this.style.background='#FDF8F6'" onmouseout="this.style.background=''">
                        <td style="padding:0.875rem 1.25rem;">
                            <span style="font-family:monospace; font-size:0.8rem; font-weight:600; color:#C17B6F;">{{ $order->order_number }}</span>
                        </td>
                        <td style="padding:0.875rem 1.25rem;">
                            <span style="font-weight:500; color:#1c1917;">{{ $order->user?->name ?? '—' }}</span>
                        </td>
                        <td style="padding:0.875rem 1.25rem;">
                            <span style="font-weight:600; color:#1c1917;">Rp {{ number_format($order->total_amount, 0, ',', '.') }}</span>
                        </td>
                        <td style="padding:0.875rem 1.25rem;">
                            <span style="display:inline-block; padding:2px 10px; border-radius:20px; font-size:0.7rem; font-weight:600; {{ $s['style'] }}">
                                {{ $s['label'] }}
                            </span>
                        </td>
                        <td style="padding:0.875rem 1.25rem; color:#78716C; white-space:nowrap; font-size:0.775rem;">
                            {{ $order->created_at->format('d M Y, H:i') }}
                        </td>
                        <td style="padding:0.875rem 1.25rem; text-align:right;">
                            @if($order->status === 'paid')
                                <form method="POST" action="{{ route('admin.orders.advance-status', $order) }}" style="display:inline;">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="admin-btn-primary" style="font-size:0.75rem; padding:0.375rem 0.875rem;">
                                        <span class="material-symbols-outlined" style="font-size:14px;">inbox</span>
                                        Tandai Diproses
                                    </button>
                                </form>
                            @elseif($order->status === 'diproses')
                                <form method="POST" action="{{ route('admin.orders.advance-status', $order) }}" style="display:inline;">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="admin-btn-primary" style="font-size:0.75rem; padding:0.375rem 0.875rem;">
                                        <span class="material-symbols-outlined" style="font-size:14px;">local_shipping</span>
                                        Tandai Dikirim
                                    </button>
                                </form>
                            @else
                                <span style="font-size:0.75rem; color:#b5b0ad;">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" style="padding:3rem; text-align:center; color:#78716C; font-size:0.8125rem;">
                            <span class="material-symbols-outlined" style="display:block; font-size:36px; margin-bottom:8px; color:#EDE8E4;">receipt_long</span>
                            Belum ada pesanan.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    @if($orders->hasPages())
        <div style="padding:1rem 1.25rem; border-top:1px solid #EDE8E4;">
            {{ $orders->links() }}
        </div>
    @endif
</div>

@endsection
