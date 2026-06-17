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
            <div class="lg:col-span-2 space-y-6">

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
                                    value="{{ auth()->user()->name }}"
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
                                aria-required="true"></textarea>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="recipient-city" class="block text-xs font-semibold text-warmGrey uppercase tracking-wider mb-1.5">
                                    Kota / Kabupaten <span class="text-red-500">*</span>
                                </label>
                                <input
                                    id="recipient-city"
                                    type="text"
                                    required
                                    class="w-full border border-warmLightGrey rounded-btn text-sm text-warmBlack px-3 py-2.5 focus:border-brand focus:ring-0 outline-none transition-colors"
                                    placeholder="Nama kota"
                                    aria-required="true">
                            </div>
                            <div>
                                <label for="recipient-postal" class="block text-xs font-semibold text-warmGrey uppercase tracking-wider mb-1.5">
                                    Kode Pos <span class="text-red-500">*</span>
                                </label>
                                <input
                                    id="recipient-postal"
                                    type="text"
                                    maxlength="5"
                                    required
                                    class="w-full border border-warmLightGrey rounded-btn text-sm text-warmBlack px-3 py-2.5 focus:border-brand focus:ring-0 outline-none transition-colors"
                                    placeholder="Contoh: 12345"
                                    aria-required="true">
                            </div>
                        </div>
                    </form>
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
                            <span id="checkout-subtotal">@rupiah($subtotal)</span>
                        </div>
                        <div class="flex justify-between text-xs text-warmGrey">
                            <span>Ongkos Kirim</span>
                            <span id="checkout-shipping">
                                @if($shipping > 0)
                                    @rupiah($shipping)
                                @else
                                    <span class="text-emerald-600 font-semibold">Gratis</span>
                                @endif
                            </span>
                        </div>
                        <div class="flex justify-between text-sm font-semibold text-warmBlack border-t border-warmLightGrey pt-3">
                            <span>Total Pembayaran</span>
                            <span id="checkout-total" class="text-brand">@rupiah($total)</span>
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

        try {
            // Ambil Snap Token dari server (dibuat oleh MidtransService)
            const response = await fetch('{{ route("checkout.token") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({
                    total: {{ $total }},
                    // TODO: Kirim item_details dari keranjang yang sebenarnya
                    items: [],
                }),
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: Gagal mendapatkan token pembayaran`);
            }

            const { snap_token } = await response.json();

            // Buka popup Midtrans Snap — pengguna mengisi data pembayaran di sini
            window.snap.pay(snap_token, {
                onSuccess: (result) => {
                    // Redirect ke halaman konfirmasi berhasil
                    window.location.href = `{{ route('checkout.finish') }}?order_id=${result.order_id}&transaction_status=${result.transaction_status}`;
                },
                onPending: (result) => {
                    // Instruksikan pengguna untuk menyelesaikan pembayaran
                    window.location.href = `{{ route('checkout.finish') }}?order_id=${result.order_id}&transaction_status=pending`;
                },
                onError: (result) => {
                    console.error('Midtrans error:', result);
                    window.location.href = '{{ route("checkout.error") }}';
                },
                onClose: () => {
                    // Pengguna menutup popup tanpa menyelesaikan pembayaran
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
