@extends('admin.layouts.admin')

@section('title', 'Detail Retur #' . $return->return_number)
@section('page-title', 'Detail Pengajuan Retur')
@section('page-subtitle', 'Informasi lengkap pengajuan retur #' . $return->return_number . ' dari pelanggan')

@section('content')

@php
    $statusMap = [
        'diajukan'  => ['label' => 'Diajukan',   'style' => 'background:#fefce8; color:#854d0e; border:1px solid #fef08a;'],
        'disetujui' => ['label' => 'Disetujui',  'style' => 'background:#eef2ff; color:#3730a3; border:1px solid #c7d2fe;'],
        'diproses'  => ['label' => 'Diproses',   'style' => 'background:#faf5ff; color:#6b21a8; border:1px solid #e9d5ff;'],
        'selesai'   => ['label' => 'Selesai',    'style' => 'background:#f0fdf4; color:#166534; border:1px solid #bbf7d0;'],
        'ditolak'   => ['label' => 'Ditolak',    'style' => 'background:#fef2f2; color:#991b1b; border:1px solid #fecaca;'],
    ];
    $resMap = [
        'tukar_barang' => ['label' => 'Tukar Barang', 'style' => 'background:#ede9fe; color:#5b21b6; border:1px solid #ddd6fe;'],
        'refund'       => ['label' => 'Refund',       'style' => 'background:#dbeafe; color:#1e40af; border:1px solid #bfdbfe;'],
    ];
    $s   = $statusMap[$return->status] ?? ['label' => ucfirst($return->status), 'style' => 'background:#f5f5f5; color:#78716C;'];
    $res = $resMap[$return->resolution_type] ?? ['label' => ucfirst($return->resolution_type), 'style' => 'background:#f5f5f5; color:#78716C;'];
@endphp

<div class="space-y-6">

    {{-- Top Navigation & Action Header --}}
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.returns.index') }}"
               class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold text-stone-600 bg-white border border-stone-200 rounded-xl hover:bg-stone-50 hover:text-stone-900 transition shadow-2xs">
                <span class="material-symbols-outlined !text-[16px]">arrow_back</span>
                Kembali ke Daftar Retur
            </a>
            <div class="h-4 w-px bg-stone-200"></div>
            <span class="font-mono text-sm font-bold text-brand-700">#{{ $return->return_number }}</span>
            <span style="display:inline-block; padding:2px 10px; border-radius:20px; font-size:0.75rem; font-weight:700; {{ $s['style'] }}">
                {{ $s['label'] }}
            </span>
            <span style="display:inline-block; padding:2px 10px; border-radius:20px; font-size:0.75rem; font-weight:700; {{ $res['style'] }}">
                {{ $res['label'] }}
            </span>
        </div>

        {{-- Action Buttons --}}
        <div class="flex items-center gap-2">
            @if($return->status === 'diajukan')
                {{-- Setujui Popover --}}
                <details class="relative inline-block">
                    <summary class="list-none inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-bold text-white bg-brand-600 hover:bg-brand-700 rounded-xl cursor-pointer transition shadow-xs">
                        <span class="material-symbols-outlined !text-[16px]">check_circle</span>
                        Setujui Retur
                    </summary>
                    <div class="absolute right-0 mt-2 z-20 bg-white border border-stone-200 rounded-2xl shadow-xl p-4 min-w-[280px]">
                        <form method="POST" action="{{ route('admin.returns.approve', $return->id) }}">
                            @csrf
                            @method('PATCH')
                            @if($return->resolution_type === 'refund')
                                <div class="mb-3">
                                    <label class="admin-label text-xs">Jumlah Refund (Rp) *</label>
                                    <input type="number" name="refund_amount" min="0" step="1"
                                           class="admin-input text-xs w-full mt-1" placeholder="Contoh: 150000" required>
                                </div>
                            @endif
                            <div class="mb-3">
                                <label class="admin-label text-xs">Catatan Admin (opsional)</label>
                                <textarea name="admin_notes" rows="2" class="admin-input text-xs w-full mt-1"
                                          placeholder="Catatan untuk pelanggan..."></textarea>
                            </div>
                            <button type="submit" class="admin-btn-primary w-full justify-center text-xs py-2">
                                Konfirmasi Setujui
                            </button>
                        </form>
                    </div>
                </details>

                {{-- Tolak Popover --}}
                <details class="relative inline-block">
                    <summary class="list-none inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-bold text-rose-600 bg-rose-50 hover:bg-rose-100 border border-rose-200 rounded-xl cursor-pointer transition">
                        <span class="material-symbols-outlined !text-[16px]">cancel</span>
                        Tolak Retur
                    </summary>
                    <div class="absolute right-0 mt-2 z-20 bg-white border border-stone-200 rounded-2xl shadow-xl p-4 min-w-[280px]">
                        <form method="POST" action="{{ route('admin.returns.reject', $return->id) }}">
                            @csrf
                            @method('PATCH')
                            <div class="mb-3">
                                <label class="admin-label text-xs">Alasan Penolakan *</label>
                                <textarea name="admin_notes" rows="3" class="admin-input text-xs w-full mt-1"
                                          placeholder="Jelaskan alasan penolakan..." required minlength="5"></textarea>
                            </div>
                            <button type="submit" class="admin-btn-danger w-full justify-center text-xs py-2">
                                Konfirmasi Tolak
                            </button>
                        </form>
                    </div>
                </details>

            @elseif($return->status === 'disetujui')
                <form method="POST" action="{{ route('admin.returns.advance', $return->id) }}">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="admin-btn-primary text-xs py-2 px-3.5">
                        <span class="material-symbols-outlined !text-[16px]">inbox</span>
                        Tandai Diproses
                    </button>
                </form>

            @elseif($return->status === 'diproses')
                <form method="POST" action="{{ route('admin.returns.advance', $return->id) }}">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="admin-btn-primary text-xs py-2 px-3.5">
                        <span class="material-symbols-outlined !text-[16px]">task_alt</span>
                        Tandai Selesai
                    </button>
                </form>
            @endif
        </div>
    </div>

    {{-- Main Grid --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Left Column (2 Cols): Detail Items & Alasan --}}
        <div class="lg:col-span-2 space-y-6">

            {{-- Card: Barang yang Diretur --}}
            <div class="bg-white rounded-2xl border border-atelier-border shadow-2xs overflow-hidden">
                <div class="px-5 py-4 bg-stone-50/80 border-b border-stone-200 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-stone-500 !text-[18px]">inventory_2</span>
                        <h2 class="text-xs font-bold uppercase tracking-wider text-stone-700">Barang yang Diajukan Retur</h2>
                    </div>
                    <span class="text-xs font-bold text-stone-500">{{ $return->items->count() }} item</span>
                </div>

                <div class="divide-y divide-stone-100">
                    @forelse($return->items as $item)
                        @php
                            $orderItem = $item->orderItem;
                        @endphp
                        <div class="p-4 flex items-center justify-between gap-4">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="w-10 h-10 rounded-xl bg-stone-100 border border-stone-200 flex items-center justify-center text-stone-400 shrink-0">
                                    <span class="material-symbols-outlined !text-[20px]">category</span>
                                </div>
                                <div class="min-w-0">
                                    <div class="text-xs font-bold text-stone-800 truncate">
                                        {{ $orderItem?->product_name ?? 'Item Produk' }}
                                    </div>
                                    @if($orderItem?->variant_name)
                                        <div class="text-[11px] text-stone-500">
                                            Varian: <span class="font-medium text-stone-700">{{ $orderItem->variant_name }}</span>
                                        </div>
                                    @endif
                                    <div class="text-[11px] text-stone-400 font-mono mt-0.5">
                                        Harga Satuan: Rp {{ number_format($orderItem?->price ?? 0, 0, ',', '.') }}
                                    </div>
                                </div>
                            </div>

                            <div class="text-right shrink-0">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-lg text-xs font-bold bg-amber-50 text-amber-800 border border-amber-200">
                                    Jumlah Retur: {{ $item->quantity }}
                                </span>
                                <div class="text-xs font-bold text-stone-800 mt-1">
                                    Rp {{ number_format(($orderItem?->price ?? 0) * $item->quantity, 0, ',', '.') }}
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="p-6 text-center text-xs text-stone-400">Tidak ada item tercatat.</div>
                    @endforelse
                </div>
            </div>

            {{-- Card: Alasan Retur & Foto Bukti --}}
            <div class="bg-white rounded-2xl border border-atelier-border shadow-2xs p-5 space-y-4">
                <div class="flex items-center gap-2 pb-3 border-b border-stone-100">
                    <span class="material-symbols-outlined text-stone-500 !text-[18px]">help_center</span>
                    <h2 class="text-xs font-bold uppercase tracking-wider text-stone-700">Alasan &amp; Bukti Pengajuan</h2>
                </div>

                <div>
                    <label class="text-[11px] font-bold text-stone-500 uppercase tracking-wider">Alasan Retur dari Pelanggan</label>
                    <div class="mt-1.5 p-3.5 bg-stone-50 border border-stone-200 rounded-xl text-xs text-stone-800 leading-relaxed">
                        {{ $return->reason }}
                    </div>
                </div>

                @if($return->evidence_photo_path)
                    <div>
                        <label class="text-[11px] font-bold text-stone-500 uppercase tracking-wider">Foto Bukti Fisik</label>
                        <div class="mt-1.5">
                            <a href="{{ Storage::disk('public')->url($return->evidence_photo_path) }}" target="_blank" class="inline-block group">
                                <img src="{{ Storage::disk('public')->url($return->evidence_photo_path) }}"
                                     alt="Foto Bukti Retur"
                                     class="max-h-64 rounded-xl border border-stone-200 object-cover shadow-2xs group-hover:opacity-90 transition">
                                <span class="inline-flex items-center gap-1 text-[11px] text-brand-600 font-semibold mt-1.5 hover:underline">
                                    <span class="material-symbols-outlined !text-[13px]">open_in_new</span>
                                    Buka Foto Resolusi Penuh
                                </span>
                            </a>
                        </div>
                    </div>
                @endif

                @if($return->admin_notes)
                    <div class="pt-3 border-t border-stone-100">
                        <label class="text-[11px] font-bold text-stone-500 uppercase tracking-wider">Catatan Admin</label>
                        <div class="mt-1.5 p-3.5 bg-brand-50/50 border border-brand-200 rounded-xl text-xs text-brand-900 leading-relaxed">
                            {{ $return->admin_notes }}
                        </div>
                    </div>
                @endif
            </div>

        </div>

        {{-- Right Column (1 Col): Order & Customer Info --}}
        <div class="space-y-6">

            {{-- Card: Ringkasan Pengajuan --}}
            <div class="bg-white rounded-2xl border border-atelier-border shadow-2xs p-5 space-y-3.5 text-xs">
                <div class="flex items-center gap-2 pb-2 border-b border-stone-100">
                    <span class="material-symbols-outlined text-stone-500 !text-[18px]">info</span>
                    <h2 class="text-xs font-bold uppercase tracking-wider text-stone-700">Ringkasan Retur</h2>
                </div>

                <div class="flex items-center justify-between">
                    <span class="text-stone-500">Nomor Retur</span>
                    <span class="font-mono font-bold text-brand-700">#{{ $return->return_number }}</span>
                </div>

                <div class="flex items-center justify-between">
                    <span class="text-stone-500">Tanggal Pengajuan</span>
                    <span class="font-medium text-stone-800">{{ $return->created_at->format('d M Y, H:i') }} WIB</span>
                </div>

                <div class="flex items-center justify-between">
                    <span class="text-stone-500">Jenis Solusi</span>
                    <span class="font-bold text-stone-800">{{ $res['label'] }}</span>
                </div>

                @if($return->resolution_type === 'refund' && $return->refund_amount)
                    <div class="flex items-center justify-between pt-2 border-t border-stone-100">
                        <span class="text-stone-500">Nominal Refund Disetujui</span>
                        <span class="font-bold text-emerald-600 font-mono text-sm">Rp {{ number_format($return->refund_amount, 0, ',', '.') }}</span>
                    </div>
                @endif

                @if($return->resolved_at)
                    <div class="flex items-center justify-between pt-2 border-t border-stone-100">
                        <span class="text-stone-500">Tanggal Diputuskan</span>
                        <span class="font-medium text-stone-800">{{ $return->resolved_at->format('d M Y, H:i') }} WIB</span>
                    </div>
                @endif
            </div>

            {{-- Card: Data Pesanan Asal --}}
            <div class="bg-white rounded-2xl border border-atelier-border shadow-2xs p-5 space-y-3.5 text-xs">
                <div class="flex items-center justify-between pb-2 border-b border-stone-100">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-stone-500 !text-[18px]">receipt_long</span>
                        <h2 class="text-xs font-bold uppercase tracking-wider text-stone-700">Pesanan Asal</h2>
                    </div>
                    @if($return->order)
                        <a href="{{ route('admin.orders.index', ['highlight' => $return->order->id]) }}"
                           class="text-[11px] font-bold text-brand-600 hover:text-brand-700 hover:underline flex items-center gap-0.5">
                            Lihat Pesanan
                            <span class="material-symbols-outlined !text-[13px]">arrow_forward</span>
                        </a>
                    @endif
                </div>

                @if($return->order)
                    <div class="flex items-center justify-between">
                        <span class="text-stone-500">Nomor Pesanan</span>
                        <span class="font-mono font-bold text-stone-800">#{{ $return->order->order_number }}</span>
                    </div>

                    <div class="flex items-center justify-between">
                        <span class="text-stone-500">Total Transaksi</span>
                        <span class="font-mono font-bold text-stone-800">Rp {{ number_format($return->order->total_amount, 0, ',', '.') }}</span>
                    </div>

                    <div class="flex items-center justify-between">
                        <span class="text-stone-500">Status Pesanan</span>
                        <span class="font-semibold text-stone-700">{{ ucfirst($return->order->status) }}</span>
                    </div>

                    <div class="flex items-center justify-between">
                        <span class="text-stone-500">Waktu Pembelian</span>
                        <span class="text-stone-700">{{ $return->order->created_at->format('d M Y') }}</span>
                    </div>
                @else
                    <div class="text-stone-400 text-center py-2">Data pesanan tidak ditemukan.</div>
                @endif
            </div>

            {{-- Card: Data Pelanggan --}}
            <div class="bg-white rounded-2xl border border-atelier-border shadow-2xs p-5 space-y-3.5 text-xs">
                <div class="flex items-center gap-2 pb-2 border-b border-stone-100">
                    <span class="material-symbols-outlined text-stone-500 !text-[18px]">person</span>
                    <h2 class="text-xs font-bold uppercase tracking-wider text-stone-700">Data Pelanggan</h2>
                </div>

                <div class="flex items-center justify-between">
                    <span class="text-stone-500">Nama</span>
                    <span class="font-bold text-stone-800">{{ $return->user?->name ?? 'Guest / Tidak Tercatat' }}</span>
                </div>

                <div class="flex items-center justify-between">
                    <span class="text-stone-500">Email</span>
                    <span class="text-stone-700">{{ $return->user?->email ?? '-' }}</span>
                </div>

                @if($return->user?->phone)
                    <div class="flex items-center justify-between">
                        <span class="text-stone-500">No. Telepon</span>
                        <span class="font-mono text-stone-700">{{ $return->user->phone }}</span>
                    </div>
                @endif

                @if($return->user)
                    <div class="pt-2 border-t border-stone-100">
                        <a href="{{ route('admin.customers.show', $return->user->id) }}"
                           class="w-full inline-flex items-center justify-center gap-1.5 px-3 py-2 text-xs font-semibold text-stone-700 bg-stone-50 hover:bg-stone-100 border border-stone-200 rounded-xl transition">
                            <span class="material-symbols-outlined !text-[15px]">account_box</span>
                            Profil Pelanggan
                        </a>
                    </div>
                @endif
            </div>

        </div>

    </div>

</div>

@endsection
