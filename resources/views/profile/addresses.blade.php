@extends('layouts.storefront')

@section('title', 'Buku Alamat | el Craft')
@section('meta_description', 'Kelola alamat pengiriman Anda di el Craft untuk pengalaman checkout yang lebih cepat.')

@section('content')
<div class="bg-warmCream min-h-screen pb-24 md:pb-0">

    {{-- ─── HEADER ────────────────────────────────── --}}
    <section class="bg-white border-b border-warmLightGrey py-10 px-5 md:px-8 lg:px-16">
        <div class="max-w-[1280px] mx-auto">
            <nav class="flex items-center space-x-2 text-xs text-warmGrey mb-2" aria-label="Breadcrumb">
                <a href="/" class="hover:text-brand transition-colors">Beranda</a>
                <span class="material-symbols-outlined !text-[12px]">chevron_right</span>
                <a href="{{ route('profile.edit') }}" class="hover:text-brand transition-colors">Profil</a>
                <span class="material-symbols-outlined !text-[12px]">chevron_right</span>
                <span class="text-warmBlack font-medium">Buku Alamat</span>
            </nav>
            <h1 class="text-2xl md:text-3xl font-semibold text-warmBlack font-sans">Buku Alamat</h1>
        </div>
    </section>

    <section class="max-w-[1280px] mx-auto px-5 md:px-8 lg:px-16 py-10">

        {{-- Flash messages --}}
        @if(session('success'))
            <div class="mb-6 bg-green-50 border border-green-200 text-green-700 text-sm px-4 py-3 rounded-card flex items-center space-x-3" role="alert">
                <span class="material-symbols-outlined !text-[18px]">check_circle</span>
                <span>{{ session('success') }}</span>
            </div>
        @endif
        @if(session('error'))
            <div class="mb-6 bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3 rounded-card flex items-center space-x-3" role="alert">
                <span class="material-symbols-outlined !text-[18px]">error</span>
                <span>{{ session('error') }}</span>
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8" x-data="{ showForm: {{ $errors->any() ? 'true' : 'false' }}, editId: null }">

            {{-- ── LEFT: Address List ─────────────────────────────── --}}
            <div class="lg:col-span-2 space-y-4">

                @forelse($addresses as $address)
                    <div class="bg-white rounded-card border {{ $address->is_default ? 'border-brand' : 'border-warmLightGrey' }} p-5 relative transition-all duration-200">

                        {{-- Default badge --}}
                        @if($address->is_default)
                            <span class="absolute top-4 right-4 text-[10px] font-semibold uppercase tracking-wider text-brand bg-brand/10 px-2 py-0.5 rounded-full">Utama</span>
                        @endif

                        <div class="flex items-start justify-between pr-16">
                            <div>
                                <p class="font-semibold text-warmBlack text-sm mb-0.5">{{ $address->label }}</p>
                                <p class="text-xs text-warmBlack font-medium">{{ $address->recipient_name }}</p>
                                <p class="text-xs text-warmGrey">{{ $address->phone }}</p>
                                <p class="text-xs text-warmGrey mt-1 leading-relaxed max-w-sm">
                                    {{ $address->full_address }}, {{ $address->district ? $address->district . ', ' : '' }}{{ $address->city }}, {{ $address->province }}, {{ $address->postal_code }}
                                </p>
                            </div>
                        </div>

                        <div class="flex items-center gap-3 mt-4 pt-4 border-t border-warmLightGrey/60">
                            @unless($address->is_default)
                                <form method="POST" action="{{ route('profile.addresses.setDefault', $address) }}">
                                    @csrf
                                    <button type="submit" class="text-xs text-brand hover:text-brandDark font-medium transition-colors">
                                        Jadikan Utama
                                    </button>
                                </form>
                                <span class="text-warmLightGrey">|</span>
                            @endunless
                            <button @click="editId = {{ $address->id }}; showForm = true" class="text-xs text-warmGrey hover:text-warmBlack font-medium transition-colors">
                                Ubah
                            </button>
                            <span class="text-warmLightGrey">|</span>
                            <form method="POST" action="{{ route('profile.addresses.destroy', $address) }}" onsubmit="return confirm('Hapus alamat ini?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-xs text-red-400 hover:text-red-600 font-medium transition-colors">
                                    Hapus
                                </button>
                            </form>
                        </div>
                    </div>
                @empty
                    <div class="bg-white rounded-card border border-warmLightGrey p-10 flex flex-col items-center justify-center text-center">
                        <span class="material-symbols-outlined text-brand !text-[40px] mb-3">location_on</span>
                        <p class="text-warmBlack font-semibold text-sm mb-1">Belum ada alamat tersimpan</p>
                        <p class="text-xs text-warmGrey">Tambahkan alamat pengiriman agar proses checkout lebih cepat.</p>
                    </div>
                @endforelse

                <button @click="showForm = true; editId = null"
                    class="w-full flex items-center justify-center gap-2 py-3.5 border-2 border-dashed border-brand/40 text-brand hover:bg-brand/5 rounded-card text-xs font-semibold uppercase tracking-wider transition-all duration-200">
                    <span class="material-symbols-outlined !text-[18px]">add</span>
                    Tambah Alamat Baru
                </button>
            </div>

            {{-- ── RIGHT: Add / Edit Form ──────────────────────────── --}}
            <div x-show="showForm" x-cloak class="bg-white rounded-card border border-warmLightGrey p-6 h-fit sticky top-24">
                <div class="flex items-center justify-between mb-5">
                    <h2 class="text-sm font-semibold text-warmBlack" x-text="editId ? 'Ubah Alamat' : 'Alamat Baru'"></h2>
                    <button @click="showForm = false" class="text-warmGrey hover:text-warmBlack flex items-center justify-center" aria-label="Tutup form">
                        <span class="material-symbols-outlined !text-[20px]">close</span>
                    </button>
                </div>

                @foreach($addresses as $addr)
                <form x-show="editId === {{ $addr->id }}" method="POST" action="{{ route('profile.addresses.update', $addr) }}" class="space-y-4">
                    @csrf @method('PUT')
                    @include('profile.partials.address-form', ['address' => $addr])
                    <button type="submit" class="w-full py-3 bg-brand hover:bg-brandDark text-white font-semibold text-xs uppercase tracking-wider rounded-btn transition-colors">
                        Simpan Perubahan
                    </button>
                </form>
                @endforeach

                <form x-show="editId === null" method="POST" action="{{ route('profile.addresses.store') }}" class="space-y-4">
                    @csrf
                    @include('profile.partials.address-form', ['address' => null])
                    <div class="flex items-center gap-2 mt-1">
                        <input type="checkbox" id="is_default_new" name="is_default" value="1" class="rounded text-brand focus:ring-brand border-warmGrey/40">
                        <label for="is_default_new" class="text-xs text-warmGrey">Jadikan alamat utama</label>
                    </div>
                    <button type="submit" class="w-full py-3 bg-brand hover:bg-brandDark text-white font-semibold text-xs uppercase tracking-wider rounded-btn transition-colors">
                        Simpan Alamat
                    </button>
                </form>

                @if($errors->any())
                    <div class="mt-3 text-red-500 text-xs space-y-1">
                        @foreach($errors->all() as $error)
                            <p>{{ $error }}</p>
                        @endforeach
                    </div>
                @endif
            </div>

        </div>
    </section>
</div>
@endsection
