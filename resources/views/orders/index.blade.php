@extends('layouts.storefront')

@section('title', 'Riwayat Pesanan | el Craft')
@section('meta_description', 'Lihat riwayat pembelian aksesoris wanita premium Anda di el Craft.')

@section('content')
<div class="bg-warmCream min-h-screen pb-24 md:pb-0">

    {{-- ─── HEADER ────────────────────────────────── --}}
    <section class="bg-white border-b border-warmLightGrey py-10 px-5 md:px-8 lg:px-16">
        <div class="max-w-[1280px] mx-auto">
            <nav class="flex items-center space-x-2 text-xs text-warmGrey mb-2" aria-label="Breadcrumb">
                <a href="/" class="hover:text-brand transition-colors">Beranda</a>
                <span class="material-symbols-outlined !text-[12px]">chevron_right</span>
                <span class="text-warmBlack font-medium">Riwayat Pesanan</span>
            </nav>
            <h1 class="text-2xl md:text-3xl font-semibold text-warmBlack font-sans">Riwayat Pesanan</h1>
        </div>
    </section>

    <section class="max-w-[1280px] mx-auto px-5 md:px-8 lg:px-16 py-10">

        @if($orders->isEmpty())
            {{-- Empty State --}}
            <div class="flex flex-col items-center justify-center py-20 text-center bg-white rounded-card border border-warmLightGrey">
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
                            'paid'       => ['label' => 'Pembayaran Berhasil', 'color' => 'text-blue-700 bg-blue-50 border-blue-200'],
                            'processing' => ['label' => 'Sedang Diproses',     'color' => 'text-purple-700 bg-purple-50 border-purple-200'],
                            'shipped'    => ['label' => 'Sedang Dikirim',      'color' => 'text-indigo-700 bg-indigo-50 border-indigo-200'],
                            'delivered'  => ['label' => 'Pesanan Diterima',    'color' => 'text-green-700 bg-green-50 border-green-200'],
                            'dibatalkan' => ['label' => 'Dibatalkan',          'color' => 'text-red-700 bg-red-50 border-red-200'],
                        ];
                        $status = $statusMap[$order->status] ?? ['label' => ucfirst($order->status), 'color' => 'text-warmGrey bg-warmCream border-warmLightGrey'];
                    @endphp

                    <div class="bg-white rounded-card border border-warmLightGrey hover:border-brand/30 hover:shadow-sm transition-all duration-200">
                        {{-- Order Header --}}
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 px-5 py-4 border-b border-warmLightGrey/60">
                            <div class="flex items-center gap-3">
                                <span class="material-symbols-outlined text-brand !text-[20px]">receipt_long</span>
                                <div>
                                    <p class="text-xs font-semibold text-warmBlack font-mono tracking-wide">{{ $order->order_number }}</p>
                                    <p class="text-[11px] text-warmGrey mt-0.5">{{ $order->created_at->format('d M Y, H:i') }} WIB</p>
                                </div>
                            </div>
                            <span class="inline-flex items-center text-[11px] font-semibold px-2.5 py-1 rounded-full border {{ $status['color'] }}">
                                {{ $status['label'] }}
                            </span>
                        </div>

                        {{-- Order Items Preview --}}
                        <div class="px-5 py-4">
                            <div class="flex flex-col gap-2">
                                @foreach($order->items->take(2) as $item)
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-img bg-warmCream flex-shrink-0 overflow-hidden border border-warmLightGrey/50">
                                            @if($item->product && $item->product->primary_image)
                                                <img src="{{ $item->product->primary_image }}" alt="{{ $item->product_name }}" class="w-full h-full object-cover">
                                            @else
                                                <span class="material-symbols-outlined text-warmGrey w-full h-full flex items-center justify-center !text-[18px]">image</span>
                                            @endif
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-xs text-warmBlack font-medium truncate">{{ $item->product_name }}</p>
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
                            <div>
                                <p class="text-[11px] text-warmGrey">Total Pesanan</p>
                                <p class="text-sm font-bold text-brandDark">@rupiah($order->total_amount)</p>
                            </div>
                            <a href="{{ route('orders.show', $order->order_number) }}"
                                class="px-4 py-2 border border-brand text-brand hover:bg-brand hover:text-white font-semibold text-xs uppercase tracking-wider rounded-btn transition-colors duration-200">
                                Lihat Detail
                            </a>
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
