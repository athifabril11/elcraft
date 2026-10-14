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
                $isActive = empty($paymentCaptured) && ($filter ?? '') === $key;
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

        @if(($capturedCount ?? 0) > 0 || !empty($paymentCaptured))
            @php
                $isCapturedActive = !empty($paymentCaptured);
            @endphp
            <a href="{{ route('admin.orders.index', array_filter(['payment_captured' => 1, 'q' => $search])) }}"
               class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full text-xs font-semibold whitespace-nowrap transition-all text-decoration-none {{ $isCapturedActive ? 'bg-rose-600 text-white shadow-xs font-bold' : 'bg-rose-50 text-rose-700 border border-rose-200 hover:bg-rose-100 hover:text-rose-900' }}">
                <span class="material-symbols-outlined" style="font-size:14px;">warning</span>
                <span>Dana Tertangkap</span>
                <span class="px-1.5 py-0.2 rounded-full text-[10px] font-bold {{ $isCapturedActive ? 'bg-white/25 text-white' : 'bg-rose-200 text-rose-800' }}">
                    {{ $capturedCount ?? 0 }}
                </span>
            </a>
        @endif
    </div>

    <!-- Search Box -->
    <form method="GET" action="{{ route('admin.orders.index') }}" class="flex items-center gap-2 m-0 shrink-0">
        @if(!empty($paymentCaptured))
            <input type="hidden" name="payment_captured" value="1">
        @elseif($filter)
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
            <a href="{{ route('admin.orders.index', array_filter(['status' => $filter, 'payment_captured' => !empty($paymentCaptured) ? 1 : null])) }}"
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
                    <th class="px-5 py-3.5">Pengiriman & Kurir</th>
                    <th class="px-5 py-3.5">Total & Pembayaran</th>
                    <th class="px-5 py-3.5">Status</th>
                    <th class="px-5 py-3.5 whitespace-nowrap">Tanggal</th>
                    <th class="px-5 py-3.5 text-right whitespace-nowrap">Aksi Cepat</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @forelse($orders as $order)
                    @php
                        $st = $statusMap[$order->status] ?? ['label' => ucfirst($order->status), 'style' => 'background:#f5f5f5; color:#78716C; border:1px solid #e5e5e5;'];
                        $isHighlighted = request('highlight') == $order->id;
                    @endphp
                    <tr class="{{ $isHighlighted ? 'bg-amber-50/70 ring-1 ring-amber-300' : 'hover:bg-brand-50/20' }} transition-colors" id="order-row-{{ $order->id }}">
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

                        <!-- Pengiriman & Ekspedisi -->
                        <td class="px-5 py-4 text-[11px] text-stone-600">
                            @if($order->address)
                                <div class="font-medium text-stone-800">{{ $order->address->city }}, {{ $order->address->province }}</div>
                            @else
                                <div class="text-stone-400 italic">Alamat tidak tersedia</div>
                            @endif
                            <div class="mt-1 flex items-center gap-1.5 flex-wrap">
                                {{-- TASK 4: SVG truck icon removed; text-only courier badge --}}
                                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold bg-amber-50 text-amber-800 border border-amber-200">
                                    {{ $order->formatted_courier }}
                                </span>
                                @if($order->shipping_cost > 0)
                                    <span class="text-[10px] text-stone-400 font-mono">Rp {{ number_format($order->shipping_cost, 0, ',', '.') }}</span>
                                @else
                                    <span class="text-[10px] text-emerald-600 font-bold">Gratis</span>
                                @endif
                            </div>
                        </td>

                        <!-- Total & Metode Pembayaran -->
                        <td class="px-5 py-4 whitespace-nowrap">
                            <span class="font-sans font-bold text-stone-900 text-xs block">
                                Rp {{ number_format($order->total_amount, 0, ',', '.') }}
                            </span>
                            <div class="mt-1">
                                @if($order->payment)
                                    {{-- TASK 4: SVG credit card icon removed; text-only payment badge --}}
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-semibold bg-stone-100 text-stone-700 border border-stone-200">
                                        {{ $order->payment->formatted_method }}
                                    </span>
                                @else
                                    <span class="text-[10px] text-stone-400 italic">Belum Ada Bayar</span>
                                @endif
                            </div>
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

                        <!-- Aksi Cepat -->
                        <td class="px-5 py-4 text-right whitespace-nowrap">
                            <div class="inline-flex items-center gap-2 justify-end">
                                {{-- Refund aksi (hanya di filter dana tertangkap, payment masih success) --}}
                                @if(!empty($paymentCaptured) && $order->payment?->status === 'success')
                                    @php
                                        $rawMethod = $order->payment->payment_method ?: $order->payment->payment_type;
                                        $methodKey = $rawMethod ? strtolower(trim($rawMethod)) : '';
                                        $apiSupported = in_array($methodKey, \App\Services\RefundService::API_SUPPORTED_METHODS, true);
                                    @endphp

                                    @if($apiSupported)
                                        <form method="POST" action="{{ route('admin.orders.refund-api', $order->id) }}" class="inline m-0"
                                              onsubmit="return confirm('Kirim refund Rp {{ number_format((int) $order->payment->amount, 0, ',', '.') }} via Midtrans Refund API untuk pesanan {{ $order->order_number }}?')">
                                            @csrf
                                            <button type="submit" class="admin-btn-primary" style="font-size:0.75rem; padding:0.35rem 0.85rem; background:#be123c; border-color:#be123c;">
                                                Refund API
                                            </button>
                                        </form>
                                    @endif

                                    <form method="POST" action="{{ route('admin.orders.refund-manual', $order->id) }}" class="inline m-0"
                                          onsubmit="return confirm('Catat refund manual untuk pesanan {{ $order->order_number }}? Pastikan pengembalian dana sudah dieksekusi di luar sistem.')">
                                        @csrf
                                        <button type="button" class="admin-btn-ghost" style="font-size:0.75rem; padding:0.35rem 0.85rem;"
                                                x-data
                                                @click="const n = prompt('Catatan refund manual (wajib):'); if (n !== null) { if (n.trim() === '') { alert('Catatan wajib diisi.'); return; } this.$el.closest('form').querySelector('input[name=manual_notes]').value = n; this.$el.closest('form').submit(); }">
                                            Refund Manual
                                        </button>
                                        <input type="hidden" name="manual_notes" value="">
                                    </form>
                                @endif

                                {{-- "Tandai Diproses" quick-action (paid only) — unchanged --}}
                                @if($order->status === 'paid')
                                    <form method="POST" action="{{ route('admin.orders.advance-status', $order) }}" class="inline m-0">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="admin-btn-primary" style="font-size:0.75rem; padding:0.35rem 0.85rem;">
                                            <span class="material-symbols-outlined" style="font-size:14px;">inbox</span>
                                            Tandai Diproses
                                        </button>
                                    </form>
                                @endif

                                {{-- "Batalkan Pesanan" (paid / diproses) — buka modal alasan --}}
                                @if(in_array($order->status, ['paid', 'diproses'], true))
                                    <button
                                        type="button"
                                        class="admin-btn-ghost"
                                        style="font-size:0.75rem; padding:0.35rem 0.85rem; color:#be123c; border-color:#fecaca;"
                                        x-data
                                        @click="$dispatch('open-cancel-paid', { id: {{ $order->id }}, order_number: '{{ $order->order_number }}', url: '{{ route('admin.orders.cancel-paid', $order->id) }}' })"
                                    >
                                        Batalkan Pesanan
                                    </button>
                                @endif

                                {{-- "Detail" button — shown for every row; opens Alpine modal --}}
                                <button
                                    type="button"
                                    class="admin-btn-ghost"
                                    style="font-size:0.75rem; padding:0.35rem 0.85rem;"
                                    x-data
                                    @click="$dispatch('open-order-detail', { id: {{ $order->id }} })"
                                >
                                    Detail
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="p-12 text-center text-stone-500 text-xs">
                            <span class="material-symbols-outlined text-stone-300 block mb-2" style="font-size:36px;">receipt_long</span>
                            Belum ada pesanan{{ !empty($paymentCaptured) ? ' dengan status dana tertangkap' : ($filter ? ' dengan status "' . ($tabStatuses[$filter] ?? $filter) . '"' : '') }}{{ $search ? ' untuk pencarian "' . $search . '"' : '' }}.
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

{{-- Modal: Batalkan Pesanan (paid/diproses) — satu instance, dibuka via event dispatch --}}
@php
    // Setelah redirect validasi gagal, modal dibuka ulang dengan old input.
    // Hanya percaya _cancel_action jika menunjuk ke route admin orders kita
    // sendiri — nilai lain (mis. kiriman tamu) diabaikan.
    $oldCancelAction = \Illuminate\Support\Str::startsWith((string) old('_cancel_action'), url('admin/orders/'))
        ? old('_cancel_action')
        : '';
@endphp
<div
    x-data="{
        open: {{ $errors->has('reason') ? 'true' : 'false' }},
        submitting: false,
        orderId: null,
        orderNumber: @js(old('_cancel_order_number', '')),
        cancelAction: @js($oldCancelAction),
        reason: @js(old('reason', '')),

        openCancel(payload) {
            this.orderId = payload.id;
            this.orderNumber = payload.order_number ?? '';
            this.cancelAction = payload.url ?? '';
            this.reason = '';
            this.submitting = false;
            this.open = true;
        },

        closeModal() {
            if (this.submitting) return;
            this.open = false;
            this.reason = '';
        }
    }"
    x-on:keydown.escape.window="closeModal()"
    x-on:open-cancel-paid.window="openCancel($event.detail)"
    id="cancel-paid-modal-root"
>
    {{-- Backdrop --}}
    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-50 flex items-start justify-center pt-16 pb-6 px-4 overflow-y-auto"
        style="display:none;"
    >
        <div class="fixed inset-0 bg-black/40 backdrop-blur-[2px]" x-on:click="closeModal()"></div>

        {{-- Modal panel --}}
        <div
            x-show="open"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-4 scale-[0.97]"
            x-transition:enter-end="opacity-100 translate-y-0 scale-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 translate-y-0 scale-100"
            x-transition:leave-end="opacity-0 translate-y-4 scale-[0.97]"
            class="relative z-10 w-full max-w-md bg-white rounded-2xl shadow-2xl border border-atelier-border overflow-hidden"
            style="display:none;"
        >
            {{-- Header --}}
            <div class="flex items-center justify-between px-6 py-4 border-b border-atelier-border bg-[#FAF7F5]">
                <div>
                    <h2 class="text-sm font-bold text-atelier-textDark tracking-tight">Batalkan Pesanan</h2>
                    <p x-show="orderNumber" class="text-[11px] text-atelier-textMuted mt-0.5 font-mono" x-text="orderNumber"></p>
                </div>
                <button
                    type="button"
                    x-on:click="closeModal()"
                    class="w-8 h-8 flex items-center justify-center rounded-xl text-atelier-textMuted hover:bg-brand-50 hover:text-brand-600 transition-colors"
                    aria-label="Tutup"
                >
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M18 6 6 18M6 6l12 12"/></svg>
                </button>
            </div>

            {{-- Body: form pembatalan --}}
            <form method="POST" :action="cancelAction" class="px-6 py-5 space-y-4 m-0" x-on:submit="submitting = true">
                @csrf
                <input type="hidden" name="_cancel_action" :value="cancelAction">
                <input type="hidden" name="_cancel_order_number" :value="orderNumber">

                {{-- Peringatan --}}
                <div class="bg-amber-50 border border-amber-200 rounded-xl p-3.5">
                    <p class="text-[11px] text-amber-800 leading-relaxed">
                        Stok pesanan akan <strong>dipulihkan</strong>, pemakaian voucher (jika ada) akan
                        <strong>dilepas</strong>, dan pelanggan akan <strong>dikirim email</strong> pemberitahuan pembatalan.
                        Pembayaran yang sudah masuk <strong>TIDAK</strong> berubah statusnya — setelah dibatalkan,
                        proses refund lewat menu <strong>Dana Tertangkap</strong>.
                    </p>
                </div>

                <div>
                    <label class="admin-label">Alasan Pembatalan <span class="text-red-500">*</span></label>
                    <textarea
                        name="reason"
                        x-model="reason"
                        rows="3"
                        maxlength="500"
                        minlength="5"
                        required
                        placeholder="Contoh: Barang rusak saat pemeriksaan gudang."
                        class="admin-input resize-none"
                    >{{ old('reason') }}</textarea>
                    @error('reason')
                        <p class="text-[11px] text-red-600 font-semibold mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex items-center justify-end gap-2 pt-1">
                    <button
                        type="button"
                        x-on:click="closeModal()"
                        class="admin-btn-ghost"
                        style="font-size:0.75rem; padding:0.4rem 0.9rem;"
                    >Batal</button>
                    <button
                        type="submit"
                        :disabled="submitting || reason.trim().length < 5"
                        class="admin-btn-primary disabled:opacity-60 disabled:cursor-not-allowed"
                        style="font-size:0.75rem; padding:0.4rem 0.9rem; background:#be123c; border-color:#be123c;"
                    >
                        <span x-show="!submitting">Konfirmasi Batalkan</span>
                        <span x-show="submitting">Memproses...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Detail Pesanan Modal (one instance, opened via Alpine event dispatch) --}}
@include('admin.orders._detail-modal')

@endsection
