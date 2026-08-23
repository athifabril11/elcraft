@extends('layouts.auth-guest')
@section('title', 'Konfirmasi Kata Sandi')

@section('content')
{{-- ─── Brand ──────────────────────────────────────────────────────────────── --}}
<div style="text-align:center; margin-bottom:12px;">
    <a href="/" style="
        font-size: 22px;
        font-weight: 700;
        color: #C17B6F;
        letter-spacing: -0.5px;
        text-decoration: none;
        display: inline-block;
    ">el Craft</a>
</div>

{{-- ─── Lock Icon ───────────────────────────────────────────────────────────── --}}
<div style="display:flex; justify-content:center; margin-bottom:12px;">
    <span style="
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 48px;
        height: 48px;
        border-radius: 50%;
        background: #F5F0EE;
    ">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none"
             stroke="#C17B6F" stroke-width="1.75"
             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
            <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
        </svg>
    </span>
</div>

{{-- ─── Title & Description ─────────────────────────────────────────────────── --}}
<div style="text-align:center; margin-bottom:16px;">
    <h1 style="font-size:15.5px; font-weight:700; color:#3D3C39; margin:0 0 4px 0;">Konfirmasi Kata Sandi</h1>
    <p style="font-size:11.5px; color:#A98077; line-height:1.4; margin:0;">
        Ini adalah area aman. Mohon konfirmasikan kata sandi Anda sebelum melanjutkan.
    </p>
</div>

{{-- ─── Form ────────────────────────────────────────────────────────────────── --}}
<form method="POST" action="{{ route('password.confirm') }}">
    @csrf

    {{-- Kata Sandi with Show/Hide Toggle --}}
    <div style="margin-bottom:16px;">
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

    {{-- Submit Button --}}
    <button
        type="submit"
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
        Konfirmasi
    </button>
</form>
@endsection
