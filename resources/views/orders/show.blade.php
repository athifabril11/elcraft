@extends('layouts.storefront')

@section('title', 'Detail Pesanan ' . $order->order_number . ' | el Craft')
@section('meta_description', 'Detail pesanan ' . $order->order_number . ' dari el Craft.')

@section('content')
<div class="bg-warmCream min-h-screen pb-24 md:pb-0">

    {{-- ─── HEADER ────────────────────────────────── --}}
    <section class="bg-white border-b border-warmLightGrey py-10 px-5 md:px-8 lg:px-16">
        <div class="max-w-[1280px] mx-auto">
            <nav class="flex items-center space-x-2 text-xs text-warmGrey mb-2" aria-label="Breadcrumb">
                <a href="/" class="hover:text-brand transition-colors">Beranda</a>
                <span class="material-symbols-outlined !text-[12px]">chevron_right</span>
                <a href="{{ route('orders.index') }}" class="hover:text-brand transition-colors">Riwayat Pesanan</a>
                <span class="material-symbols-outlined !text-[12px]">chevron_right</span>
                <span class="text-warmBlack font-medium font-mono">{{ $order->order_number }}</span>
            </nav>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <h1 class="text-2xl md:text-3xl font-semibold text-warmBlack font-sans">Detail Pesanan</h1>

                {{-- Cancel Button --}}
                @if($order->status === 'pending')
                    <form method="POST" action="{{ route('orders.cancel', $order->order_number) }}"
                        onsubmit="return confirm('Batalkan pesanan ini? Tindakan ini tidak dapat dibatalkan.')">
                        @csrf
                        <button type="submit"
                            class="flex items-center gap-2 px-4 py-2.5 border border-red-300 text-red-500 hover:bg-red-50 font-semibold text-xs uppercase tracking-wider rounded-btn transition-colors duration-200">
                            <span class="material-symbols-outlined !text-[16px]">cancel</span>
                            Batalkan Pesanan
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </section>

    @php
        $statusMap = [
            'pending'    => ['label' => 'Menunggu Pembayaran', 'color' => 'text-yellow-700 bg-yellow-50 border-yellow-200', 'icon' => 'schedule'],
            'paid'       => ['label' => 'Pembayaran Berhasil', 'color' => 'text-blue-700 bg-blue-50 border-blue-200',   'icon' => 'payments'],
            'processing' => ['label' => 'Sedang Diproses',     'color' => 'text-purple-700 bg-purple-50 border-purple-200', 'icon' => 'inventory'],
            'shipped'    => ['label' => 'Sedang Dikirim',      'color' => 'text-indigo-700 bg-indigo-50 border-indigo-200', 'icon' => 'local_shipping'],
            'delivered'  => ['label' => 'Pesanan Diterima',    'color' => 'text-green-700 bg-green-50 border-green-200', 'icon' => 'check_circle'],
            'dibatalkan' => ['label' => 'Dibatalkan',          'color' => 'text-red-700 bg-red-50 border-red-200',    'icon' => 'cancel'],
        ];
        $status = $statusMap[$order->status] ?? ['label' => ucfirst($order->status), 'color' => 'text-warmGrey bg-warmCream border-warmLightGrey', 'icon' => 'info'];
    @endphp

    {{-- Flash Messages --}}
    @if(session('success') || session('error'))
        <div class="max-w-[1280px] mx-auto px-5 md:px-8 lg:px-16 pt-6">
            @if(session('success'))
                <div class="bg-green-50 border border-green-200 text-green-700 text-sm px-4 py-3 rounded-card flex items-center space-x-3">
                    <span class="material-symbols-outlined !text-[18px]">check_circle</span>
                    <span>{{ session('success') }}</span>
                </div>
            @endif
            @if(session('error'))
                <div class="bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3 rounded-card flex items-center space-x-3">
                    <span class="material-symbols-outlined !text-[18px]">error</span>
                    <span>{{ session('error') }}</span>
                </div>
            @endif
        </div>
    @endif

    <section class="max-w-[1280px] mx-auto px-5 md:px-8 lg:px-16 py-10">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

            {{-- ── LEFT: Order Items & Tracking ─────────────────── --}}
            <div class="lg:col-span-2 space-y-6">

                {{-- Status Banner --}}
                <div class="flex items-center gap-3 p-4 rounded-card border {{ $status['color'] }}">
                    <span class="material-symbols-outlined !text-[22px]">{{ $status['icon'] }}</span>
                    <div>
                        <p class="font-semibold text-sm">{{ $status['label'] }}</p>
                        <p class="text-[11px] opacity-80">Diperbarui: {{ $order->updated_at->format('d M Y, H:i') }} WIB</p>
                    </div>
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
                                        <img src="{{ $item->product->primary_image }}" alt="{{ $item->product_name }}" class="w-full h-full object-cover">
                                    @else
                                        <div class="w-full h-full flex items-center justify-center">
                                            <span class="material-symbols-outlined text-warmGrey !text-[24px]">image</span>
                                        </div>
                                    @endif
                                </div>
                                {{-- Details --}}
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-warmBlack truncate">{{ $item->product_name }}</p>
                                    @if($item->variant_name)
                                        <p class="text-xs text-warmGrey">Varian: {{ $item->variant_name }}</p>
                                    @endif
                                    <p class="text-xs text-warmGrey mt-0.5">@rupiah($item->price) × {{ $item->quantity }}</p>
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
                                <span class="text-warmBlack font-medium capitalize">{{ $order->payment->payment_method ?? 'Belum dipilih' }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-warmGrey">Status</span>
                                <span class="font-semibold capitalize
                                    {{ $order->payment->status === 'success' ? 'text-green-600' :
                                       ($order->payment->status === 'failed' ? 'text-red-500' : 'text-yellow-600') }}">
                                    {{ match($order->payment->status) {
                                        'success' => 'Berhasil',
                                        'failed'  => 'Gagal',
                                        default   => 'Menunggu'
                                    } }}
                                </span>
                            </div>
                            @if($order->payment->paid_at)
                                <div class="flex justify-between">
                                    <span class="text-warmGrey">Dibayar pada</span>
                                    <span class="text-warmBlack">{{ \Carbon\Carbon::parse($order->payment->paid_at)->format('d M Y, H:i') }}</span>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif

                {{-- Shipping Address --}}
                @if($order->address)
                    <div class="bg-white rounded-card border border-warmLightGrey p-5">
                        <h2 class="text-sm font-semibold text-warmBlack mb-3 flex items-center gap-2">
                            <span class="material-symbols-outlined !text-[18px] text-brand">location_on</span>
                            Alamat Pengiriman
                        </h2>
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
                    <a href="{{ route('orders.index') }}"
                        class="block w-full text-center py-3 border border-brand text-brand hover:bg-warmCream font-semibold text-xs uppercase tracking-wider rounded-btn transition-colors duration-200">
                        Kembali ke Riwayat
                    </a>
                    @if($order->status === 'delivered')
                        <a href="/products"
                            class="block w-full text-center py-3 bg-brand hover:bg-brandDark text-white font-semibold text-xs uppercase tracking-wider rounded-btn transition-colors duration-200">
                            Belanja Lagi
                        </a>
                    @endif
                </div>

            </div>
        </div>
    </section>
</div>
@endsection
