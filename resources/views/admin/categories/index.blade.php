@extends('admin.layouts.admin')

@section('title', 'Kelola Kategori')

@section('content')
<div x-data="{
    showAddModal: false,
    showEditModal: false,
    editCategory: { id: null, name: '', slug: '', description: '', is_active: 1, actionUrl: '' },
    openEdit(cat, actionUrl) {
        this.editCategory = {
            id: cat.id,
            name: cat.name,
            slug: cat.slug,
            description: cat.description || '',
            is_active: cat.is_active ? 1 : 0,
            actionUrl: actionUrl
        };
        this.showEditModal = true;
    }
}" class="space-y-6">

    <!-- Header & Summary Cards -->
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-brand-500 ring-4 ring-brand-100"></span>
                <h1 class="text-2xl font-extrabold text-atelier-textDark tracking-tight font-sans">Kategori Produk Kerajinan</h1>
            </div>
            <p class="text-xs text-atelier-textMuted mt-1">
                Kelola klasifikasi dan pengelompokan karya perhiasan el Craft
            </p>
        </div>

        <div class="flex items-center gap-3">
            <button @click="showAddModal = true"
                    class="px-4 py-2.5 bg-brand-600 hover:bg-brand-700 text-white text-xs font-bold rounded-xl transition flex items-center gap-1.5 shadow-xs cursor-pointer">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                <span>Tambah Kategori Baru</span>
            </button>
        </div>
    </div>

    <!-- Quick Stats Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-white p-5 rounded-2xl border border-atelier-border shadow-2xs flex items-center justify-between">
            <div>
                <span class="text-[11px] font-bold uppercase tracking-wider text-atelier-textLight">Total Kategori</span>
                <div class="text-2xl font-black text-atelier-textDark font-sans mt-0.5">{{ $totalCategories }}</div>
            </div>
            <div class="w-10 h-10 rounded-xl bg-brand-50 text-brand-600 flex items-center justify-center border border-brand-100">
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="7" height="7" rx="1"></rect><rect x="3" y="14" width="7" height="7" rx="1"></rect><path d="M14 4h7"></path><path d="M14 9h7"></path><path d="M14 15h7"></path><path d="M14 20h7"></path></svg>
            </div>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-atelier-border shadow-2xs flex items-center justify-between">
            <div>
                <span class="text-[11px] font-bold uppercase tracking-wider text-atelier-textLight">Kategori Aktif</span>
                <div class="text-2xl font-black text-emerald-700 font-sans mt-0.5">{{ $activeCategories }}</div>
            </div>
            <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center border border-emerald-100">
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><polyline points="20 6 9 17 4 12"></polyline></svg>
            </div>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-atelier-border shadow-2xs flex items-center justify-between">
            <div>
                <span class="text-[11px] font-bold uppercase tracking-wider text-atelier-textLight">Total Karya Terkatalog</span>
                <div class="text-2xl font-black text-stone-800 font-sans mt-0.5">{{ $totalProductsInCategories }} Produk</div>
            </div>
            <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center border border-amber-100">
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><polygon points="6 3 18 3 22 9 12 22 2 9 6 3"></polygon></svg>
            </div>
        </div>
    </div>

    <!-- Filters & Search Form -->
    <div class="bg-white rounded-2xl p-4 border border-atelier-border shadow-2xs flex flex-wrap items-center justify-between gap-3">
        <form method="GET" action="{{ route('admin.categories.index') }}" class="flex flex-wrap items-center gap-3 flex-1">
            <div class="relative flex-1 min-w-[200px] max-w-md">
                <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-stone-400">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                </span>
                <input type="text" name="q" value="{{ $search }}" placeholder="Cari nama kategori atau slug..."
                       class="w-full pl-9 pr-3 py-2 text-xs border border-stone-200 rounded-xl outline-none focus:border-brand-400 bg-stone-50 hover:bg-white focus:bg-white transition">
            </div>

            <select name="status" onchange="this.form.submit()" class="text-xs border border-stone-200 rounded-xl px-3 py-2 bg-stone-50 outline-none focus:border-brand-400 text-stone-700">
                <option value="">Status: Semua</option>
                <option value="active" {{ $status === 'active' ? 'selected' : '' }}>Hanya Aktif</option>
                <option value="inactive" {{ $status === 'inactive' ? 'selected' : '' }}>Hanya Nonaktif</option>
            </select>

            <button type="submit" class="px-3.5 py-2 bg-stone-100 hover:bg-stone-200 text-stone-700 text-xs font-semibold rounded-xl transition">
                Filter
            </button>

            @if($search || $status)
                <a href="{{ route('admin.categories.index') }}" class="text-xs text-rose-600 hover:text-rose-700 font-semibold flex items-center gap-1">
                    <span>Reset Filter</span>
                </a>
            @endif
        </form>
    </div>

    <!-- Table of Categories -->
    <div class="bg-white rounded-2xl border border-atelier-border shadow-2xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-stone-50/80 text-atelier-textLight uppercase tracking-wider font-semibold border-b border-atelier-borderSubtle">
                    <tr>
                        <th class="px-6 py-3.5">Kategori &amp; URL Slug</th>
                        <th class="px-6 py-3.5">Deskripsi</th>
                        <th class="px-6 py-3.5 text-center">Jumlah Produk</th>
                        <th class="px-6 py-3.5 text-center">Status</th>
                        <th class="px-6 py-3.5 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-atelier-borderSubtle">
                    @forelse($categories as $category)
                    <tr class="hover:bg-brand-50/20 transition-colors">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="font-bold text-stone-900 text-sm">{{ $category->name }}</div>
                            <div class="text-[11px] text-brand-700 font-mono mt-0.5">/products?category={{ $category->slug }}</div>
                        </td>
                        <td class="px-6 py-4 text-stone-600 max-w-xs truncate">
                            {{ $category->description ?? '—' }}
                        </td>
                        <td class="px-6 py-4 text-center whitespace-nowrap">
                            <span class="px-3 py-1 bg-stone-100 text-stone-800 rounded-full font-bold text-xs">
                                {{ $category->products_count }} Produk
                            </span>
                        </td>
                        <td class="px-6 py-4 text-center whitespace-nowrap">
                            <form method="POST" action="{{ route('admin.categories.toggle', $category) }}" class="inline m-0">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="px-2.5 py-1 rounded-full text-[10px] font-bold transition cursor-pointer {{ $category->is_active ? 'bg-emerald-50 text-emerald-700 border border-emerald-200 hover:bg-emerald-100' : 'bg-stone-100 text-stone-500 border border-stone-200 hover:bg-stone-200' }}">
                                    {{ $category->is_active ? 'Aktif' : 'Nonaktif' }}
                                </button>
                            </form>
                        </td>
                        <td class="px-6 py-4 text-right whitespace-nowrap">
                            <div class="flex items-center justify-end gap-1.5">
                                <!-- Edit button -->
                                <button type="button"
                                        @click="openEdit({{ $category->toJson() }}, '{{ route('admin.categories.update', $category) }}')"
                                        title="Edit Kategori"
                                        class="p-1.5 text-stone-500 hover:text-brand-600 rounded-lg hover:bg-brand-50 transition cursor-pointer">
                                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"></path></svg>
                                </button>

                                <!-- Delete button -->
                                @if($category->products_count === 0)
                                    <button type="button" title="Hapus Kategori" @click="$dispatch('confirm-delete', {
                                        action: '{{ route('admin.categories.destroy', $category) }}',
                                        title: 'Hapus Kategori?',
                                        message: 'Apakah Anda yakin ingin menghapus kategori &quot;{{ addslashes($category->name) }}&quot;? Tindakan ini tidak dapat dibatalkan.'
                                    })" class="p-1.5 text-stone-400 hover:text-rose-600 rounded-lg hover:bg-rose-50 transition cursor-pointer">
                                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                    </button>
                                @else
                                    <button type="button" title="Kategori tidak dapat dihapus karena memiliki {{ $category->products_count }} produk" class="p-1.5 text-stone-300 cursor-not-allowed opacity-50">
                                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-6 py-12 text-center text-xs text-atelier-textMuted">
                            <div class="w-12 h-12 mx-auto mb-2 text-stone-300 flex items-center justify-center rounded-2xl bg-stone-50 border border-stone-100">
                                <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="7" height="7" rx="1"></rect><rect x="3" y="14" width="7" height="7" rx="1"></rect><path d="M14 4h7"></path><path d="M14 9h7"></path><path d="M14 15h7"></path><path d="M14 20h7"></path></svg>
                            </div>
                            <div class="font-bold text-stone-700">Belum ada kategori ditemukan.</div>
                            <div class="text-[11px] text-stone-400 mt-1">Gunakan tombol "Tambah Kategori Baru" untuk membuat kategori pertama.</div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($categories->hasPages())
        <div class="p-4 border-t border-atelier-border bg-stone-50/50">
            {{ $categories->links() }}
        </div>
        @endif
    </div>

    <!-- MODAL TAMBAH KATEGORI -->
    <div x-show="showAddModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs">
        <div class="bg-white rounded-2xl max-w-md w-full shadow-2xl border border-atelier-border overflow-hidden" @click.away="showAddModal = false">
            <div class="p-6 border-b border-atelier-border flex items-center justify-between bg-stone-50/60">
                <div>
                    <h3 class="text-base font-bold text-atelier-textDark font-sans">Tambah Kategori Baru</h3>
                    <p class="text-[11px] text-atelier-textMuted">Buat pengelompokan karya perhiasan baru</p>
                </div>
                <button @click="showAddModal = false" class="p-1 text-atelier-textLight hover:text-stone-800 rounded-lg cursor-pointer">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                </button>
            </div>
            <form method="POST" action="{{ route('admin.categories.store') }}" class="p-6 space-y-4 text-xs">
                @csrf
                <div>
                    <label class="block font-bold text-stone-800 uppercase tracking-wider text-[10px] mb-1">Nama Kategori *</label>
                    <input type="text" name="name" required placeholder="cth: Cincin Perak Vintage"
                           class="w-full px-3.5 py-2.5 border border-stone-200 rounded-xl focus:outline-none focus:border-brand-400 bg-stone-50 hover:bg-white focus:bg-white transition">
                </div>
                <div>
                    <label class="block font-bold text-stone-800 uppercase tracking-wider text-[10px] mb-1">Slug URL (Opsional)</label>
                    <input type="text" name="slug" placeholder="Otomatis dibuat dari nama kategori jika dikosongkan"
                           class="w-full px-3.5 py-2.5 border border-stone-200 rounded-xl font-mono text-brand-700 focus:outline-none focus:border-brand-400 bg-stone-50 hover:bg-white focus:bg-white transition">
                </div>
                <div>
                    <label class="block font-bold text-stone-800 uppercase tracking-wider text-[10px] mb-1">Deskripsi Singkat</label>
                    <textarea name="description" rows="3" placeholder="Koleksi perhiasan elegan berbahan perak murni..."
                              class="w-full px-3.5 py-2.5 border border-stone-200 rounded-xl focus:outline-none focus:border-brand-400 bg-stone-50 hover:bg-white focus:bg-white transition"></textarea>
                </div>
                <div class="flex items-center gap-2 pt-2">
                    <input type="checkbox" id="add_is_active" name="is_active" value="1" checked class="w-4 h-4 rounded text-brand-600 focus:ring-brand-400">
                    <label for="add_is_active" class="font-bold text-stone-700 cursor-pointer">Aktifkan Kategori ini di Katalog</label>
                </div>
                <div class="pt-4 border-t border-atelier-border flex items-center justify-end gap-2">
                    <button type="button" @click="showAddModal = false" class="px-4 py-2 border border-stone-200 text-stone-600 font-semibold rounded-xl hover:bg-stone-50 transition cursor-pointer">
                        Batal
                    </button>
                    <button type="submit" class="px-5 py-2 bg-brand-600 hover:bg-brand-700 text-white font-bold rounded-xl transition shadow-xs cursor-pointer">
                        Simpan Kategori
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL EDIT KATEGORI -->
    <div x-show="showEditModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs">
        <div class="bg-white rounded-2xl max-w-md w-full shadow-2xl border border-atelier-border overflow-hidden" @click.away="showEditModal = false">
            <div class="p-6 border-b border-atelier-border flex items-center justify-between bg-stone-50/60">
                <div>
                    <h3 class="text-base font-bold text-atelier-textDark font-sans">Edit Kategori</h3>
                    <p class="text-[11px] text-atelier-textMuted">Perbarui rincian kategori karya</p>
                </div>
                <button @click="showEditModal = false" class="p-1 text-atelier-textLight hover:text-stone-800 rounded-lg cursor-pointer">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                </button>
            </div>
            <form method="POST" :action="editCategory.actionUrl" class="p-6 space-y-4 text-xs">
                @csrf
                @method('PUT')
                <div>
                    <label class="block font-bold text-stone-800 uppercase tracking-wider text-[10px] mb-1">Nama Kategori *</label>
                    <input type="text" name="name" x-model="editCategory.name" required
                           class="w-full px-3.5 py-2.5 border border-stone-200 rounded-xl focus:outline-none focus:border-brand-400 bg-stone-50 hover:bg-white focus:bg-white transition">
                </div>
                <div>
                    <label class="block font-bold text-stone-800 uppercase tracking-wider text-[10px] mb-1">Slug URL</label>
                    <input type="text" name="slug" x-model="editCategory.slug"
                           class="w-full px-3.5 py-2.5 border border-stone-200 rounded-xl font-mono text-brand-700 focus:outline-none focus:border-brand-400 bg-stone-50 hover:bg-white focus:bg-white transition">
                </div>
                <div>
                    <label class="block font-bold text-stone-800 uppercase tracking-wider text-[10px] mb-1">Deskripsi Singkat</label>
                    <textarea name="description" rows="3" x-model="editCategory.description"
                              class="w-full px-3.5 py-2.5 border border-stone-200 rounded-xl focus:outline-none focus:border-brand-400 bg-stone-50 hover:bg-white focus:bg-white transition"></textarea>
                </div>
                <div class="flex items-center gap-2 pt-2">
                    <input type="checkbox" id="edit_is_active" name="is_active" value="1" :checked="editCategory.is_active == 1" class="w-4 h-4 rounded text-brand-600 focus:ring-brand-400">
                    <label for="edit_is_active" class="font-bold text-stone-700 cursor-pointer">Kategori Aktif di Toko</label>
                </div>
                <div class="pt-4 border-t border-atelier-border flex items-center justify-end gap-2">
                    <button type="button" @click="showEditModal = false" class="px-4 py-2 border border-stone-200 text-stone-600 font-semibold rounded-xl hover:bg-stone-50 transition cursor-pointer">
                        Batal
                    </button>
                    <button type="submit" class="px-5 py-2 bg-brand-600 hover:bg-brand-700 text-white font-bold rounded-xl transition shadow-xs cursor-pointer">
                        Perbarui Kategori
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
