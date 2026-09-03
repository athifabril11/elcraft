@extends('admin.layouts.admin')

@section('title', 'Auto-Selesai Pesanan')
@section('page-title', 'Auto-Selesai Pesanan')
@section('page-subtitle', 'Konfigurasi batas waktu penyelesaian pesanan otomatis setelah pengiriman')

@section('content')

<div style="max-width:640px;">

    {{-- Current value info card --}}
    <div class="admin-card" style="padding:1.5rem; margin-bottom:1.5rem; display:flex; align-items:center; gap:1rem;">
        <div style="width:48px; height:48px; border-radius:12px; background:#C17B6F18; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
            <span class="material-symbols-outlined" style="font-size:24px; color:#C17B6F;">schedule_send</span>
        </div>
        <div>
            <div style="font-size:0.7rem; color:#78716C; font-weight:600; text-transform:uppercase; letter-spacing:0.05em;">Pengaturan Saat Ini</div>
            <div style="font-size:1.1rem; font-weight:700; color:#1c1917; margin-top:2px;">{{ $autoCompleteDays }} hari</div>
            <div style="font-size:0.75rem; color:#78716C;">Pesanan otomatis diselesaikan {{ $autoCompleteDays }} hari setelah dikirim jika pelanggan tidak konfirmasi</div>
        </div>
    </div>

    {{-- Update form card --}}
    <div class="admin-card" style="padding:1.5rem;">
        <h2 style="font-size:0.9rem; font-weight:700; color:#1c1917; margin:0 0 1.25rem; display:flex; align-items:center; gap:8px;">
            <span class="material-symbols-outlined" style="font-size:18px; color:#C17B6F;">edit</span>
            Ubah Batas Waktu
        </h2>

        <form id="order-auto-complete-form" action="{{ route('admin.settings.order-auto-complete.update') }}" method="POST">
            @csrf

            @if($errors->any())
                <div style="background:#fee2e2; border:1px solid #fca5a5; border-radius:8px; padding:0.75rem 1rem; margin-bottom:1rem; font-size:0.8rem; color:#991b1b;">
                    @foreach($errors->all() as $error) <div>• {{ $error }}</div> @endforeach
                </div>
            @endif

            <div style="margin-bottom:1.5rem;">
                <label for="auto-complete-days-input" class="admin-label">
                    Jumlah hari sebelum pesanan otomatis diselesaikan
                </label>
                <div style="display:flex; align-items:center; gap:0.75rem;">
                    <input
                        type="number"
                        id="auto-complete-days-input"
                        name="auto_complete_days"
                        class="admin-input"
                        value="{{ old('auto_complete_days', $autoCompleteDays) }}"
                        min="1"
                        max="30"
                        style="max-width:120px;"
                        required
                    >
                    <span style="font-size:0.8125rem; color:#78716C;">hari (min: 1, max: 30)</span>
                </div>
                <p style="font-size:0.75rem; color:#78716C; margin-top:6px;">
                    Hitungan dimulai sejak status pesanan berubah menjadi <strong>Dikirim</strong>.
                    Jika pelanggan tidak mengkonfirmasi penerimaan dalam waktu ini, pesanan akan otomatis diselesaikan oleh sistem.
                </p>
            </div>

            <button type="submit" id="save-auto-complete-btn" class="admin-btn-primary">
                <span class="material-symbols-outlined" style="font-size:16px;">save</span>
                Simpan Pengaturan
            </button>
        </form>
    </div>

    {{-- Info note --}}
    <div style="margin-top:1rem; padding:0.75rem 1rem; background:#fef9c3; border:1px solid #fef08a; border-radius:8px; font-size:0.775rem; color:#713f12; display:flex; gap:8px;">
        <span class="material-symbols-outlined" style="font-size:16px; flex-shrink:0; margin-top:1px;">info</span>
        <span>Perubahan ini hanya memengaruhi pesanan yang belum diselesaikan. Pengecekan dilakukan oleh scheduled job. Pastikan <code>php artisan schedule:run</code> berjalan secara berkala via cron.</span>
    </div>
</div>

@endsection
