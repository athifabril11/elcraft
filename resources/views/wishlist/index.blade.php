@extends('layouts.storefront')

@section('title', 'Wishlist Saya | el Craft')
@section('meta_description', 'Daftar produk aksesoris wanita premium el Craft yang Anda simpan di wishlist.')

@section('content')
<div class="bg-warmCream min-h-screen pb-24 md:pb-0">

    {{-- ─── HEADER ────────────────────────────────── --}}
    <section class="bg-white border-b border-warmLightGrey py-10 px-5 md:px-8 lg:px-16">
        <div class="max-w-[1280px] mx-auto">
            <nav class="flex items-center space-x-2 text-xs text-warmGrey mb-2" aria-label="Breadcrumb">
                <a href="/" class="hover:text-brand transition-colors">Beranda</a>
                <span class="material-symbols-outlined !text-[12px]">chevron_right</span>
                <span class="text-warmBlack font-medium">Wishlist Saya</span>
            </nav>
            <div class="flex items-center justify-between">
                <h1 class="text-2xl md:text-3xl font-semibold text-warmBlack font-sans">
                    Wishlist Saya
                    @if($wishlists->isNotEmpty())
                        <span class="text-base font-normal text-warmGrey ml-2">({{ $wishlists->count() }} produk)</span>
                    @endif
                </h1>
                @if($wishlists->isNotEmpty())
                    <a href="/products" class="hidden md:flex items-center gap-1.5 text-xs text-brand hover:text-brandDark font-semibold transition-colors">
                        <span class="material-symbols-outlined !text-[16px]">add</span>
                        Tambah Produk
                    </a>
                @endif
            </div>
        </div>
    </section>

    {{-- ─── KONTEN UTAMA ────────────────────────────── --}}
    <section class="max-w-[1280px] mx-auto px-5 md:px-8 lg:px-16 py-10">

        @if($wishlists->isEmpty())
            {{-- Empty State --}}
            <div class="flex flex-col items-center justify-center py-20 text-center bg-white rounded-card border border-warmLightGrey">
                <div class="w-20 h-20 rounded-full bg-warmCream flex items-center justify-center mb-6">
                    <span class="material-symbols-outlined text-brand !text-[36px]">favorite</span>
                </div>
                <h2 class="text-xl font-semibold text-warmBlack mb-2 font-sans">Wishlist Masih Kosong</h2>
                <p class="text-xs text-warmGrey max-w-xs leading-relaxed mb-8">
                    Simpan produk favorit Anda agar mudah ditemukan kembali. Klik ikon hati pada produk mana pun untuk menambahkannya.
                </p>
                <a href="/products"
                    class="px-6 py-3 bg-brand hover:bg-brandDark text-white text-xs font-semibold uppercase tracking-wider rounded-btn transition-colors duration-250">
                    Jelajahi Produk
                </a>
            </div>

        @else
            {{-- Wishlist Grid --}}
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4 md:gap-5" id="wishlist-grid">

                @foreach($wishlists as $item)
                    @if(!$item->product)
                        {{-- Produk telah dihapus — skip --}}
                        @continue
                    @endif

                    @php
                        $product   = $item->product;
                        $isOutOfStock = $product->stock <= 0;
                        $finalPrice   = $product->final_price;
                        $hasDiscount  = $product->isDiscountActive();
                    @endphp

                    <div id="wishlist-card-{{ $item->id }}"
                        class="group bg-white rounded-card border border-warmLightGrey hover:border-brand/30 hover:shadow-md transition-all duration-250 overflow-hidden flex flex-col"
                        x-data="wishlistCard({{ $item->id }})">

                        {{-- Thumbnail --}}
                        <div class="relative aspect-square overflow-hidden bg-warmCream">
                            <a href="{{ route('products.show', $product->slug) }}" class="block w-full h-full">
                                @if($product->primary_image)
                                    <img src="{{ $product->primary_image }}"
                                        alt="{{ $product->name }}"
                                        class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                                        loading="lazy">
                                @else
                                    <div class="w-full h-full flex items-center justify-center">
                                        <span class="material-symbols-outlined text-warmGrey !text-[48px]">image</span>
                                    </div>
                                @endif
                            </a>

                            {{-- Out-of-stock overlay --}}
                            @if($isOutOfStock)
                                <div class="absolute inset-0 bg-warmBlack/40 flex items-center justify-center">
                                    <span class="bg-white text-warmBlack text-[11px] font-bold uppercase tracking-wider px-3 py-1.5 rounded-full">
                                        Stok Habis
                                    </span>
                                </div>
                            @endif

                            {{-- Discount badge --}}
                            @if($hasDiscount && !$isOutOfStock)
                                <span class="absolute top-2 left-2 bg-brand text-white text-[10px] font-bold px-2 py-0.5 rounded-full">
                                    -{{ $product->discount_percent }}%
                                </span>
                            @endif

                            {{-- Remove from wishlist button --}}
                            <button
                                @click="removeFromWishlist()"
                                :disabled="removing"
                                class="absolute top-2 right-2 w-8 h-8 rounded-full bg-white/90 hover:bg-red-50 border border-warmLightGrey/50 flex items-center justify-center transition-all duration-200 shadow-sm"
                                aria-label="Hapus dari wishlist">
                                <span class="material-symbols-outlined !text-[16px]"
                                    :class="removing ? 'text-warmGrey animate-pulse' : 'text-red-400 hover:text-red-600'">
                                    delete
                                </span>
                            </button>
                        </div>

                        {{-- Product Info --}}
                        <div class="p-3 flex flex-col flex-1">
                            {{-- Category --}}
                            @if($product->category)
                                <p class="text-[10px] font-semibold uppercase tracking-wider text-warmGrey/70 mb-1">
                                    {{ $product->category->name }}
                                </p>
                            @endif

                            {{-- Name --}}
                            <a href="{{ route('products.show', $product->slug) }}"
                                class="text-xs font-semibold text-warmBlack hover:text-brand transition-colors line-clamp-2 leading-relaxed flex-1 mb-2">
                                {{ $product->name }}
                            </a>

                            {{-- Price --}}
                            <div class="flex items-baseline gap-1.5 mb-3">
                                <span class="text-sm font-bold text-brandDark">@rupiah($finalPrice)</span>
                                @if($hasDiscount)
                                    <span class="text-[11px] text-warmGrey line-through">@rupiah($product->price)</span>
                                @endif
                            </div>

                            {{-- Added at --}}
                            <p class="text-[10px] text-warmGrey/60 mb-3">
                                Disimpan {{ $item->created_at->diffForHumans() }}
                            </p>

                            {{-- Action Buttons --}}
                            @if($isOutOfStock)
                                <button disabled
                                    class="w-full py-2 bg-warmLightGrey text-warmGrey text-xs font-semibold uppercase tracking-wider rounded-btn cursor-not-allowed">
                                    Stok Habis
                                </button>
                            @else
                                <button
                                    @click="moveToCart({{ $product->id }}, '{{ addslashes($product->name) }}')"
                                    :disabled="movingToCart"
                                    class="w-full py-2 bg-brand hover:bg-brandDark text-white text-xs font-semibold uppercase tracking-wider rounded-btn transition-colors duration-200 flex items-center justify-center gap-1.5 disabled:opacity-60 disabled:cursor-not-allowed">
                                    <span x-show="movingToCart" class="material-symbols-outlined !text-[14px] animate-spin">sync</span>
                                    <span class="material-symbols-outlined !text-[14px]" x-show="!movingToCart">shopping_bag</span>
                                    <span x-text="movingToCart ? 'Menambahkan...' : 'Tambah ke Keranjang'"></span>
                                </button>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </section>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('wishlistCard', (wishlistId) => ({
            removing: false,
            movingToCart: false,

            async removeFromWishlist() {
                if (this.removing) return;
                this.removing = true;

                try {
                    const res = await fetch(`/wishlist/${wishlistId}`, {
                        method: 'DELETE',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json',
                        },
                    });
                    const data = await res.json();

                    if (data.success) {
                        // Update badge di navbar
                        window.updateWishlistBadge && window.updateWishlistBadge(data.count, data.ids);

                        // Animate dan hapus card dari DOM
                        const card = document.getElementById(`wishlist-card-${wishlistId}`);
                        if (card) {
                            card.style.transition = 'opacity 0.3s, transform 0.3s';
                            card.style.opacity = '0';
                            card.style.transform = 'scale(0.95)';
                            setTimeout(() => {
                                card.remove();
                                // Jika grid kosong, reload untuk tampilkan empty state
                                if (!document.querySelector('[id^="wishlist-card-"]')) {
                                    window.location.reload();
                                }
                            }, 300);
                        }
                        showToast('Produk dihapus dari wishlist.', 'info');
                    }
                } catch {
                    showToast('Gagal menghapus. Coba lagi.', 'error');
                    this.removing = false;
                }
            },

            async moveToCart(productId, productName) {
                if (this.movingToCart) return;
                this.movingToCart = true;

                try {
                    // Step 1: Tambah ke keranjang
                    const cartRes = await fetch('/cart/add', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                        body: JSON.stringify({ product_id: productId, variant_id: null, quantity: 1 }),
                    });
                    const cartData = await cartRes.json();

                    if (!cartData.success) {
                        showToast(cartData.message || 'Gagal menambahkan ke keranjang.', 'error');
                        this.movingToCart = false;
                        return;
                    }

                    // Update cart badge
                    window.updateCartBadge && window.updateCartBadge(cartData.cart_count);
                    showToast(`"${productName}" dipindahkan ke keranjang.`, 'success');

                    // Step 2: Hapus dari wishlist (hanya jika cart sukses)
                    const delRes = await fetch(`/wishlist/${wishlistId}`, {
                        method: 'DELETE',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json',
                        },
                    });
                    const delData = await delRes.json();

                    if (delData.success) {
                        window.updateWishlistBadge && window.updateWishlistBadge(delData.count, delData.ids);
                        const card = document.getElementById(`wishlist-card-${wishlistId}`);
                        if (card) {
                            card.style.transition = 'opacity 0.3s, transform 0.3s';
                            card.style.opacity = '0';
                            card.style.transform = 'scale(0.95)';
                            setTimeout(() => {
                                card.remove();
                                if (!document.querySelector('[id^="wishlist-card-"]')) {
                                    window.location.reload();
                                }
                            }, 300);
                        }
                    }
                } catch {
                    showToast('Terjadi kesalahan. Coba lagi.', 'error');
                    this.movingToCart = false;
                }
            },
        }));
    });

    // Global helper untuk update wishlist badge di navbar dari luar Alpine scope
    window.updateWishlistBadge = (count) => {
        if (window.Alpine) {
            const body = document.body;
            const data = Alpine.$data(body);
            if (data && typeof data.wishlistCount !== 'undefined') {
                data.wishlistCount = count;
            }
        }
        localStorage.setItem('elcraft_wishlist_count', count);
    };
</script>
@endpush
