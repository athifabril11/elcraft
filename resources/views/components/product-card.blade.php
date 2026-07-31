@props(['product'])

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
            @if($product->variants->isNotEmpty())
                <a href="/products/{{ $product->slug }}" class="w-full py-2.5 bg-brand hover:bg-brandDark text-white font-semibold text-xs tracking-wider uppercase rounded-btn transition-colors duration-200 flex items-center justify-center space-x-1.5">
                    <span class="material-symbols-outlined !text-[16px]">shopping_bag</span>
                    <span>ADD TO CART</span>
                </a>
            @else
                <button onclick="addToCart('{{ $product->id }}', '{{ addslashes($product->name) }}', '{{ $product->final_price }}')" class="w-full py-2.5 bg-brand hover:bg-brandDark text-white font-semibold text-xs tracking-wider uppercase rounded-btn transition-colors duration-200 flex items-center justify-center space-x-1.5">
                    <span class="material-symbols-outlined !text-[16px]">shopping_bag</span>
                    <span>ADD TO CART</span>
                </button>
            @endif
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
                <span class="text-xs text-warmGrey line-through">@rupiah($product->price)</span>
                <span class="text-sm font-semibold text-warmBlack">@rupiah($product->final_price)</span>
            @else
                <span class="text-sm font-semibold text-warmBlack">@rupiah($product->price)</span>
            @endif
        </div>
    </div>

    <!-- Add to Cart for Mobile View (Visible under card text) -->
    <div class="p-4 pt-0 md:hidden">
        @if($product->variants->isNotEmpty())
            <a href="/products/{{ $product->slug }}" class="w-full py-2 bg-brand text-white font-semibold text-[11px] tracking-wider uppercase rounded-btn flex items-center justify-center space-x-1.5 active:bg-brandDark">
                <span class="material-symbols-outlined !text-[16px]">shopping_bag</span>
                <span>ADD TO CART</span>
            </a>
        @else
            <button onclick="addToCart('{{ $product->id }}', '{{ addslashes($product->name) }}', '{{ $product->final_price }}')" class="w-full py-2 bg-brand text-white font-semibold text-[11px] tracking-wider uppercase rounded-btn flex items-center justify-center space-x-1.5 active:bg-brandDark">
                <span class="material-symbols-outlined !text-[16px]">shopping_bag</span>
                <span>ADD TO CART</span>
            </button>
        @endif
    </div>
</div>
