@extends('layouts.storefront')

@section('title', 'Koleksi Aksesoris el Craft | Timeless Elegance')

@section('content')
    <!-- Breadcrumb & Header Section -->
    <section class="bg-warmCream py-10 px-5 md:px-8 lg:px-16 border-b border-warmLightGrey/50">
        <div class="max-w-[1280px] mx-auto flex flex-col md:flex-row justify-between items-start md:items-center space-y-4 md:space-y-0">
            <div>
                <nav class="flex items-center space-x-2 text-xs text-warmGrey mb-2">
                    <a href="/" class="hover:text-brand transition-colors">Beranda</a>
                    <span class="material-symbols-outlined !text-[12px]">chevron_right</span>
                    <span class="text-warmBlack font-medium">Produk</span>
                </nav>
                <h1 class="text-2xl md:text-3xl font-semibold text-warmBlack font-sans">Koleksi Aksesoris</h1>
            </div>
            @if(request()->anyFilled(['search', 'category', 'min_price', 'max_price', 'sort_by']))
                <a href="/products" class="inline-flex items-center space-x-1.5 text-xs text-brand hover:text-brandDark font-semibold uppercase tracking-wider transition-colors duration-200">
                    <span class="material-symbols-outlined !text-[16px]">close</span>
                    <span>Hapus Semua Filter</span>
                </a>
            @endif
        </div>
    </section>

    <!-- Main Listing Layout -->
    <section class="bg-white py-12 px-5 md:px-8 lg:px-16 max-w-[1280px] mx-auto">
        <div class="flex flex-col lg:flex-row gap-10">
            
            <!-- 1. SIDEBAR FILTER (Desktop View Only) -->
            <aside class="hidden lg:block w-64 flex-shrink-0">
                <form action="/products" method="GET" class="space-y-8 sticky top-28">
                    <!-- Keep hidden inputs to preserve active selections -->
                    @if(request('category'))
                        <input type="hidden" name="category" value="{{ request('category') }}">
                    @endif
                    @if(request('sort_by'))
                        <input type="hidden" name="sort_by" value="{{ request('sort_by') }}">
                    @endif

                    <!-- Pencarian Kata Kunci -->
                    <div>
                        <label for="search-input" class="text-xs font-semibold uppercase tracking-wider text-warmGrey block mb-3">Pencarian</label>
                        <div class="relative flex items-center border border-warmLightGrey rounded-btn px-3 py-2.5 bg-white focus-within:border-brand transition-colors">
                            <span class="material-symbols-outlined text-warmGrey !text-[18px] mr-2">search</span>
                            <input type="text" id="search-input" name="search" value="{{ request('search') }}" placeholder="Cari aksesoris..." class="w-full border-none outline-none focus:ring-0 text-sm text-warmBlack placeholder-warmGrey/40 p-0">
                        </div>
                    </div>

                    <!-- Kategori List -->
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wider text-warmGrey block mb-4">Kategori</label>
                        <div class="flex flex-col space-y-3">
                            <a href="{{ request()->fullUrlWithQuery(['category' => null, 'page' => null]) }}" class="text-sm flex items-center justify-between {{ !request('category') ? 'text-brand font-semibold' : 'text-warmBlack hover:text-brand' }} transition-colors duration-150">
                                <span>Semua Kategori</span>
                                <span class="text-xs text-warmGrey bg-warmCream px-2 py-0.5 rounded-full">{{ \App\Models\Product::where('is_active', true)->count() }}</span>
                            </a>
                            @foreach($categories as $cat)
                                <a href="{{ request()->fullUrlWithQuery(['category' => $cat->slug, 'page' => null]) }}" class="text-sm flex items-center justify-between {{ request('category') === $cat->slug ? 'text-brand font-semibold' : 'text-warmBlack hover:text-brand' }} transition-colors duration-150">
                                    <span>{{ $cat->name }}</span>
                                    <span class="text-xs text-warmGrey bg-warmCream px-2 py-0.5 rounded-full">{{ $cat->products()->where('is_active', true)->count() }}</span>
                                </a>
                            @endforeach
                        </div>
                    </div>

                    <!-- Rentang Harga -->
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wider text-warmGrey block mb-3">Rentang Harga (Rp)</label>
                        <div class="grid grid-cols-2 gap-2 mb-3">
                            <input type="number" name="min_price" value="{{ request('min_price') }}" placeholder="Min" class="w-full border border-warmLightGrey rounded-btn text-xs text-warmBlack px-3 py-2 focus:border-brand focus:ring-0">
                            <input type="number" name="max_price" value="{{ request('max_price') }}" placeholder="Max" class="w-full border border-warmLightGrey rounded-btn text-xs text-warmBlack px-3 py-2 focus:border-brand focus:ring-0">
                        </div>
                        <button type="submit" class="w-full py-2 bg-warmBlack hover:bg-brand text-white font-semibold text-xs uppercase tracking-wider rounded-btn transition-colors duration-350">Terapkan</button>
                    </div>
                </form>
            </aside>

            <!-- 2. PRODUCT GRID SECTION -->
            <div class="flex-grow">
                <!-- Sorting & Mobile Filter Toggle Header -->
                <div class="flex justify-between items-center mb-8 border-b border-warmLightGrey pb-4">
                    <span class="text-xs text-warmGrey">
                        Menampilkan {{ $products->firstItem() ?? 0 }} - {{ $products->lastItem() ?? 0 }} dari {{ $products->total() }} produk
                    </span>
                    
                    <div class="flex items-center space-x-3">
                        <!-- Mobile Filter Trigger -->
                        <button onclick="toggleMobileFilters()" aria-expanded="false" aria-controls="mobile-filter-drawer" class="lg:hidden flex items-center space-x-1.5 px-3 py-2 border border-warmLightGrey rounded-btn text-xs text-warmBlack hover:border-brand">
                            <span class="material-symbols-outlined !text-[16px]">filter_list</span>
                            <span>Filter</span>
                        </button>

                        <!-- Sorting Dropdown -->
                        <div class="flex items-center space-x-2">
                            <label for="sort-select" class="hidden md:inline-block text-xs text-warmGrey font-medium">Urutkan:</label>
                            <select id="sort-select" onchange="window.location.href = this.value" aria-label="Urutkan produk" class="border border-warmLightGrey rounded-btn text-xs text-warmBlack focus:border-brand focus:ring-0 py-1.5 pl-3 pr-8 bg-white cursor-pointer">
                                <option value="{{ request()->fullUrlWithQuery(['sort_by' => 'latest', 'page' => null]) }}" {{ request('sort_by') === 'latest' || !request('sort_by') ? 'selected' : '' }}>Terbaru</option>
                                <option value="{{ request()->fullUrlWithQuery(['sort_by' => 'price_low', 'page' => null]) }}" {{ request('sort_by') === 'price_low' ? 'selected' : '' }}>Harga: Rendah ke Tinggi</option>
                                <option value="{{ request()->fullUrlWithQuery(['sort_by' => 'price_high', 'page' => null]) }}" {{ request('sort_by') === 'price_high' ? 'selected' : '' }}>Harga: Tinggi ke Rendah</option>
                                <option value="{{ request()->fullUrlWithQuery(['sort_by' => 'popular', 'page' => null]) }}" {{ request('sort_by') === 'popular' ? 'selected' : '' }}>Terpopuler</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Products Grid -->
                @if($products->isEmpty())
                    <!-- Empty State -->
                    <div class="flex flex-col items-center justify-center py-20 text-center">
                        <div class="w-16 h-16 rounded-full bg-warmCream flex items-center justify-center mb-5 text-warmGrey">
                            <span class="material-symbols-outlined !text-[32px]">inventory_2</span>
                        </div>
                        <h3 class="text-lg font-semibold text-warmBlack mb-2 font-sans">Aksesoris Tidak Ditemukan</h3>
                        <p class="text-xs text-warmGrey max-w-xs leading-relaxed mb-6">Maaf, kami tidak menemukan aksesoris yang cocok dengan kriteria pencarian atau filter Anda.</p>
                        <a href="/products" class="px-5 py-2.5 bg-brand hover:bg-brandDark text-white text-xs font-semibold uppercase tracking-wider rounded-btn transition-colors">Lihat Semua Produk</a>
                    </div>
                @else
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-6">
                        @foreach($products as $product)
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

                    <!-- Custom Styled Pagination -->
                    @if ($products->hasPages())
                        <div class="mt-12 flex justify-center items-center space-x-2">
                            {{-- Previous Page Link --}}
                            @if ($products->onFirstPage())
                                <span class="w-10 h-10 rounded-btn border border-warmLightGrey flex items-center justify-center text-warmGrey cursor-not-allowed">
                                    <span class="material-symbols-outlined !text-[18px]">chevron_left</span>
                                </span>
                            @else
                                <a href="{{ $products->previousPageUrl() }}" class="w-10 h-10 rounded-btn border border-warmLightGrey hover:border-brand flex items-center justify-center text-warmBlack hover:text-brand transition-colors duration-200">
                                    <span class="material-symbols-outlined !text-[18px]">chevron_left</span>
                                </a>
                            @endif

                            {{-- Pagination Elements --}}
                            @foreach ($products->getUrlRange(1, $products->lastPage()) as $page => $url)
                                @if ($page == $products->currentPage())
                                    <span class="w-10 h-10 rounded-btn bg-brand text-white flex items-center justify-center font-semibold text-sm shadow-xs">{{ $page }}</span>
                                @else
                                    <a href="{{ $url }}" class="w-10 h-10 rounded-btn border border-warmLightGrey hover:border-brand flex items-center justify-center text-warmBlack hover:text-brand font-semibold text-sm transition-colors duration-200">{{ $page }}</a>
                                @endif
                            @endforeach

                            {{-- Next Page Link --}}
                            @if ($products->hasMorePages())
                                <a href="{{ $products->nextPageUrl() }}" class="w-10 h-10 rounded-btn border border-warmLightGrey hover:border-brand flex items-center justify-center text-warmBlack hover:text-brand transition-colors duration-200">
                                    <span class="material-symbols-outlined !text-[18px]">chevron_right</span>
                                </a>
                            @else
                                <span class="w-10 h-10 rounded-btn border border-warmLightGrey flex items-center justify-center text-warmGrey cursor-not-allowed">
                                    <span class="material-symbols-outlined !text-[18px]">chevron_right</span>
                                </span>
                            @endif
                        </div>
                    @endif
                @endif
            </div>

        </div>
    </section>

    <!-- 3. MOBILE FILTER DRAWER -->
    <div id="mobile-filter-drawer" class="fixed inset-0 z-50 bg-warmBlack/30 backdrop-blur-xs transition-all duration-300 opacity-0 pointer-events-none">
        <div class="bg-white w-80 h-full absolute left-0 top-0 shadow-lg p-6 flex flex-col justify-between transform -translate-x-full transition-transform duration-300">
            <div class="flex-grow overflow-y-auto hide-scrollbar pb-6">
                <div class="flex justify-between items-center mb-8">
                    <span class="text-lg font-semibold text-warmBlack">Penyaringan</span>
                    <button onclick="toggleMobileFilters()" class="text-warmBlack hover:text-brand flex items-center justify-center" aria-label="Close filters">
                        <span class="material-symbols-outlined !text-[24px]">close</span>
                    </button>
                </div>

                <form action="/products" method="GET" class="space-y-6">
                    @if(request('sort_by'))
                        <input type="hidden" name="sort_by" value="{{ request('sort_by') }}">
                    @endif

                    <!-- Pencarian -->
                    <div>
                        <label for="mobile-search-input" class="text-xs font-semibold uppercase tracking-wider text-warmGrey block mb-2">Pencarian</label>
                        <div class="relative flex items-center border border-warmLightGrey rounded-btn px-3 py-2 bg-white">
                            <input type="text" id="mobile-search-input" name="search" value="{{ request('search') }}" placeholder="Cari..." class="w-full border-none outline-none focus:ring-0 text-sm text-warmBlack placeholder-warmGrey/40 p-0">
                        </div>
                    </div>

                    <!-- Kategori -->
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wider text-warmGrey block mb-3">Kategori</label>
                        <div class="flex flex-col space-y-2">
                            <a href="{{ request()->fullUrlWithQuery(['category' => null, 'page' => null]) }}" class="text-sm {{ !request('category') ? 'text-brand font-semibold' : 'text-warmBlack' }}">
                                Semua Kategori
                            </a>
                            @foreach($categories as $cat)
                                <a href="{{ request()->fullUrlWithQuery(['category' => $cat->slug, 'page' => null]) }}" class="text-sm {{ request('category') === $cat->slug ? 'text-brand font-semibold' : 'text-warmBlack' }}">
                                    {{ $cat->name }}
                                </a>
                            @endforeach
                        </div>
                    </div>

                    <!-- Harga -->
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wider text-warmGrey block mb-2">Rentang Harga (Rp)</label>
                        <div class="grid grid-cols-2 gap-2 mb-4">
                            <input type="number" name="min_price" value="{{ request('min_price') }}" placeholder="Min" class="w-full border border-warmLightGrey rounded-btn text-xs text-warmBlack px-3 py-2 focus:border-brand focus:ring-0">
                            <input type="number" name="max_price" value="{{ request('max_price') }}" placeholder="Max" class="w-full border border-warmLightGrey rounded-btn text-xs text-warmBlack px-3 py-2 focus:border-brand focus:ring-0">
                        </div>
                        <button type="submit" class="w-full py-2.5 bg-brand hover:bg-brandDark text-white font-semibold text-xs uppercase tracking-wider rounded-btn transition-colors">Terapkan Filter</button>
                    </div>
                </form>
            </div>
            
            <div class="border-t border-warmLightGrey pt-4">
                <a href="/products" class="block w-full text-center py-2.5 border border-warmLightGrey text-warmGrey hover:text-warmBlack font-semibold text-xs uppercase tracking-wider rounded-btn transition-colors">Reset Filter</a>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        // Toggle Mobile Filter Drawer
        function toggleMobileFilters() {
            const drawer = document.getElementById('mobile-filter-drawer');
            const isHidden = drawer.classList.contains('pointer-events-none');
            
            if (isHidden) {
                drawer.classList.remove('opacity-0', 'pointer-events-none');
                drawer.firstElementChild.classList.remove('-translate-x-full');
            } else {
                drawer.classList.add('opacity-0', 'pointer-events-none');
                drawer.firstElementChild.classList.add('-translate-x-full');
            }
        }
    </script>
@endpush
