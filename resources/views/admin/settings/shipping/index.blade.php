@extends('admin.layouts.admin')

@section('title', 'Kota Asal Pengiriman')
@section('page-title', 'Kota Asal Pengiriman')
@section('page-subtitle', 'Kota asal yang digunakan dalam kalkulasi ongkos kirim RajaOngkir')

@section('content')

<div style="max-width:640px;">

    <!-- Current origin info card -->
    <div class="admin-card" style="padding:1.5rem; margin-bottom:1.5rem; display:flex; align-items:center; gap:1rem;">
        <div style="width:48px; height:48px; border-radius:12px; background:#C17B6F18; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
            <span class="material-symbols-outlined" style="font-size:24px; color:#C17B6F;">warehouse</span>
        </div>
        <div>
            <div style="font-size:0.7rem; color:#78716C; font-weight:600; text-transform:uppercase; letter-spacing:0.05em;">Kota Asal Saat Ini</div>
            <div style="font-size:1.1rem; font-weight:700; color:#1c1917; margin-top:2px;">{{ $originCityName }}</div>
            <div style="font-size:0.75rem; color:#78716C;">City ID: <code style="background:#f5f5f5; padding:1px 4px; border-radius:4px;">{{ $originCityId }}</code></div>
        </div>
    </div>

    <!-- Update form card -->
    <div class="admin-card" style="padding:1.5rem;">
        <h2 style="font-size:0.9rem; font-weight:700; color:#1c1917; margin:0 0 1.25rem; display:flex; align-items:center; gap:8px;">
            <span class="material-symbols-outlined" style="font-size:18px; color:#C17B6F;">edit_location</span>
            Ubah Kota Asal
        </h2>

        <form id="shipping-origin-form" action="{{ route('admin.settings.shipping.update') }}" method="POST">
            @csrf

            <input type="hidden" id="origin-city-id-input" name="origin_city_id" value="{{ $originCityId }}">
            <input type="hidden" id="origin-city-name-input" name="origin_city_name" value="{{ $originCityName }}">

            @if($errors->any())
                <div style="background:#fee2e2; border:1px solid #fca5a5; border-radius:8px; padding:0.75rem 1rem; margin-bottom:1rem; font-size:0.8rem; color:#991b1b;">
                    @foreach($errors->all() as $error) <div>• {{ $error }}</div> @endforeach
                </div>
            @endif

            <!-- Province dropdown -->
            <div style="margin-bottom:1rem;">
                <label for="origin-province-select" class="admin-label">Provinsi</label>
                <select id="origin-province-select" class="admin-input">
                    <option value="">— Memuat provinsi... —</option>
                </select>
            </div>

            <!-- City dropdown -->
            <div style="margin-bottom:1.5rem;">
                <label for="origin-city-select" class="admin-label">Kota / Kabupaten</label>
                <select id="origin-city-select" class="admin-input" disabled>
                    <option value="">— Pilih provinsi dulu —</option>
                </select>
            </div>

            <!-- Selected preview -->
            <div id="selected-preview" style="display:none; background:#FDF8F6; border:1px solid #EDE8E4; border-radius:8px; padding:0.75rem 1rem; margin-bottom:1.25rem; font-size:0.8125rem; color:#1c1917;">
                <span style="color:#78716C; font-size:0.7rem; font-weight:600; text-transform:uppercase; letter-spacing:0.05em;">Dipilih:</span>
                <span id="selected-preview-text" style="font-weight:600; margin-left:6px;"></span>
            </div>

            <button type="submit" id="save-origin-btn" class="admin-btn-primary" disabled>
                <span class="material-symbols-outlined" style="font-size:16px;">save</span>
                Simpan Kota Asal
            </button>
        </form>
    </div>

    <!-- Info note -->
    <div style="margin-top:1rem; padding:0.75rem 1rem; background:#fef9c3; border:1px solid #fef08a; border-radius:8px; font-size:0.775rem; color:#713f12; display:flex; gap:8px;">
        <span class="material-symbols-outlined" style="font-size:16px; flex-shrink:0; margin-top:1px;">info</span>
        <span>Perubahan kota asal akan langsung berlaku pada kalkulasi ongkos kirim berikutnya. Cache ongkir yang tersimpan tidak otomatis dihapus — jika perlu, gunakan <code>php artisan cache:clear</code> setelah mengubah kota asal.</span>
    </div>
</div>

@endsection

@push('scripts')
<script>
(function () {
    const provinceSelect = document.getElementById('origin-province-select');
    const citySelect     = document.getElementById('origin-city-select');
    const cityIdInput    = document.getElementById('origin-city-id-input');
    const cityNameInput  = document.getElementById('origin-city-name-input');
    const saveBtn        = document.getElementById('save-origin-btn');
    const preview        = document.getElementById('selected-preview');
    const previewText    = document.getElementById('selected-preview-text');

    const currentCityId = '{{ $originCityId }}';

    // Load provinces on page load
    fetch('/shipping/provinces')
        .then(r => r.json())
        .then(data => {
            if (!data.success) return;
            provinceSelect.innerHTML = '<option value="">— Pilih provinsi —</option>';
            data.data.forEach(p => {
                const opt = new Option(p.name, p.id);
                provinceSelect.add(opt);
            });
        })
        .catch(() => {
            provinceSelect.innerHTML = '<option value="">Gagal memuat provinsi</option>';
        });

    provinceSelect.addEventListener('change', function () {
        const pid = this.value;
        if (!pid) {
            citySelect.innerHTML = '<option value="">— Pilih provinsi dulu —</option>';
            citySelect.disabled = true;
            saveBtn.disabled = true;
            return;
        }
        citySelect.innerHTML = '<option value="">Memuat kota...</option>';
        citySelect.disabled = true;

        fetch(`/shipping/cities/${pid}`)
            .then(r => r.json())
            .then(data => {
                if (!data.success) {
                    citySelect.innerHTML = '<option value="">Gagal memuat kota</option>';
                    return;
                }
                citySelect.innerHTML = '<option value="">— Pilih kota —</option>';
                data.data.forEach(c => {
                    const opt = new Option(c.name, c.id);
                    if (String(c.id) === currentCityId) opt.selected = true;
                    citySelect.add(opt);
                });
                citySelect.disabled = false;
                // Trigger change if a city was pre-selected
                citySelect.dispatchEvent(new Event('change'));
            });
    });

    citySelect.addEventListener('change', function () {
        const cid  = this.value;
        const cname = this.options[this.selectedIndex]?.text ?? '';
        if (!cid) {
            saveBtn.disabled = true;
            preview.style.display = 'none';
            return;
        }
        cityIdInput.value   = cid;
        cityNameInput.value = cname;
        previewText.textContent = cname + ' (ID: ' + cid + ')';
        preview.style.display = 'block';
        saveBtn.disabled = false;
    });
})();
</script>
@endpush
