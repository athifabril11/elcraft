@extends('admin.layouts.admin')

@section('title', 'Kurir Pengiriman')
@section('page-title', 'Kurir Pengiriman')
@section('page-subtitle', 'Aktifkan atau nonaktifkan kurir pengiriman yang tersedia untuk pelanggan di halaman checkout')

@section('content')

<div style="max-width:800px;">

    <div class="admin-card" style="overflow:hidden;">
        <table style="width:100%; border-collapse:collapse; font-size:0.8125rem;">
            <thead>
                <tr style="background:#FDF8F6; border-bottom:1px solid #EDE8E4;">
                    <th style="padding:0.75rem 1.25rem; text-align:left; font-size:0.7rem; font-weight:700; color:#78716C; text-transform:uppercase; letter-spacing:0.05em;">Kurir</th>
                    <th style="padding:0.75rem 1.25rem; text-align:left; font-size:0.7rem; font-weight:700; color:#78716C; text-transform:uppercase; letter-spacing:0.05em;">Kode API</th>
                    <th style="padding:0.75rem 1.25rem; text-align:left; font-size:0.7rem; font-weight:700; color:#78716C; text-transform:uppercase; letter-spacing:0.05em;">Status</th>
                    <th style="padding:0.75rem 1.25rem; text-align:right; font-size:0.7rem; font-weight:700; color:#78716C; text-transform:uppercase; letter-spacing:0.05em;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @foreach($couriers as $courier)
                <tr style="border-bottom:1px solid #F5F0EC; {{ !$courier->is_active ? 'opacity:0.65;' : '' }}">
                    <td style="padding:0.875rem 1.25rem;">
                        <div style="display:flex; align-items:center; gap:10px;">
                            <div style="width:32px; height:32px; border-radius:8px; background:#C17B6F14; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                                <span class="material-symbols-outlined" style="font-size:18px; color:#C17B6F;">local_shipping</span>
                            </div>
                            <div>
                                <div style="font-weight:600; color:#1c1917;">{{ $courier->name }}</div>
                            </div>
                        </div>
                    </td>
                    <td style="padding:0.875rem 1.25rem;">
                        <code style="background:#f5f5f5; padding:2px 6px; border-radius:4px; font-size:0.75rem; color:#44403c; font-weight:600;">{{ $courier->code }}</code>
                    </td>
                    <td style="padding:0.875rem 1.25rem;">
                        @if($courier->is_active)
                            <span class="badge-active">Aktif</span>
                        @else
                            <span class="badge-inactive">Nonaktif</span>
                        @endif
                    </td>
                    <td style="padding:0.875rem 1.25rem; text-align:right;">
                        <form method="POST" action="{{ route('admin.settings.couriers.toggle', $courier) }}" style="display:inline-block;">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="admin-btn-ghost" style="padding:0.35rem 0.85rem; font-size:0.75rem;"
                                title="{{ $courier->is_active ? 'Nonaktifkan kurir' : 'Aktifkan kurir' }}">
                                <span class="material-symbols-outlined" style="font-size:15px;">
                                    {{ $courier->is_active ? 'toggle_on' : 'toggle_off' }}
                                </span>
                                {{ $courier->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                            </button>
                        </form>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <!-- Info note -->
    <div style="margin-top:1.25rem; padding:0.875rem 1.25rem; background:#fef9c3; border:1px solid #fef08a; border-radius:8px; font-size:0.775rem; color:#713f12; display:flex; gap:10px; align-items:flex-start;">
        <span class="material-symbols-outlined" style="font-size:18px; flex-shrink:0; margin-top:1px;">info</span>
        <div>
            <strong>Catatan:</strong> Kurir yang dinonaktifkan tidak akan muncul sebagai opsi di halaman checkout dan request kalkulasi ongkos kirim untuk kurir tersebut akan otomatis ditolak oleh sistem.
        </div>
    </div>

</div>

@endsection
