@extends('layouts.storefront')

@section('title', 'Pembayaran Berhasil | el Craft')

@section('content')
<div class="min-h-screen bg-warmCream flex items-center justify-center px-5 py-20">
    <div class="bg-white rounded-card border border-warmLightGrey p-10 max-w-md w-full text-center shadow-sm">

        @if($status === 'settlement' || $status === 'capture')
            {{-- Berhasil --}}
            <div class="w-16 h-16 rounded-full bg-emerald-50 border border-emerald-200 flex items-center justify-center mx-auto mb-6">
                <span class="material-symbols-outlined text-emerald-600 !text-[36px]" style="font-variation-settings:'FILL' 1,'wght' 400,'GRAD' 0,'opsz' 24">check_circle</span>
            </div>
            <h1 class="text-2xl font-semibold text-warmBlack mb-2">Pembayaran Berhasil!</h1>
            <p class="text-sm text-warmGrey mb-2 leading-relaxed">
                Terima kasih atas pesanan Anda. Kami akan segera memproses dan mengirimkan produk Anda.
            </p>
        @elseif($status === 'pending')
            {{-- Menunggu --}}
            <div class="w-16 h-16 rounded-full bg-amber-50 border border-amber-200 flex items-center justify-center mx-auto mb-6">
                <span class="material-symbols-outlined text-amber-600 !text-[36px]">schedule</span>
            </div>
            <h1 class="text-2xl font-semibold text-warmBlack mb-2">Menunggu Pembayaran</h1>
            <p class="text-sm text-warmGrey mb-2 leading-relaxed">
                Pesanan Anda sudah diterima. Silakan selesaikan pembayaran sesuai instruksi yang dikirim ke email Anda.
            </p>
        @else
            {{-- Status lain --}}
            <div class="w-16 h-16 rounded-full bg-warmCream flex items-center justify-center mx-auto mb-6">
                <span class="material-symbols-outlined text-warmGrey !text-[36px]">receipt</span>
            </div>
            <h1 class="text-2xl font-semibold text-warmBlack mb-2">Pesanan Diterima</h1>
        @endif

        @if($order_id)
            <p class="text-xs text-warmGrey mt-4 mb-6">
                Nomor Pesanan: <span class="font-semibold text-warmBlack">{{ $order_id }}</span>
            </p>
        @endif

        <div class="flex flex-col space-y-3">
            <a href="/products" class="w-full py-2.5 bg-brand hover:bg-brandDark text-white font-semibold text-sm rounded-btn transition-colors text-center">
                Lanjut Belanja
            </a>
            <a href="/" class="w-full py-2.5 border border-warmLightGrey text-warmGrey hover:text-warmBlack font-semibold text-sm rounded-btn transition-colors text-center">
                Kembali ke Beranda
            </a>
        </div>
    </div>
</div>
@endsection
