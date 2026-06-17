@extends('layouts.storefront')

@section('title', 'el Craft | Timeless Elegance & Premium Accessories')

@section('content')
    <!-- 2. HERO SECTION (Auto-Sliding Banner) -->
    <section class="relative bg-warmCream overflow-hidden w-full h-[620px] md:h-[500px] lg:h-[600px] flex items-center">
        <div id="hero-slider" class="relative w-full h-full">
            <!-- Slide 1 -->
            <div class="hero-slide absolute inset-0 w-full h-full flex flex-col md:flex-row items-center justify-between opacity-100 transition-all duration-1000 z-10 px-5 md:px-8 lg:px-16 max-w-[1280px] mx-auto py-10 md:py-0">
                <div class="flex-1 md:pr-10 flex flex-col items-start text-left justify-center h-full order-2 md:order-1 mt-6 md:mt-0">
                    <span class="text-[12px] font-semibold uppercase text-brand tracking-[0.2em] mb-4">NEW COLLECTION 2024</span>
                    <h1 class="text-3xl md:text-4xl lg:text-5xl font-semibold text-warmBlack leading-tight mb-4 font-sans max-w-xl">Accessories for Every Moment</h1>
                    <p class="text-sm md:text-base text-warmGrey mb-8 leading-relaxed max-w-md">Handpicked pieces for the modern woman. Curated with love, crafted with care.</p>
                    <a href="/products" class="px-7 py-3.5 bg-brand hover:bg-brandDark text-white font-semibold text-xs tracking-widest uppercase rounded-btn transition-colors duration-300">EXPLORE COLLECTION</a>
                </div>
                <div class="flex-1 w-full h-1/2 md:h-full flex items-center justify-center order-1 md:order-2">
                    <div class="relative w-full h-full max-h-[250px] md:max-h-[360px] lg:max-h-[440px] overflow-hidden rounded-img bg-warmCream border border-warmLightGrey/50">
                        <img src="https://lh3.googleusercontent.com/aida-public/AB6AXuACgnNR_SNVVkyqhzyRl-TmSAoP_Y8vJ3aqpG7AB94XOfi9YqRjRaIyzbpLk9XcxwPmQTiUELlriwyF9dFYkUNw2d9R5JODd5tdtsHO_6B0qAuN4JowY143ywQUVCy4lmS9-MMg6VbNUfF49autKx0n838npvhTdsPxR1ak3s2qIQ6Uxta7dVn_UIoUvEpAKiNs4gLRJs3_eZeWPzq6z_2gJtDV31lnGb5HzyXiaJpxpgo-l6kYgSL-KsvbFOS8MMF2OZ9cFZGo29cj" alt="High-fashion minimalist photography shot of a woman wearing delicate gold jewelry" class="w-full h-full object-cover grayscale-[10%] opacity-90 transition-transform duration-700 hover:scale-105">
                    </div>
                </div>
            </div>

            <!-- Slide 2 -->
            <div class="hero-slide absolute inset-0 w-full h-full flex flex-col md:flex-row items-center justify-between opacity-0 transition-all duration-1000 z-0 px-5 md:px-8 lg:px-16 max-w-[1280px] mx-auto py-10 md:py-0">
                <div class="flex-1 md:pr-10 flex flex-col items-start text-left justify-center h-full order-2 md:order-1 mt-6 md:mt-0">
                    <span class="text-[12px] font-semibold uppercase text-brand tracking-[0.2em] mb-4">ELEGANT STATEMENT</span>
                    <h1 class="text-3xl md:text-4xl lg:text-5xl font-semibold text-warmBlack leading-tight mb-4 font-sans max-w-xl">Crafted to Sparkle and Last</h1>
                    <p class="text-sm md:text-base text-warmGrey mb-8 leading-relaxed max-w-md">Find necklaces, bracelets, and rings made from premium materials for your everyday style.</p>
                    <a href="/products" class="px-7 py-3.5 bg-brand hover:bg-brandDark text-white font-semibold text-xs tracking-widest uppercase rounded-btn transition-colors duration-300">SHOP NEW ARRIVALS</a>
                </div>
                <div class="flex-1 w-full h-1/2 md:h-full flex items-center justify-center order-1 md:order-2">
                    <div class="relative w-full h-full max-h-[250px] md:max-h-[360px] lg:max-h-[440px] overflow-hidden rounded-img bg-warmCream border border-warmLightGrey/50">
                        <img src="https://images.unsplash.com/photo-1599643478518-a784e5dc4c8f?q=80&w=1200&auto=format&fit=crop" alt="Necklaces closeup on textured linen" class="w-full h-full object-cover opacity-90 transition-transform duration-700 hover:scale-105">
                    </div>
                </div>
            </div>

            <!-- Slide 3 -->
            <div class="hero-slide absolute inset-0 w-full h-full flex flex-col md:flex-row items-center justify-between opacity-0 transition-all duration-1000 z-0 px-5 md:px-8 lg:px-16 max-w-[1280px] mx-auto py-10 md:py-0">
                <div class="flex-1 md:pr-10 flex flex-col items-start text-left justify-center h-full order-2 md:order-1 mt-6 md:mt-0">
                    <span class="text-[12px] font-semibold uppercase text-brand tracking-[0.2em] mb-4">EXCLUSIVE SELECTION</span>
                    <h1 class="text-3xl md:text-4xl lg:text-5xl font-semibold text-warmBlack leading-tight mb-4 font-sans max-w-xl">Timeless Beauty in Detail</h1>
                    <p class="text-sm md:text-base text-warmGrey mb-8 leading-relaxed max-w-md">Elevating everyday moments through artisanal precision and feminine designs.</p>
                    <a href="/products" class="px-7 py-3.5 bg-brand hover:bg-brandDark text-white font-semibold text-xs tracking-widest uppercase rounded-btn transition-colors duration-300">VIEW COLLECTION</a>
                </div>
                <div class="flex-1 w-full h-1/2 md:h-full flex items-center justify-center order-1 md:order-2">
                    <div class="relative w-full h-full max-h-[250px] md:max-h-[360px] lg:max-h-[440px] overflow-hidden rounded-img bg-warmCream border border-warmLightGrey/50">
                        <img src="https://images.unsplash.com/photo-1605100804763-247f67b3557e?q=80&w=1200&auto=format&fit=crop" alt="Premium rings stacking showcase" class="w-full h-full object-cover opacity-90 transition-transform duration-700 hover:scale-105">
                    </div>
                </div>
            </div>

            <!-- Dot Indicators (Active dot is Rose Gold pill shape) -->
            <div class="absolute bottom-6 left-1/2 transform -translate-x-1/2 z-20 flex space-x-3 items-center" role="tablist" aria-label="Pilihan Slide">
                <button onclick="goToSlide(0)" class="hero-dot w-8 h-2 rounded-full bg-brand transition-all duration-300" role="tab" aria-selected="true" aria-label="Tampilkan slide 1"></button>
                <button onclick="goToSlide(1)" class="hero-dot w-2 h-2 rounded-full bg-warmGrey/40 hover:bg-brand/50 transition-all duration-300" role="tab" aria-selected="false" aria-label="Tampilkan slide 2"></button>
                <button onclick="goToSlide(2)" class="hero-dot w-2 h-2 rounded-full bg-warmGrey/40 hover:bg-brand/50 transition-all duration-300" role="tab" aria-selected="false" aria-label="Tampilkan slide 3"></button>
            </div>
        </div>
    </section>

    <!-- 3. CATEGORY STRIP (Background: #FFFFFF) -->
    <section id="categories" class="bg-white py-16 px-5 md:px-8 lg:px-16 max-w-[1280px] mx-auto">
        <div class="mb-8 text-center md:text-left">
            <span class="text-xs font-semibold text-warmGrey tracking-[0.05em] uppercase">SHOP BY CATEGORY</span>
        </div>
        
        <!-- Horizontal scrollable strip on mobile, Grid on desktop -->
        <div class="flex md:grid md:grid-cols-6 gap-6 overflow-x-auto pb-4 md:pb-0 hide-scrollbar snap-x snap-mandatory">
            @php
                $categoryImages = [
                    'gelang' => 'https://images.unsplash.com/photo-1611591437281-460bfbe1220a?q=80&w=600&auto=format&fit=crop',
                    'kalung' => 'https://images.unsplash.com/photo-1599643478518-a784e5dc4c8f?q=80&w=600&auto=format&fit=crop',
                    'cincin' => 'https://images.unsplash.com/photo-1605100804763-247f67b3557e?q=80&w=600&auto=format&fit=crop',
                    'bros' => 'https://images.unsplash.com/photo-1535632066927-ab7c9ab60908?q=80&w=600&auto=format&fit=crop',
                    'anting' => 'https://images.unsplash.com/photo-1630019852942-f89202989a59?q=80&w=600&auto=format&fit=crop',
                    'aksesoris-rambut' => 'https://images.unsplash.com/photo-1576243345690-4e4b79b63288?q=80&w=600&auto=format&fit=crop',
                ];
            @endphp
            @foreach($categories as $cat)
                @php
                    $imgUrl = $cat->image ?? ($categoryImages[$cat->slug] ?? 'https://images.unsplash.com/photo-1515562141207-7a88fb7ce338?q=80&w=600&auto=format&fit=crop');
                @endphp
                <a href="/products?category={{ $cat->slug }}" class="flex-shrink-0 w-[140px] md:w-auto snap-start group bg-warmCream hover:bg-accent rounded-card p-5 flex flex-col items-center justify-between text-center transition-all duration-300 border border-warmLightGrey/30">
                    <div class="w-16 h-16 rounded-full overflow-hidden bg-white mb-4 flex items-center justify-center p-1 border border-warmLightGrey/50 group-hover:scale-105 transition-transform duration-300">
                        <img src="{{ $imgUrl }}" alt="{{ $cat->name }}" class="w-full h-full object-cover rounded-full">
                    </div>
                    <span class="text-[12px] font-semibold text-warmBlack uppercase tracking-[0.05em] group-hover:text-brandDark transition-colors duration-200">{{ $cat->name }}</span>
                </a>
            @endforeach
        </div>
    </section>

    <!-- 4. FEATURED PRODUCTS / NEW ARRIVALS (Background: #FFFFFF) -->
    <section class="bg-white py-16 px-5 md:px-8 lg:px-16 max-w-[1280px] mx-auto border-t border-warmLightGrey/50">
        <div class="flex justify-between items-center mb-10">
            <div>
                <span class="text-xs font-semibold text-warmGrey tracking-[0.05em] uppercase">NEW ARRIVALS</span>
            </div>
            <a href="/products" class="text-sm font-semibold text-brand hover:text-brandDark flex items-center transition-colors duration-200 uppercase tracking-wider">
                View All <span class="material-symbols-outlined ml-1 !text-sm">arrow_forward</span>
            </a>
        </div>
        
        <!-- 4-column grid (Desktop), 2-column grid (Mobile) -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-6">
            @foreach($featuredProducts as $product)
                @php
                    $imgUrl = $product->primary_image ?? 'https://images.unsplash.com/photo-1599643478518-a784e5dc4c8f?q=80&w=600&auto=format&fit=crop';
                    $isDiscount = $product->isDiscountActive();
                @endphp
                <!-- Product Card -->
                <div class="group relative flex flex-col justify-between bg-white border border-warmLightGrey rounded-card overflow-hidden transition-all duration-300 hover:border-brand/40">
                    <!-- Image Area -->
                    <div class="relative aspect-square w-full bg-warmCream overflow-hidden flex items-center justify-center">
                        <img src="{{ $imgUrl }}" alt="{{ $product->name }}" class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-105">
                        
                        <!-- Discount Badge -->
                        @if($isDiscount)
                            <span class="absolute top-3 left-3 bg-brand text-white text-[10px] font-semibold tracking-wider px-2 py-0.5 rounded-[4px]">-{{ $product->discount_percent }}%</span>
                        @endif

                        <!-- Wishlist Heart Icon -->
                        <button onclick="toggleWishlistItem(this, '{{ $product->id }}', '{{ $product->name }}')" data-product-id="{{ $product->id }}" class="wishlist-btn absolute top-3 right-3 w-8 h-8 rounded-full bg-white flex items-center justify-center text-warmGrey hover:text-brand hover:scale-110 shadow-xs border border-warmLightGrey/50 md:opacity-0 md:group-hover:opacity-100 transition-all duration-300" aria-label="Add to Wishlist">
                            <span class="material-symbols-outlined !text-[20px] transition-all">favorite</span>
                        </button>
                        
                        <!-- Add to Cart Full Width Button (appears on hover on desktop) -->
                        <div class="absolute bottom-3 left-3 right-3 transform translate-y-4 opacity-0 group-hover:translate-y-0 group-hover:opacity-100 transition-all duration-300 hidden md:block">
                            <button onclick="addToCart('{{ $product->id }}', '{{ $product->name }}', '{{ $product->final_price }}')" class="w-full py-2.5 bg-brand hover:bg-brandDark text-white font-semibold text-xs tracking-wider uppercase rounded-btn transition-colors duration-200">
                                Add to Cart
                            </button>
                        </div>
                    </div>
                    
                    <!-- Product Text Info -->
                    <div class="p-4 flex flex-col flex-grow">
                        <span class="text-[10px] font-semibold text-warmGrey tracking-[0.05em] uppercase mb-1">{{ $product->category->name }}</span>
                        <h3 class="text-sm font-medium text-warmBlack hover:text-brand line-clamp-1 mb-2 transition-colors duration-200">
                            <a href="/products/{{ $product->slug }}">{{ $product->name }}</a>
                        </h3>
                        
                        <div class="flex items-center space-x-2 mt-auto">
                            @if($isDiscount)
                                <span class="text-xs text-warmGrey line-through">Rp {{ number_format($product->price, 0, ',', '.') }}</span>
                                <span class="text-sm font-semibold text-warmBlack">Rp {{ number_format($product->final_price, 0, ',', '.') }}</span>
                            @else
                                <span class="text-sm font-semibold text-warmBlack">Rp {{ number_format($product->price, 0, ',', '.') }}</span>
                            @endif
                        </div>
                    </div>

                    <!-- Add to Cart for Mobile View (Visible under card text) -->
                    <div class="p-4 pt-0 md:hidden">
                        <button onclick="addToCart('{{ $product->id }}', '{{ $product->name }}', '{{ $product->final_price }}')" class="w-full py-2 bg-brand text-white font-semibold text-[11px] tracking-wider uppercase rounded-btn flex items-center justify-center space-x-1.5 active:bg-brandDark">
                            <span class="material-symbols-outlined !text-[16px]">shopping_bag</span>
                            <span>Add to Cart</span>
                        </button>
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    <!-- 6. STORE ADVANTAGES (Background: #FDF8F6) -->
    <section class="bg-warmCream py-16 px-5 md:px-8 lg:px-16">
        <div class="max-w-[1280px] mx-auto">
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-8">
                <!-- Advantage 1 -->
                <div class="flex flex-col items-center text-center p-4">
                    <div class="w-14 h-14 rounded-full bg-accent flex items-center justify-center mb-4 text-brand">
                        <span class="material-symbols-outlined !text-[26px]">local_shipping</span>
                    </div>
                    <h3 class="text-[12px] font-semibold text-warmBlack uppercase tracking-[0.05em] mb-2">Nationwide Shipping</h3>
                    <p class="text-xs text-warmGrey leading-relaxed max-w-[200px]">Pengiriman aman ke seluruh penjuru Indonesia.</p>
                </div>
                
                <!-- Advantage 2 -->
                <div class="flex flex-col items-center text-center p-4">
                    <div class="w-14 h-14 rounded-full bg-accent flex items-center justify-center mb-4 text-brand">
                        <span class="material-symbols-outlined !text-[26px]">verified</span>
                    </div>
                    <h3 class="text-[12px] font-semibold text-warmBlack uppercase tracking-[0.05em] mb-2">Original Products</h3>
                    <p class="text-xs text-warmGrey leading-relaxed max-w-[200px]">Jaminan produk asli langsung dari pengrajin terkurasi.</p>
                </div>
                
                <!-- Advantage 3 -->
                <div class="flex flex-col items-center text-center p-4">
                    <div class="w-14 h-14 rounded-full bg-accent flex items-center justify-center mb-4 text-brand">
                        <span class="material-symbols-outlined !text-[26px]">workspace_premium</span>
                    </div>
                    <h3 class="text-[12px] font-semibold text-warmBlack uppercase tracking-[0.05em] mb-2">Quality Guaranteed</h3>
                    <p class="text-xs text-warmGrey leading-relaxed max-w-[200px]">Setiap aksesoris melewati kontrol kualitas yang ketat.</p>
                </div>
                
                <!-- Advantage 4 -->
                <div class="flex flex-col items-center text-center p-4">
                    <div class="w-14 h-14 rounded-full bg-accent flex items-center justify-center mb-4 text-brand">
                        <span class="material-symbols-outlined !text-[26px]">swap_horiz</span>
                    </div>
                    <h3 class="text-[12px] font-semibold text-warmBlack uppercase tracking-[0.05em] mb-2">Easy Returns</h3>
                    <p class="text-xs text-warmGrey leading-relaxed max-w-[200px]">Garansi pengembalian mudah jika barang tidak sesuai.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- 7. NEWSLETTER (Background: #FFFFFF) -->
    <section class="bg-white py-20 px-5 text-center border-b border-warmLightGrey/50">
        <div class="max-w-[480px] mx-auto flex flex-col items-center">
            <h2 class="text-2xl font-semibold text-warmBlack mb-3 font-sans">Join the Inner Circle</h2>
            <p class="text-sm text-warmGrey mb-8 max-w-sm leading-relaxed">Be the first to discover new arrivals and exclusive drops.</p>
            
            <form onsubmit="handleSubscribe(event)" class="w-full flex flex-col sm:flex-row gap-4 items-center">
                <input type="email" placeholder="Email Address" aria-label="Alamat Email untuk Berlangganan" required class="flex-grow w-full bg-transparent border-t-0 border-l-0 border-r-0 border-b border-warmGrey focus:border-brand focus:ring-0 text-sm text-warmBlack py-2.5 px-0 placeholder-warmGrey/60 outline-none transition-colors duration-200">
                <button type="submit" class="w-full sm:w-auto px-6 py-2.5 bg-brand hover:bg-brandDark text-white font-semibold text-xs tracking-widest uppercase rounded-btn transition-colors duration-200">
                    Subscribe
                </button>
            </form>
        </div>
    </section>
@endsection

@push('scripts')
    <script>
        // Hero Carousel Animation Logic
        let currentSlide = 0;
        const slides = document.querySelectorAll('.hero-slide');
        const dots = document.querySelectorAll('.hero-dot');
        let slideInterval;

        function showSlide(index) {
            slides.forEach((slide, i) => {
                if (i === index) {
                    slide.classList.remove('opacity-0', 'z-0');
                    slide.classList.add('opacity-100', 'z-10');
                } else {
                    slide.classList.remove('opacity-100', 'z-10');
                    slide.classList.add('opacity-0', 'z-0');
                }
            });
            
            dots.forEach((dot, i) => {
                if (i === index) {
                    dot.className = 'hero-dot w-8 h-2 rounded-full bg-brand transition-all duration-300';
                } else {
                    dot.className = 'hero-dot w-2 h-2 rounded-full bg-warmGrey/40 hover:bg-brand/50 transition-all duration-300';
                }
            });
            currentSlide = index;
        }

        function goToSlide(index) {
            clearInterval(slideInterval);
            showSlide(index);
            startSlideTimer();
        }

        function startSlideTimer() {
            slideInterval = setInterval(() => {
                let nextSlide = (currentSlide + 1) % slides.length;
                showSlide(nextSlide);
            }, 5000);
        }

        window.addEventListener('DOMContentLoaded', () => {
            startSlideTimer();
        });
    </script>
@endpush