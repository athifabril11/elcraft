@extends('layouts.storefront')

@section('title', $product->name . ' | el Craft')

@section('content')
<div x-data="{
    activeTab: 'desc',
    qty: 1,
    maxStock: {{ $product->stock }},
    selectedImage: '{{ $product->primary_image ?? 'https://images.unsplash.com/photo-1599643478518-a784e5dc4c8f?q=80&w=800&auto=format&fit=crop' }}',
    selectedVariants: {},
    basePrice: {{ $product->final_price }},
    additionalPrice: 0,
    isAdding: false,
    
    changeQty(delta) {
        this.qty = Math.max(1, Math.min(this.maxStock, this.qty + delta));
    },
    selectVariant(type, name, addPrice, stock, id) {
        this.selectedVariants[type] = { name, addPrice, stock, id };
        this.recalcPrice();
    },
    recalcPrice() {
        this.additionalPrice = Object.values(this.selectedVariants).reduce((sum, v) => sum + v.addPrice, 0);
        const stocks = Object.values(this.selectedVariants).map(v => v.stock);
        this.maxStock = stocks.length > 0 ? Math.min(...stocks) : {{ $product->stock }};
        if (this.qty > this.maxStock) this.qty = this.maxStock;
    },
    getTotalPrice() {
        return this.basePrice + this.additionalPrice;
    },
    formatRupiah(amount) {
        return 'Rp ' + new Intl.NumberFormat('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(amount);
    },
    handleAddToCart() {
        const requiredCount = {{ $product->variants->where('is_active', true)->groupBy('variant_type')->count() }};
        if (Object.keys(this.selectedVariants).length < requiredCount) {
            if (window.showToast) {
                window.showToast('Silakan pilih varian produk terlebih dahulu.', 'error');
            }
            return;
        }

        const variantId = requiredCount > 0 ? Object.values(this.selectedVariants)[0]?.id : null;
        this.isAdding = true;

        fetch('/cart/add', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({
                product_id: '{{ $product->id }}',
                variant_id: variantId,
                quantity: this.qty
            })
        })
        .then(res => {
            if (res.status === 401) {
                window.location.href = '/login?redirect=' + encodeURIComponent(window.location.pathname);
                return;
            }
            if (!res.ok) {
                return res.json().then(err => { throw new Error(err.message || 'Gagal menambahkan produk.'); });
            }
            return res.json();
        })
        .then(data => {
            if (data && data.success) {
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
                window.showToast(err.message || 'Gagal menambahkan produk.', 'error');
            }
        })
        .finally(() => {
            this.isAdding = false;
        });
    }
}" class="pb-24 md:pb-0">

    {{-- ─── PRODUCT MAIN SECTION ───────────────── --}}
    <section class="bg-white py-10 px-5 md:px-8 lg:px-16">
        <div class="max-w-[1280px] mx-auto flex flex-col lg:flex-row gap-12">

            {{-- LEFT: Image Gallery --}}
            <div class="lg:w-[52%] flex flex-col-reverse md:flex-row gap-4">

                {{-- Thumbnail Strip --}}
                <div class="flex md:flex-col gap-3 overflow-x-auto md:overflow-y-auto md:max-h-[520px] hide-scrollbar">
                    @forelse($product->images as $i => $img)
                        <button @click="selectedImage = '{{ $img->image_url }}'"
                            aria-label="Tampilkan foto produk {{ $i + 1 }}"
                            class="thumb-btn flex-shrink-0 w-16 h-16 md:w-20 md:h-20 rounded-img overflow-hidden border-2 transition-all duration-200 bg-warmCream"
                            :class="selectedImage === '{{ $img->image_url }}' ? 'border-brand' : 'border-warmLightGrey hover:border-brand/50'">
                            <img src="{{ $img->image_url }}" alt="Thumbnail {{ $i + 1 }}" class="w-full h-full object-cover">
                        </button>
                    @empty
                        <div class="flex-shrink-0 w-16 h-16 md:w-20 md:h-20 rounded-img overflow-hidden border-2 border-brand bg-warmCream flex items-center justify-center">
                            <span class="material-symbols-outlined text-warmGrey">image</span>
                        </div>
                    @endforelse
                </div>

                {{-- Main Image --}}
                <div class="flex-1 relative aspect-square rounded-img overflow-hidden bg-warmCream border border-warmLightGrey/50 group">
                    <img :src="selectedImage"
                        alt="{{ $product->name }}"
                        class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-105">

                    {{-- Discount Badge --}}
                    @if($product->isDiscountActive())
                        <span class="absolute top-4 left-4 bg-brand text-white text-xs font-semibold tracking-wider px-3 py-1 rounded-[4px]">
                            -{{ $product->discount_percent }}% OFF
                        </span>
                    @endif

                    {{-- Wishlist --}}
                    <button onclick="toggleWishlistItem(this, '{{ $product->id }}', '{{ addslashes($product->name) }}')"
                        data-product-id="{{ $product->id }}"
                        class="wishlist-btn absolute top-4 right-4 w-10 h-10 rounded-full bg-white shadow-sm border border-warmLightGrey/50 flex items-center justify-center text-warmGrey hover:text-brand hover:scale-110 transition-all duration-200"
                        aria-label="Add to Wishlist">
                        <span class="material-symbols-outlined !text-[22px]">favorite</span>
                    </button>
                </div>
            </div>

            {{-- RIGHT: Product Info --}}
            <div class="lg:w-[48%] flex flex-col space-y-6">

                {{-- Category + Badge --}}
                <div class="flex items-center justify-between">
                    <a href="/products?category={{ $product->category->slug }}"
                        class="text-xs font-semibold uppercase tracking-widest text-brand hover:text-brandDark transition-colors">
                        {{ $product->category->name }}
                    </a>
                    @if($product->stock > 0)
                        <span class="inline-flex items-center space-x-1 text-[10px] font-semibold text-emerald-700 bg-emerald-50 border border-emerald-200 px-2 py-1 rounded-full">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                            <span>Stok Tersedia</span>
                        </span>
                    @else
                        <span class="inline-flex items-center space-x-1 text-[10px] font-semibold text-red-600 bg-red-50 border border-red-200 px-2 py-1 rounded-full">
                            <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span>
                            <span>Habis</span>
                        </span>
                    @endif
                </div>

                {{-- Product Name --}}
                <h1 class="text-2xl md:text-3xl font-semibold text-warmBlack font-sans leading-snug">{{ $product->name }}</h1>

                {{-- Rating Summary --}}
                @php $avgRating = $product->average_rating; @endphp
                <div class="flex items-center space-x-3">
                    <div class="flex items-center space-x-0.5">
                        @for ($s = 1; $s <= 5; $s++)
                            @if ($s <= floor($avgRating))
                                <span class="material-symbols-outlined !text-[18px] text-amber-400" style="font-variation-settings:'FILL' 1,'wght' 400,'GRAD' 0,'opsz' 24">star</span>
                            @elseif ($s - $avgRating < 1 && $s - $avgRating > 0)
                                <span class="material-symbols-outlined !text-[18px] text-amber-400" style="font-variation-settings:'FILL' 1,'wght' 400,'GRAD' 0,'opsz' 24">star_half</span>
                            @else
                                <span class="material-symbols-outlined !text-[18px] text-warmLightGrey" style="font-variation-settings:'FILL' 0,'wght' 300,'GRAD' 0,'opsz' 24">star</span>
                            @endif
                        @endfor
                    </div>
                    <span class="text-sm font-semibold text-warmBlack">{{ number_format($avgRating, 1) }}</span>
                    <span class="text-xs text-warmGrey">({{ $product->reviews->count() }} ulasan)</span>
                    <a href="#reviews" @click.prevent="activeTab = 'review'; document.getElementById('reviews').scrollIntoView({ behavior: 'smooth' })" class="text-xs text-brand hover:text-brandDark underline underline-offset-2 transition-colors">Lihat ulasan</a>
                </div>

                {{-- Price Block --}}
                <div class="flex items-end space-x-3 py-4 border-t border-b border-warmLightGrey/70">
                    @if($product->isDiscountActive())
                        <span class="text-3xl font-semibold text-warmBlack" x-text="formatRupiah(getTotalPrice())">
                            @rupiah($product->final_price)
                        </span>
                        <div class="flex flex-col">
                            <span class="text-sm text-warmGrey line-through">@rupiah($product->price)</span>
                            <span class="text-xs font-semibold text-brand">Hemat @rupiah($product->price - $product->final_price)</span>
                        </div>
                    @else
                        <span class="text-3xl font-semibold text-warmBlack" x-text="formatRupiah(getTotalPrice())">
                            @rupiah($product->price)
                        </span>
                    @endif
                </div>

                {{-- Variant Picker --}}
                @if($product->variants->isNotEmpty())
                    @php
                        $variantGroups = $product->variants->groupBy('variant_type');
                    @endphp
                    @foreach($variantGroups as $type => $variants)
                        <div>
                            <label class="text-xs font-semibold uppercase tracking-wider text-warmGrey block mb-3">
                                Pilih {{ $type }}: <span class="text-warmBlack normal-case capitalize font-medium ml-1" x-text="selectedVariants['{{ $type }}']?.name || ''"></span>
                            </label>
                            <div class="flex flex-wrap gap-2">
                                @foreach($variants as $v)
                                    <button type="button"
                                        @click="selectVariant('{{ $type }}', '{{ $v->variant_name }}', {{ $v->additional_price }}, {{ $v->stock }}, {{ $v->id }})"
                                        class="variant-btn px-4 py-2 border rounded-btn text-xs font-medium transition-all duration-150 {{ $v->stock === 0 ? 'opacity-40 cursor-not-allowed line-through' : '' }}"
                                        :class="selectedVariants['{{ $type }}']?.name === '{{ $v->variant_name }}' ? 'border-brand text-brand bg-brand/5' : 'border-warmLightGrey text-warmBlack hover:border-brand hover:text-brand'"
                                        {{ $v->stock === 0 ? 'disabled' : '' }}>
                                        {{ $v->variant_name }}
                                        @if($v->additional_price > 0)
                                            <span class="text-warmGrey ml-1">+@rupiah($v->additional_price)</span>
                                        @endif
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                @endif

                {{-- Quantity + CTA --}}
                <div class="flex flex-col sm:flex-row gap-3 pt-2">
                    {{-- Qty Selector --}}
                    <div class="flex items-center border border-warmLightGrey rounded-btn overflow-hidden">
                        <button @click="changeQty(-1)" aria-label="Kurangi jumlah" class="w-10 h-11 flex items-center justify-center text-warmGrey hover:text-brand hover:bg-warmCream transition-colors border-r border-warmLightGrey">
                            <span class="material-symbols-outlined !text-[18px]">remove</span>
                        </button>
                        <input type="number" x-model.number="qty" readonly min="1" :max="maxStock" aria-label="Jumlah beli"
                            class="w-12 h-11 text-center text-sm font-semibold text-warmBlack border-none outline-none focus:ring-0 bg-white">
                        <button @click="changeQty(1)" aria-label="Tambah jumlah" class="w-10 h-11 flex items-center justify-center text-warmGrey hover:text-brand hover:bg-warmCream transition-colors border-l border-warmLightGrey">
                            <span class="material-symbols-outlined !text-[18px]">add</span>
                        </button>
                    </div>

                    {{-- Add to Cart --}}
                    <button @click="handleAddToCart()"
                        class="flex-1 py-3 bg-brand hover:bg-brandDark text-white font-semibold text-sm uppercase tracking-widest rounded-btn transition-colors duration-200 flex items-center justify-center space-x-2"
                        :class="maxStock === 0 || isAdding ? 'opacity-50 cursor-not-allowed' : ''"
                        :disabled="maxStock === 0 || isAdding">
                        <span x-show="isAdding" x-cloak class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                        <span x-show="!isAdding" class="material-symbols-outlined !text-[20px]">shopping_bag</span>
                        <span x-text="maxStock === 0 ? 'Habis Terjual' : (isAdding ? 'Menambahkan...' : 'Tambah ke Keranjang')">Tambah ke Keranjang</span>
                    </button>
                </div>

                {{-- Product Meta Info --}}
                <div class="grid grid-cols-2 gap-3 pt-2 border-t border-warmLightGrey/50">
                    <div class="flex items-center space-x-2 text-xs text-warmGrey">
                        <span class="material-symbols-outlined !text-[16px] text-brand">category</span>
                        <span>Kategori: <span class="text-warmBlack font-medium">{{ $product->category->name }}</span></span>
                    </div>
                    <div class="flex items-center space-x-2 text-xs text-warmGrey">
                        <span class="material-symbols-outlined !text-[16px] text-brand">scale</span>
                        <span>Berat: <span class="text-warmBlack font-medium">{{ $product->weight }} gram</span></span>
                    </div>
                    <div class="flex items-center space-x-2 text-xs text-warmGrey">
                        <span class="material-symbols-outlined !text-[16px] text-brand">inventory_2</span>
                        <span>Stok: <span class="text-warmBlack font-medium" x-text="maxStock"></span></span>
                    </div>
                    <div class="flex items-center space-x-2 text-xs text-warmGrey">
                        <span class="material-symbols-outlined !text-[16px] text-brand">local_shipping</span>
                        <span class="text-warmBlack font-medium">Gratis Ongkir</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ─── DESCRIPTION + REVIEWS TABS ─────────── --}}
    <section class="bg-white py-12 px-5 md:px-8 lg:px-16 border-t border-warmLightGrey/50">
        <div class="max-w-[1280px] mx-auto">

            {{-- Tab Switcher --}}
            <div class="flex border-b border-warmLightGrey mb-8 space-x-8">
                <button @click="activeTab = 'desc'" 
                    class="pb-3 text-sm font-semibold border-b-2 transition-all duration-200"
                    :class="activeTab === 'desc' ? 'border-brand text-brand' : 'border-transparent text-warmGrey hover:text-warmBlack'">
                    Deskripsi Produk
                </button>
                <button @click="activeTab = 'review'" 
                    class="pb-3 text-sm font-semibold border-b-2 transition-all duration-200"
                    :class="activeTab === 'review' ? 'border-brand text-brand' : 'border-transparent text-warmGrey hover:text-warmBlack'">
                    Ulasan ({{ $product->reviews->count() }})
                </button>
            </div>

            {{-- Description Tab --}}
            <div x-show="activeTab === 'desc'">
                <div class="prose prose-sm max-w-none text-warmGrey leading-relaxed text-sm">
                    {!! nl2br(e($product->description ?? 'Deskripsi produk belum tersedia.')) !!}
                </div>

                {{-- Advantages chips --}}
                <div class="mt-8 grid grid-cols-2 md:grid-cols-4 gap-4">
                    @foreach([
                        ['local_shipping', 'Pengiriman Cepat', 'Seluruh Indonesia'],
                        ['verified_user', 'Produk Original', 'Garansi keaslian'],
                        ['autorenew', 'Mudah Dikembalikan', 'Jika tidak sesuai'],
                        ['workspace_premium', 'Kualitas Premium', 'Kontrol kualitas ketat'],
                    ] as [$icon, $title, $sub])
                        <div class="flex items-start space-x-3 p-4 bg-warmCream rounded-card border border-warmLightGrey/40">
                            <span class="material-symbols-outlined !text-[20px] text-brand mt-0.5">{{ $icon }}</span>
                            <div>
                                <p class="text-xs font-semibold text-warmBlack">{{ $title }}</p>
                                <p class="text-[11px] text-warmGrey mt-0.5">{{ $sub }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Reviews Tab --}}
            <div x-show="activeTab === 'review'" id="reviews" x-cloak>
                @if($product->reviews->isEmpty())
                    <div class="flex flex-col items-center py-16 text-center">
                        <div class="w-14 h-14 rounded-full bg-warmCream flex items-center justify-center mb-4 text-warmGrey">
                            <span class="material-symbols-outlined !text-[28px]">chat_bubble_outline</span>
                        </div>
                        <p class="text-sm text-warmGrey">Belum ada ulasan untuk produk ini.</p>
                        <p class="text-xs text-warmGrey/60 mt-1">Jadilah yang pertama mengulas!</p>
                    </div>
                @else
                    {{-- Average Rating Big --}}
                    @php $avg = $product->average_rating; $reviewCount = $product->reviews->count(); @endphp
                    <div class="flex items-center space-x-6 mb-8 p-6 bg-warmCream rounded-card border border-warmLightGrey/40">
                        <div class="text-center">
                            <p class="text-5xl font-semibold text-warmBlack">{{ number_format($avg, 1) }}</p>
                            <div class="flex items-center justify-center space-x-0.5 mt-1">
                                @for ($s = 1; $s <= 5; $s++)
                                    <span class="material-symbols-outlined !text-[16px] {{ $s <= round($avg) ? 'text-amber-400' : 'text-warmLightGrey' }}" style="font-variation-settings:'FILL' 1,'wght' 400,'GRAD' 0,'opsz' 24">star</span>
                                @endfor
                            </div>
                            <p class="text-xs text-warmGrey mt-1">{{ $reviewCount }} ulasan</p>
                        </div>
                        {{-- Star Bars --}}
                        <div class="flex-1 space-y-1.5">
                            @for($s = 5; $s >= 1; $s--)
                                @php $cnt = $product->reviews->where('rating', $s)->count(); $pct = $reviewCount > 0 ? ($cnt / $reviewCount) * 100 : 0; @endphp
                                <div class="flex items-center space-x-3">
                                    <span class="text-xs text-warmGrey w-4">{{ $s }}</span>
                                    <span class="material-symbols-outlined !text-[14px] text-amber-400" style="font-variation-settings:'FILL' 1,'wght' 400,'GRAD' 0,'opsz' 24">star</span>
                                    <div class="flex-1 bg-warmLightGrey rounded-full h-1.5">
                                        <div class="bg-amber-400 h-1.5 rounded-full transition-all duration-500" style="width: {{ $pct }}%"></div>
                                    </div>
                                    <span class="text-xs text-warmGrey w-4">{{ $cnt }}</span>
                                </div>
                            @endfor
                        </div>
                    </div>

                    {{-- Review Cards --}}
                    <div class="space-y-5">
                        @foreach($product->reviews as $review)
                            <div class="p-5 border border-warmLightGrey/60 rounded-card bg-white hover:border-brand/20 transition-colors">
                                <div class="flex items-start justify-between mb-3">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-9 h-9 rounded-full bg-accent flex items-center justify-center text-brand font-semibold text-sm">
                                            {{ strtoupper(substr($review->user->name ?? 'A', 0, 1)) }}
                                        </div>
                                        <div>
                                            <p class="text-sm font-semibold text-warmBlack">{{ $review->user->name ?? 'Anonim' }}</p>
                                            <p class="text-[10px] text-warmGrey">Pembeli Terverifikasi</p>
                                        </div>
                                    </div>
                                    <div class="flex items-center space-x-0.5">
                                        @for($s = 1; $s <= 5; $s++)
                                            <span class="material-symbols-outlined !text-[14px] {{ $s <= $review->rating ? 'text-amber-400' : 'text-warmLightGrey' }}" style="font-variation-settings:'FILL' 1,'wght' 400,'GRAD' 0,'opsz' 24">star</span>
                                        @endfor
                                    </div>
                                </div>
                                @if($review->comment)
                                    <p class="text-sm text-warmGrey leading-relaxed">{{ $review->comment }}</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </section>

    {{-- ─── RELATED PRODUCTS ────────────────────── --}}
    @if($relatedProducts->isNotEmpty())
        <section class="bg-warmCream py-14 px-5 md:px-8 lg:px-16 border-t border-warmLightGrey/40">
            <div class="max-w-[1280px] mx-auto">
                <div class="flex justify-between items-center mb-8">
                    <span class="text-xs font-semibold text-warmGrey tracking-[0.05em] uppercase">Produk Serupa</span>
                    <a href="/products?category={{ $product->category->slug }}" class="text-sm font-semibold text-brand hover:text-brandDark flex items-center transition-colors uppercase tracking-wider">
                        Lihat Semua <span class="material-symbols-outlined ml-1 !text-sm">arrow_forward</span>
                    </a>
                </div>

                <div class="grid grid-cols-2 md:grid-cols-4 gap-5">
                    @foreach($relatedProducts as $rel)
                        @php
                            $relImg = $rel->primary_image ?? 'https://images.unsplash.com/photo-1599643478518-a784e5dc4c8f?q=80&w=600&auto=format&fit=crop';
                            $relDiscount = $rel->isDiscountActive();
                        @endphp
                        <div class="group relative flex flex-col bg-white border border-warmLightGrey rounded-card overflow-hidden transition-all duration-300 hover:border-brand/40">
                            <div class="relative aspect-square w-full bg-warmCream overflow-hidden">
                                <img src="{{ $relImg }}" alt="{{ $rel->name }}" class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-105">
                                @if($relDiscount)
                                    <span class="absolute top-2.5 left-2.5 bg-brand text-white text-[10px] font-semibold px-2 py-0.5 rounded-[4px]">-{{ $rel->discount_percent }}%</span>
                                @endif
                                <button onclick="toggleWishlistItem(this, '{{ $rel->id }}', '{{ addslashes($rel->name) }}')"
                                    data-product-id="{{ $rel->id }}"
                                    class="wishlist-btn absolute top-2.5 right-2.5 w-8 h-8 rounded-full bg-white flex items-center justify-center text-warmGrey hover:text-brand shadow-xs border border-warmLightGrey/50 md:opacity-0 md:group-hover:opacity-100 transition-all duration-300">
                                    <span class="material-symbols-outlined !text-[18px]">favorite</span>
                                </button>
                                <div class="absolute bottom-2.5 left-2.5 right-2.5 transform translate-y-4 opacity-0 group-hover:translate-y-0 group-hover:opacity-100 transition-all duration-300 hidden md:block">
                                    @if($rel->variants->isNotEmpty())
                                        <a href="/products/{{ $rel->slug }}" class="w-full py-2 bg-brand hover:bg-brandDark text-white font-semibold text-xs tracking-wider uppercase rounded-btn transition-colors flex items-center justify-center space-x-1.5">
                                            <span class="material-symbols-outlined !text-[16px]">shopping_bag</span>
                                            <span>ADD TO CART</span>
                                        </a>
                                    @else
                                        <button onclick="addToCart('{{ $rel->id }}', '{{ addslashes($rel->name) }}', '{{ $rel->final_price }}')" class="w-full py-2 bg-brand hover:bg-brandDark text-white font-semibold text-xs tracking-wider uppercase rounded-btn transition-colors flex items-center justify-center space-x-1.5">
                                            <span class="material-symbols-outlined !text-[16px]">shopping_bag</span>
                                            <span>ADD TO CART</span>
                                        </button>
                                    @endif
                                </div>
                            </div>
                            <div class="p-3 flex flex-col flex-grow">
                                <span class="text-[10px] font-semibold text-warmGrey uppercase tracking-wide mb-1">{{ $rel->category->name }}</span>
                                <h3 class="text-xs font-medium text-warmBlack hover:text-brand line-clamp-2 mb-2 transition-colors">
                                    <a href="/products/{{ $rel->slug }}">{{ $rel->name }}</a>
                                </h3>
                                <div class="flex items-center space-x-2 mt-auto">
                                    @if($relDiscount)
                                        <span class="text-[11px] text-warmGrey line-through">@rupiah($rel->price)</span>
                                        <span class="text-xs font-semibold text-warmBlack">@rupiah($rel->final_price)</span>
                                    @else
                                        <span class="text-xs font-semibold text-warmBlack">@rupiah($rel->price)</span>
                                    @endif
                                </div>
                            </div>
                            <div class="px-3 pb-3 md:hidden">
                                @if($rel->variants->isNotEmpty())
                                    <a href="/products/{{ $rel->slug }}" class="w-full py-1.5 bg-brand text-white font-semibold text-[11px] uppercase rounded-btn flex items-center justify-center space-x-1.5 active:bg-brandDark">
                                        <span class="material-symbols-outlined !text-[14px]">shopping_bag</span>
                                        <span>ADD TO CART</span>
                                    </a>
                                @else
                                    <button onclick="addToCart('{{ $rel->id }}', '{{ addslashes($rel->name) }}', '{{ $rel->final_price }}')" class="w-full py-1.5 bg-brand text-white font-semibold text-[11px] uppercase rounded-btn flex items-center justify-center space-x-1.5 active:bg-brandDark">
                                        <span class="material-symbols-outlined !text-[14px]">shopping_bag</span>
                                        <span>ADD TO CART</span>
                                    </button>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif
</div>
@endsection