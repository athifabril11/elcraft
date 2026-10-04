@extends('admin.layouts.admin')

@section('title', 'Kelola Banner Promo Beranda')
@section('page-title', 'Kelola Banner Promo Beranda')
@section('page-subtitle', 'Manajemen slide hero carousel promosi di halaman utama toko el Craft')

@section('content')

<div x-data="{
    showAddModal: false,
    showEditModal: false,
    editData: {
        id: '',
        title: '',
        eyebrow: '',
        description: '',
        button_text: 'LIHAT KOLEKSI',
        button_url: '/products',
        sort_order: 0,
        is_active: true,
        image_url: ''
    },
    openEdit(banner) {
        this.editData = { ...banner };
        this.showEditModal = true;
    }
}">

@if($errors->any())
    <div class="mb-4 p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs font-semibold space-y-1">
        @foreach($errors->all() as $err)
            <div class="flex items-center gap-1.5">
                <span class="material-symbols-outlined text-sm text-rose-600">error</span>
                <span>{{ $err }}</span>
            </div>
        @endforeach
    </div>
@endif

<!-- ========================================================= -->
<!-- HEADER & QUICK ACTION -->
<!-- ========================================================= -->
<div class="admin-card p-5 mb-6 flex flex-col sm:flex-row items-center justify-between gap-4">
    <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-brand-100 text-brand-700 flex items-center justify-center">
            <span class="material-symbols-outlined text-xl">view_carousel</span>
        </div>
        <div>
            <h3 class="text-xs font-bold uppercase tracking-wider text-atelier-textDark m-0">Slide Carousel Beranda</h3>
            <p class="text-[11px] text-atelier-textMuted mt-0.5 m-0">
                {{ $totalActiveBanners }} banner aktif tayang saat ini • Rekomendasi resolusi: <strong>1200 x 500 px</strong> (rasio landscape)
            </p>
        </div>
    </div>

    <button @click="showAddModal = true" class="admin-btn-primary text-xs !py-2 shrink-0">
        <span class="material-symbols-outlined !text-sm">add_photo_alternate</span>
        <span>Tambah Banner Baru</span>
    </button>
</div>

<!-- ========================================================= -->
<!-- BANNERS LIST -->
<!-- ========================================================= -->
<div class="space-y-4">
    @forelse($banners as $banner)
        <div class="admin-card p-5 flex flex-col md:flex-row items-start md:items-center justify-between gap-5 hover:border-brand-300 transition">
            
            <div class="flex flex-col sm:flex-row items-start sm:items-center gap-4 min-w-0 flex-1">
                <!-- Thumbnail -->
                <div class="w-full sm:w-48 h-24 rounded-xl bg-stone-100 border border-atelier-border overflow-hidden shrink-0">
                    <img src="{{ asset('storage/' . $banner->image_path) }}" alt="{{ $banner->title }}"
                         class="w-full h-full object-cover">
                </div>

                <div class="min-w-0 space-y-1">
                    @if($banner->eyebrow)
                        <span class="text-[10px] font-bold text-brand-600 uppercase tracking-widest">{{ $banner->eyebrow }}</span>
                    @endif
                    <h4 class="text-sm font-bold text-atelier-textDark m-0 truncate">{{ $banner->title }}</h4>
                    @if($banner->description)
                        <p class="text-xs text-atelier-textMuted m-0 line-clamp-1 max-w-xl">{{ $banner->description }}</p>
                    @endif
                    <div class="text-[10px] text-atelier-textLight font-mono flex items-center gap-3 pt-1">
                        <span>CTA: <strong>{{ $banner->button_text }}</strong> &rarr; <code class="text-brand-700">{{ $banner->button_url }}</code></span>
                        <span>•</span>
                        <span>Urutan: #<strong>{{ $banner->sort_order }}</strong></span>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-3 self-end md:self-center shrink-0">
                @if($banner->is_active)
                    <span class="badge-active">Tayang</span>
                @else
                    <span class="badge-inactive !bg-rose-100 !text-rose-700">Nonaktif</span>
                @endif

                <button type="button" @click="openEdit({
                    id: '{{ $banner->id }}',
                    title: '{{ addslashes($banner->title) }}',
                    eyebrow: '{{ addslashes($banner->eyebrow ?? '') }}',
                    description: '{{ addslashes($banner->description ?? '') }}',
                    button_text: '{{ addslashes($banner->button_text ?? '') }}',
                    button_url: '{{ addslashes($banner->button_url ?? '') }}',
                    sort_order: '{{ $banner->sort_order }}',
                    is_active: {{ $banner->is_active ? 'true' : 'false' }},
                    image_url: '{{ asset('storage/' . $banner->image_path) }}'
                })" class="p-1.5 text-atelier-textLight hover:text-brand-600 rounded-lg hover:bg-brand-50 transition" title="Edit Banner">
                    <span class="material-symbols-outlined !text-sm">edit</span>
                </button>

                <form method="POST" action="{{ route('admin.banners.toggle', $banner->id) }}" class="inline m-0">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="p-1.5 {{ $banner->is_active ? 'text-emerald-600 hover:text-rose-600' : 'text-stone-400 hover:text-emerald-600' }} rounded-lg hover:bg-brand-50 transition" title="Toggle Tayang/Nonaktif">
                        <span class="material-symbols-outlined !text-sm">{{ $banner->is_active ? 'toggle_on' : 'toggle_off' }}</span>
                    </button>
                </form>

                <button type="button" title="Hapus" @click="$dispatch('confirm-delete', {
                    action: '{{ route('admin.banners.destroy', $banner->id) }}',
                    title: 'Hapus Banner?',
                    message: 'Apakah Anda yakin ingin menghapus banner ini? Tindakan ini tidak dapat dibatalkan.'
                })" class="p-1.5 text-atelier-textLight hover:text-rose-600 rounded-lg hover:bg-rose-50 transition">
                    <span class="material-symbols-outlined !text-sm">delete</span>
                </button>
            </div>

        </div>
    @empty
        <div class="admin-card p-12 text-center text-atelier-textMuted text-xs">
            <span class="material-symbols-outlined text-4xl text-atelier-textLight mb-2">view_carousel</span>
            <p class="font-semibold text-atelier-textDark">Belum ada banner promosi yang diunggah.</p>
            <p class="text-atelier-textLight mt-1">Halaman utama toko saat ini menampilkan slide bawaan sistem.</p>
        </div>
    @endforelse

    @if($banners->hasPages())
        <div class="p-4 border-t border-atelier-border bg-white rounded-xl flex justify-end">
            {{ $banners->links() }}
        </div>
    @endif
</div>

<!-- ========================================================= -->
<!-- MODAL TAMBAH BANNER -->
<!-- ========================================================= -->
<div x-show="showAddModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs">
    <div class="bg-white rounded-2xl max-w-lg w-full shadow-2xl border border-atelier-border overflow-hidden" @click.away="showAddModal = false">
        <form method="POST" action="{{ route('admin.banners.store') }}" enctype="multipart/form-data" class="m-0">
            @csrf
            <div class="p-5 border-b border-atelier-border flex items-center justify-between bg-stone-50/60">
                <h3 class="text-sm font-bold text-atelier-textDark font-sans">Tambah Banner Promosi Baru</h3>
                <button type="button" @click="showAddModal = false" class="text-atelier-textLight hover:text-stone-800">
                    <span class="material-symbols-outlined text-lg">close</span>
                </button>
            </div>

            <div class="p-6 space-y-4 text-xs">
                <div>
                    <label class="admin-label">Judul Utama Banner *</label>
                    <input type="text" name="title" required placeholder="cth: Koleksi Kemilau Emas Mawar 2026" class="admin-input">
                </div>

                <div>
                    <label class="admin-label">Teks Eyebrow (Aksen Atas, opsional)</label>
                    <input type="text" name="eyebrow" placeholder="cth: EXCLUSIVE SELECTION atau NEW ARRIVAL" class="admin-input">
                </div>

                <div>
                    <label class="admin-label">Deskripsi Singkat</label>
                    <textarea name="description" rows="2" placeholder="cth: Kilau mutiara alami pilihan dengan sentuhan kemewahan modern." class="admin-input"></textarea>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="admin-label">Teks Tombol</label>
                        <input type="text" name="button_text" value="LIHAT KOLEKSI" class="admin-input">
                    </div>
                    <div>
                        <label class="admin-label">Link Halaman Produk</label>
                        <input type="text" name="button_url" value="/products" class="admin-input font-mono">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="admin-label">Urutan Tampilan (#)</label>
                        <input type="number" name="sort_order" value="0" min="0" class="admin-input">
                    </div>
                    <div class="flex items-center pt-5">
                        <input type="checkbox" name="is_active" id="banner_add_active" value="1" checked class="rounded border-stone-300 text-brand-600 focus:ring-brand-400">
                        <label for="banner_add_active" class="ml-2 text-xs font-semibold text-atelier-textDark cursor-pointer">Langsung Tayangkan</label>
                    </div>
                </div>

                <div>
                    <label class="admin-label">File Gambar Banner * (Maks 4MB, rasio landscape)</label>
                    <input type="file" name="image" required accept="image/*" class="admin-input !py-1.5">
                    <p class="text-[10px] text-atelier-textLight mt-1">Disarankan format JPEG, PNG, atau WebP (1200x500 px)</p>
                </div>
            </div>

            <div class="p-4 border-t border-atelier-border bg-stone-50/60 flex items-center justify-end gap-2">
                <button type="button" @click="showAddModal = false" class="admin-btn-ghost text-xs">Batal</button>
                <button type="submit" class="admin-btn-primary text-xs">Upload &amp; Simpan</button>
            </div>
        </form>
    </div>
</div>

<!-- ========================================================= -->
<!-- MODAL EDIT BANNER -->
<!-- ========================================================= -->
<div x-show="showEditModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs">
    <div class="bg-white rounded-2xl max-w-lg w-full shadow-2xl border border-atelier-border overflow-hidden" @click.away="showEditModal = false">
        <form method="POST" :action="'/admin/banners/' + editData.id" enctype="multipart/form-data" class="m-0">
            @csrf
            @method('PUT')
            <div class="p-5 border-b border-atelier-border flex items-center justify-between bg-stone-50/60">
                <h3 class="text-sm font-bold text-atelier-textDark font-sans">Edit Banner Promosi</h3>
                <button type="button" @click="showEditModal = false" class="text-atelier-textLight hover:text-stone-800">
                    <span class="material-symbols-outlined text-lg">close</span>
                </button>
            </div>

            <div class="p-6 space-y-4 text-xs">
                <div>
                    <label class="admin-label">Judul Utama Banner *</label>
                    <input type="text" name="title" x-model="editData.title" required class="admin-input">
                </div>

                <div>
                    <label class="admin-label">Teks Eyebrow</label>
                    <input type="text" name="eyebrow" x-model="editData.eyebrow" class="admin-input">
                </div>

                <div>
                    <label class="admin-label">Deskripsi Singkat</label>
                    <textarea name="description" x-model="editData.description" rows="2" class="admin-input"></textarea>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="admin-label">Teks Tombol</label>
                        <input type="text" name="button_text" x-model="editData.button_text" class="admin-input">
                    </div>
                    <div>
                        <label class="admin-label">Link Halaman Produk</label>
                        <input type="text" name="button_url" x-model="editData.button_url" class="admin-input font-mono">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="admin-label">Urutan Tampilan (#)</label>
                        <input type="number" name="sort_order" x-model="editData.sort_order" min="0" class="admin-input">
                    </div>
                    <div class="flex items-center pt-5">
                        <input type="checkbox" name="is_active" id="banner_edit_active" value="1" :checked="editData.is_active" class="rounded border-stone-300 text-brand-600 focus:ring-brand-400">
                        <label for="banner_edit_active" class="ml-2 text-xs font-semibold text-atelier-textDark cursor-pointer">Tayangkan Banner</label>
                    </div>
                </div>

                <div>
                    <label class="admin-label">Ganti Gambar (Kosongkan jika tidak diubah)</label>
                    <input type="file" name="image" accept="image/*" class="admin-input !py-1.5">
                </div>
            </div>

            <div class="p-4 border-t border-atelier-border bg-stone-50/60 flex items-center justify-end gap-2">
                <button type="button" @click="showEditModal = false" class="admin-btn-ghost text-xs">Batal</button>
                <button type="submit" class="admin-btn-primary text-xs">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

</div>

@endsection
