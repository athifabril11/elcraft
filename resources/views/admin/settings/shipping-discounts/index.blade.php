@extends('admin.layouts.admin')

@section('title', 'Diskon Ongkir')
@section('page-title', 'Aturan Diskon Ongkir')
@section('page-subtitle', 'Kelola aturan diskon ongkos kirim per provinsi atau kota tujuan')

@section('content')

<div x-data="discountForm()" style="display:grid; grid-template-columns:1fr 380px; gap:1.5rem; align-items:start;">

    <!-- Rules table -->
    <div>
        @if($rules->isEmpty())
            <div class="admin-card" style="padding:3rem; text-align:center; color:#78716C;">
                <span class="material-symbols-outlined" style="font-size:40px; color:#EDE8E4; display:block; margin-bottom:0.75rem;">local_offer</span>
                <div style="font-weight:600; margin-bottom:4px;">Belum ada aturan diskon</div>
                <div style="font-size:0.8rem;">Tambahkan aturan menggunakan form di sebelah kanan.</div>
            </div>
        @else
            <div class="admin-card" style="overflow:hidden;">
                <table style="width:100%; border-collapse:collapse; font-size:0.8125rem;">
                    <thead>
                        <tr style="background:#FDF8F6; border-bottom:1px solid #EDE8E4;">
                            <th style="padding:0.75rem 1rem; text-align:left; font-size:0.7rem; font-weight:700; color:#78716C; text-transform:uppercase; letter-spacing:0.05em;">Cakupan</th>
                            <th style="padding:0.75rem 1rem; text-align:left; font-size:0.7rem; font-weight:700; color:#78716C; text-transform:uppercase; letter-spacing:0.05em;">Nama</th>
                            <th style="padding:0.75rem 1rem; text-align:left; font-size:0.7rem; font-weight:700; color:#78716C; text-transform:uppercase; letter-spacing:0.05em;">Tipe</th>
                            <th style="padding:0.75rem 1rem; text-align:left; font-size:0.7rem; font-weight:700; color:#78716C; text-transform:uppercase; letter-spacing:0.05em;">Diskon</th>
                            <th style="padding:0.75rem 1rem; text-align:left; font-size:0.7rem; font-weight:700; color:#78716C; text-transform:uppercase; letter-spacing:0.05em;">Status</th>
                            <th style="padding:0.75rem 1rem; text-align:right; font-size:0.7rem; font-weight:700; color:#78716C; text-transform:uppercase; letter-spacing:0.05em;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($rules as $rule)
                        <tr style="border-bottom:1px solid #F5F0EC; {{ !$rule->is_active ? 'opacity:0.6;' : '' }}">
                            <td style="padding:0.75rem 1rem;">
                                @if($rule->scope === 'province')
                                    <span class="badge-province">Provinsi</span>
                                @else
                                    <span class="badge-city">Kota</span>
                                @endif
                                <div style="font-size:0.65rem; color:#78716C; margin-top:2px;">ID: {{ $rule->scope_id }}</div>
                            </td>
                            <td style="padding:0.75rem 1rem;">
                                <div style="font-weight:600; color:#1c1917;">{{ $rule->scope_name }}</div>
                                @if($rule->description)
                                    <div style="font-size:0.7rem; color:#78716C;">{{ $rule->description }}</div>
                                @endif
                            </td>
                            <td style="padding:0.75rem 1rem;">
                                @if($rule->discount_type === 'free')
                                    <span class="badge-free">Gratis</span>
                                @else
                                    <span class="badge-fixed">Potongan Tetap</span>
                                @endif
                            </td>
                            <td style="padding:0.75rem 1rem; font-weight:600; color:#1c1917;">
                                @if($rule->discount_type === 'free')
                                    <span style="color:#059669;">Gratis Ongkir</span>
                                @else
                                    Rp {{ number_format($rule->discount_amount, 0, ',', '.') }}
                                @endif
                            </td>
                            <td style="padding:0.75rem 1rem;">
                                @if($rule->is_active)
                                    <span class="badge-active">Aktif</span>
                                @else
                                    <span class="badge-inactive">Nonaktif</span>
                                @endif
                            </td>
                            <td style="padding:0.75rem 1rem; text-align:right;">
                                <div style="display:flex; gap:6px; justify-content:flex-end; flex-wrap:wrap;">
                                    <!-- Toggle active -->
                                    <form method="POST" action="{{ route('admin.settings.shipping-discounts.toggle', $rule) }}">
                                        @csrf
                                        <button type="submit" class="admin-btn-ghost" style="padding:0.3rem 0.75rem; font-size:0.7rem;"
                                            title="{{ $rule->is_active ? 'Nonaktifkan' : 'Aktifkan' }}">
                                            <span class="material-symbols-outlined" style="font-size:14px;">
                                                {{ $rule->is_active ? 'toggle_on' : 'toggle_off' }}
                                            </span>
                                            {{ $rule->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                        </button>
                                    </form>

                                    <!-- Delete -->
                                    <form method="POST" action="{{ route('admin.settings.shipping-discounts.destroy', $rule) }}"
                                          onsubmit="return confirm('Yakin hapus aturan ini?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="admin-btn-danger" title="Hapus">
                                            <span class="material-symbols-outlined" style="font-size:14px;">delete</span>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <!-- Add rule form -->
    <div class="admin-card" style="padding:1.5rem; position:sticky; top:1rem;">
        <h2 style="font-size:0.9rem; font-weight:700; color:#1c1917; margin:0 0 1.25rem; display:flex; align-items:center; gap:8px;">
            <span class="material-symbols-outlined" style="font-size:18px; color:#C17B6F;">add_circle</span>
            Tambah Aturan
        </h2>

        <form method="POST" action="{{ route('admin.settings.shipping-discounts.store') }}">
            @csrf

            @if($errors->any())
                <div style="background:#fee2e2; border:1px solid #fca5a5; border-radius:8px; padding:0.75rem 1rem; margin-bottom:1rem; font-size:0.75rem; color:#991b1b;">
                    @foreach($errors->all() as $error) <div>• {{ $error }}</div> @endforeach
                </div>
            @endif

            <!-- Scope type -->
            <div style="margin-bottom:1rem;">
                <label class="admin-label">Tipe Cakupan</label>
                <div style="display:flex; gap:8px;">
                    <label style="flex:1; display:flex; align-items:center; gap:6px; padding:0.5rem 0.75rem; border:1px solid #EDE8E4; border-radius:8px; cursor:pointer; font-size:0.8rem; transition:border-color 0.15s;"
                           :style="scope === 'province' ? 'border-color:#C17B6F; background:#C17B6F08;' : ''">
                        <input type="radio" name="scope" value="province" x-model="scope" style="accent-color:#C17B6F;">
                        Provinsi
                    </label>
                    <label style="flex:1; display:flex; align-items:center; gap:6px; padding:0.5rem 0.75rem; border:1px solid #EDE8E4; border-radius:8px; cursor:pointer; font-size:0.8rem; transition:border-color 0.15s;"
                           :style="scope === 'city' ? 'border-color:#C17B6F; background:#C17B6F08;' : ''">
                        <input type="radio" name="scope" value="city" x-model="scope" style="accent-color:#C17B6F;">
                        Kota
                    </label>
                </div>
            </div>

            <!-- Province dropdown (always shown) -->
            <div style="margin-bottom:1rem;">
                <label for="form-province-select" class="admin-label">Provinsi</label>
                <select id="form-province-select" class="admin-input" @change="onProvinceChange($event)">
                    <option value="">— Pilih provinsi —</option>
                    <template x-for="p in provinces" :key="p.id">
                        <option :value="p.id" x-text="p.name"></option>
                    </template>
                </select>
                <!-- Hidden input for province scope -->
                <template x-if="scope === 'province'">
                    <div>
                        <input type="hidden" name="scope_id" :value="selectedProvinceId">
                        <input type="hidden" name="scope_name" :value="selectedProvinceName">
                    </div>
                </template>
            </div>

            <!-- City dropdown (only for city scope) -->
            <template x-if="scope === 'city'">
                <div style="margin-bottom:1rem;">
                    <label for="form-city-select" class="admin-label">Kota / Kabupaten</label>
                    <select id="form-city-select" name="scope_id" class="admin-input" :disabled="citiesLoading || !selectedProvinceId" @change="onCityChange($event)">
                        <option value="">
                            <span x-text="citiesLoading ? 'Memuat kota...' : (selectedProvinceId ? '— Pilih kota —' : '— Pilih provinsi dulu —')"></span>
                        </option>
                        <template x-for="c in cities" :key="c.id">
                            <option :value="c.id" x-text="c.name"></option>
                        </template>
                    </select>
                    <input type="hidden" name="scope_name" :value="selectedCityName">
                </div>
            </template>

            <!-- Discount type -->
            <div style="margin-bottom:1rem;">
                <label class="admin-label">Tipe Diskon</label>
                <div style="display:flex; gap:8px;">
                    <label style="flex:1; display:flex; align-items:center; gap:6px; padding:0.5rem 0.75rem; border:1px solid #EDE8E4; border-radius:8px; cursor:pointer; font-size:0.8rem;"
                           :style="discountType === 'free' ? 'border-color:#C17B6F; background:#C17B6F08;' : ''">
                        <input type="radio" name="discount_type" value="free" x-model="discountType" style="accent-color:#C17B6F;">
                        Gratis Ongkir
                    </label>
                    <label style="flex:1; display:flex; align-items:center; gap:6px; padding:0.5rem 0.75rem; border:1px solid #EDE8E4; border-radius:8px; cursor:pointer; font-size:0.8rem;"
                           :style="discountType === 'fixed' ? 'border-color:#C17B6F; background:#C17B6F08;' : ''">
                        <input type="radio" name="discount_type" value="fixed" x-model="discountType" style="accent-color:#C17B6F;">
                        Potongan Tetap
                    </label>
                </div>
            </div>

            <!-- Discount amount (only for fixed) -->
            <template x-if="discountType === 'fixed'">
                <div style="margin-bottom:1rem;">
                    <label for="form-discount-amount" class="admin-label">Jumlah Potongan (Rp)</label>
                    <input type="number" id="form-discount-amount" name="discount_amount"
                           class="admin-input" min="0" step="1000"
                           placeholder="cth: 10000">
                </div>
            </template>

            <!-- Description -->
            <div style="margin-bottom:1.25rem;">
                <label for="form-description" class="admin-label">Keterangan (opsional)</label>
                <input type="text" id="form-description" name="description"
                       class="admin-input" placeholder="cth: Gratis ongkir Jawa Tengah">
            </div>

            <button type="submit" class="admin-btn-primary" style="width:100%;">
                <span class="material-symbols-outlined" style="font-size:16px;">add</span>
                Tambah Aturan
            </button>
        </form>
    </div>
</div>

@endsection

@push('scripts')
<script>
function discountForm() {
    return {
        scope: 'province',
        discountType: 'free',
        provinces: [],
        cities: [],
        selectedProvinceId: '',
        selectedProvinceName: '',
        selectedCityId: '',
        selectedCityName: '',
        citiesLoading: false,

        init() {
            fetch('/shipping/provinces')
                .then(r => r.json())
                .then(data => {
                    if (data.success) this.provinces = data.data;
                });
        },

        onProvinceChange(event) {
            const opt = event.target.options[event.target.selectedIndex];
            this.selectedProvinceId   = event.target.value;
            this.selectedProvinceName = opt ? opt.text : '';
            this.cities = [];
            this.selectedCityId = '';
            this.selectedCityName = '';

            if (!this.selectedProvinceId) return;

            this.citiesLoading = true;
            fetch(`/shipping/cities/${this.selectedProvinceId}`)
                .then(r => r.json())
                .then(data => {
                    this.cities = data.success ? data.data : [];
                    this.citiesLoading = false;
                })
                .catch(() => { this.citiesLoading = false; });
        },

        onCityChange(event) {
            const opt = event.target.options[event.target.selectedIndex];
            this.selectedCityId   = event.target.value;
            this.selectedCityName = opt ? opt.text : '';
        },
    };
}
</script>
@endpush
