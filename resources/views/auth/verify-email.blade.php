@extends('layouts.auth-guest')
@section('title', 'Verifikasi Email')

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

{{-- ─── Envelope Icon ──────────────────────────────────────────────────────── --}}
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
            <rect x="2" y="4" width="20" height="16" rx="2"/>
            <polyline points="2,4 12,13 22,4"/>
        </svg>
    </span>
</div>

{{-- ─── Title & Description ─────────────────────────────────────────────────── --}}
<div style="text-align:center; margin-bottom:16px;">
    <h1 style="font-size:15.5px; font-weight:700; color:#3D3C39; margin:0 0 4px 0;">Verifikasi Email Anda</h1>
    <p style="font-size:11.5px; color:#A98077; line-height:1.4; margin:0;">
        Terima kasih telah mendaftar! Mohon verifikasi alamat email Anda dengan mengklik tautan yang kami kirimkan.
    </p>
</div>

{{-- ─── Resend Success Banner ───────────────────────────────────────────────── --}}
@if (session('status') == 'verification-link-sent')
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
        Tautan verifikasi baru telah dikirimkan ke alamat email Anda.
    </div>
@elseif (session('status') == 'verification-link-failed')
    <div style="
        margin-bottom: 14px;
        padding: 8px 12px;
        border-radius: 10px;
        border: 1px solid #FECACA;
        background: #FEF2F2;
        color: #B91C1C;
        font-size: 11.5px;
        line-height: 1.4;
        font-weight: 500;
        text-align: center;
    ">
        Email verifikasi gagal dikirim. Coba lagi beberapa saat lagi.
    </div>
@endif

{{-- ─── Resend Button ───────────────────────────────────────────────────────── --}}
<form method="POST" action="{{ route('verification.send') }}">
    @csrf
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
        Kirim Ulang Email Verifikasi
    </button>
</form>

{{-- ─── Divider ─────────────────────────────────────────────────────────────── --}}
<div style="display:flex; align-items:center; gap:10px; margin-top:14px; margin-bottom:12px;">
    <div style="flex:1; height:1px; background:#E8B4AC;"></div>
    <span style="font-size:11.5px; font-weight:500; color:#A98077;">atau</span>
    <div style="flex:1; height:1px; background:#E8B4AC;"></div>
</div>

{{-- ─── Logout Link ─────────────────────────────────────────────────────────── --}}
<form method="POST" action="{{ route('logout') }}" style="text-align:center; margin:0;">
    @csrf
    <button
        type="submit"
        style="
            font-size: 11.5px;
            font-weight: 600;
            color: #C17B6F;
            background: none;
            border: none;
            cursor: pointer;
            padding: 0;
            transition: color .2s;
        "
        onmouseenter="this.style.color='#9E5A4E'"
        onmouseleave="this.style.color='#C17B6F'">
        Keluar dari akun
    </button>
</form>
@endsection
