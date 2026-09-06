@extends('layouts.storefront')

@section('title', 'Konfirmasi Retur ' . $returnRequest->return_number . ' | el Craft')
@section('meta_description', 'Detail pengajuan retur ' . $returnRequest->return_number . ' untuk pesanan ' . $order->order_number . ' dari el Craft.')

@section('content')
<div class="bg-white min-h-screen pb-24 md:pb-0">

    {{-- ─── HEADER ────────────────────────────────── --}}
    <x-page-header title="Detail Retur" :back-url="route('orders.show', $order->order_number)" />

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

            {{-- ── LEFT: Return Details ─────────────── --}}
            <div class="lg:col-span-2 space-y-6">

                {{-- Success Banner --}}
                <div class="bg-green-50 border border-green-200 rounded-card p-5 flex items-start gap-4">
                    <div class="w-10 h-10 rounded-full bg-green-100 flex items-center justify-center flex-shrink-0">
                        <span class="material-symbols-outlined !text-[22px] text-green-600">check_circle</span>
                    </div>
                    <div>
                        <p class="font-semibold text-green-800 text-sm">Pengajuan Retur Berhasil Dikirim</p>
                        <p class="text-xs text-green-700 mt-1 leading-relaxed">
                            Pengajuan retur Anda sedang dalam proses peninjauan oleh admin.
                            Silakan hubungi kami via WhatsApp untuk mempercepat proses.
                        </p>
                    </div>
                </div>

                {{-- Return Info Card --}}
                <div class="bg-white rounded-card border border-warmLightGrey p-5">
                    <h2 class="text-sm font-semibold text-warmBlack mb-4 flex items-center gap-2">
                        <span class="material-symbols-outlined !text-[18px] text-brand">assignment_return</span>
                        Informasi Pengajuan Retur
                    </h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                        <div>
                            <p class="text-warmGrey">Nomor Retur</p>
                            <p class="text-warmBlack font-bold font-mono tracking-wide mt-0.5 text-brandDark">{{ $returnRequest->return_number }}</p>
                        </div>
                        <div>
                            <p class="text-warmGrey">Nomor Pesanan</p>
                            <p class="text-warmBlack font-medium font-mono mt-0.5">{{ $order->order_number }}</p>
                        </div>
                        <div>
                            <p class="text-warmGrey">Jenis Penyelesaian</p>
                            <p class="text-warmBlack font-medium mt-0.5">
                                @if($returnRequest->resolution_type === 'tukar_barang')
                                    <span class="inline-flex items-center gap-1">
                                        <span class="material-symbols-outlined !text-[13px] text-brand">swap_horiz</span>
                                        Tukar Barang
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1">
                                        <span class="material-symbols-outlined !text-[13px] text-brand">payments</span>
                                        Refund
                                    </span>
                                @endif
                            </p>
                        </div>
                        <div>
                            <p class="text-warmGrey">Status Retur</p>
                            <p class="mt-0.5">
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-yellow-50 text-yellow-700 border border-yellow-200">
                                    <span class="material-symbols-outlined !text-[12px]">schedule</span>
                                    Diajukan
                                </span>
                            </p>
                        </div>
                        <div class="sm:col-span-2">
                            <p class="text-warmGrey">Tanggal Pengajuan</p>
                            <p class="text-warmBlack font-medium mt-0.5">{{ $returnRequest->created_at->format('d M Y, H:i') }} WIB</p>
                        </div>
                        <div class="sm:col-span-2">
                            <p class="text-warmGrey mb-1">Alasan Retur</p>
                            <div class="bg-warmCream/60 border border-warmLightGrey rounded-[6px] px-3 py-2.5 leading-relaxed text-warmBlack">{{ $returnRequest->reason }}</div>
                        </div>
                        @if($returnRequest->evidence_photo_path)
                            <div class="sm:col-span-2">
                                <p class="text-warmGrey mb-1">Foto Bukti</p>
                                <div class="w-32 h-32 rounded-img overflow-hidden border border-warmLightGrey">
                                    <img src="{{ Storage::disk('public')->url($returnRequest->evidence_photo_path) }}"
                                         alt="Foto Bukti Retur"
                                         class="w-full h-full object-cover">
                                </div>
                                <p class="text-[10px] text-warmGrey mt-1 leading-normal">
                                    Foto bukti sudah tersimpan di sistem kami dan bisa dilihat admin.
                                    Anda boleh melampirkannya lagi secara manual di WhatsApp jika perlu.
                                </p>
                            </div>
                        @else
                            <div class="sm:col-span-2">
                                <p class="text-[11px] text-warmGrey/70 italic">
                                    Foto bukti sudah tersimpan di sistem kami dan bisa dilihat admin.
                                    Anda boleh melampirkannya secara manual di WhatsApp jika diperlukan.
                                </p>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Return Items --}}
                @if($returnRequest->items->isNotEmpty())
                    <div class="bg-white rounded-card border border-warmLightGrey overflow-hidden">
                        <div class="px-5 py-4 border-b border-warmLightGrey/60 bg-warmCream">
                            <h2 class="text-sm font-semibold text-warmBlack flex items-center gap-2">
                                <span class="material-symbols-outlined !text-[18px] text-brand">inventory_2</span>
                                Produk yang Diretur ({{ $returnRequest->items->count() }} jenis)
                            </h2>
                        </div>
                        <div class="divide-y divide-warmLightGrey/50">
                            @foreach($returnRequest->items as $returnItem)
                                @php $orderItem = $returnItem->orderItem; @endphp
                                <div class="flex items-center gap-4 px-5 py-4">
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium text-warmBlack truncate">{{ $orderItem->product_name ?? '-' }}</p>
                                        @if($orderItem && $orderItem->variant_name)
                                            <p class="text-xs text-warmGrey">Varian: {{ $orderItem->variant_name }}</p>
                                        @endif
                                        <p class="text-xs text-warmGrey mt-0.5">
                                            @rupiah($orderItem->price ?? 0) × {{ $returnItem->quantity }}
                                            <span class="text-warmGrey/60">(dari {{ $orderItem->quantity ?? '?' }} dipesan)</span>
                                        </p>
                                    </div>
                                    <div class="flex-shrink-0 text-right">
                                        <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-rose-50 text-rose-700 border border-rose-200">
                                            Qty: {{ $returnItem->quantity }}
                                        </span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

            </div>

            {{-- ── RIGHT: Actions Panel ── --}}
            <div class="space-y-5">

                {{-- WhatsApp CTA --}}
                @if($waLink)
                    <div class="bg-green-50 border border-green-200 rounded-card p-5">
                        <h3 class="text-sm font-semibold text-green-800 mb-3 flex items-center gap-2">
                            <span class="material-symbols-outlined !text-[18px]">chat</span>
                            Hubungi Admin
                        </h3>
                        <p class="text-xs text-green-700 leading-relaxed mb-4">
                            Kirim pesan ke admin kami via WhatsApp untuk mempercepat proses retur Anda.
                            Nomor retur dan informasi pesanan sudah terisi otomatis.
                        </p>
                        <a href="{{ $waLink }}"
                           id="btn-whatsapp-admin"
                           target="_blank"
                           rel="noopener noreferrer"
                           class="flex items-center justify-center gap-2 w-full py-3 bg-green-600 hover:bg-green-700 text-white font-semibold text-xs uppercase tracking-wider rounded-btn transition-colors duration-200">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                            </svg>
                            Kirim ke WhatsApp Admin
                        </a>
                    </div>
                @else
                    <div class="bg-warmCream rounded-card border border-warmLightGrey p-5">
                        <h3 class="text-sm font-semibold text-warmBlack mb-2 flex items-center gap-2">
                            <span class="material-symbols-outlined !text-[18px] text-brand">chat</span>
                            Hubungi Admin
                        </h3>
                        <p class="text-xs text-warmGrey leading-relaxed">
                            Silakan hubungi admin kami untuk informasi lebih lanjut mengenai proses retur Anda.
                        </p>
                    </div>
                @endif

                {{-- Return Summary --}}
                <div class="bg-white rounded-card border border-warmLightGrey p-5">
                    <h3 class="text-sm font-semibold text-warmBlack mb-3 flex items-center gap-2">
                        <span class="material-symbols-outlined !text-[18px] text-brand">receipt</span>
                        Ringkasan Retur
                    </h3>
                    <div class="space-y-2 text-xs">
                        <div class="flex justify-between text-warmGrey">
                            <span>No. Retur</span>
                            <span class="font-mono font-semibold text-brandDark">{{ $returnRequest->return_number }}</span>
                        </div>
                        <div class="flex justify-between text-warmGrey">
                            <span>No. Pesanan</span>
                            <span class="font-mono text-warmBlack">{{ $order->order_number }}</span>
                        </div>
                        <div class="flex justify-between text-warmGrey">
                            <span>Penyelesaian</span>
                            <span class="text-warmBlack font-medium capitalize">{{ $returnRequest->resolution_type === 'tukar_barang' ? 'Tukar Barang' : 'Refund' }}</span>
                        </div>
                        <div class="flex justify-between text-warmGrey">
                            <span>Status</span>
                            <span class="text-yellow-700 font-semibold">Diajukan</span>
                        </div>
                    </div>
                </div>

                {{-- Navigation Buttons --}}
                <div class="space-y-3">
                    <a href="{{ route('orders.show', $order->order_number) }}"
                       class="block w-full text-center py-3 border border-brand text-brand hover:bg-warmCream font-semibold text-xs uppercase tracking-wider rounded-btn transition-colors duration-200">
                        Kembali ke Detail Pesanan
                    </a>
                    <a href="{{ route('orders.index') }}"
                       class="block w-full text-center py-3 border border-warmLightGrey text-warmGrey hover:bg-warmCream font-semibold text-xs uppercase tracking-wider rounded-btn transition-colors duration-200">
                        Semua Pesanan
                    </a>
                </div>

            </div>
        </div>
    </section>
</div>
@endsection
