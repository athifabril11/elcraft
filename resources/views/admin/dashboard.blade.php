@extends('admin.layouts.admin')

@section('title', 'Dashboard Admin')
@section('page-title', 'Dashboard')
@section('page-subtitle', 'Selamat datang di panel administrasi el Craft')

@section('content')
<div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:1rem; margin-bottom:2rem;">
    <!-- Stat card: Kota Asal -->
    <div class="admin-card" style="padding:1.25rem;">
        <div style="display:flex; align-items:center; gap:12px; margin-bottom:0.75rem;">
            <div style="width:36px; height:36px; border-radius:8px; background:#C17B6F18; display:flex; align-items:center; justify-content:center;">
                <span class="material-symbols-outlined" style="font-size:20px; color:#C17B6F;">warehouse</span>
            </div>
            <span style="font-size:0.75rem; font-weight:600; color:#78716C; text-transform:uppercase; letter-spacing:0.05em;">Kota Asal Kirim</span>
        </div>
        <div style="font-size:1rem; font-weight:700; color:#1c1917;">
            {{ \App\Models\ShopSetting::get('shipping_origin_city_name', 'Jember') }}
        </div>
        <div style="font-size:0.7rem; color:#78716C; margin-top:2px;">
            ID: {{ \App\Models\ShopSetting::get('shipping_origin_city_id', '256') }}
        </div>
        <a href="{{ route('admin.settings.shipping.index') }}" style="display:inline-block; margin-top:0.75rem; font-size:0.7rem; color:#C17B6F; text-decoration:none; font-weight:600;">
            Ubah →
        </a>
    </div>

    <!-- Stat card: Aturan Diskon -->
    <div class="admin-card" style="padding:1.25rem;">
        <div style="display:flex; align-items:center; gap:12px; margin-bottom:0.75rem;">
            <div style="width:36px; height:36px; border-radius:8px; background:#C17B6F18; display:flex; align-items:center; justify-content:center;">
                <span class="material-symbols-outlined" style="font-size:20px; color:#C17B6F;">local_offer</span>
            </div>
            <span style="font-size:0.75rem; font-weight:600; color:#78716C; text-transform:uppercase; letter-spacing:0.05em;">Aturan Diskon Ongkir</span>
        </div>
        <div style="font-size:1rem; font-weight:700; color:#1c1917;">
            {{ \App\Models\ShippingDiscountRule::where('is_active', true)->count() }}
            <span style="font-size:0.75rem; font-weight:400; color:#78716C;">aktif</span>
        </div>
        <div style="font-size:0.7rem; color:#78716C; margin-top:2px;">
            {{ \App\Models\ShippingDiscountRule::count() }} total aturan
        </div>
        <a href="{{ route('admin.settings.shipping-discounts.index') }}" style="display:inline-block; margin-top:0.75rem; font-size:0.7rem; color:#C17B6F; text-decoration:none; font-weight:600;">
            Kelola →
        </a>
    <!-- Stat card: Kurir -->
    <div class="admin-card" style="padding:1.25rem;">
        <div style="display:flex; align-items:center; gap:12px; margin-bottom:0.75rem;">
            <div style="width:36px; height:36px; border-radius:8px; background:#C17B6F18; display:flex; align-items:center; justify-content:center;">
                <span class="material-symbols-outlined" style="font-size:20px; color:#C17B6F;">local_shipping</span>
            </div>
            <span style="font-size:0.75rem; font-weight:600; color:#78716C; text-transform:uppercase; letter-spacing:0.05em;">Kurir Pengiriman</span>
        </div>
        <div style="font-size:1rem; font-weight:700; color:#1c1917;">
            {{ \App\Models\Courier::where('is_active', true)->count() }}
            <span style="font-size:0.75rem; font-weight:400; color:#78716C;">aktif</span>
        </div>
        <div style="font-size:0.7rem; color:#78716C; margin-top:2px;">
            {{ \App\Models\Courier::count() }} total kurir didukung
        </div>
        <a href="{{ route('admin.settings.couriers.index') }}" style="display:inline-block; margin-top:0.75rem; font-size:0.7rem; color:#C17B6F; text-decoration:none; font-weight:600;">
            Kelola →
        </a>
    </div>
</div>

<!-- Quick links -->
<div class="admin-card" style="padding:1.5rem;">
    <h2 style="font-size:0.875rem; font-weight:700; color:#1c1917; margin:0 0 1rem;">Pengaturan Cepat</h2>
    <div style="display:flex; gap:0.75rem; flex-wrap:wrap;">
        <a href="{{ route('admin.settings.shipping.index') }}" class="admin-btn-ghost">
            <span class="material-symbols-outlined" style="font-size:16px;">warehouse</span>
            Kota Asal Pengiriman
        </a>
        <a href="{{ route('admin.settings.shipping-discounts.index') }}" class="admin-btn-ghost">
            <span class="material-symbols-outlined" style="font-size:16px;">local_offer</span>
            Diskon Ongkir
        </a>
        <a href="{{ route('admin.settings.couriers.index') }}" class="admin-btn-ghost">
            <span class="material-symbols-outlined" style="font-size:16px;">local_shipping</span>
            Kurir
        </a>
    </div>
</div>
@endsection
