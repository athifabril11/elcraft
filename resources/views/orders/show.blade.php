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
                    @if($order->status === 'selesai')
                        <a href="/products"
                            class="block w-full text-center py-3 bg-brand hover:bg-brandDark text-white font-semibold text-xs uppercase tracking-wider rounded-btn transition-colors duration-200">
                            Belanja Lagi
                        </a>
                    @endif
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
@endsection
