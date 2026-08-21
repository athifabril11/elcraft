@extends('layouts.auth-guest')
@section('title', 'Masuk')

@section('content')
<div class="bg-white rounded-2xl shadow-sm border border-[#E8B4AC]/40 p-8" style="width:100%;max-width:380px;">

    {{-- ─── Brand / Logo ─────────────────────────────────────────────────────── --}}
    <div class="text-center mb-7">
        {{-- TODO: replace with client logo image when available:
             <img src="{{ asset('images/logo.png') }}" alt="el Craft" class="h-10 mx-auto mb-3"> --}}
        <a href="/" class="text-[26px] font-bold text-[#C17B6F] tracking-tight font-sans inline-block mb-2">
            el Craft
        </a>
        <p class="text-sm font-medium" style="color:#A98077;">Masuk ke akun Anda</p>
    </div>

    {{-- ─── Session Status ────────────────────────────────────────────────────── --}}
    @if (session('status'))
        <div class="mb-4 text-sm font-medium text-green-600">
            {{ session('status') }}
        </div>
    @endif

    {{-- ─── Login Form ─────────────────────────────────────────────────────────── --}}
    <form method="POST" action="{{ route('login') }}" id="login-form">
        @csrf

        {{-- Email --}}
        <div class="mb-4">
            <label for="email" class="block text-xs font-medium mb-1.5" style="color:#5F5E5A;">
                Email
            </label>
            <input
                id="email"
                type="email"
                name="email"
                value="{{ old('email') }}"
                required
                autofocus
                autocomplete="username"
                style="width:100%;padding:10px 14px;font-size:14px;color:#9E5A4E;border:1px solid #E8B4AC;border-radius:12px;background:#fff;outline:none;transition:border-color .2s,box-shadow .2s;"
                placeholder="nama@email.com"
                onfocus="this.style.borderColor='#C17B6F';this.style.boxShadow='0 0 0 3px rgba(193,123,111,0.15)';"
                onblur="this.style.borderColor='#E8B4AC';this.style.boxShadow='none';"
            >
            @error('email')
                <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p>
            @enderror
        </div>

        {{-- Password with Show/Hide Toggle --}}
        <div class="mb-4">
            <label for="password" class="block text-xs font-medium mb-1.5" style="color:#5F5E5A;">
                Kata Sandi
            </label>
            <div x-data="{ showPassword: false }" style="position:relative;">
                <input
                    id="password"
                    name="password"
                    :type="showPassword ? 'text' : 'password'"
                    required
                    autocomplete="current-password"
                    style="width:100%;padding:10px 44px 10px 14px;font-size:14px;color:#9E5A4E;border:1px solid #E8B4AC;border-radius:12px;background:#fff;outline:none;transition:border-color .2s,box-shadow .2s;"
                    placeholder="••••••••"
                    onfocus="this.style.borderColor='#C17B6F';this.style.boxShadow='0 0 0 3px rgba(193,123,111,0.15)';"
                    onblur="this.style.borderColor='#E8B4AC';this.style.boxShadow='none';"
                >
                <button
                    type="button"
                    @click="showPassword = !showPassword"
                    tabindex="-1"
                    :aria-label="showPassword ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'"
                    style="position:absolute;top:0;right:0;bottom:0;width:44px;display:flex;align-items:center;justify-content:center;color:#A98077;background:none;border:none;cursor:pointer;padding:0;"
                    onmouseenter="this.style.color='#C17B6F'"
                    onmouseleave="this.style.color='#A98077'"
                >
                    {{-- Open eye — password hidden --}}
                    <svg x-show="!showPassword" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                        <circle cx="12" cy="12" r="3"/>
                    </svg>
                    {{-- Crossed eye — password visible --}}
                    <svg x-show="showPassword" x-cloak width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-11-8-11-8a18.5 18.5 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/>
                        <line x1="1" y1="1" x2="23" y2="23"/>
                    </svg>
                </button>
            </div>
            @error('password')
                <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p>
            @enderror
        </div>

        {{-- Remember Me + Forgot Password --}}
        <div class="flex items-center justify-between mb-6">
            <label for="remember_me" class="flex items-center gap-2 cursor-pointer select-none">
                <input
                    id="remember_me"
                    type="checkbox"
                    name="remember"
                    class="w-4 h-4 rounded cursor-pointer"
                    style="border-color:#E8B4AC;color:#C17B6F;"
                >
                <span class="text-xs font-medium" style="color:#A98077;">Ingat saya</span>
            </label>
            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}"
                   class="text-xs font-semibold transition-colors duration-200"
                   style="color:#C17B6F;"
                   onmouseenter="this.style.color='#9E5A4E'"
                   onmouseleave="this.style.color='#C17B6F'">
                    Lupa kata sandi?
                </a>
            @endif
        </div>

        {{-- Submit --}}
        <button
            type="submit"
            id="btn-login-submit"
            style="width:100%;padding:10px;background:#C17B6F;color:#fff;font-weight:600;font-size:14px;border:none;border-radius:12px;cursor:pointer;transition:background .2s;"
            onmouseenter="this.style.background='#9E5A4E'"
            onmouseleave="this.style.background='#C17B6F'">
            Masuk
        </button>
    </form>

    {{-- ─── Divider ────────────────────────────────────────────────────────────── --}}
    <div class="flex items-center gap-3 mt-5">
        <div class="flex-1" style="height:1px;background:#E8B4AC;"></div>
        <span class="text-xs font-medium" style="color:#A98077;">atau</span>
        <div class="flex-1" style="height:1px;background:#E8B4AC;"></div>
    </div>

    {{-- ─── Masuk dengan Google ────────────────────────────────────────────────── --}}
    <a href="{{ route('auth.google.redirect') }}"
       id="btn-google-login"
       class="flex items-center justify-center gap-3 mt-4"
       style="width:100%;padding:10px 16px;border:1px solid #E8B4AC;border-radius:12px;background:#fff;color:#9E5A4E;font-weight:600;font-size:14px;text-decoration:none;transition:background .2s;"
       onmouseenter="this.style.background='#F5F0EE'"
       onmouseleave="this.style.background='#fff'">
        {{-- Official Google "G" SVG Logo (4-color) --}}
        <svg width="18" height="18" viewBox="0 0 18 18" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <path d="M17.64 9.2c0-.637-.057-1.251-.164-1.84H9v3.481h4.844c-.209 1.125-.843 2.078-1.796 2.716v2.259h2.908c1.702-1.567 2.684-3.875 2.684-6.615z" fill="#4285F4"/>
            <path d="M9 18c2.43 0 4.467-.806 5.956-2.18l-2.908-2.259c-.806.54-1.837.86-3.048.86-2.344 0-4.328-1.584-5.036-3.711H.957v2.332A8.997 8.997 0 0 0 9 18z" fill="#34A853"/>
            <path d="M3.964 10.71A5.41 5.41 0 0 1 3.682 9c0-.593.102-1.17.282-1.71V4.958H.957A8.996 8.996 0 0 0 0 9c0 1.452.348 2.827.957 4.042l3.007-2.332z" fill="#FBBC05"/>
            <path d="M9 3.58c1.321 0 2.508.454 3.44 1.345l2.582-2.58C13.463.891 11.426 0 9 0A8.997 8.997 0 0 0 .957 4.958L3.964 7.29C4.672 5.163 6.656 3.58 9 3.58z" fill="#EA4335"/>
        </svg>
        Masuk dengan Google
    </a>

    {{-- ─── Register Link ─────────────────────────────────────────────────────── --}}
    <p class="mt-6 text-center text-xs" style="color:#A98077;">
        Belum punya akun?
        <a href="{{ route('register') }}"
           class="font-semibold transition-colors duration-200"
           style="color:#C17B6F;"
           onmouseenter="this.style.color='#9E5A4E'"
           onmouseleave="this.style.color='#C17B6F'">
            Daftar
        </a>
    </p>

</div>
@endsection
