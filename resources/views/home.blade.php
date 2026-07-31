@extends('layouts.storefront')

@section('title', 'el Craft | Timeless Elegance & Premium Accessories')

@section('meta_description', 'Temukan koleksi aksesoris wanita premium di el Craft — gelang, kalung, cincin, anting, dan bros dengan desain elegan. Gratis ongkir dan garansi keaslian produk.')

@section('content')
    <!-- 2. HERO SECTION (Auto-Sliding Banner) -->
    <section x-data="{
        currentSlide: 0,
        slidesCount: 3,
        timer: null,
        startTimer() {
            this.timer = setInterval(() => {
                this.currentSlide = (this.currentSlide + 1) % this.slidesCount;
            }, 6000);
        },
        goToSlide(index) {
            clearInterval(this.timer);
            this.currentSlide = index;
            this.startTimer();
        }
    }" x-init="startTimer()" class="relative bg-[#FAF6F3] overflow-hidden w-full h-[640px] md:h-[500px] lg:h-[580px] flex items-center">
        <div id="hero-slider" class="relative w-full h-full">
            <!-- Slide 1 (Exclusive Selection - Ring Showcase) -->
            <div class="hero-slide absolute inset-0 w-full h-full flex flex-col md:flex-row items-center justify-between transition-all duration-1000 px-6 md:px-12 lg:px-20 max-w-[1280px] mx-auto py-6 md:py-0 pb-16 md:pb-0"
                 :class="currentSlide === 0 ? 'opacity-100 z-10' : 'opacity-0 z-0'">
                <div class="flex-1 md:pr-12 flex flex-col items-start text-left justify-center h-full order-2 md:order-1 mt-4 md:mt-0 pb-4 md:pb-0">
                    <span class="text-[12px] font-bold uppercase text-[#C17B6F] tracking-[0.25em] mb-4">EXCLUSIVE SELECTION</span>
                    <h1 class="text-3xl md:text-5xl lg:text-6xl font-bold text-[#1C1917] leading-[1.12] mb-4 font-sans max-w-xl">Timeless Beauty in Detail</h1>
                    <p class="text-sm md:text-base text-[#6B6661] mb-6 leading-relaxed max-w-md">Elevating everyday moments through artisanal precision and feminine designs.</p>
                    <a href="/products" class="px-8 py-3.5 bg-[#C17B6F] hover:bg-[#A86458] text-white font-semibold text-xs tracking-widest uppercase rounded-lg transition-all duration-300 shadow-xs hover:shadow-md">VIEW COLLECTION</a>
                </div>
                <div class="flex-1 w-full h-1/2 md:h-full flex items-center justify-center order-1 md:order-2">
                    <div class="relative w-full max-w-[480px] aspect-[4/3] md:aspect-[5/4] lg:aspect-[4/3] overflow-hidden rounded-2xl bg-[#1C1917] shadow-lg">
                        <img src="/images/hero_ring.png" alt="Timeless Beauty Ring" class="w-full h-full object-cover transition-transform duration-700 hover:scale-105">
                    </div>
                </div>
            </div>

            <!-- Slide 2 (New Collection 2024) -->
            <div class="hero-slide absolute inset-0 w-full h-full flex flex-col md:flex-row items-center justify-between transition-all duration-1000 px-6 md:px-12 lg:px-20 max-w-[1280px] mx-auto py-6 md:py-0 pb-16 md:pb-0"
                 :class="currentSlide === 1 ? 'opacity-100 z-10' : 'opacity-0 z-0'" x-cloak>
                <div class="flex-1 md:pr-12 flex flex-col items-start text-left justify-center h-full order-2 md:order-1 mt-4 md:mt-0 pb-4 md:pb-0">
                    <span class="text-[12px] font-bold uppercase text-[#C17B6F] tracking-[0.25em] mb-4">NEW COLLECTION 2024</span>
                    <h1 class="text-3xl md:text-5xl lg:text-6xl font-bold text-[#1C1917] leading-[1.12] mb-4 font-sans max-w-xl">Accessories for Every Moment</h1>
                    <p class="text-sm md:text-base text-[#6B6661] mb-6 leading-relaxed max-w-md">Handpicked pieces for the modern woman. Curated with love, crafted with care.</p>
                    <a href="/products" class="px-8 py-3.5 bg-[#C17B6F] hover:bg-[#A86458] text-white font-semibold text-xs tracking-widest uppercase rounded-lg transition-all duration-300 shadow-xs hover:shadow-md">EXPLORE COLLECTION</a>
                </div>
                <div class="flex-1 w-full h-1/2 md:h-full flex items-center justify-center order-1 md:order-2">
                    <div class="relative w-full max-w-[480px] aspect-[4/3] md:aspect-[5/4] lg:aspect-[4/3] overflow-hidden rounded-2xl bg-[#1C1917] shadow-lg">
                        <img src="https://images.unsplash.com/photo-1599643478518-a784e5dc4c8f?q=80&w=1200&auto=format&fit=crop" alt="High-fashion minimalist photography shot of fine jewelry" class="w-full h-full object-cover transition-transform duration-700 hover:scale-105">
                    </div>
                </div>
            </div>

            <!-- Slide 3 (Elegant Statement) -->
            <div class="hero-slide absolute inset-0 w-full h-full flex flex-col md:flex-row items-center justify-between transition-all duration-1000 px-6 md:px-12 lg:px-20 max-w-[1280px] mx-auto py-6 md:py-0 pb-16 md:pb-0"
                 :class="currentSlide === 2 ? 'opacity-100 z-10' : 'opacity-0 z-0'" x-cloak>
                <div class="flex-1 md:pr-12 flex flex-col items-start text-left justify-center h-full order-2 md:order-1 mt-4 md:mt-0 pb-4 md:pb-0">
                    <span class="text-[12px] font-bold uppercase text-[#C17B6F] tracking-[0.25em] mb-4">ELEGANT STATEMENT</span>
                    <h1 class="text-3xl md:text-5xl lg:text-6xl font-bold text-[#1C1917] leading-[1.12] mb-4 font-sans max-w-xl">Crafted to Sparkle and Last</h1>
                    <p class="text-sm md:text-base text-[#6B6661] mb-6 leading-relaxed max-w-md">Find necklaces, bracelets, and rings made from premium materials for your everyday style.</p>
                    <a href="/products" class="px-8 py-3.5 bg-[#C17B6F] hover:bg-[#A86458] text-white font-semibold text-xs tracking-widest uppercase rounded-lg transition-all duration-300 shadow-xs hover:shadow-md">SHOP NEW ARRIVALS</a>
                </div>
                <div class="flex-1 w-full h-1/2 md:h-full flex items-center justify-center order-1 md:order-2">
                    <div class="relative w-full max-w-[480px] aspect-[4/3] md:aspect-[5/4] lg:aspect-[4/3] overflow-hidden rounded-2xl bg-[#1C1917] shadow-lg">
                        <img src="https://images.unsplash.com/photo-1605100804763-247f67b3557e?q=80&w=1200&auto=format&fit=crop" alt="Premium rings stacking showcase" class="w-full h-full object-cover transition-transform duration-700 hover:scale-105">
                    </div>
                </div>
            </div>

            <!-- Dot Indicators (Active dot is Rose Gold pill shape) -->
            <div class="hero-dots-container absolute bottom-4 md:bottom-6 left-1/2 transform -translate-x-1/2 z-20 flex space-x-3 items-center" role="tablist" aria-label="Pilihan Slide">
                <button @click="goToSlide(0)" class="hero-dot rounded-full transition-all duration-300" :class="currentSlide === 0 ? 'w-8 h-2 bg-[#C17B6F]' : 'w-2 h-2 bg-[#D5CECB] hover:bg-[#C17B6F]/50'" role="tab" :aria-selected="currentSlide === 0" aria-label="Tampilkan slide 1"></button>
                <button @click="goToSlide(1)" class="hero-dot rounded-full transition-all duration-300" :class="currentSlide === 1 ? 'w-8 h-2 bg-[#C17B6F]' : 'w-2 h-2 bg-[#D5CECB] hover:bg-[#C17B6F]/50'" role="tab" :aria-selected="currentSlide === 1" aria-label="Tampilkan slide 2"></button>
                <button @click="goToSlide(2)" class="hero-dot rounded-full transition-all duration-300" :class="currentSlide === 2 ? 'w-8 h-2 bg-[#C17B6F]' : 'w-2 h-2 bg-[#D5CECB] hover:bg-[#C17B6F]/50'" role="tab" :aria-selected="currentSlide === 2" aria-label="Tampilkan slide 3"></button>
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
                <x-product-card :product="$product" />
            @endforeach
        </div>
    </section>

    <!-- 5. PRODUK TERPOPULER -->
    <section class="bg-warmCream py-16 px-5 md:px-8 lg:px-16 max-w-[1280px] mx-auto border-t border-warmLightGrey/50">
        <div class="flex justify-between items-center mb-10">
            <div>
                <span class="text-xs font-semibold text-warmGrey tracking-[0.05em] uppercase">Produk Terpopuler</span>
            </div>
            <a href="/products" class="text-sm font-semibold text-brand hover:text-brandDark flex items-center transition-colors duration-200 uppercase tracking-wider">
                Lihat Semua <span class="material-symbols-outlined ml-1 !text-sm">arrow_forward</span>
            </a>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-6">
            @foreach($popularProducts as $product)
                <x-product-card :product="$product" />
            @endforeach
        </div>
    </section>

    <!-- 6. SEDANG DISKON -->
    @if($discountedProducts->isNotEmpty())
    <section class="bg-white py-16 px-5 md:px-8 lg:px-16 max-w-[1280px] mx-auto border-t border-warmLightGrey/50">
        <div class="flex justify-between items-center mb-10">
            <div>
                <span class="text-xs font-semibold text-warmGrey tracking-[0.05em] uppercase">Sedang Diskon</span>
            </div>
            <a href="/products" class="text-sm font-semibold text-brand hover:text-brandDark flex items-center transition-colors duration-200 uppercase tracking-wider">
                Lihat Semua <span class="material-symbols-outlined ml-1 !text-sm">arrow_forward</span>
            </a>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-6">
            @foreach($discountedProducts as $product)
                <x-product-card :product="$product" />
            @endforeach
        </div>
    </section>
    @endif

    <!-- 7. STORE ADVANTAGES (Background: #FDF8F6) -->
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