@extends('admin.layouts.admin')

@section('title', 'Nomor WhatsApp Admin')
@section('page-title', 'Nomor WhatsApp Admin')
@section('page-subtitle', 'Nomor WhatsApp yang tampil di footer storefront dan tombol CTA pelanggan')

@section('content')

@php
    $savedDigits = preg_replace('/\D/', '', $waNumber ?? '');
    $isValid     = strlen($savedDigits) >= 10;
    $previewLink = $isValid ? 'https://wa.me/' . $savedDigits : null;
@endphp

<div style="max-width:640px;">

    {{-- Info card: current stored number --}}
    <div class="admin-card" style="padding:1.5rem; margin-bottom:1.5rem; display:flex; align-items:center; gap:1rem;">
        <div style="width:48px; height:48px; border-radius:12px; background:#C17B6F18; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
            <span class="material-symbols-outlined" style="font-size:24px; color:#C17B6F;">chat</span>
        </div>
        <div>
            <div style="font-size:0.7rem; color:#78716C; font-weight:600; text-transform:uppercase; letter-spacing:0.05em;">Nomor WhatsApp Saat Ini</div>
            @if($isValid)
                <div style="font-size:1.1rem; font-weight:700; color:#1c1917; margin-top:2px;">{{ $savedDigits }}</div>
                <div style="font-size:0.75rem; color:#78716C; margin-top:2px;">
                    <a href="{{ $previewLink }}" target="_blank" rel="noopener"
                       style="color:#C17B6F; text-decoration:underline; text-underline-offset:2px;">
                        Uji link wa.me ↗
                    </a>
                </div>
            @else
                <div style="font-size:0.875rem; font-weight:600; color:#92400E; margin-top:2px;">Belum diatur</div>
                <div style="font-size:0.75rem; color:#78716C; margin-top:1px;">Link WhatsApp di footer & CTA sedang disembunyikan dari pengunjung.</div>
            @endif
        </div>
    </div>

    {{-- Update form card --}}
    <div class="admin-card" style="padding:1.5rem;">
        <h2 style="font-size:0.9rem; font-weight:700; color:#1c1917; margin:0 0 1.25rem; display:flex; align-items:center; gap:8px;">
            <span class="material-symbols-outlined" style="font-size:18px; color:#C17B6F;">edit</span>
            Ubah Nomor WhatsApp
        </h2>

        {{-- Success flash --}}
        @if(session('success'))
            <div style="background:#dcfce7; border:1px solid #86efac; border-radius:8px; padding:0.75rem 1rem; margin-bottom:1rem; font-size:0.8rem; color:#166534; display:flex; gap:8px; align-items:center;">
                <span class="material-symbols-outlined" style="font-size:16px; flex-shrink:0;">check_circle</span>
                {{ session('success') }}
            </div>
        @endif

        {{-- Validation errors --}}
        @if($errors->any())
            <div style="background:#fee2e2; border:1px solid #fca5a5; border-radius:8px; padding:0.75rem 1rem; margin-bottom:1rem; font-size:0.8rem; color:#991b1b;">
                @foreach($errors->all() as $error) <div>• {{ $error }}</div> @endforeach
            </div>
        @endif

        <form action="{{ route('admin.settings.whatsapp.update') }}" method="POST">
            @csrf

            {{-- Penjelasan --}}
            <p style="font-size:0.8rem; color:#78716C; margin:0 0 1.25rem; line-height:1.6;">
                Nomor ini digunakan untuk link WhatsApp di <strong>footer storefront</strong> dan tombol CTA lainnya
                yang menghadap pelanggan. Masukkan nomor dengan kode negara (contoh: <code style="background:#f5f5f5; padding:1px 5px; border-radius:4px;">6281234567890</code>).
                Karakter non-angka (spasi, tanda +, tanda hubung) akan dihapus otomatis sebelum disimpan.
            </p>

            <div style="margin-bottom:1.5rem;">
                <label for="wa-number-input" class="admin-label">Nomor WhatsApp (dengan kode negara)</label>
                <input
                    type="text"
                    id="wa-number-input"
                    name="wa_number"
                    class="admin-input"
                    value="{{ old('wa_number', $waNumber) }}"
                    placeholder="Contoh: 6281234567890"
                    maxlength="20"
                    autocomplete="off"
                >
                <div style="font-size:0.7rem; color:#a8a29e; margin-top:4px;">Minimal 10 digit angka setelah karakter non-angka dihapus.</div>
            </div>

            <button type="submit" id="save-wa-btn" class="admin-btn-primary">
                <span class="material-symbols-outlined" style="font-size:16px;">save</span>
                Simpan Nomor WhatsApp
            </button>
        </form>
    </div>

    {{-- Info note --}}
    <div style="margin-top:1rem; padding:0.75rem 1rem; background:#fef9c3; border:1px solid #fef08a; border-radius:8px; font-size:0.775rem; color:#713f12; display:flex; gap:8px;">
        <span class="material-symbols-outlined" style="font-size:16px; flex-shrink:0; margin-top:1px;">info</span>
        <span>Perubahan nomor akan langsung berlaku di seluruh halaman storefront tanpa perlu restart server.</span>
    </div>

</div>

@endsection
