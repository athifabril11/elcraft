@extends('layouts.auth-guest')
@section('title', 'Lupa Kata Sandi')

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
    <h1 style="font-size:15.5px; font-weight:700; color:#3D3C39; margin:0 0 4px 0;">Lupa Sandi?</h1>
    <p style="font-size:11.5px; color:#A98077; line-height:1.4; margin:0;">
        Masukkan email Anda, kami akan kirim link untuk reset kata sandi.
    </p>
</div>

{{-- ─── Success Status Banner ───────────────────────────────────────────────── --}}
@if (session('status'))
    <div style="
        margin-bottom: 14px;
        padding: 8px 12px;
        border-radius: 10px;
        border: 1px solid #BBF7D0;
        background: #F0FDF4;
        color: #15803D;
        font-size: 11.5px;
        line-height: 1.4;
        font-weight: 500;
        text-align: center;
    ">
        {{ session('status') }}
    </div>
@endif

{{-- ─── Form ────────────────────────────────────────────────────────────────── --}}
<form method="POST" action="{{ route('password.email') }}">
    @csrf

    {{-- Email Label --}}
    <label for="email" style="
        display: block;
        font-size: 11.5px;
        font-weight: 500;
        color: #5F5E5A;
        margin-bottom: 5px;
    ">Email</label>

    {{-- Email Input with icon --}}
    <div style="position:relative; margin-bottom:14px;">
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
            autocomplete="email"
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
        @error('email')
            <p style="margin-top:4px; font-size:11px; color:#ef4444;">{{ $message }}</p>
        @enderror
    </div>

    {{-- Submit Button --}}
    <button
        type="submit"
        style="
            width: 100%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
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
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="2"
             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <line x1="22" y1="2" x2="11" y2="13"/>
            <polygon points="22 2 15 22 11 13 2 9 22 2"/>
        </svg>
        Kirim Link Reset
    </button>
</form>

{{-- ─── Footer Link ─────────────────────────────────────────────────────────── --}}
<p style="margin-top:14px; text-align:center; font-size:11.5px; color:#A98077; margin-bottom:0;">
    Ingat kata sandi?
    <a href="{{ route('login') }}"
       style="font-weight:600; color:#C17B6F; text-decoration:none; transition:color .2s;"
       onmouseenter="this.style.color='#9E5A4E'"
       onmouseleave="this.style.color='#C17B6F'">
        Masuk
    </a>
</p>
@endsection
