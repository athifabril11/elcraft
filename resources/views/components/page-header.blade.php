@props([
    'title',
    'backUrl' => null,
    'subtitle' => null,
])

@php
    $fallback = $backUrl ?? '/';
@endphp

<section class="bg-white pt-4 md:pt-6 pb-2 px-5 md:px-8 lg:px-16">
    <div class="max-w-[1280px] mx-auto flex items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <a href="{{ $fallback }}"
                onclick="if (window.history.length > 1 && document.referrer && document.referrer.indexOf(window.location.host) !== -1) { window.history.back(); event.preventDefault(); } else { window.location.replace('{{ $fallback }}'); event.preventDefault(); }"
                class="w-8 h-8 rounded-full inline-flex items-center justify-center transition-opacity duration-200 hover:opacity-90 shrink-0 cursor-pointer"
                style="background-color:#F6E3DF; border:none;"
                aria-label="Kembali">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M10 4L6 8L10 12" stroke="#993C1D" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </a>

            <h1 class="text-xl md:text-2xl font-semibold text-warmBlack font-sans leading-none flex items-center gap-2">
                <span>{{ $title }}</span>
                @if(isset($subtitle))
                    <span class="text-xs md:text-sm font-normal text-warmGrey font-sans">{{ $subtitle }}</span>
                @endif
            </h1>
        </div>

        @if(isset($slot) && $slot->isNotEmpty())
            <div class="flex items-center">
                {{ $slot }}
            </div>
        @endif
    </div>
</section>
