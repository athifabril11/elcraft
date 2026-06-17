@extends('layouts.storefront')

@section('title', 'Keranjang Belanja | el Craft')

@section('content')
<div x-data="cartPage({{ $cart->getTotalPrice() }}, {{ json_encode($cart->items->map(function($item) {
    return [
        'id' => $item->id,
        'quantity' => $item->quantity,
        'subtotal' => $item->getSubtotal(),
    ];
})->toArray()) }})" class="bg-white min-h-screen pb-20">
    
    <!-- Bagian Breadcrumb & Header Halaman -->
    <section class="bg-warmCream py-10 px-5 md:px-8 lg:px-16 border-b border-warmLightGrey/50">
        <div class="max-w-[1280px] mx-auto">
            <nav class="flex items-center space-x-2 text-xs text-warmGrey mb-2" aria-label="Breadcrumb">
                <a href="/" class="hover:text-brand transition-colors">Beranda</a>
                <span class="material-symbols-outlined !text-[12px]">chevron_right</span>
                <span class="text-warmBlack font-medium">Keranjang Belanja</span>
            </nav>
            <h1 class="text-2xl md:text-3xl font-semibold text-warmBlack font-sans">Keranjang Belanja</h1>
        </div>
    </section>

    <!-- Kontainer Utama Keranjang -->
    <section class="py-12 px-5 md:px-8 lg:px-16 max-w-[1280px] mx-auto">
        
        <!-- Tampilan Loading Overlay -->
        <div x-show="isLoading" x-cloak class="fixed inset-0 bg-white/50 backdrop-blur-xs z-50 flex items-center justify-center pointer-events-none">
            <div class="w-10 h-10 border-4 border-brand border-t-transparent rounded-full animate-spin"></div>
        </div>

        <!-- Keranjang Kosong (Empty State) -->
        <div x-show="items.length === 0" x-cloak class="flex flex-col items-center justify-center py-20 text-center">
            <div class="w-20 h-20 rounded-full bg-warmCream flex items-center justify-center mb-6 text-brand">
                <span class="material-symbols-outlined !text-[36px]">shopping_bag</span>
            </div>
            <h2 class="text-xl font-semibold text-warmBlack mb-2 font-sans">Keranjang Belanja Anda Kosong</h2>
            <p class="text-xs text-warmGrey max-w-xs leading-relaxed mb-8">Temukan perhiasan dan aksesoris wanita berkualitas premium untuk menyempurnakan penampilan Anda.</p>
            <a href="/products" class="px-6 py-3 bg-brand hover:bg-brandDark text-white text-xs font-semibold uppercase tracking-wider rounded-btn transition-colors duration-250">
                Lihat Koleksi Produk
            </a>
        </div>

        <!-- Grid Layout untuk Keranjang Aktif -->
        <div x-show="items.length > 0" class="flex flex-col lg:flex-row gap-10 items-start">
            
            <!-- Kolom Kiri: Daftar Item Keranjang -->
            <div class="w-full lg:flex-1 space-y-6">
                @foreach($cart->items as $item)
                    <div x-show="items.some(i => i.id === {{ $item->id }})" 
                         class="flex flex-col sm:flex-row items-start sm:items-center justify-between border-b border-warmLightGrey/70 pb-6 last:border-b-0 space-y-4 sm:space-y-0">
                        
                        <!-- Informasi Produk & Gambar -->
                        <div class="flex items-center space-x-4 flex-1">
                            <div class="w-20 h-20 rounded-img overflow-hidden bg-warmCream flex-shrink-0 border border-warmLightGrey/50">
                                <img src="{{ $item->getPrimaryImage() }}" alt="{{ $item->product->name }}" class="w-full h-full object-cover">
                            </div>
                            <div>
                                <span class="text-[10px] font-semibold text-warmGrey tracking-[0.05em] uppercase block mb-1">
                                    {{ $item->product->category->name }}
                                </span>
                                <h3 class="text-sm font-semibold text-warmBlack hover:text-brand line-clamp-1 transition-colors">
                                    <a href="/products/{{ $item->product->slug }}">{{ $item->product->name }}</a>
                                </h3>
                                @if($item->variant)
                                    <span class="text-[11px] font-medium text-brand block mt-0.5">
                                        Varian: {{ $item->variant->name }}
                                    </span>
                                @endif
                                <span class="text-xs text-warmGrey block mt-1">
                                    @rupiah($item->getUnitPrice())
                                </span>
                            </div>
                        </div>

                        <!-- Kontrol Kuantitas & Subtotal Kanan -->
                        <div class="flex items-center justify-between sm:justify-end w-full sm:w-auto space-x-8">
                            <!-- Input Kuantitas (Aksesibilitas Minimum 44x44px) -->
                            <div class="flex items-center border border-warmLightGrey rounded-btn overflow-hidden h-11 bg-white">
                                <button @click="updateQuantity({{ $item->id }}, items.find(i => i.id === {{ $item->id }}).quantity - 1)"
                                        type="button" 
                                        class="w-11 h-11 flex items-center justify-center text-warmGrey hover:text-brand hover:bg-warmCream transition-colors"
                                        aria-label="Kurangi kuantitas">
                                    <span class="material-symbols-outlined !text-[16px]">remove</span>
                                </button>
                                <span class="w-10 text-center text-xs font-semibold text-warmBlack select-none"
                                      x-text="items.find(i => i.id === {{ $item->id }})?.quantity">
                                    {{ $item->quantity }}
                                </span>
                                <button @click="updateQuantity({{ $item->id }}, items.find(i => i.id === {{ $item->id }}).quantity + 1)"
                                        type="button" 
                                        class="w-11 h-11 flex items-center justify-center text-warmGrey hover:text-brand hover:bg-warmCream transition-colors"
                                        aria-label="Tambah kuantitas">
                                    <span class="material-symbols-outlined !text-[16px]">add</span>
                                </button>
                            </div>

                            <!-- Total Harga Per baris item -->
                            <div class="text-right min-w-[100px]">
                                <span class="text-sm font-semibold text-warmBlack block"
                                      x-text="formatRupiah(items.find(i => i.id === {{ $item->id }})?.subtotal ?? {{ $item->getSubtotal() }})">
                                    @rupiah($item->getSubtotal())
                                </span>
                            </div>

                            <!-- Tombol Hapus (Target Sentuh Min 44x44px) -->
                            <button @click="removeItem({{ $item->id }})" 
                                    class="w-11 h-11 flex items-center justify-center text-warmGrey hover:text-brand hover:bg-warmCream rounded-full transition-all"
                                    aria-label="Hapus item">
                                <span class="material-symbols-outlined !text-[20px]">delete</span>
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- Kolom Kanan: Ringkasan Belanja (Sticky) -->
            <div class="w-full lg:w-96 lg:sticky lg:top-28">
                <div class="bg-white border border-warmLightGrey rounded-card p-6 shadow-xs">
                    <h3 class="text-base font-semibold text-warmBlack mb-6 font-sans">Ringkasan Belanja</h3>
                    
                    <div class="space-y-4 text-xs">
                        <div class="flex justify-between text-warmGrey">
                            <span>Subtotal Produk</span>
                            <span x-text="formatRupiah(cartTotal)">@rupiah($cart->getTotalPrice())</span>
                        </div>
                        <div class="flex justify-between text-warmGrey">
                            <span>Biaya Pengiriman</span>
                            <span class="italic text-[11px]">Dihitung saat checkout</span>
                        </div>
                        
                        <div class="border-t border-warmLightGrey pt-4 flex justify-between items-center text-warmBlack">
                            <span class="font-semibold text-sm">Total Belanja</span>
                            <span class="font-bold text-lg text-brandDark" x-text="formatRupiah(cartTotal)">@rupiah($cart->getTotalPrice())</span>
                        </div>
                    </div>

                    <div class="mt-8 space-y-3">
                        <a href="/checkout" 
                           class="block w-full text-center py-3.5 bg-brand hover:bg-brandDark text-white font-semibold text-xs uppercase tracking-widest rounded-btn transition-colors duration-250">
                            Lanjut ke Checkout
                        </a>
                        <a href="/products" 
                           class="block w-full text-center py-3 border border-brand text-brand hover:bg-warmCream font-semibold text-xs uppercase tracking-widest rounded-btn transition-colors duration-250">
                            Lanjut Belanja
                        </a>
                    </div>
                </div>
            </div>

        </div>

    </section>
</div>

<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('cartPage', (initialCartTotal, initialItems) => ({
            cartTotal: initialCartTotal,
            items: initialItems,
            isLoading: false,

            formatRupiah(value) {
                return 'Rp ' + Number(value).toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
            },

            updateQuantity(itemId, newQty) {
                if (newQty < 1) return;
                this.isLoading = true;

                fetch('/cart/update', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        item_id: itemId,
                        quantity: newQty
                    })
                })
                .then(res => {
                    if (!res.ok) {
                        return res.json().then(err => { throw new Error(err.message || 'Gagal memperbarui kuantitas.'); });
                    }
                    return res.json();
                })
                .then(data => {
                    if (data.success) {
                        const item = this.items.find(i => i.id === itemId);
                        if (item) {
                            item.quantity = newQty;
                            item.subtotal = data.item_subtotal;
                        }
                        this.cartTotal = data.cart_total;

                        if (window.updateCartBadge) {
                            window.updateCartBadge(data.cart_count);
                        }
                        if (window.showToast) {
                            window.showToast(data.message, 'success');
                        }
                    }
                })
                .catch(err => {
                    if (window.showToast) {
                        window.showToast(err.message || 'Gagal memperbarui kuantitas.', 'error');
                    }
                })
                .finally(() => {
                    this.isLoading = false;
                });
            },

            removeItem(itemId) {
                if (!confirm('Apakah Anda yakin ingin menghapus produk ini dari keranjang?')) return;
                this.isLoading = true;

                fetch('/cart/remove', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        item_id: itemId
                    })
                })
                .then(res => {
                    if (!res.ok) {
                        return res.json().then(err => { throw new Error(err.message || 'Gagal menghapus item.'); });
                    }
                    return res.json();
                })
                .then(data => {
                    if (data.success) {
                        this.items = this.items.filter(i => i.id !== itemId);
                        this.cartTotal = data.cart_total;

                        if (window.updateCartBadge) {
                            window.updateCartBadge(data.cart_count);
                        }
                        if (window.showToast) {
                            window.showToast(data.message, 'info');
                        }
                    }
                })
                .catch(err => {
                    if (window.showToast) {
                        window.showToast(err.message || 'Gagal menghapus produk.', 'error');
                    }
                })
                .finally(() => {
                    this.isLoading = false;
                });
            }
        }));
    });
</script>
@endsection
