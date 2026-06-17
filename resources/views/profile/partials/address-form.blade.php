{{-- Shared address form fields (used for both Create and Edit) --}}
<div>
    <label class="block text-xs font-medium text-warmGrey mb-1" for="label_{{ $address->id ?? 'new' }}">Label Alamat</label>
    <input id="label_{{ $address->id ?? 'new' }}" type="text" name="label"
        value="{{ old('label', $address->label ?? '') }}"
        placeholder="Contoh: Rumah, Kantor"
        class="w-full border border-warmLightGrey rounded-input px-3 py-2.5 text-sm text-warmBlack focus:outline-none focus:ring-1 focus:ring-brand focus:border-brand transition"
        required>
</div>
<div class="grid grid-cols-2 gap-3">
    <div>
        <label class="block text-xs font-medium text-warmGrey mb-1" for="recipient_name_{{ $address->id ?? 'new' }}">Nama Penerima</label>
        <input id="recipient_name_{{ $address->id ?? 'new' }}" type="text" name="recipient_name"
            value="{{ old('recipient_name', $address->recipient_name ?? '') }}"
            placeholder="Nama lengkap"
            class="w-full border border-warmLightGrey rounded-input px-3 py-2.5 text-sm text-warmBlack focus:outline-none focus:ring-1 focus:ring-brand focus:border-brand transition"
            required>
    </div>
    <div>
        <label class="block text-xs font-medium text-warmGrey mb-1" for="phone_{{ $address->id ?? 'new' }}">Nomor HP</label>
        <input id="phone_{{ $address->id ?? 'new' }}" type="text" name="phone"
            value="{{ old('phone', $address->phone ?? '') }}"
            placeholder="08xxxxxxxxxx"
            class="w-full border border-warmLightGrey rounded-input px-3 py-2.5 text-sm text-warmBlack focus:outline-none focus:ring-1 focus:ring-brand focus:border-brand transition"
            required>
    </div>
</div>
<div>
    <label class="block text-xs font-medium text-warmGrey mb-1" for="full_address_{{ $address->id ?? 'new' }}">Alamat Lengkap</label>
    <textarea id="full_address_{{ $address->id ?? 'new' }}" name="full_address" rows="2"
        placeholder="Nama jalan, nomor rumah, RT/RW"
        class="w-full border border-warmLightGrey rounded-input px-3 py-2.5 text-sm text-warmBlack focus:outline-none focus:ring-1 focus:ring-brand focus:border-brand transition resize-none"
        required>{{ old('full_address', $address->full_address ?? '') }}</textarea>
</div>
<div class="grid grid-cols-2 gap-3">
    <div>
        <label class="block text-xs font-medium text-warmGrey mb-1" for="district_{{ $address->id ?? 'new' }}">Kecamatan</label>
        <input id="district_{{ $address->id ?? 'new' }}" type="text" name="district"
            value="{{ old('district', $address->district ?? '') }}"
            placeholder="Kecamatan (opsional)"
            class="w-full border border-warmLightGrey rounded-input px-3 py-2.5 text-sm text-warmBlack focus:outline-none focus:ring-1 focus:ring-brand focus:border-brand transition">
    </div>
    <div>
        <label class="block text-xs font-medium text-warmGrey mb-1" for="city_{{ $address->id ?? 'new' }}">Kota / Kabupaten</label>
        <input id="city_{{ $address->id ?? 'new' }}" type="text" name="city"
            value="{{ old('city', $address->city ?? '') }}"
            placeholder="Kota"
            class="w-full border border-warmLightGrey rounded-input px-3 py-2.5 text-sm text-warmBlack focus:outline-none focus:ring-1 focus:ring-brand focus:border-brand transition"
            required>
    </div>
</div>
<div class="grid grid-cols-2 gap-3">
    <div>
        <label class="block text-xs font-medium text-warmGrey mb-1" for="province_{{ $address->id ?? 'new' }}">Provinsi</label>
        <input id="province_{{ $address->id ?? 'new' }}" type="text" name="province"
            value="{{ old('province', $address->province ?? '') }}"
            placeholder="Provinsi"
            class="w-full border border-warmLightGrey rounded-input px-3 py-2.5 text-sm text-warmBlack focus:outline-none focus:ring-1 focus:ring-brand focus:border-brand transition"
            required>
    </div>
    <div>
        <label class="block text-xs font-medium text-warmGrey mb-1" for="postal_code_{{ $address->id ?? 'new' }}">Kode Pos</label>
        <input id="postal_code_{{ $address->id ?? 'new' }}" type="text" name="postal_code"
            value="{{ old('postal_code', $address->postal_code ?? '') }}"
            placeholder="12345"
            class="w-full border border-warmLightGrey rounded-input px-3 py-2.5 text-sm text-warmBlack focus:outline-none focus:ring-1 focus:ring-brand focus:border-brand transition"
            required>
    </div>
</div>
