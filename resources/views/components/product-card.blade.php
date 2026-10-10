@props(['product'])

@php
    $imgUrl = $product->primary_image ?? 'https://images.unsplash.com/photo-1599643478518-a784e5dc4c8f?q=80&w=600&auto=format&fit=crop';
    $isDiscount = $product->isDiscountActive();
@endphp

<!-- Product Card -->
<div class="group relative flex flex-col justify-between h-full bg-white border border-warmLightGrey rounded-card overflow-hidden transition-all duration-300 hover:border-brand/40">
    <!-- Image Area -->
    <div class="relative aspect-square w-full bg-warmCream overflow-hidden flex items-center justify-center">
        <a href="/products/{{ $product->slug }}" class="block w-full h-full">
            <img src="{{ $imgUrl }}" alt="{{ $product->name }}" class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-105">
        </a>

        <!-- Discount Badge -->
        @if($isDiscount)
            <span class="absolute top-2 left-2 md:top-3 md:left-3 bg-brand text-white text-[9px] md:text-[10px] font-semibold tracking-wider px-1.5 md:px-2 py-0.5 rounded-[4px]">-{{ $product->discount_percent }}%</span>
        @endif

        <!-- Wishlist Heart Icon -->
        <button onclick="toggleWishlistItem(this, '{{ $product->id }}', '{{ $product->name }}')" data-product-id="{{ $product->id }}" class="wishlist-btn absolute top-2 right-2 md:top-3 md:right-3 w-9 h-9 md:w-10 md:h-10 rounded-full bg-white flex items-center justify-center text-warmGrey hover:text-brand hover:scale-110 shadow-xs border border-warmLightGrey/50 md:opacity-0 md:group-hover:opacity-100 transition-all duration-300" aria-label="Add to Wishlist">
            <span class="material-symbols-outlined !text-[18px] md:!text-[20px] transition-all">favorite</span>
        </button>

        <!-- Add to Cart Full Width Button (appears on hover on desktop) -->
        <div class="absolute bottom-2.5 left-2.5 right-2.5 md:bottom-3 md:left-3 md:right-3 transform translate-y-4 opacity-0 group-hover:translate-y-0 group-hover:opacity-100 transition-all duration-300 hidden md:block z-10 pointer-events-none group-hover:pointer-events-auto">
            @if($product->variants->isNotEmpty())
                <a href="/products/{{ $product->slug }}" class="w-full py-1.5 px-2 bg-brand hover:bg-brandDark text-white font-semibold text-xs rounded-btn transition-colors duration-200 flex items-center justify-center space-x-1 whitespace-nowrap shadow-sm">
                    <span class="material-symbols-outlined !text-[14px]">shopping_bag</span>
                    <span>+ Keranjang</span>
                </a>
            @else
                <button onclick="addToCart('{{ $product->id }}', '{{ addslashes($product->name) }}', '{{ $product->final_price }}')" class="w-full py-1.5 px-2 bg-brand hover:bg-brandDark text-white font-semibold text-xs rounded-btn transition-colors duration-200 flex items-center justify-center space-x-1 whitespace-nowrap shadow-sm cursor-pointer">
                    <span class="material-symbols-outlined !text-[14px]">shopping_bag</span>
                    <span>+ Keranjang</span>
                </button>
            @endif
        </div>
    </div>

    <!-- Product Text Info -->
    <div class="p-2.5 sm:p-3 md:p-4 flex flex-col flex-grow">
        <span class="text-[9px] md:text-[10px] font-semibold text-warmGrey tracking-[0.05em] uppercase mb-0.5 md:mb-1">{{ $product->category->name }}</span>
        <h3 class="text-xs md:text-sm font-medium text-warmBlack hover:text-brand line-clamp-1 mb-1.5 md:mb-2 transition-colors duration-200">
            <a href="/products/{{ $product->slug }}">{{ $product->name }}</a>
        </h3>

        <div class="flex items-center space-x-1.5 md:space-x-2 mt-auto">
            @if($isDiscount)
                <span class="text-[10px] md:text-xs text-warmGrey line-through">@rupiah($product->price)</span>
                <span class="text-xs md:text-sm font-semibold text-warmBlack">@rupiah($product->final_price)</span>
            @else
                <span class="text-xs md:text-sm font-semibold text-warmBlack">@rupiah($product->price)</span>
            @endif
        </div>
    </div>

    <!-- Add to Cart for Mobile View (Visible under card text) -->
    <div class="p-2.5 pt-0 sm:p-3 sm:pt-0 md:hidden">
        @if($product->variants->isNotEmpty())
            <a href="/products/{{ $product->slug }}" class="w-full py-1.5 sm:py-2 bg-brand text-white font-semibold text-[10px] sm:text-[11px] tracking-wider uppercase rounded-btn flex items-center justify-center space-x-1 active:bg-brandDark">
                <span class="material-symbols-outlined !text-[14px]">shopping_bag</span>
                <span>+ Keranjang</span>
            </a>
        @else
            <button onclick="addToCart('{{ $product->id }}', '{{ addslashes($product->name) }}', '{{ $product->final_price }}')" class="w-full py-1.5 sm:py-2 bg-brand text-white font-semibold text-[10px] sm:text-[11px] tracking-wider uppercase rounded-btn flex items-center justify-center space-x-1 active:bg-brandDark">
                <span class="material-symbols-outlined !text-[14px]">shopping_bag</span>
                <span>+ Keranjang</span>
            </button>
        @endif
    </div>
</div>
