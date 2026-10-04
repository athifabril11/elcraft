@extends('layouts.auth-guest')
@section('title', 'Masuk')

@section('content')
{{-- ─── Brand / Logo ─────────────────────────────────────────────────────── --}}
<div style="text-align:center; margin-bottom:18px;">
    <a href="/" style="
        font-size: 24px;
        font-weight: 700;
        color: #C17B6F;
        letter-spacing: -0.5px;
        text-decoration: none;
        display: inline-block;
        margin-bottom: 4px;
    ">el Craft</a>
    <p style="font-size:12px; font-weight:500; color:#A98077; margin:0;">Masuk ke akun Anda</p>
</div>

{{-- ─── Session Status ────────────────────────────────────────────────────── --}}
@if (session('status'))
    <div x-data="{ show: true }" x-init="setTimeout(() => show = false, 4000)" x-show="show" x-transition.duration.300ms style="
        margin-bottom: 14px;
        padding: 8px 12px;
        border-radius: 10px;
        border: 1px solid #BBF7D0;
        background: #F0FDF4;
        color: #15803D;
        font-size: 11.5px;
        font-weight: 500;
        text-align: center;
    ">
        {{ session('status') }}
    </div>
@endif

{{-- ─── Login Form ─────────────────────────────────────────────────────────── --}}
<form method="POST" action="{{ route('login') }}" id="login-form">
    @csrf

    {{-- Email --}}
    <div style="margin-bottom:12px;">
        <label for="email" style="
            display: block;
            font-size: 11.5px;
            font-weight: 500;
            color: #5F5E5A;
            margin-bottom: 5px;
        ">Email</label>
        <div style="position:relative;">
            <span style="
                position: absolute;
                top: 50%;
                left: 12px;
                transform: translateY(-50%);
                display: flex;
                align-items: center;
                pointer-events: none;
            ">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none"
                     stroke="#C8A09A" stroke-width="2"
                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <rect x="2" y="4" width="20" height="16" rx="2"/>
                    <polyline points="2,4 12,13 22,4"/>
                </svg>
            </span>
            <input
                id="email"
                type="email"
                name="email"
                value="{{ old('email') }}"
                required
                autofocus
                autocomplete="username"
                placeholder="nama@email.com"
                style="
                    width: 100%;
                    padding: 9px 12px 9px 34px;
                    font-size: 13px;
                    color: #9E5A4E;
                    border: 1px solid #E8B4AC;
                    border-radius: 10px;
                    background: #fff;
                    outline: none;
                    transition: border-color .2s, box-shadow .2s;
                    box-sizing: border-box;
                "
                onfocus="this.style.borderColor='#C17B6F';this.style.boxShadow='0 0 0 3px rgba(193,123,111,0.15)';"
                onblur="this.style.borderColor='#E8B4AC';this.style.boxShadow='none';"
            >
        </div>
        @error('email')
            <p style="margin-top:4px; font-size:11px; color:#ef4444;">{{ $message }}</p>
        @enderror
    </div>

    {{-- Password with Show/Hide Toggle --}}
    <div style="margin-bottom:12px;">
        <label for="password" style="
            display: block;
            font-size: 11.5px;
            font-weight: 500;
            color: #5F5E5A;
            margin-bottom: 5px;
        ">Kata Sandi</label>
        <div x-data="{ showPassword: false }" style="position:relative;">
            <input
                id="password"
                name="password"
                :type="showPassword ? 'text' : 'password'"
                required
                autocomplete="current-password"
                placeholder="••••••••"
                style="
                    width: 100%;
                    padding: 9px 36px 9px 12px;
                    font-size: 13px;
                    color: #9E5A4E;
                    border: 1px solid #E8B4AC;
                    border-radius: 10px;
                    background: #fff;
                    outline: none;
                    transition: border-color .2s, box-shadow .2s;
                    box-sizing: border-box;
                "
                onfocus="this.style.borderColor='#C17B6F';this.style.boxShadow='0 0 0 3px rgba(193,123,111,0.15)';"
                onblur="this.style.borderColor='#E8B4AC';this.style.boxShadow='none';"
            >
            <button
                type="button"
                @click="showPassword = !showPassword"
                tabindex="-1"
                :aria-label="showPassword ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'"
                style="
                    position: absolute;
                    top: 0;
                    right: 0;
                    bottom: 0;
                    width: 36px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    color: #C8A09A;
                    background: none;
                    border: none;
                    cursor: pointer;
                    padding: 0;
                    transition: color .2s;
                "
                onmouseenter="this.style.color='#C17B6F'"
                onmouseleave="this.style.color='#C8A09A'"
            >
                <svg x-show="!showPassword" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                    <circle cx="12" cy="12" r="3"/>
                </svg>
                <svg x-show="showPassword" x-cloak width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-11-8-11-8a18.5 18.5 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/>
                    <line x1="1" y1="1" x2="23" y2="23"/>
                </svg>
            </button>
        </div>
        @error('password')
            <p style="margin-top:4px; font-size:11px; color:#ef4444;">{{ $message }}</p>
        @enderror
    </div>

    {{-- Remember Me + Forgot Password --}}
    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:16px;">
        <label for="remember_me" style="display:flex; align-items:center; gap:6px; cursor:pointer; user-select:none;">
            <input
                id="remember_me"
                type="checkbox"
                name="remember"
                style="width:14px; height:14px; accent-color:#C17B6F; cursor:pointer;"
            >
            <span style="font-size:11.5px; font-weight:500; color:#A98077;">Ingat saya</span>
        </label>
        @if (Route::has('password.request'))
            <a href="{{ route('password.request') }}"
               style="font-size:11.5px; font-weight:600; color:#C17B6F; text-decoration:none; transition:color .2s;"
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
        style="
            width: 100%;
            padding: 10.5px 16px;
            background: #C17B6F;
            color: #fff;
            font-size: 13.5px;
            font-weight: 600;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            transition: background .2s;
        "
        onmouseenter="this.style.background='#9E5A4E'"
        onmouseleave="this.style.background='#C17B6F'">
        Masuk
    </button>
</form>

{{-- ─── Divider ────────────────────────────────────────────────────────────── --}}
<div style="display:flex; align-items:center; gap:10px; margin-top:14px; margin-bottom:12px;">
    <div style="flex:1; height:1px; background:#E8B4AC;"></div>
    <span style="font-size:11.5px; font-weight:500; color:#A98077;">atau</span>
    <div style="flex:1; height:1px; background:#E8B4AC;"></div>
</div>

{{-- ─── Masuk dengan Google ────────────────────────────────────────────────── --}}
<a href="{{ route('auth.google.redirect') }}"
   id="btn-google-login"
   style="
       display: flex;
       align-items: center;
       justify-content: center;
       gap: 8px;
       width: 100%;
       padding: 9.5px 16px;
       border: 1px solid #E8B4AC;
       border-radius: 10px;
       background: #fff;
       color: #9E5A4E;
       font-weight: 600;
       font-size: 13px;
       text-decoration: none;
       box-sizing: border-box;
       transition: background .2s;
   "
   onmouseenter="this.style.background='#F5F0EE'"
   onmouseleave="this.style.background='#fff'">
    <svg width="16" height="16" viewBox="0 0 18 18" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
        <path d="M17.64 9.2c0-.637-.057-1.251-.164-1.84H9v3.481h4.844c-.209 1.125-.843 2.078-1.796 2.716v2.259h2.908c1.702-1.567 2.684-3.875 2.684-6.615z" fill="#4285F4"/>
        <path d="M9 18c2.43 0 4.467-.806 5.956-2.18l-2.908-2.259c-.806.54-1.837.86-3.048.86-2.344 0-4.328-1.584-5.036-3.711H.957v2.332A8.997 8.997 0 0 0 9 18z" fill="#34A853"/>
        <path d="M3.964 10.71A5.41 5.41 0 0 1 3.682 9c0-.593.102-1.17.282-1.71V4.958H.957A8.996 8.996 0 0 0 0 9c0 1.452.348 2.827.957 4.042l3.007-2.332z" fill="#FBBC05"/>
        <path d="M9 3.58c1.321 0 2.508.454 3.44 1.345l2.582-2.58C13.463.891 11.426 0 9 0A8.997 8.997 0 0 0 .957 4.958L3.964 7.29C4.672 5.163 6.656 3.58 9 3.58z" fill="#EA4335"/>
    </svg>
    Masuk dengan Google
</a>

{{-- ─── Register Link ─────────────────────────────────────────────────────── --}}
<p style="margin-top:14px; text-align:center; font-size:11.5px; color:#A98077; margin-bottom:0;">
    Belum punya akun?
    <a href="{{ route('register') }}"
       style="font-weight:600; color:#C17B6F; text-decoration:none; transition:color .2s;"
       onmouseenter="this.style.color='#9E5A4E'"
       onmouseleave="this.style.color='#C17B6F'">
        Daftar
    </a>
</p>
@endsection
