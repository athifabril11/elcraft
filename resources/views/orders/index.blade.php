@extends('layouts.storefront')

@section('title', 'Pesanan | el Craft')
@section('meta_description', 'Lihat riwayat pembelian aksesoris wanita premium Anda di el Craft.')

@section('content')
<div class="bg-white min-h-screen pb-24 md:pb-0">

    {{-- ─── HEADER ────────────────────────────────── --}}
    <x-page-header title="Pesanan" />

    <section class="max-w-[1280px] mx-auto px-5 md:px-8 lg:px-16 py-10">

        {{-- Flash Messages --}}
        @if(session('success'))
            <div x-data="{ show: true }" x-init="setTimeout(() => show = false, 4000)" x-show="show" x-transition.duration.300ms
                 class="mb-6 bg-green-50 border border-green-200 text-green-800 text-xs sm:text-sm px-4 py-3 rounded-card flex items-center justify-between gap-3 shadow-2xs" role="alert">
                <div class="flex items-center gap-2.5 min-w-0">
                    <span class="material-symbols-outlined !text-[20px] text-green-600 shrink-0">check_circle</span>
                    <span class="font-medium">{{ session('success') }}</span>
                </div>
                <button type="button" @click="show = false" class="text-green-600/70 hover:text-green-800 p-0.5 rounded cursor-pointer shrink-0" title="Tutup">
                    <span class="material-symbols-outlined !text-[18px]">close</span>
                </button>
            </div>
        @endif
        @if(session('error'))
            <div x-data="{ show: true }" x-init="setTimeout(() => show = false, 4000)" x-show="show" x-transition.duration.300ms
                 class="mb-6 bg-red-50 border border-red-200 text-red-800 text-xs sm:text-sm px-4 py-3 rounded-card flex items-center justify-between gap-3 shadow-2xs" role="alert">
                <div class="flex items-center gap-2.5 min-w-0">
                    <span class="material-symbols-outlined !text-[20px] text-red-600 shrink-0">error</span>
                    <span class="font-medium">{{ session('error') }}</span>
                </div>
                <button type="button" @click="show = false" class="text-red-600/70 hover:text-red-800 p-0.5 rounded cursor-pointer shrink-0" title="Tutup">
                    <span class="material-symbols-outlined !text-[18px]">close</span>
                </button>
            </div>
        @endif

        @if($orders->isEmpty())
            {{-- Empty State --}}
            <div class="flex flex-col items-center justify-center py-20 text-center">
                <div class="w-20 h-20 rounded-full bg-warmCream flex items-center justify-center mb-6 text-brand">
                    <span class="material-symbols-outlined !text-[36px]">receipt_long</span>
                </div>
                <h2 class="text-xl font-semibold text-warmBlack mb-2 font-sans">Belum Ada Pesanan</h2>
                <p class="text-xs text-warmGrey max-w-xs leading-relaxed mb-8">Anda belum pernah melakukan pembelian di el Craft. Temukan koleksi aksesoris premium kami.</p>
                <a href="/products" class="px-6 py-3 bg-brand hover:bg-brandDark text-white text-xs font-semibold uppercase tracking-wider rounded-btn transition-colors duration-250">
                    Mulai Belanja
                </a>
            </div>
        @else
            <div class="space-y-4">
                @foreach($orders as $order)
                    @php
                        $statusMap = [
                            'pending'    => ['label' => 'Menunggu Pembayaran', 'color' => 'text-yellow-700 bg-yellow-50 border-yellow-200'],
                            'paid'       => ['label' => 'Pembayaran Berhasil', 'color' => 'text-green-700 bg-green-50 border-green-200'],
                            'diproses'   => ['label' => 'Sedang Diproses',     'color' => 'text-purple-700 bg-purple-50 border-purple-200'],
                            'dikirim'    => ['label' => 'Sedang Dikirim',      'color' => 'text-indigo-700 bg-indigo-50 border-indigo-200'],
                            'selesai'    => ['label' => 'Pesanan Diterima',    'color' => 'text-green-700 bg-green-50 border-green-200'],
                            'dibatalkan' => ['label' => 'Dibatalkan',          'color' => 'text-red-700 bg-red-50 border-red-200'],
                        ];
                        $status = $statusMap[$order->status] ?? ['label' => ucfirst($order->status), 'color' => 'text-warmGrey bg-warmCream border-warmLightGrey'];
                    @endphp

                    <div class="bg-white rounded-card border border-warmLightGrey hover:border-brand/30 hover:shadow-sm transition-all duration-200 overflow-hidden">
                        {{-- Order Header --}}
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 px-5 py-4 border-b border-warmLightGrey/60 bg-warmCream/10">
                            <div class="flex items-center gap-3">
                                <span class="material-symbols-outlined text-brand !text-[20px]">receipt_long</span>
                                <div>
                                    <p class="text-xs font-semibold text-warmBlack font-mono tracking-wide">{{ $order->order_number }}</p>
                                    <p class="text-[11px] text-warmGrey mt-0.5">{{ $order->created_at->format('d M Y, H:i') }} WIB</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2 flex-wrap">
                                @if($order->status === 'pending' && $order->payment_expires_at)
                                    <div x-data="orderTimer('{{ $order->payment_expires_at->toISOString() }}')" x-init="initTimer()"
                                         class="inline-flex items-center gap-1.5 text-[11px] font-medium text-amber-800 bg-amber-50 border border-amber-200 px-2.5 py-0.5 rounded-full">
                                        <span class="material-symbols-outlined !text-[14px] text-amber-600 animate-pulse">timer</span>
                                        <span>Sisa Waktu: <strong x-text="timeLeft" class="font-mono font-bold">{{ $order->payment_expires_at->diffForHumans(['parts' => 2]) }}</strong></span>
                                    </div>
                                @endif
                                <span class="inline-flex items-center text-[11px] font-semibold px-2.5 py-1 rounded-full border {{ $status['color'] }}">
                                    {{ $status['label'] }}
                                </span>
                            </div>
                        </div>

                        {{-- Order Items Preview --}}
                        <div class="px-5 py-4">
                            <div class="flex flex-col gap-2">
                                @foreach($order->items->take(2) as $item)
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-img bg-warmCream flex-shrink-0 overflow-hidden border border-warmLightGrey/50">
                                            @if($item->product && $item->product->primary_image)
                                                <a href="/products/{{ $item->product->slug }}" class="block w-full h-full">
                                                    <img src="{{ $item->product->primary_image }}" alt="{{ $item->product_name }}" class="w-full h-full object-cover">
                                                </a>
                                            @else
                                                <span class="material-symbols-outlined text-warmGrey w-full h-full flex items-center justify-center !text-[18px]">image</span>
                                            @endif
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            @if($item->product)
                                                <a href="/products/{{ $item->product->slug }}" class="text-xs text-warmBlack font-medium hover:text-brand truncate block transition-colors">{{ $item->product_name }}</a>
                                            @else
                                                <p class="text-xs text-warmBlack font-medium truncate">{{ $item->product_name }}</p>
                                            @endif
                                            @if($item->variant_name)
                                                <p class="text-[11px] text-warmGrey">{{ $item->variant_name }}</p>
                                            @endif
                                        </div>
                                        <p class="text-xs text-warmGrey flex-shrink-0">x{{ $item->quantity }}</p>
                                    </div>
                                @endforeach
                                @if($order->items->count() > 2)
                                    <p class="text-[11px] text-warmGrey pl-1">+{{ $order->items->count() - 2 }} produk lainnya</p>
                                @endif
                            </div>
                        </div>

                        {{-- Order Footer --}}
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 px-5 py-4 border-t border-warmLightGrey/60 bg-warmCream/30 rounded-b-card">
                            <div class="space-y-1">
                                <div class="flex items-center gap-2.5 text-[11px] text-warmGrey flex-wrap">
                                    <span class="inline-flex items-center gap-1 font-medium text-warmBlack">
                                        <span class="material-symbols-outlined !text-[15px] text-brand">local_shipping</span>
                                        <span>{{ $order->formatted_courier }}</span>
                                    </span>
                                    @if($order->payment)
                                        <span class="text-warmGrey/40">•</span>
                                        <span class="inline-flex items-center gap-1 font-medium text-warmBlack">
                                            <span class="material-symbols-outlined !text-[15px] text-brand">credit_card</span>
                                            <span>{{ $order->payment->formatted_method }}</span>
                                        </span>
                                    @endif
                                </div>
                                <div class="flex items-baseline gap-2">
                                    <span class="text-[11px] text-warmGrey">Total Belanja:</span>
                                    <span class="text-sm font-bold text-brandDark">@rupiah($order->total_amount)</span>
                                </div>
                            </div>

                            <div class="flex items-center gap-2 flex-wrap sm:flex-nowrap justify-end">
                                @if($order->status === 'pending')
                                    {{-- Bayar Sekarang --}}
                                    <button type="button"
                                        data-order="{{ $order->order_number }}"
                                        data-resume-url="{{ route('orders.resume-payment', $order->order_number) }}"
                                        onclick="resumeOrderPayment(this)"
                                        class="px-3.5 py-2 bg-brand hover:bg-brandDark text-white font-semibold text-xs uppercase tracking-wider rounded-btn transition-colors duration-200 flex items-center justify-center gap-1.5 shadow-2xs cursor-pointer">
                                        <span class="material-symbols-outlined !text-[16px]">payments</span>
                                        <span class="btn-text">Bayar Sekarang</span>
                                    </button>

                                    {{-- Batalkan Pesanan --}}
                                    <form method="POST" action="{{ route('orders.cancel', $order->order_number) }}"
                                        onsubmit="return confirm('Apakah Anda yakin ingin membatalkan pesanan #{{ $order->order_number }}?')">
                                        @csrf
                                        <button type="submit"
                                            class="px-3 py-2 border border-rose-200 text-rose-600 hover:bg-rose-50 font-semibold text-xs uppercase tracking-wider rounded-btn transition-colors duration-200 flex items-center justify-center gap-1 cursor-pointer">
                                            <span class="material-symbols-outlined !text-[15px]">cancel</span>
                                            <span>Batalkan</span>
                                        </button>
                                    </form>
                                @endif

                                @if(in_array($order->status, ['dibatalkan', 'selesai']))
                                    {{-- Beli Lagi --}}
                                    <form method="POST" action="{{ route('orders.reorder', $order->order_number) }}">
                                        @csrf
                                        <button type="submit"
                                            class="px-3.5 py-2 bg-brand hover:bg-brandDark text-white font-semibold text-xs uppercase tracking-wider rounded-btn transition-colors duration-200 flex items-center justify-center gap-1.5 shadow-2xs cursor-pointer">
                                            <span class="material-symbols-outlined !text-[16px]">shopping_cart</span>
                                            <span>Beli Lagi</span>
                                        </button>
                                    </form>
                                @endif

                                <a href="{{ route('orders.show', $order->order_number) }}"
                                    class="px-3.5 py-2 border border-brand text-brand hover:bg-brand hover:text-white font-semibold text-xs uppercase tracking-wider rounded-btn transition-colors duration-200 text-center">
                                    Lihat Detail
                                </a>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Pagination --}}
            @if($orders->hasPages())
                <div class="mt-8 flex justify-center">
                    {{ $orders->links() }}
                </div>
            @endif
        @endif

    </section>
</div>
@endsection

@push('scripts')
{{-- Midtrans Snap JS --}}
@if(config('midtrans.is_production'))
    <script src="https://app.midtrans.com/snap/snap.js" data-client-key="{{ config('midtrans.client_key_production') }}"></script>
@else
    <script src="https://app.sandbox.midtrans.com/snap/snap.js" data-client-key="{{ config('midtrans.client_key_sandbox') }}"></script>
@endif

<script>
function orderTimer(expiryIsoString) {
    return {
        expiry: expiryIsoString ? new Date(expiryIsoString).getTime() : null,
        timeLeft: '24:00:00',
        isExpired: false,
        interval: null,

        initTimer() {
            if (!this.expiry) {
                this.timeLeft = '-';
                return;
            }
            this.update();
            this.interval = setInterval(() => this.update(), 1000);
        },

        update() {
            const now = new Date().getTime();
            const diff = this.expiry - now;

            if (diff <= 0) {
                this.timeLeft = 'Kedaluwarsa';
                this.isExpired = true;
                if (this.interval) clearInterval(this.interval);
                return;
            }

            const hours = Math.floor(diff / (1000 * 60 * 60));
            const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((diff % (1000 * 60)) / 1000);

            const hh = String(hours).padStart(2, '0');
            const mm = String(minutes).padStart(2, '0');
            const ss = String(seconds).padStart(2, '0');

            this.timeLeft = `${hh}:${mm}:${ss}`;
        }
    };
}

async function resumeOrderPayment(btn) {
    const resumeUrl = btn.dataset.resumeUrl;
    const defaultBtnHtml = btn.innerHTML;

    btn.disabled = true;
    btn.innerHTML = `<span class="inline-block w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full animate-spin"></span><span>Memproses...</span>`;

    try {
        const response = await fetch(resumeUrl, {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
            },
        });

        if (!response.ok) {
            throw new Error('Gagal memuat status pembayaran.');
        }

        const data = await response.json();

        if (data.expired) {
            btn.innerHTML = `<span>Kedaluwarsa</span>`;
            alert('Sesi pembayaran ini telah kedaluwarsa.');
            btn.disabled = false;
            btn.innerHTML = defaultBtnHtml;
            return;
        }

        btn.disabled = false;
        btn.innerHTML = defaultBtnHtml;

        window.snap.pay(data.snap_token, {
            onSuccess: (result) => {
                window.location.href = `{{ route('checkout.finish') }}?order_id=${result.order_id}&transaction_status=${result.transaction_status}`;
            },
            onPending: (result) => {
                // Halaman "Menunggu Pembayaran" hanya dipakai setelah checkout.
                // Dari halaman Pesanan Saya cukup tetap di halaman ini (tanpa popup).
                btn.disabled = false;
                btn.innerHTML = defaultBtnHtml;
            },
            onError: (result) => {
                console.error('Midtrans error:', result);
                window.location.href = '{{ route("checkout.error") }}';
            },
            onClose: () => {
                btn.disabled = false;
                btn.innerHTML = defaultBtnHtml;
            },
        });
    } catch (error) {
        console.error('Resume payment error:', error);
        alert('Terjadi kesalahan: ' + error.message);
        btn.disabled = false;
        btn.innerHTML = defaultBtnHtml;
    }
}
</script>
@endpush
