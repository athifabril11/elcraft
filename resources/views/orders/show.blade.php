@extends('layouts.storefront')

@section('title', 'Detail Pesanan ' . $order->order_number . ' | el Craft')
@section('meta_description', 'Detail pesanan ' . $order->order_number . ' dari el Craft.')

@section('content')
<div class="bg-white min-h-screen pb-24 md:pb-0">

    {{-- ─── HEADER ────────────────────────────────── --}}
    <x-page-header title="Detail Pesanan" :back-url="route('orders.index')">
        @if(in_array($order->status, ['dibatalkan', 'selesai']))
            <form method="POST" action="{{ route('orders.reorder', $order->order_number) }}">
                @csrf
                <button type="submit"
                    class="flex items-center gap-2 px-4 py-2.5 bg-brand hover:bg-brandDark text-white font-semibold text-xs uppercase tracking-wider rounded-btn transition-colors duration-200 shadow-2xs cursor-pointer">
                    <span class="material-symbols-outlined !text-[16px]">shopping_cart</span>
                    Beli Lagi
                </button>
            </form>
        @endif
    </x-page-header>

    @php
        $statusMap = [
            'pending'    => ['label' => 'Menunggu Pembayaran', 'color' => 'text-yellow-700 bg-yellow-50 border-yellow-200', 'icon' => 'schedule'],
            'paid'       => ['label' => 'Pembayaran Berhasil', 'color' => 'text-green-700 bg-green-50 border-green-200',   'icon' => 'check_circle'],
            'diproses'   => ['label' => 'Sedang Diproses',     'color' => 'text-purple-700 bg-purple-50 border-purple-200', 'icon' => 'inventory'],
            'dikirim'    => ['label' => 'Sedang Dikirim',      'color' => 'text-indigo-700 bg-indigo-50 border-indigo-200', 'icon' => 'local_shipping'],
            'selesai'    => ['label' => 'Pesanan Diterima',    'color' => 'text-green-700 bg-green-50 border-green-200',   'icon' => 'check_circle'],
            'dibatalkan' => ['label' => 'Dibatalkan',          'color' => 'text-red-700 bg-red-50 border-red-200',         'icon' => 'cancel'],
        ];
        $status = $statusMap[$order->status] ?? ['label' => ucfirst($order->status), 'color' => 'text-warmGrey bg-warmCream border-warmLightGrey', 'icon' => 'info'];
    @endphp

    {{-- Flash Messages --}}
    @if(session('success') || session('error'))
        <div class="max-w-[1280px] mx-auto px-5 md:px-8 lg:px-16 pt-6">
            @if(session('success'))
                <div x-data="{ show: true }" x-init="setTimeout(() => show = false, 4000)" x-show="show" x-transition.duration.300ms
                     class="bg-green-50 border border-green-200 text-green-800 text-xs sm:text-sm px-4 py-3 rounded-card flex items-center justify-between gap-3 shadow-2xs" role="alert">
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
                     class="bg-red-50 border border-red-200 text-red-800 text-xs sm:text-sm px-4 py-3 rounded-card flex items-center justify-between gap-3 shadow-2xs" role="alert">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <span class="material-symbols-outlined !text-[20px] text-red-600 shrink-0">error</span>
                        <span class="font-medium">{{ session('error') }}</span>
                    </div>
                    <button type="button" @click="show = false" class="text-red-600/70 hover:text-red-800 p-0.5 rounded cursor-pointer shrink-0" title="Tutup">
                        <span class="material-symbols-outlined !text-[18px]">close</span>
                    </button>
                </div>
            @endif
        </div>
    @endif

    <section class="max-w-[1280px] mx-auto px-5 md:px-8 lg:px-16 py-10">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

            {{-- ── LEFT: Order Items & Tracking ─────────────────── --}}
            <div class="lg:col-span-2 space-y-6">

                {{-- Order Info & Status Card --}}
                <div class="bg-white rounded-card border border-warmLightGrey p-5 space-y-4">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-4 border-b border-warmLightGrey/60">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full bg-warmCream flex items-center justify-center text-brand shrink-0">
                                <span class="material-symbols-outlined !text-[20px]">receipt_long</span>
                            </div>
                            <div>
                                <span class="text-[11px] font-semibold text-warmGrey uppercase tracking-wider">Nomor Pesanan</span>
                                <p class="text-sm md:text-base font-bold text-warmBlack font-mono tracking-wide text-brandDark">{{ $order->order_number }}</p>
                            </div>
                        </div>
                        <div class="text-left sm:text-right">
                            <span class="text-[11px] font-semibold text-warmGrey uppercase tracking-wider">Waktu Pemesanan</span>
                            <p class="text-xs font-medium text-warmBlack mt-0.5">{{ $order->created_at->format('d M Y, H:i') }} WIB</p>
                        </div>
                    </div>

                    {{-- Status Banner — disembunyikan untuk status pending, karena sudah
                         ditampilkan oleh kotak countdown "Menunggu Pembayaran" di bawah --}}
                    @if($order->status !== 'pending' || !$order->payment_expires_at)
                    <div class="flex items-center gap-3 p-3.5 rounded-card border {{ $status['color'] }}">
                        <span class="material-symbols-outlined !text-[20px]">{{ $status['icon'] }}</span>
                        <div class="flex-1 flex flex-col sm:flex-row sm:items-center justify-between gap-1">
                            <div>
                                <p class="font-semibold text-xs md:text-sm">{{ $status['label'] }}</p>
                                <p class="text-[11px] opacity-80">Terakhir diperbarui: {{ $order->updated_at->format('d M Y, H:i') }} WIB</p>
                            </div>
                        </div>
                    </div>
                    @endif

                    @if($order->status === 'pending' && $order->payment_expires_at && !$order->isPaymentExpired())
                        {{-- Strip batas waktu — satu-satunya tampilan status pending, dengan hitungan mundur --}}
                        <div x-data="orderTimer('{{ $order->payment_expires_at->toISOString() }}')" x-init="initTimer()"
                             class="rounded-card bg-amber-50 border border-amber-200 px-4 py-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:gap-6">

                            <div class="flex items-center gap-3 flex-1 min-w-0">
                                <span class="material-symbols-outlined !text-[26px] shrink-0"
                                      :class="isExpired ? 'text-red-500' : 'text-amber-500'" aria-hidden="true">schedule</span>
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold leading-snug"
                                       :class="isExpired ? 'text-red-700' : 'text-amber-950'"
                                       x-text="isExpired ? 'Pembayaran Kedaluwarsa' : 'Menunggu Pembayaran'">Menunggu Pembayaran</p>
                                    <p class="text-xs leading-snug mt-0.5"
                                       :class="isExpired ? 'text-red-600/90' : 'text-amber-700'">
                                        Batas waktu {{ $order->payment_expires_at->format('d M Y, H:i') }} WIB
                                    </p>
                                </div>
                            </div>

                            <div class="flex items-center justify-between gap-3 pt-3 border-t border-amber-200/70
                                        sm:justify-end sm:pt-0 sm:pl-6 sm:gap-4 sm:border-t-0 sm:border-l sm:border-amber-300/70">
                                <span class="text-[11px] font-semibold uppercase tracking-wide leading-none"
                                      :class="isExpired ? 'text-red-500' : 'text-amber-600'"
                                      x-text="isExpired ? 'Status' : 'Sisa Waktu'">Sisa Waktu</span>
                                <span x-text="timeLeft"
                                      class="font-mono font-bold leading-none tabular-nums"
                                      :class="isExpired ? 'text-sm text-red-700' : 'text-2xl text-amber-950'">--:--:--</span>
                            </div>
                        </div>
                    @elseif($order->status === 'pending' && $order->isPaymentExpired())
                        {{-- Batas waktu pembayaran sudah lewat --}}
                        <div class="rounded-card bg-red-50 border border-red-200 px-4 py-4 flex items-center gap-3">
                            <span class="material-symbols-outlined !text-[26px] text-red-500 shrink-0" aria-hidden="true">timer_off</span>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold leading-snug text-red-800">Pembayaran Kedaluwarsa</p>
                                <p class="text-xs leading-snug mt-0.5 text-red-700">
                                    Batas waktu pada <span class="font-semibold">{{ $order->payment_expires_at->format('d M Y, H:i') }} WIB</span> sudah berakhir. Pesanan ini akan dibatalkan otomatis.
                                </p>
                            </div>
                        </div>
                    @endif
                </div>

                {{-- Order Items Card --}}
                <div class="bg-white rounded-card border border-warmLightGrey overflow-hidden">
                    <div class="px-5 py-4 border-b border-warmLightGrey/60">
                        <h2 class="text-sm font-semibold text-warmBlack flex items-center gap-2">
                            <span class="material-symbols-outlined !text-[18px] text-brand">shopping_bag</span>
                            Produk Dipesan ({{ $order->items->count() }} item)
                        </h2>
                    </div>
                    <div class="divide-y divide-warmLightGrey/50">
                        @foreach($order->items as $item)
                            <div class="flex items-center gap-4 px-5 py-4">
                                {{-- Thumbnail --}}
                                <div class="w-16 h-16 rounded-img bg-warmCream flex-shrink-0 overflow-hidden border border-warmLightGrey/50">
                                    @if($item->product && $item->product->primary_image)
                                        <a href="/products/{{ $item->product->slug }}" class="block w-full h-full">
                                            <img src="{{ $item->product->primary_image }}" alt="{{ $item->product_name }}" class="w-full h-full object-cover">
                                        </a>
                                    @else
                                        <div class="w-full h-full flex items-center justify-center">
                                            <span class="material-symbols-outlined text-warmGrey !text-[24px]">image</span>
                                        </div>
                                    @endif
                                </div>
                                {{-- Details --}}
                                <div class="flex-1 min-w-0">
                                    @if($item->product)
                                        <a href="/products/{{ $item->product->slug }}" class="text-sm font-medium text-warmBlack hover:text-brand truncate block transition-colors">{{ $item->product_name }}</a>
                                    @else
                                        <p class="text-sm font-medium text-warmBlack truncate">{{ $item->product_name }}</p>
                                    @endif
                                    @if($item->variant_name)
                                        <p class="text-xs text-warmGrey">Varian: {{ $item->variant_name }}</p>
                                    @endif
                                    <p class="text-xs text-warmGrey mt-0.5">@rupiah($item->price) × {{ $item->quantity }}</p>
                                    
                                    @if($order->status === 'selesai')
                                        @php
                                            $review = \App\Models\Review::where('order_item_id', $item->id)->first();
                                        @endphp
                                        <div class="mt-2">
                                            @if($review)
                                                <div class="flex items-center space-x-1">
                                                    <div class="flex text-amber-400">
                                                        @for($i = 1; $i <= 5; $i++)
                                                            <span class="material-symbols-outlined !text-[14px]" style="font-variation-settings: 'FILL' {{ $i <= $review->rating ? '1' : '0' }}, 'wght' 400, 'GRAD' 0, 'opsz' 24">star</span>
                                                        @endfor
                                                    </div>
                                                    <span class="text-[10px] text-warmGrey">({{ $review->is_approved ? 'Ulasan Disetujui' : 'Menunggu Moderasi' }})</span>
                                                </div>
                                            @else
                                                <button
                                                    @click="$dispatch('open-review-modal', { orderItemId: {{ $item->id }}, productName: '{{ addslashes($item->product_name) }}' })"
                                                    class="px-3 py-1 bg-brand hover:bg-brandDark text-white text-[10px] font-semibold uppercase tracking-wider rounded-[4px] transition-colors duration-200">
                                                    Tulis Ulasan
                                                </button>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                                {{-- Subtotal --}}
                                <p class="text-sm font-semibold text-warmBlack flex-shrink-0">@rupiah($item->subtotal)</p>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Shipment Tracking Card --}}
                @if($order->shipment)
                    <div class="bg-white rounded-card border border-warmLightGrey p-5">
                        <h2 class="text-sm font-semibold text-warmBlack flex items-center gap-2 mb-4">
                            <span class="material-symbols-outlined !text-[18px] text-brand">local_shipping</span>
                            Informasi Pengiriman
                        </h2>
                        <div class="grid grid-cols-2 gap-4 text-xs">
                            <div>
                                <p class="text-warmGrey">Kurir</p>
                                <p class="text-warmBlack font-medium mt-0.5">{{ $order->shipment->courier ?? '-' }} ({{ $order->shipment->service ?? '-' }})</p>
                            </div>
                            @if($order->shipment->tracking_number)
                                <div>
                                    <p class="text-warmGrey">Nomor Resi</p>
                                    <p class="text-warmBlack font-medium font-mono mt-0.5">{{ $order->shipment->tracking_number }}</p>
                                </div>
                            @endif
                            @if($order->shipment->shipped_at)
                                <div>
                                    <p class="text-warmGrey">Tanggal Kirim</p>
                                    <p class="text-warmBlack font-medium mt-0.5">{{ \Carbon\Carbon::parse($order->shipment->shipped_at)->format('d M Y') }}</p>
                                </div>
                            @endif
                            @if($order->shipment->delivered_at)
                                <div>
                                    <p class="text-warmGrey">Tanggal Diterima</p>
                                    <p class="text-warmBlack font-medium mt-0.5">{{ \Carbon\Carbon::parse($order->shipment->delivered_at)->format('d M Y') }}</p>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif

            </div>

            {{-- ── RIGHT: Summary Panel ──────────────────────────── --}}
            <div class="space-y-5">

                {{-- Order Summary --}}
                <div class="bg-white rounded-card border border-warmLightGrey p-5">
                    <h2 class="text-sm font-semibold text-warmBlack mb-4 flex items-center gap-2">
                        <span class="material-symbols-outlined !text-[18px] text-brand">summarize</span>
                        Ringkasan Pembayaran
                    </h2>
                    <div class="space-y-2.5 text-xs">
                        <div class="flex justify-between text-warmGrey">
                            <span>Subtotal Produk</span>
                            <span>@rupiah($order->subtotal)</span>
                        </div>
                        @if($order->voucher_discount > 0)
                            <div class="flex justify-between text-green-600">
                                <span>Diskon Voucher {{ $order->voucher?->code ? '(' . $order->voucher->code . ')' : '' }}</span>
                                <span>- @rupiah($order->voucher_discount)</span>
                            </div>
                        @endif
                        <div class="flex justify-between text-warmGrey">
                            <span>Ongkos Kirim</span>
                            @if($order->shipping_cost > 0)
                                <span>@rupiah($order->shipping_cost)</span>
                            @else
                                <span class="text-green-600">Gratis</span>
                            @endif
                        </div>
                        <div class="border-t border-warmLightGrey pt-2.5 flex justify-between text-warmBlack font-bold text-sm">
                            <span>Total</span>
                            <span class="text-brandDark">@rupiah($order->total_amount)</span>
                        </div>
                    </div>
                </div>

                {{-- Payment Info --}}
                @if($order->payment)
                    <div class="bg-white rounded-card border border-warmLightGrey p-5">
                        <h2 class="text-sm font-semibold text-warmBlack mb-4 flex items-center gap-2">
                            <span class="material-symbols-outlined !text-[18px] text-brand">payments</span>
                            Informasi Pembayaran
                        </h2>
                        <div class="space-y-2 text-xs">
                            <div class="flex justify-between">
                                <span class="text-warmGrey">Metode</span>
                                <span class="text-warmBlack font-medium">{{ $order->payment->formatted_method }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-warmGrey">Status</span>
                                <span class="font-semibold capitalize
                                    {{ $order->payment->status === 'success' ? 'text-green-600' :
                                       ($order->payment->status === 'failed' ? 'text-red-500' : 'text-yellow-600') }}">
                                    {{ match($order->payment->status) {
                                        'success' => 'Berhasil',
                                        'failed'  => 'Gagal',
                                        default   => 'Menunggu Pembayaran'
                                    } }}
                                </span>
                            </div>
                            @if($order->payment->paid_at)
                                <div class="flex justify-between">
                                    <span class="text-warmGrey">Dibayar pada</span>
                                    <span class="text-warmBlack">{{ \Carbon\Carbon::parse($order->payment->paid_at)->format('d M Y, H:i') }}</span>
                                </div>
                            @elseif($order->status === 'pending' && $order->payment_expires_at)
                                <div class="flex justify-between text-amber-800">
                                    <span>Batas Waktu</span>
                                    <span class="font-medium font-mono">{{ $order->payment_expires_at->format('d M Y, H:i') }} WIB</span>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif

                {{-- Shipping Address & Courier --}}
                @if($order->address)
                    <div class="bg-white rounded-card border border-warmLightGrey p-5 space-y-3">
                        <div class="flex items-center justify-between">
                            <h2 class="text-sm font-semibold text-warmBlack flex items-center gap-2">
                                <span class="material-symbols-outlined !text-[18px] text-brand">location_on</span>
                                Alamat Pengiriman
                            </h2>
                            <span class="inline-flex items-center gap-1 text-[11px] font-bold px-2.5 py-0.5 rounded-full bg-amber-50 text-amber-800 border border-amber-200">
                                <span class="material-symbols-outlined !text-[14px]">local_shipping</span>
                                {{ $order->formatted_courier }}
                            </span>
                        </div>
                        <div class="text-xs space-y-1 text-warmGrey">
                            <p class="text-warmBlack font-medium">{{ $order->address->recipient_name }}</p>
                            <p>{{ $order->address->phone }}</p>
                            <p class="leading-relaxed">
                                {{ $order->address->full_address }},
                                {{ $order->address->city }},
                                {{ $order->address->province }}
                                {{ $order->address->postal_code }}
                            </p>
                        </div>
                    </div>
                @endif

                {{-- Actions --}}
                <div class="space-y-3">
                    @if($order->status === 'pending' && !$order->isPaymentExpired())
                        {{-- Bayar Sekarang --}}
                        <button type="button"
                            data-order="{{ $order->order_number }}"
                            data-resume-url="{{ route('orders.resume-payment', $order->order_number) }}"
                            onclick="resumeOrderPayment(this)"
                            class="w-full flex items-center justify-center gap-2 py-3 bg-brand hover:bg-brandDark text-white font-semibold text-xs uppercase tracking-wider rounded-btn transition-colors duration-200 shadow-2xs cursor-pointer">
                            <span class="material-symbols-outlined !text-[18px]">payments</span>
                            <span>Bayar Sekarang</span>
                        </button>

                        {{-- Batalkan Pesanan --}}
                        <form method="POST" action="{{ route('orders.cancel', $order->order_number) }}"
                            onsubmit="return confirm('Apakah Anda yakin ingin membatalkan pesanan ini?')">
                            @csrf
                            <button type="submit"
                                class="w-full flex items-center justify-center gap-2 py-2.5 border border-rose-300 text-rose-600 hover:bg-rose-50 font-semibold text-xs uppercase tracking-wider rounded-btn transition-colors duration-200 cursor-pointer">
                                <span class="material-symbols-outlined !text-[16px]">cancel</span>
                                <span>Batalkan Pesanan</span>
                            </button>
                        </form>
                    @endif

                    @if(in_array($order->status, ['dibatalkan', 'selesai']))
                        <form method="POST" action="{{ route('orders.reorder', $order->order_number) }}">
                            @csrf
                            <button type="submit"
                                class="w-full flex items-center justify-center gap-2 py-3 bg-brand hover:bg-brandDark text-white font-semibold text-xs uppercase tracking-wider rounded-btn transition-colors duration-200 shadow-2xs cursor-pointer">
                                <span class="material-symbols-outlined !text-[18px]">shopping_cart</span>
                                <span>Beli Lagi</span>
                            </button>
                        </form>
                    @endif

                    @if($order->status === 'dikirim')
                        <form method="POST" action="{{ route('orders.confirm-received', $order->order_number) }}"
                            onsubmit="return confirm('Konfirmasi bahwa pesanan sudah Anda terima?')">
                            @csrf
                            @method('PATCH')
                            <button type="submit"
                                class="block w-full text-center py-3 bg-brand hover:bg-brandDark text-white font-semibold text-xs uppercase tracking-wider rounded-btn transition-colors duration-200">
                                Konfirmasi Pesanan Diterima
                            </button>
                        </form>
                    @endif

                    {{-- Ajukan Retur — only for dikirim/selesai AND no existing return --}}
                    @if(in_array($order->status, ['dikirim', 'selesai']) && !$order->returnRequest)
                        <a href="{{ route('orders.returns.create', $order->order_number) }}"
                           id="btn-ajukan-retur"
                           class="flex items-center justify-center gap-2 w-full text-center py-3 bg-rose-500 hover:bg-rose-600 text-white font-semibold text-xs uppercase tracking-wider rounded-btn transition-colors duration-200">
                            <span class="material-symbols-outlined !text-[16px]">assignment_return</span>
                            Ajukan Retur
                        </a>
                    @elseif($order->returnRequest)
                        <a href="{{ route('orders.returns.show', $order->order_number) }}"
                           class="flex items-center justify-center gap-2 w-full text-center py-3 border border-rose-300 text-rose-600 hover:bg-rose-50 font-semibold text-xs uppercase tracking-wider rounded-btn transition-colors duration-200">
                            <span class="material-symbols-outlined !text-[16px]">assignment_return</span>
                            Lihat Detail Retur
                        </a>
                    @endif

                    <a href="{{ route('orders.index') }}"
                        class="block w-full text-center py-3 border border-brand text-brand hover:bg-warmCream font-semibold text-xs uppercase tracking-wider rounded-btn transition-colors duration-200">
                        Kembali ke Pesanan
                    </a>
                </div>

            </div>
        </div>
    </section>

    {{-- ─── MODAL TULIS ULASAN ────────────────────────── --}}
    <div x-data="{
            open: false,
            orderItemId: null,
            productName: '',
            rating: 5,
            comment: '',
            imagePreview: null,
            hoverRating: 0,
            
            handleImageChange(e) {
                const file = e.target.files[0];
                if (file) {
                    if (file.size > 2 * 1024 * 1024) {
                        alert('Ukuran file maksimal adalah 2MB');
                        e.target.value = '';
                        this.imagePreview = null;
                        return;
                    }
                    const reader = new FileReader();
                    reader.onload = (event) => {
                        this.imagePreview = event.target.result;
                    };
                    reader.readAsDataURL(file);
                } else {
                    this.imagePreview = null;
                }
            }
         }"
         @open-review-modal.window="open = true; orderItemId = $event.detail.orderItemId; productName = $event.detail.productName; rating = 5; comment = ''; imagePreview = null; hoverRating = 0;"
         x-show="open"
         x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-warmBlack/40 backdrop-blur-xs transition-opacity duration-300"
         style="display: none;">
         
        <div @click.outside="open = false"
             class="bg-white w-full max-w-md rounded-card border border-warmLightGrey shadow-lg overflow-hidden transform transition-all duration-300 flex flex-col">
             
            {{-- Header --}}
            <div class="px-5 py-4 border-b border-warmLightGrey/60 flex items-center justify-between bg-warmCream">
                <h3 class="font-semibold text-sm text-warmBlack">Tulis Ulasan Produk</h3>
                <button @click="open = false" class="text-warmGrey hover:text-warmBlack flex items-center justify-center p-1">
                    <span class="material-symbols-outlined !text-[20px]">close</span>
                </button>
            </div>
            
            {{-- Form --}}
            <form action="{{ route('reviews.store') }}" method="POST" enctype="multipart/form-data" class="p-5 space-y-4">
                @csrf
                <input type="hidden" name="order_item_id" :value="orderItemId">
                
                {{-- Product Name Display --}}
                <div>
                    <span class="text-[10px] uppercase font-semibold text-warmGrey tracking-wider">Produk</span>
                    <p class="text-xs font-semibold text-warmBlack mt-0.5" x-text="productName"></p>
                </div>
                
                {{-- Star Rating --}}
                <div>
                    <span class="text-[10px] uppercase font-semibold text-warmGrey tracking-wider block mb-1">Rating</span>
                    <div class="flex items-center space-x-1">
                        <input type="hidden" name="rating" :value="rating">
                        @for($i = 1; $i <= 5; $i++)
                            <button type="button"
                                    @click="rating = {{ $i }}"
                                    @mouseenter="hoverRating = {{ $i }}"
                                    @mouseleave="hoverRating = 0"
                                    class="text-amber-400 focus:outline-none transition-transform duration-150 hover:scale-110">
                                <span class="material-symbols-outlined !text-[28px]"
                                      :style="'font-variation-settings: \'FILL\' ' + ((hoverRating || rating) >= {{ $i }} ? '1' : '0') + ', \'wght\' 400, \'GRAD\' 0, \'opsz\' 24'">
                                    star
                                </span>
                            </button>
                        @endfor
                        <span class="text-xs text-warmGrey ml-2 font-medium"
                              x-text="rating === 5 ? 'Sangat Puas' : (rating === 4 ? 'Puas' : (rating === 3 ? 'Cukup' : (rating === 2 ? 'Kurang' : 'Sangat Kurang')))"></span>
                    </div>
                </div>
                
                {{-- Comment --}}
                <div>
                    <div class="flex justify-between items-center mb-1">
                        <label for="comment" class="text-[10px] uppercase font-semibold text-warmGrey tracking-wider">Ulasan</label>
                        <span class="text-[10px] text-warmGrey/60" x-text="(1000 - comment.length) + ' karakter tersisa'"></span>
                    </div>
                    <textarea id="comment"
                              name="comment"
                              rows="4"
                              x-model="comment"
                              maxlength="1000"
                              placeholder="Bagikan pengalaman Anda menggunakan produk ini..."
                              class="w-full text-xs border border-warmLightGrey rounded-[6px] p-3 focus:border-brand focus:ring-1 focus:ring-brand/30 outline-none resize-none font-sans placeholder-warmGrey/40 leading-relaxed"></textarea>
                </div>
                
                {{-- Image Upload --}}
                <div>
                    <label class="text-[10px] uppercase font-semibold text-warmGrey tracking-wider block mb-1">Foto Produk (Opsional)</label>
                    <div class="flex items-center space-x-4">
                        {{-- Custom upload button --}}
                        <label class="flex flex-col items-center justify-center w-16 h-16 border border-dashed border-warmGrey/40 rounded-[6px] cursor-pointer hover:border-brand hover:bg-warmCream/20 transition-all duration-200">
                            <span class="material-symbols-outlined text-warmGrey !text-[20px]">add_a_photo</span>
                            <span class="text-[8px] text-warmGrey font-medium mt-1">Pilih Foto</span>
                            <input type="file" name="image" accept="image/*" class="hidden" @change="handleImageChange">
                        </label>
                        
                        {{-- Preview --}}
                        <div x-show="imagePreview" class="relative w-16 h-16 rounded-[6px] overflow-hidden border border-warmLightGrey" x-cloak>
                            <img :src="imagePreview" class="w-full h-full object-cover">
                            <button type="button" @click="imagePreview = null; $el.closest('form').querySelector('input[type=file]').value = ''"
                                    class="absolute -top-1 -right-1 bg-warmBlack/70 text-white rounded-full p-0.5 flex items-center justify-center hover:bg-red-500 transition-colors">
                                <span class="material-symbols-outlined !text-[12px]">close</span>
                            </button>
                        </div>
                    </div>
                    <p class="text-[9px] text-warmGrey mt-1 leading-normal">Maksimal 2MB (format: JPG, JPEG, PNG, WEBP).</p>
                </div>
                
                {{-- Submit Button --}}
                <div class="flex items-center justify-end space-x-3 pt-2">
                    <button type="button" @click="open = false"
                            class="px-4 py-2 border border-warmLightGrey text-warmGrey text-xs font-semibold uppercase tracking-wider rounded-btn hover:bg-warmCream/40 transition-colors duration-200">
                        Batal
                    </button>
                    <button type="submit"
                            class="px-4 py-2 bg-brand hover:bg-brandDark text-white text-xs font-semibold uppercase tracking-wider rounded-btn transition-colors duration-200">
                        Kirim Ulasan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Popup Notifikasi: Menunggu Pembayaran --}}
<div id="pending-payment-modal"
     class="fixed inset-0 z-[200] hidden items-center justify-center px-5 bg-warmBlack/50 backdrop-blur-sm">
    <div class="bg-white rounded-card border border-warmLightGrey p-8 max-w-sm w-full text-center shadow-lg relative">
        <button type="button" onclick="closePendingPaymentModal()"
                aria-label="Tutup"
                class="absolute top-3 right-3 w-8 h-8 flex items-center justify-center rounded-full text-warmGrey hover:bg-warmCream hover:text-warmBlack transition-colors cursor-pointer">
            <span class="material-symbols-outlined !text-[20px]">close</span>
        </button>

        <div class="w-16 h-16 rounded-full bg-amber-50 border border-amber-200 flex items-center justify-center mx-auto mb-5">
            <span class="material-symbols-outlined text-amber-600 !text-[34px]">schedule</span>
        </div>
        <h3 class="text-lg font-semibold text-warmBlack mb-2">Menunggu Pembayaran</h3>
        <p class="text-sm text-warmGrey leading-relaxed mb-6">
            Pesanan Anda sudah dibuat dan masih menunggu pembayaran.
            Silakan selesaikan pembayaran agar pesanan segera diproses.
        </p>

        <button type="button" onclick="closePendingPaymentModal()"
                class="w-full py-2.5 bg-brand hover:bg-brandDark text-white font-semibold text-sm rounded-btn transition-colors duration-200 cursor-pointer">
            Kembali ke Beranda
        </button>
    </div>
</div>
@endsection

@push('scripts')
{{-- Midtrans Snap JS — diperlukan untuk membuka popup pembayaran --}}
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

/**
 * Popup notifikasi "Menunggu Pembayaran" — menggantikan redirect ke halaman
 * "Menunggu Pembayaran" yang sebelumnya terjadi saat Snap ditutup/di-pending
 * dari halaman detail pesanan. Tombol X / close membawa user kembali ke beranda.
 */
function showPendingPaymentModal() {
    const modal = document.getElementById('pending-payment-modal');
    if (!modal) return;
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function closePendingPaymentModal() {
    window.location.href = '{{ url('/') }}';
}

/**
 * resumeOrderPayment — dipanggil saat tombol pembayaran diklik.
 *
 * 1. Fetch endpoint resume-payment untuk mendapatkan snap_token.
 * 2. Jika expired, tampilkan pesan dan redirect ke /checkout setelah 1.5 detik.
 * 3. Jika masih valid, buka popup Midtrans Snap dengan handler yang sama
 *    seperti di halaman checkout.
 */
async function resumeOrderPayment(btn) {
    const resumeUrl = btn.dataset.resumeUrl;
    const defaultBtnHtml = btn.innerHTML;

    btn.disabled = true;
    btn.innerHTML = `<span class="inline-block w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span><span>Memproses...</span>`;

    try {
        const response = await fetch(resumeUrl, {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
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

        // Pulihkan tombol sebelum popup Midtrans Snap dibuka agar tidak stuck di state loading
        btn.disabled = false;
        btn.innerHTML = defaultBtnHtml;

        // Token masih valid — buka popup Midtrans Snap
        window.snap.pay(data.snap_token, {
            onSuccess: (result) => {
                window.location.href = `{{ route('checkout.finish') }}?order_id=${result.order_id}&transaction_status=${result.transaction_status}`;
            },
            onPending: (result) => {
                // Dari halaman detail: tampilkan popup notifikasi, jangan redirect
                // ke halaman "Menunggu Pembayaran" (halaman itu hanya untuk alur checkout).
                showPendingPaymentModal();
            },
            onError: (result) => {
                console.error('Midtrans error:', result);
                window.location.href = '{{ route("checkout.error") }}';
            },
            onClose: () => {
                // Popup Snap ditutup tanpa pembayaran sukses — tampilkan notifikasi
                // "Menunggu Pembayaran" dengan tombol close yang kembali ke beranda.
                btn.disabled = false;
                btn.innerHTML = defaultBtnHtml;
                showPendingPaymentModal();
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
