@extends('layouts.storefront')

@section('title', 'Checkout | el Craft')

@section('meta_description', 'Selesaikan pembelian aksesoris premium Anda di el Craft. Pembayaran aman dan pengiriman cepat ke seluruh Indonesia.')

@section('content')
<div class="pb-24 md:pb-0 bg-warmCream min-h-screen">

    {{-- ─── HEADER CHECKOUT ───────────────────────────────── --}}
    <section class="bg-white border-b border-warmLightGrey py-6 px-5 md:px-8 lg:px-16">
        <div class="max-w-[1280px] mx-auto flex items-center justify-between">
            {{-- Breadcrumb --}}
            <nav class="flex items-center space-x-2 text-xs text-warmGrey" aria-label="Langkah checkout">
                <a href="/cart" class="hover:text-brand transition-colors">Keranjang</a>
                <span class="material-symbols-outlined !text-[12px]">chevron_right</span>
                <span class="text-warmBlack font-semibold">Checkout</span>
                <span class="material-symbols-outlined !text-[12px]">chevron_right</span>
                <span class="text-warmGrey">Pembayaran</span>
            </nav>
            <span class="text-lg font-semibold text-brand tracking-wide font-sans">el Craft</span>
        </div>
    </section>

    {{-- ─── NOTIFIKASI SESI ────────────────────────────────── --}}
    @if(session('info'))
        <div class="max-w-[1280px] mx-auto px-5 md:px-8 lg:px-16 pt-6">
            <div class="bg-blue-50 border border-blue-200 text-blue-700 text-sm px-4 py-3 rounded-card flex items-center space-x-3" role="alert">
                <span class="material-symbols-outlined !text-[18px]">info</span>
                <span>{{ session('info') }}</span>
            </div>
        </div>
    @endif

    @if(session('error'))
        <div class="max-w-[1280px] mx-auto px-5 md:px-8 lg:px-16 pt-6">
            <div class="bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3 rounded-card flex items-center space-x-3" role="alert">
                <span class="material-symbols-outlined !text-[18px]">error</span>
                <span>{{ session('error') }}</span>
            </div>
        </div>
    @endif

    {{-- ─── KONTEN UTAMA ───────────────────────────────────── --}}
    <section class="max-w-[1280px] mx-auto px-5 md:px-8 lg:px-16 py-10">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

            {{-- ── KIRI: Form Alamat Pengiriman ──────────────────── --}}
            <div class="lg:col-span-2 space-y-6" x-data="checkoutPage({{ $subtotal }})">


                {{-- Pilih Alamat Tersimpan (jika ada) --}}
                @if($addresses->isNotEmpty())
                <div class="bg-white rounded-card border border-warmLightGrey p-6">
                    <h2 class="text-base font-semibold text-warmBlack mb-4 flex items-center space-x-2">
                        <span class="material-symbols-outlined text-brand !text-[20px]">bookmark</span>
                        <span>Pilih Alamat Tersimpan</span>
                    </h2>
                    <div class="space-y-3">
                        @foreach($addresses as $addr)
                        <label class="flex items-start gap-3 cursor-pointer p-3 rounded-card border border-warmLightGrey hover:border-brand/50 transition-colors has-[:checked]:border-brand has-[:checked]:bg-brand/5">
                            <input type="radio" name="saved_address" value="{{ $addr->id }}"
                                class="mt-0.5 text-brand focus:ring-brand border-warmGrey/40"
                                x-on:change="fillAddress({{ json_encode(['name' => $addr->recipient_name, 'phone' => $addr->phone, 'address' => $addr->full_address, 'city' => $addr->city, 'postal' => $addr->postal_code]) }})"
                                {{ $addr->is_default ? 'checked' : '' }}>
                            <div class="flex-1 min-w-0">
                                <span class="text-xs font-semibold text-warmBlack">{{ $addr->label }}</span>
                                @if($addr->is_default)
                                    <span class="ml-2 text-[10px] font-semibold text-brand bg-brand/10 px-1.5 py-0.5 rounded-full">Utama</span>
                                @endif
                                <p class="text-[11px] text-warmGrey mt-0.5 leading-relaxed">{{ $addr->recipient_name }} · {{ $addr->phone }}</p>
                                <p class="text-[11px] text-warmGrey truncate">{{ $addr->full_address }}, {{ $addr->city }}, {{ $addr->postal_code }}</p>
                            </div>
                        </label>
                        @endforeach
                        <a href="{{ route('profile.addresses') }}" class="text-xs text-brand hover:text-brandDark font-medium flex items-center gap-1 mt-1">
                            <span class="material-symbols-outlined !text-[14px]">add</span> Kelola buku alamat
                        </a>
                    </div>
                </div>
                @endif

                {{-- Informasi Pengiriman --}}
                <div class="bg-white rounded-card border border-warmLightGrey p-6">
                    <h2 class="text-base font-semibold text-warmBlack mb-5 flex items-center space-x-2">
                        <span class="material-symbols-outlined text-brand !text-[20px]">local_shipping</span>
                        <span>Informasi Pengiriman</span>
                    </h2>

                    <form id="shipping-form" class="space-y-4">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="recipient-name" class="block text-xs font-semibold text-warmGrey uppercase tracking-wider mb-1.5">
                                    Nama Penerima <span class="text-red-500">*</span>
                                </label>
                                <input
                                    id="recipient-name"
                                    type="text"
                                    value="{{ $addresses->firstWhere('is_default', true)->recipient_name ?? auth()->user()->name }}"
                                    required
                                    class="w-full border border-warmLightGrey rounded-btn text-sm text-warmBlack px-3 py-2.5 focus:border-brand focus:ring-0 outline-none transition-colors"
                                    placeholder="Nama lengkap penerima"
                                    aria-required="true">
                            </div>
                            <div>
                                <label for="recipient-phone" class="block text-xs font-semibold text-warmGrey uppercase tracking-wider mb-1.5">
                                    Nomor Telepon <span class="text-red-500">*</span>
                                </label>
                                <input
                                    id="recipient-phone"
                                    type="tel"
                                    value="{{ $addresses->firstWhere('is_default', true)->phone ?? '' }}"
                                    required
                                    class="w-full border border-warmLightGrey rounded-btn text-sm text-warmBlack px-3 py-2.5 focus:border-brand focus:ring-0 outline-none transition-colors"
                                    placeholder="08xxxxxxxxxx"
                                    aria-required="true">
                            </div>
                        </div>

                        <div>
                            <label for="recipient-address" class="block text-xs font-semibold text-warmGrey uppercase tracking-wider mb-1.5">
                                Alamat Lengkap <span class="text-red-500">*</span>
                            </label>
                            <textarea
                                id="recipient-address"
                                rows="3"
                                required
                                class="w-full border border-warmLightGrey rounded-btn text-sm text-warmBlack px-3 py-2.5 focus:border-brand focus:ring-0 outline-none transition-colors resize-none"
                                placeholder="Nama jalan, nomor rumah, RT/RW, Kelurahan, Kecamatan"
                                aria-required="true">{{ $addresses->firstWhere('is_default', true)->full_address ?? '' }}</textarea>
                        </div>

                        {{-- Provinsi & Kota (RajaOngkir) --}}
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="province-select" class="block text-xs font-semibold text-warmGrey uppercase tracking-wider mb-1.5">
                                    Provinsi <span class="text-red-500">*</span>
                                </label>
                                <select
                                    id="province-select"
                                    required
                                    @change="onProvinceChange($event)"
                                    class="w-full border border-warmLightGrey rounded-btn text-sm text-warmBlack px-3 py-2.5 focus:border-brand focus:ring-0 outline-none transition-colors bg-white disabled:opacity-50 disabled:cursor-not-allowed"
                                    :disabled="provincesLoading"
                                    aria-required="true">
                                    <option value="" disabled selected>-- Pilih Provinsi --</option>
                                    <template x-for="prov in provinces" :key="prov.province_id">
                                        <option :value="prov.province_id" x-text="prov.province"
                                            :selected="selectedProvinceId == prov.province_id"></option>
                                    </template>
                                </select>
                                <p x-show="provincesLoading" class="text-[10px] text-warmGrey mt-1 flex items-center gap-1" x-cloak>
                                    <span class="material-symbols-outlined !text-[12px] animate-spin">sync</span> Memuat provinsi...
                                </p>
                            </div>
                            <div>
                                <label for="city-select" class="block text-xs font-semibold text-warmGrey uppercase tracking-wider mb-1.5">
                                    Kota / Kabupaten <span class="text-red-500">*</span>
                                </label>
                                <select
                                    id="city-select"
                                    required
                                    @change="onCityChange($event)"
                                    class="w-full border border-warmLightGrey rounded-btn text-sm text-warmBlack px-3 py-2.5 focus:border-brand focus:ring-0 outline-none transition-colors bg-white disabled:opacity-50 disabled:cursor-not-allowed"
                                    :disabled="citiesLoading || !selectedProvinceId"
                                    aria-required="true">
                                    <option value="" disabled selected>-- Pilih Kota --</option>
                                    <template x-for="city in cities" :key="city.city_id">
                                        <option :value="city.city_id" x-text="city.type + ' ' + city.city_name"
                                            :selected="selectedCityId == city.city_id"></option>
                                    </template>
                                </select>
                                <p x-show="citiesLoading" class="text-[10px] text-warmGrey mt-1 flex items-center gap-1" x-cloak>
                                    <span class="material-symbols-outlined !text-[12px] animate-spin">sync</span> Memuat kota...
                                </p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="recipient-postal" class="block text-xs font-semibold text-warmGrey uppercase tracking-wider mb-1.5">
                                    Kode Pos
                                </label>
                                <input
                                    id="recipient-postal"
                                    type="text"
                                    maxlength="5"
                                    x-model="postalCode"
                                    class="w-full border border-warmLightGrey rounded-btn text-sm text-warmBlack px-3 py-2.5 focus:border-brand focus:ring-0 outline-none transition-colors"
                                    placeholder="Contoh: 12345"
                                    aria-label="Kode pos">
                            </div>
                        </div>
                    </form>
                </div>

                {{-- Pilih Kurir & Layanan --}}
                <div class="bg-white rounded-card border border-warmLightGrey p-6">
                    <h2 class="text-base font-semibold text-warmBlack mb-4 flex items-center space-x-2">
                        <span class="material-symbols-outlined text-brand !text-[20px]">inventory_2</span>
                        <span>Pilih Kurir</span>
                    </h2>

                    {{-- Pilihan Kurir --}}
                    <div class="flex gap-3 mb-4 flex-wrap">
                        @foreach(['jne' => 'JNE', 'pos' => 'POS Indonesia', 'tiki' => 'TIKI'] as $code => $label)
                        <button type="button"
                            @click="selectCourier('{{ $code }}')"
                            :class="selectedCourier === '{{ $code }}' ? 'border-brand bg-brand/5 text-brand font-semibold' : 'border-warmLightGrey text-warmGrey hover:border-brand/50'"
                            class="px-4 py-2 text-xs border rounded-btn transition-all duration-200 flex items-center gap-1.5">
                            <span class="material-symbols-outlined !text-[14px]"
                                :class="selectedCourier === '{{ $code }}' ? 'text-brand' : 'text-warmGrey'">local_shipping</span>
                            {{ $label }}
                        </button>
                        @endforeach
                    </div>

                    {{-- Loading / Empty / Results --}}
                    <div x-show="costLoading" class="flex items-center gap-2 text-xs text-warmGrey py-3" x-cloak>
                        <span class="material-symbols-outlined !text-[16px] animate-spin">sync</span>
                        Menghitung ongkos kirim...
                    </div>

                    <div x-show="!costLoading && !selectedCityId && !shippingError" class="text-xs text-warmGrey py-2" x-cloak>
                        Pilih kota tujuan terlebih dahulu untuk melihat opsi pengiriman.
                    </div>

                    <div x-show="shippingError && !costLoading" class="text-xs text-red-500 py-2 flex items-center gap-1.5" x-cloak>
                        <span class="material-symbols-outlined !text-[14px]">error</span>
                        <span x-text="shippingError"></span>
                    </div>

                    <div x-show="shippingOptions.length > 0 && !costLoading" class="space-y-2" x-cloak>
                        <template x-for="(opt, idx) in shippingOptions" :key="opt.service">
                            <label class="flex items-center justify-between gap-3 cursor-pointer p-3 rounded-card border transition-colors"
                                :class="selectedShipping?.service === opt.service ? 'border-brand bg-brand/5' : 'border-warmLightGrey hover:border-brand/40'">
                                <div class="flex items-center gap-2 min-w-0">
                                    <input type="radio" name="shipping_service" :value="opt.service"
                                        @change="selectShippingOption(opt)"
                                        :checked="selectedShipping?.service === opt.service"
                                        class="text-brand focus:ring-brand border-warmGrey/40 flex-shrink-0">
                                    <div class="min-w-0">
                                        <p class="text-xs font-semibold text-warmBlack" x-text="opt.courier + ' ' + opt.description"></p>
                                        <p class="text-[10px] text-warmGrey" x-text="'Estimasi: ' + opt.etd + ' hari'"></p>
                                    </div>
                                </div>
                                <span class="text-xs font-bold text-brand flex-shrink-0" x-text="formatRupiah(opt.cost)"></span>
                            </label>
                        </template>
                    </div>
                </div>

                {{-- Catatan untuk Penjual --}}
                <div class="bg-white rounded-card border border-warmLightGrey p-6">
                    <h2 class="text-base font-semibold text-warmBlack mb-4 flex items-center space-x-2">
                        <span class="material-symbols-outlined text-brand !text-[20px]">edit_note</span>
                        <span>Catatan Pesanan (Opsional)</span>
                    </h2>
                    <textarea
                        id="order-notes"
                        rows="2"
                        class="w-full border border-warmLightGrey rounded-btn text-sm text-warmBlack px-3 py-2.5 focus:border-brand focus:ring-0 outline-none transition-colors resize-none"
                        placeholder="Misal: warna, ukuran khusus, permintaan gift wrap, dll."
                        aria-label="Catatan untuk penjual"></textarea>
                </div>

                {{-- Kode Voucher --}}
                <div class="bg-white rounded-card border border-warmLightGrey p-6">
                    <h2 class="text-base font-semibold text-warmBlack mb-4 flex items-center space-x-2">
                        <span class="material-symbols-outlined text-brand !text-[20px]">local_offer</span>
                        <span>Kode Voucher / Promo</span>
                    </h2>
                    <div class="flex gap-2">
                        <input id="voucher-code" type="text" x-model="voucherCode"
                            placeholder="Masukkan kode voucher"
                            class="flex-1 border border-warmLightGrey rounded-btn text-sm text-warmBlack px-3 py-2.5 focus:border-brand focus:ring-0 outline-none transition-colors uppercase"
                            :disabled="voucherApplied"
                            aria-label="Kode voucher">
                        <button type="button"
                            x-show="!voucherApplied"
                            @click="applyVoucher()"
                            :disabled="!voucherCode.trim() || voucherLoading"
                            class="px-4 py-2.5 bg-brand hover:bg-brandDark text-white font-semibold text-xs uppercase tracking-wider rounded-btn transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-1.5">
                            <span x-show="voucherLoading" class="material-symbols-outlined !text-[14px] animate-spin">sync</span>
                            <span x-text="voucherLoading ? 'Memeriksa...' : 'Terapkan'"></span>
                        </button>
                        <button type="button"
                            x-show="voucherApplied"
                            @click="removeVoucher()"
                            class="px-4 py-2.5 border border-red-300 text-red-500 hover:bg-red-50 font-semibold text-xs uppercase tracking-wider rounded-btn transition-colors">
                            Hapus
                        </button>
                    </div>
                    <p x-show="voucherMessage" x-text="voucherMessage" x-cloak
                        :class="voucherApplied ? 'text-green-600' : 'text-red-500'"
                        class="text-xs mt-2 font-medium"></p>
                </div>

                {{-- !! PENTING: TIDAK ADA FIELD KARTU KREDIT DI SINI !! --}}
                {{--
                    Data kartu kredit diisi LANGSUNG di popup/iframe Midtrans Snap.
                    Server el Craft TIDAK PERNAH menyentuh nomor kartu, CVV, atau
                    tanggal kedaluwarsa — sesuai standar PCI-DSS.
                --}}

            </div>

            {{-- ── KANAN: Ringkasan Pesanan + Tombol Bayar ───────── --}}
            <div class="space-y-5">

                {{-- Ringkasan Pesanan --}}
                <div class="bg-white rounded-card border border-warmLightGrey p-6 sticky top-28">
                    <h2 class="text-base font-semibold text-warmBlack mb-5">Ringkasan Pesanan</h2>

                    {{-- Daftar Produk --}}
                    @if(empty($cartItems))
                        <div class="flex flex-col items-center py-8 text-center">
                            <span class="material-symbols-outlined text-warmGrey !text-[40px] mb-3">shopping_bag</span>
                            <p class="text-sm text-warmGrey">Keranjang belanja kosong.</p>
                            <a href="/products" class="mt-4 text-xs font-semibold text-brand hover:text-brandDark transition-colors">
                                Mulai Belanja →
                            </a>
                        </div>
                    @else
                        <ul class="space-y-4 mb-5" aria-label="Daftar produk dalam pesanan">
                            @foreach($cartItems as $item)
                                <li class="flex items-center space-x-3">
                                    <div class="w-14 h-14 rounded-img overflow-hidden bg-warmCream flex-shrink-0">
                                        <img src="{{ $item['image'] ?? 'https://images.unsplash.com/photo-1599643478518-a784e5dc4c8f?q=80&w=200' }}"
                                             alt="{{ $item['name'] }}"
                                             class="w-full h-full object-cover">
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-xs font-semibold text-warmBlack line-clamp-2">{{ $item['name'] }}</p>
                                        <p class="text-[11px] text-warmGrey">Qty: {{ $item['quantity'] }}</p>
                                    </div>
                                    <span class="text-xs font-semibold text-warmBlack whitespace-nowrap">
                                        @rupiah($item['price'] * $item['quantity'])
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    {{-- Baris Harga --}}
                    <div class="border-t border-warmLightGrey pt-4 space-y-2.5">
                        <div class="flex justify-between text-xs text-warmGrey">
                            <span>Subtotal</span>
                            <span>@rupiah($subtotal)</span>
                        </div>
                        <div x-show="voucherDiscount > 0" class="flex justify-between text-xs text-green-600 font-medium">
                            <span>Diskon Voucher</span>
                            <span>- <span x-text="formatRupiah(voucherDiscount)"></span></span>
                        </div>
                        <div class="flex justify-between text-xs text-warmGrey">
                            <span>Ongkos Kirim</span>
                            <span id="checkout-shipping">
                                <span x-show="!selectedShipping" class="text-warmGrey/60 italic text-[11px]">Pilih kurir</span>
                                <span x-show="selectedShipping && selectedShipping.cost === 0" class="text-emerald-600 font-semibold" x-cloak>Gratis</span>
                                <span x-show="selectedShipping && selectedShipping.cost > 0" x-text="formatRupiah(selectedShipping?.cost ?? 0)" x-cloak></span>
                            </span>
                        </div>
                        <div class="flex justify-between text-sm font-semibold text-warmBlack border-t border-warmLightGrey pt-3">
                            <span>Total Pembayaran</span>
                            <span class="text-brand" x-text="formatRupiah(Math.max(0, subtotal - voucherDiscount + (selectedShipping?.cost ?? 0)))"></span>
                        </div>
                    </div>

                    {{-- Tombol Bayar via Midtrans Snap --}}
                    {{--
                        Tombol ini memicu pembuatan Snap Token di server,
                        lalu membuka popup Midtrans Snap.
                        Data kartu diisi di dalam popup — BUKAN di halaman ini.
                    --}}
                    <button
                        id="pay-button"
                        onclick="initiatePayment()"
                        class="mt-6 w-full py-3.5 bg-brand hover:bg-brandDark text-white font-semibold text-sm uppercase tracking-widest rounded-btn transition-colors duration-200 flex items-center justify-center space-x-2 disabled:opacity-60 disabled:cursor-not-allowed"
                        @if(empty($cartItems)) disabled @endif
                        aria-label="Bayar sekarang via Midtrans">
                        <span class="material-symbols-outlined !text-[20px]">lock</span>
                        <span>Bayar Sekarang</span>
                    </button>

                    {{-- Label keamanan --}}
                    <p class="text-center text-[10px] text-warmGrey mt-3 flex items-center justify-center space-x-1">
                        <span class="material-symbols-outlined !text-[13px]">verified_user</span>
                        <span>Pembayaran aman &amp; terenkripsi via Midtrans</span>
                    </p>

                    {{-- Logo metode pembayaran --}}
                    <div class="mt-4 flex flex-wrap justify-center gap-2 opacity-60">
                        <span class="text-[9px] bg-warmCream border border-warmLightGrey px-2 py-1 rounded font-medium text-warmGrey">VISA</span>
                        <span class="text-[9px] bg-warmCream border border-warmLightGrey px-2 py-1 rounded font-medium text-warmGrey">MASTERCARD</span>
                        <span class="text-[9px] bg-warmCream border border-warmLightGrey px-2 py-1 rounded font-medium text-warmGrey">GoPay</span>
                        <span class="text-[9px] bg-warmCream border border-warmLightGrey px-2 py-1 rounded font-medium text-warmGrey">ShopeePay</span>
                        <span class="text-[9px] bg-warmCream border border-warmLightGrey px-2 py-1 rounded font-medium text-warmGrey">BCA VA</span>
                        <span class="text-[9px] bg-warmCream border border-warmLightGrey px-2 py-1 rounded font-medium text-warmGrey">QRIS</span>
                    </div>
                </div>

            </div>
        </div>
    </section>
</div>
@endsection

@push('scripts')
{{-- Midtrans Snap JS — library dari Midtrans untuk menampilkan popup pembayaran --}}
@if(config('midtrans.is_production'))
    <script src="https://app.midtrans.com/snap/snap.js" data-client-key="{{ config('midtrans.client_key_production') }}"></script>
@else
    <script src="https://app.sandbox.midtrans.com/snap/snap.js" data-client-key="{{ config('midtrans.client_key_sandbox') }}"></script>
@endif

<script>
    /**
     * checkoutPage — Alpine.js component
     * Mengelola: auto-fill alamat dari buku alamat, voucher AJAX, dan live total.
     */
    document.addEventListener('alpine:init', () => {
        Alpine.data('checkoutPage', (initialSubtotal) => ({
            subtotal: initialSubtotal,
            voucherCode: '',
            voucherApplied: false,
            voucherDiscount: 0,
            voucherMessage: '',
            voucherLoading: false,

            // --- RajaOngkir State ---
            provinces: [],
            cities: [],
            provincesLoading: false,
            citiesLoading: false,
            costLoading: false,
            selectedProvinceId: null,
            selectedCityId: null,
            selectedCityName: '',
            selectedCourier: 'jne',
            shippingOptions: [],
            selectedShipping: null,
            shippingError: '',
            postalCode: '{{ $addresses->firstWhere("is_default", true)?->postal_code ?? "" }}',

            async init() {
                await this.loadProvinces();
                @if($addresses->firstWhere('is_default', true)?->province_id)
                    this.selectedProvinceId = {{ $addresses->firstWhere('is_default', true)->province_id }};
                    await this.loadCities(this.selectedProvinceId);
                    @if($addresses->firstWhere('is_default', true)?->city_id)
                        this.selectedCityId = {{ $addresses->firstWhere('is_default', true)->city_id }};
                        await this.fetchShippingCost();
                    @endif
                @endif
            },

            formatRupiah(amount) {
                return 'Rp ' + new Intl.NumberFormat('id-ID').format(Math.round(amount));
            },

            fillAddress(data) {
                document.getElementById('recipient-name').value    = data.name   || '';
                document.getElementById('recipient-phone').value   = data.phone  || '';
                document.getElementById('recipient-address').value = data.address || '';
                this.postalCode = data.postal || '';
            },

            async loadProvinces() {
                this.provincesLoading = true;
                try {
                    const res  = await fetch('{{ route("shipping.provinces") }}');
                    const json = await res.json();
                    this.provinces = json.success ? json.data : [];
                } catch (e) {
                    console.error('Failed to load provinces', e);
                } finally {
                    this.provincesLoading = false;
                }
            },

            async onProvinceChange(event) {
                const provinceId = parseInt(event.target.value);
                this.selectedProvinceId = provinceId;
                this.selectedCityId     = null;
                this.selectedCityName   = '';
                this.cities             = [];
                this.shippingOptions    = [];
                this.selectedShipping   = null;
                this.shippingError      = '';
                await this.loadCities(provinceId);
            },

            async loadCities(provinceId) {
                this.citiesLoading = true;
                try {
                    const res  = await fetch(`/shipping/cities/${provinceId}`);
                    const json = await res.json();
                    this.cities = json.success ? json.data : [];
                } catch (e) {
                    console.error('Failed to load cities', e);
                } finally {
                    this.citiesLoading = false;
                }
            },

            async onCityChange(event) {
                const cityId = parseInt(event.target.value);
                const cityOpt = this.cities.find(c => c.city_id == cityId);
                this.selectedCityId   = cityId;
                this.selectedCityName = cityOpt ? (cityOpt.type + ' ' + cityOpt.city_name) : '';
                // Auto-fill postal code from RajaOngkir city data
                if (cityOpt?.postal_code) this.postalCode = cityOpt.postal_code;
                this.shippingOptions  = [];
                this.selectedShipping = null;
                this.shippingError    = '';
                await this.fetchShippingCost();
            },

            selectCourier(code) {
                this.selectedCourier  = code;
                this.shippingOptions  = [];
                this.selectedShipping = null;
                if (this.selectedCityId) this.fetchShippingCost();
            },

            selectShippingOption(opt) {
                this.selectedShipping = opt;
            },

            async fetchShippingCost() {
                if (!this.selectedCityId) return;
                this.costLoading   = true;
                this.shippingError = '';
                // Estimate total weight: 500g per item (fallback)
                const weight = Math.max(100, {{ $cartItems ? count($cartItems) : 1 }} * 500);
                try {
                    const res = await fetch('{{ route("shipping.cost") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                        body: JSON.stringify({
                            city_id: this.selectedCityId,
                            weight:  weight,
                            courier: this.selectedCourier,
                        }),
                    });
                    const json = await res.json();
                    if (json.success && json.data.length > 0) {
                        this.shippingOptions = json.data;
                        this.selectedShipping = json.data[0]; // auto-select cheapest
                    } else {
                        this.shippingOptions  = [];
                        this.selectedShipping = null;
                        this.shippingError    = json.message || 'Layanan pengiriman tidak tersedia untuk tujuan ini.';
                    }
                } catch (e) {
                    this.shippingError = 'Gagal menghitung ongkos kirim. Silakan coba lagi.';
                } finally {
                    this.costLoading = false;
                }
            },

            async applyVoucher() {
                if (!this.voucherCode.trim()) return;
                this.voucherLoading = true;
                this.voucherMessage = '';

                try {
                    const res = await fetch('{{ route("voucher.apply") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                        body: JSON.stringify({
                            code: this.voucherCode.trim(),
                            subtotal: this.subtotal,
                        }),
                    });
                    const data = await res.json();

                    if (data.valid) {
                        this.voucherApplied  = true;
                        this.voucherDiscount = data.discount_amount;
                        this.voucherMessage  = data.message;
                    } else {
                        this.voucherApplied  = false;
                        this.voucherDiscount = 0;
                        this.voucherMessage  = data.message;
                    }
                } catch (err) {
                    this.voucherMessage = 'Gagal menghubungi server. Silakan coba lagi.';
                } finally {
                    this.voucherLoading = false;
                }
            },

            removeVoucher() {
                this.voucherCode     = '';
                this.voucherApplied  = false;
                this.voucherDiscount = 0;
                this.voucherMessage  = '';
            },
        }));
    });

    /**
     * initiatePayment — Alur pembayaran PCI-DSS compliant
     *
     * 1. Request Snap Token ke server kita (POST /checkout/token)
     * 2. Gunakan token untuk membuka popup Midtrans Snap
     * 3. Server kita TIDAK PERNAH melihat data kartu — semua ada di popup Midtrans
     */
    async function initiatePayment() {
        const btn = document.getElementById('pay-button');
        btn.disabled = true;
        btn.innerHTML = `<span class="material-symbols-outlined !text-[18px] animate-spin">sync</span><span>Memproses...</span>`;

        const form = document.getElementById('shipping-form');
        if (!form.reportValidity()) {
            btn.disabled = false;
            btn.innerHTML = `<span class="material-symbols-outlined !text-[20px]">lock</span><span>Bayar Sekarang</span>`;
            return;
        }

        const name    = document.getElementById('recipient-name').value;
        const phone   = document.getElementById('recipient-phone').value;
        const address = document.getElementById('recipient-address').value;
        const notes   = document.getElementById('order-notes').value;

        // Ambil Alpine state untuk kota, provinsi, kurir
        const alpineEl = document.querySelector('[x-data]');
        const alpine   = alpineEl ? Alpine.$data(alpineEl) : null;

        const cityId       = alpine?.selectedCityId  ?? null;
        const cityName     = alpine?.selectedCityName ?? '';
        const provinceId   = alpine?.selectedProvinceId ?? null;
        const shippingCost = alpine?.selectedShipping?.cost ?? 0;
        const courier      = alpine?.selectedCourier ?? 'jne';
        const service      = alpine?.selectedShipping?.service ?? '';
        const postalCode   = alpine?.postalCode ?? '';

        if (!cityId) {
            alert('Silakan pilih kota tujuan pengiriman terlebih dahulu.');
            btn.disabled = false;
            btn.innerHTML = `<span class="material-symbols-outlined !text-[20px]">lock</span><span>Bayar Sekarang</span>`;
            return;
        }

        if (!alpine?.selectedShipping) {
            alert('Silakan pilih layanan kurir pengiriman terlebih dahulu.');
            btn.disabled = false;
            btn.innerHTML = `<span class="material-symbols-outlined !text-[20px]">lock</span><span>Bayar Sekarang</span>`;
            return;
        }

        // Ambil voucher code dari Alpine state jika ada
        const voucherInput = document.getElementById('voucher-code');
        const voucherCode  = voucherInput ? voucherInput.value.trim() : '';

        try {
            // Ambil Snap Token dari server (dibuat oleh MidtransService)
            const response = await fetch('{{ route("checkout.token") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({
                    recipient_name:  name,
                    recipient_phone: phone,
                    full_address:    address,
                    city:            cityName,
                    city_id:         cityId,
                    province_id:     provinceId,
                    postal_code:     postalCode,
                    shipping_cost:   shippingCost,
                    courier:         courier,
                    courier_service: service,
                    notes:           notes,
                    voucher_code:    voucherCode || null,
                }),
            });

            if (!response.ok) {
                const errData = await response.json();
                throw new Error(errData.message || `HTTP ${response.status}: Gagal mendapatkan token pembayaran`);
            }

            const { snap_token } = await response.json();

            // Buka popup Midtrans Snap — pengguna mengisi data pembayaran di sini
            window.snap.pay(snap_token, {
                onSuccess: (result) => {
                    window.location.href = `{{ route('checkout.finish') }}?order_id=${result.order_id}&transaction_status=${result.transaction_status}`;
                },
                onPending: (result) => {
                    window.location.href = `{{ route('checkout.finish') }}?order_id=${result.order_id}&transaction_status=pending`;
                },
                onError: (result) => {
                    console.error('Midtrans error:', result);
                    window.location.href = '{{ route("checkout.error") }}';
                },
                onClose: () => {
                    btn.disabled = false;
                    btn.innerHTML = `<span class="material-symbols-outlined !text-[20px]">lock</span><span>Bayar Sekarang</span>`;
                },
            });

        } catch (error) {
            console.error('Payment initiation error:', error);
            alert('Terjadi kesalahan: ' + error.message + '\nSilakan coba lagi atau hubungi kami.');
            btn.disabled = false;
            btn.innerHTML = `<span class="material-symbols-outlined !text-[20px]">lock</span><span>Bayar Sekarang</span>`;
        }
    }
</script>
@endpush
