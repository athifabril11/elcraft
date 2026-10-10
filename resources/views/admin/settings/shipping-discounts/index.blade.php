@extends('admin.layouts.admin')

@section('title', 'Diskon Ongkir')
@section('page-title', 'Aturan Diskon Ongkir')
@section('page-subtitle', 'Kelola aturan diskon ongkos kirim per provinsi atau kota tujuan')

@section('content')

<div x-data="discountForm()" class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_20rem] gap-6 items-start">

    <!-- Rules table -->
    <div class="min-w-0">
        @if($rules->isEmpty())
            <div class="admin-card p-12 text-center text-atelier-textMuted">
                <span class="material-symbols-outlined text-4xl text-stone-300 block mb-3">local_offer</span>
                <div class="font-semibold text-stone-800 mb-1">Belum ada aturan diskon</div>
                <div class="text-xs text-atelier-textMuted">Tambahkan aturan menggunakan form di sebelah kanan.</div>
            </div>
        @else
            <div class="admin-card overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="border-b border-atelier-border bg-stone-50/60 text-[10px] uppercase font-bold text-atelier-textLight tracking-wider">
                                <th class="py-3 px-4">Cakupan</th>
                                <th class="py-3 px-4">Nama</th>
                                <th class="py-3 px-4">Tipe</th>
                                <th class="py-3 px-4">Diskon</th>
                                <th class="py-3 px-4">Status</th>
                                <th class="py-3 px-4 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-atelier-border">
                            @foreach($rules as $rule)
                            <tr class="hover:bg-brand-50/20 transition {{ !$rule->is_active ? 'opacity-60' : '' }}">
                                <td class="py-3 px-4 whitespace-nowrap">
                                    @if($rule->scope === 'province')
                                        <span class="badge-province">Provinsi</span>
                                    @else
                                        <span class="badge-city">Kota</span>
                                    @endif
                                    <div class="text-[10px] text-atelier-textMuted mt-0.5">ID: {{ $rule->scope_id }}</div>
                                </td>
                                <td class="py-3 px-4">
                                    <div class="font-semibold text-stone-900">{{ $rule->scope_name }}</div>
                                    @if($rule->description)
                                        <div class="text-[11px] text-atelier-textMuted">{{ $rule->description }}</div>
                                    @endif
                                </td>
                                <td class="py-3 px-4 whitespace-nowrap">
                                    @if($rule->discount_type === 'free')
                                        <span class="badge-free">Gratis</span>
                                    @else
                                        <span class="badge-fixed">Potongan Tetap</span>
                                    @endif
                                </td>
                                <td class="py-3 px-4 font-semibold text-stone-900 whitespace-nowrap">
                                    @if($rule->discount_type === 'free')
                                        <span class="text-emerald-600">Gratis Ongkir</span>
                                    @else
                                        Rp {{ number_format($rule->discount_amount, 0, ',', '.') }}
                                    @endif
                                </td>
                                <td class="py-3 px-4 whitespace-nowrap">
                                    @if($rule->is_active)
                                        <span class="badge-active">Aktif</span>
                                    @else
                                        <span class="badge-inactive">Nonaktif</span>
                                    @endif
                                </td>
                                <td class="py-3 px-4 text-right whitespace-nowrap">
                                    <div class="flex items-center justify-end gap-1.5 flex-wrap">
                                        <!-- Toggle active -->
                                        <form method="POST" action="{{ route('admin.settings.shipping-discounts.toggle', $rule) }}" class="inline m-0">
                                            @csrf
                                            <button type="submit" class="admin-btn-ghost !py-1 !px-2.5 !text-xs"
                                                title="{{ $rule->is_active ? 'Nonaktifkan' : 'Aktifkan' }}">
                                                <span class="material-symbols-outlined !text-sm">
                                                    {{ $rule->is_active ? 'toggle_on' : 'toggle_off' }}
                                                </span>
                                                {{ $rule->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                            </button>
                                        </form>

                                        <!-- Delete -->
                                        <button type="button" title="Hapus" @click="$dispatch('confirm-delete', {
                                            action: '{{ route('admin.settings.shipping-discounts.destroy', $rule) }}',
                                            title: 'Hapus Aturan Diskon?',
                                            message: 'Apakah Anda yakin ingin menghapus aturan diskon ongkir ini? Tindakan ini tidak dapat dibatalkan.'
                                        })" class="admin-btn-danger !py-1 !px-2.5 !text-xs">
                                            <span class="material-symbols-outlined !text-sm">delete</span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>

    <!-- Add rule form -->
    <div class="admin-card p-4 sm:p-5 lg:sticky lg:top-4">
        <h2 class="text-sm font-bold text-stone-900 flex items-center gap-1.5 mb-3">
            <span class="material-symbols-outlined text-base text-brand-500">add_circle</span>
            <span>Tambah Aturan</span>
        </h2>

        <form method="POST" action="{{ route('admin.settings.shipping-discounts.store') }}" class="space-y-3 m-0">
            @csrf

            @if($errors->any())
                <div class="p-3 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs font-semibold space-y-1">
                    @foreach($errors->all() as $error)
                        <div class="flex items-start gap-1">
                            <span>•</span>
                            <span>{{ $error }}</span>
                        </div>
                    @endforeach
                </div>
            @endif

            <!-- Scope type -->
            <div>
                <label class="admin-label">Tipe Cakupan</label>
                <div class="grid grid-cols-2 gap-1 p-1 bg-stone-100 rounded-xl border border-atelier-border">
                    <label class="relative flex items-center justify-center cursor-pointer select-none">
                        <input type="radio" name="scope" value="province" x-model="scope" checked class="sr-only peer">
                        <span class="w-full text-center py-1.5 px-3 rounded-lg text-xs font-semibold text-stone-600 transition peer-checked:bg-white peer-checked:text-brand-700 peer-checked:shadow-xs peer-checked:font-bold peer-focus-visible:ring-2 peer-focus-visible:ring-brand-500">
                            Provinsi
                        </span>
                    </label>
                    <label class="relative flex items-center justify-center cursor-pointer select-none">
                        <input type="radio" name="scope" value="city" x-model="scope" class="sr-only peer">
                        <span class="w-full text-center py-1.5 px-3 rounded-lg text-xs font-semibold text-stone-600 transition peer-checked:bg-white peer-checked:text-brand-700 peer-checked:shadow-xs peer-checked:font-bold peer-focus-visible:ring-2 peer-focus-visible:ring-brand-500">
                            Kota
                        </span>
                    </label>
                </div>
            </div>

            <!-- Province dropdown (always shown) -->
            <div>
                <label for="form-province-select" class="admin-label">Provinsi</label>
                <select id="form-province-select" class="admin-input h-9 text-sm" @change="onProvinceChange($event)">
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
                <div>
                    <label for="form-city-select" class="admin-label">Kota / Kabupaten</label>
                    <select id="form-city-select" name="scope_id" class="admin-input h-9 text-sm" :disabled="citiesLoading || !selectedProvinceId" @change="onCityChange($event)">
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
            <div>
                <label class="admin-label">Tipe Diskon</label>
                <div class="grid grid-cols-2 gap-1 p-1 bg-stone-100 rounded-xl border border-atelier-border">
                    <label class="relative flex items-center justify-center cursor-pointer select-none">
                        <input type="radio" name="discount_type" value="free" x-model="discountType" checked class="sr-only peer">
                        <span class="w-full text-center py-1.5 px-2 rounded-lg text-xs font-semibold text-stone-600 transition peer-checked:bg-white peer-checked:text-brand-700 peer-checked:shadow-xs peer-checked:font-bold peer-focus-visible:ring-2 peer-focus-visible:ring-brand-500">
                            Gratis Ongkir
                        </span>
                    </label>
                    <label class="relative flex items-center justify-center cursor-pointer select-none">
                        <input type="radio" name="discount_type" value="fixed" x-model="discountType" class="sr-only peer">
                        <span class="w-full text-center py-1.5 px-2 rounded-lg text-xs font-semibold text-stone-600 transition peer-checked:bg-white peer-checked:text-brand-700 peer-checked:shadow-xs peer-checked:font-bold peer-focus-visible:ring-2 peer-focus-visible:ring-brand-500">
                            Potongan Tetap
                        </span>
                    </label>
                </div>
            </div>

            <!-- Discount amount (only for fixed) -->
            <template x-if="discountType === 'fixed'">
                <div>
                    <label for="form-discount-amount" class="admin-label">Jumlah Potongan (Rp)</label>
                    <input type="number" id="form-discount-amount" name="discount_amount"
                           class="admin-input h-9 text-sm" min="0" step="1000"
                           placeholder="cth: 10000">
                </div>
            </template>

            <!-- Description -->
            <div>
                <label for="form-description" class="admin-label">Keterangan (opsional)</label>
                <input type="text" id="form-description" name="description"
                       class="admin-input h-9 text-sm" placeholder="cth: Gratis ongkir Jawa Tengah">
            </div>

            <button type="submit" class="admin-btn-primary w-full h-10 justify-center text-sm font-semibold shadow-xs">
                <span class="material-symbols-outlined text-base">add</span>
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
