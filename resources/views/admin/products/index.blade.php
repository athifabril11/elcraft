@extends('admin.layouts.admin')

@section('title', 'Kelola Produk')

@section('content')
<div x-data="{
    showAddModal: false,
    showEditModal: false,
    activeTab: 'info',
    editTab: 'info',
    variants: [],
    editProduct: {
        id: null,
        name: '',
        category_id: '',
        sku: '',
        price: 0,
        weight: 100,
        stock: 0,
        discount_type: 'none',
        discount_value: 0,
        discount_start: '',
        discount_end: '',
        description: '',
        is_featured: 0,
        is_active: 1,
        actionUrl: '',
        variants: [],
        primary_image_url: null,
        images: []
    },
    addVariantRow() {
        this.variants.push({ variant_name: '', sku: '', additional_price: 0, stock: 0 });
    },
    removeVariantRow(index) {
        this.variants.splice(index, 1);
    },
    addEditVariantRow() {
        this.editProduct.variants.push({ id: null, variant_name: '', sku: '', additional_price: 0, stock: 0 });
    },
    removeEditVariantRow(index) {
        this.editProduct.variants.splice(index, 1);
    },
    openEdit(prod, actionUrl) {
        this.editProduct = {
            id: prod.id,
            name: prod.name,
            category_id: prod.category_id,
            sku: prod.sku || '',
            price: prod.price,
            weight: prod.weight || 100,
            stock: prod.stock,
            discount_type: prod.discount_type || 'none',
            discount_value: prod.discount_value || 0,
            discount_start: prod.discount_start ? prod.discount_start.substring(0,10) : '',
            discount_end: prod.discount_end ? prod.discount_end.substring(0,10) : '',
            description: prod.description || '',
            is_featured: prod.is_featured ? 1 : 0,
            is_active: prod.is_active ? 1 : 0,
            actionUrl: actionUrl,
            variants: prod.variants && prod.variants.length > 0 ? JSON.parse(JSON.stringify(prod.variants)) : [],
            primary_image_url: prod.primary_image || null,
            images: prod.images || []
        };
        this.editTab = 'info';
        this.showEditModal = true;
    }
}" class="space-y-6">

    <!-- Header Section -->
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-brand-500 ring-4 ring-brand-100"></span>
                <h1 class="text-2xl font-extrabold text-atelier-textDark tracking-tight font-sans">Katalog Produk Kerajinan</h1>
            </div>
            <p class="text-xs text-atelier-textMuted mt-1">
                Kelola karya perhiasan, sistem 2-tier SKU, harga dasar, foto produk, dan varian dinamis
            </p>
        </div>

        <div class="flex items-center gap-3">
            <button @click="showAddModal = true; activeTab = 'info'; variants = [];"
                    class="px-4 py-2.5 bg-brand-600 hover:bg-brand-700 text-white text-xs font-bold rounded-xl transition flex items-center gap-1.5 shadow-xs cursor-pointer">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                <span>Tambah Produk Baru</span>
            </button>
        </div>
    </div>

    <!-- Quick Stats Cards (Interactive Click-to-Filter) -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3.5">
        <!-- Total Produk -->
        <a href="{{ route('admin.products.index') }}"
           class="p-4 rounded-2xl border transition-all duration-200 flex items-center justify-between group {{ empty($stockFilter) ? 'bg-white border-brand-300 ring-2 ring-brand-400/20 shadow-xs' : 'bg-white border-atelier-border hover:border-brand-200 hover:shadow-xs' }}">
            <div>
                <span class="text-[10px] font-bold uppercase tracking-wider text-atelier-textLight">Total Produk</span>
                <div class="text-xl font-black text-atelier-textDark font-sans mt-0.5">{{ $totalProducts }}</div>
            </div>
            <div class="w-10 h-10 rounded-xl bg-brand-50 text-brand-600 flex items-center justify-center border border-brand-100 group-hover:scale-105 transition-transform shrink-0">
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>
            </div>
        </a>

        <!-- Stok Aman -->
        <a href="{{ route('admin.products.index', array_merge(request()->except(['stock', 'page']), ['stock' => 'safe'])) }}"
           class="p-4 rounded-2xl border transition-all duration-200 flex items-center justify-between group {{ $stockFilter === 'safe' ? 'bg-emerald-50/40 border-emerald-300 ring-2 ring-emerald-400/20 shadow-xs' : 'bg-white border-atelier-border hover:border-emerald-200 hover:shadow-xs' }}">
            <div>
                <span class="text-[10px] font-bold uppercase tracking-wider text-emerald-800">Stok Aman (> 3)</span>
                <div class="text-xl font-black text-emerald-700 font-sans mt-0.5">{{ $safeStockCount }}</div>
            </div>
            <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center border border-emerald-100 group-hover:scale-105 transition-transform shrink-0">
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><polyline points="20 6 9 17 4 12"></polyline></svg>
            </div>
        </a>

        <!-- Stok Menipis -->
        <a href="{{ route('admin.products.index', array_merge(request()->except(['stock', 'page']), ['stock' => 'low'])) }}"
           class="p-4 rounded-2xl border transition-all duration-200 flex items-center justify-between group {{ $stockFilter === 'low' ? 'bg-amber-50/40 border-amber-300 ring-2 ring-amber-400/20 shadow-xs' : 'bg-white border-atelier-border hover:border-amber-200 hover:shadow-xs' }}">
            <div>
                <span class="text-[10px] font-bold uppercase tracking-wider text-amber-800">Stok Menipis (≤ 3)</span>
                <div class="text-xl font-black text-amber-600 font-sans mt-0.5">{{ $lowStockCount }}</div>
            </div>
            <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center border border-amber-100 group-hover:scale-105 transition-transform shrink-0">
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
            </div>
        </a>

        <!-- Stok Habis -->
        <a href="{{ route('admin.products.index', array_merge(request()->except(['stock', 'page']), ['stock' => 'out'])) }}"
           class="p-4 rounded-2xl border transition-all duration-200 flex items-center justify-between group {{ $stockFilter === 'out' ? 'bg-rose-50/40 border-rose-300 ring-2 ring-rose-400/20 shadow-xs' : 'bg-white border-atelier-border hover:border-rose-200 hover:shadow-xs' }}">
            <div>
                <span class="text-[10px] font-bold uppercase tracking-wider text-rose-800">Stok Habis (0)</span>
                <div class="text-xl font-black text-rose-600 font-sans mt-0.5">{{ $outOfStockCount }}</div>
            </div>
            <div class="w-10 h-10 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center border border-rose-100 group-hover:scale-105 transition-transform shrink-0">
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>
            </div>
        </a>
    </div>

    <!-- Filter & Search Bar with Bespoke Select Styling -->
    <div class="bg-white rounded-2xl p-4 border border-atelier-border shadow-2xs">
        <form method="GET" action="{{ route('admin.products.index') }}" class="flex flex-wrap items-center gap-3">
            <!-- Search Input -->
            <div class="relative flex-1 min-w-[220px]">
                <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-stone-400">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                </span>
                <input type="text" name="q" value="{{ $search }}" placeholder="Cari nama produk, SKU, atau kategori..."
                       class="w-full pl-10 pr-3.5 py-2.5 text-xs border border-stone-200 rounded-xl outline-none focus:border-brand-400 bg-stone-50/70 hover:bg-white focus:bg-white transition shadow-2xs">
            </div>

            <!-- Filter Kategori -->
            <div class="relative min-w-[150px]">
                <select name="category" onchange="this.form.submit()" class="w-full appearance-none text-xs border border-stone-200 rounded-xl pl-3.5 pr-8 py-2.5 bg-stone-50/70 hover:bg-white focus:bg-white outline-none focus:border-brand-400 text-stone-700 font-medium transition cursor-pointer shadow-2xs">
                    <option value="">Semua Kategori</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}" {{ $categoryFilter == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                    @endforeach
                </select>
                <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-stone-400">
                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polyline points="6 9 12 15 18 9"></polyline></svg>
                </div>
            </div>

            <!-- Filter Stok -->
            <div class="relative min-w-[160px]">
                <select name="stock" onchange="this.form.submit()" class="w-full appearance-none text-xs border border-stone-200 rounded-xl pl-3.5 pr-8 py-2.5 bg-stone-50/70 hover:bg-white focus:bg-white outline-none focus:border-brand-400 text-stone-700 font-medium transition cursor-pointer shadow-2xs">
                    <option value="">Kondisi Stok: Semua</option>
                    <option value="safe" {{ $stockFilter === 'safe' ? 'selected' : '' }}>Stok Aman (> 3)</option>
                    <option value="low" {{ $stockFilter === 'low' ? 'selected' : '' }}>Stok Menipis (≤ 3)</option>
                    <option value="out" {{ $stockFilter === 'out' ? 'selected' : '' }}>Stok Habis (0)</option>
                </select>
                <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-stone-400">
                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polyline points="6 9 12 15 18 9"></polyline></svg>
                </div>
            </div>

            <!-- Filter Status -->
            <div class="relative min-w-[140px]">
                <select name="status" onchange="this.form.submit()" class="w-full appearance-none text-xs border border-stone-200 rounded-xl pl-3.5 pr-8 py-2.5 bg-stone-50/70 hover:bg-white focus:bg-white outline-none focus:border-brand-400 text-stone-700 font-medium transition cursor-pointer shadow-2xs">
                    <option value="">Status: Semua</option>
                    <option value="active" {{ $statusFilter === 'active' ? 'selected' : '' }}>Hanya Aktif</option>
                    <option value="inactive" {{ $statusFilter === 'inactive' ? 'selected' : '' }}>Hanya Nonaktif</option>
                </select>
                <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-stone-400">
                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polyline points="6 9 12 15 18 9"></polyline></svg>
                </div>
            </div>

            <button type="submit" class="px-4 py-2.5 bg-stone-100 hover:bg-stone-200 text-stone-700 text-xs font-semibold rounded-xl transition cursor-pointer">
                Filter
            </button>

            @if($search || $categoryFilter || $stockFilter || $statusFilter)
                <a href="{{ route('admin.products.index') }}" class="text-xs text-rose-600 hover:text-rose-700 font-semibold flex items-center gap-1.5 px-2 py-1 rounded-lg hover:bg-rose-50 transition">
                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                    <span>Reset Filter</span>
                </a>
            @endif
        </form>
    </div>

    <!-- Products Table (Balanced, Responsive & Zero Column Truncation) -->
    <div class="bg-white rounded-2xl border border-atelier-border shadow-2xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs min-w-[920px]">
                <thead class="bg-stone-50/90 text-atelier-textLight uppercase tracking-wider font-semibold border-b border-atelier-borderSubtle">
                    <tr>
                        <th class="px-5 py-3.5">Produk &amp; Detail</th>
                        <th class="px-4 py-3.5 whitespace-nowrap">Harga Dasar</th>
                        <th class="px-4 py-3.5">Varian</th>
                        <th class="px-4 py-3.5 whitespace-nowrap min-w-[95px]">Stok</th>
                        <th class="px-4 py-3.5 text-center whitespace-nowrap min-w-[100px]">Status</th>
                        <th class="px-5 py-3.5 text-right whitespace-nowrap min-w-[100px]">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-atelier-borderSubtle">
                    @forelse($products as $product)
                    @php
                        $primaryImg = $product->primary_image;
                        $imgSrc = $primaryImg ? (str_starts_with($primaryImg, 'http') ? $primaryImg : asset('storage/'.$primaryImg)) : null;
                    @endphp
                    <tr class="hover:bg-brand-50/20 transition-colors">
                        <!-- Foto & Info Produk & Kategori -->
                        <td class="px-5 py-3.5">
                            <div class="flex items-center gap-3">
                                <div class="w-12 h-12 rounded-xl bg-stone-100 border border-stone-200/80 overflow-hidden flex items-center justify-center shrink-0 shadow-2xs">
                                    @if($imgSrc)
                                        <img src="{{ $imgSrc }}" alt="{{ $product->name }}" class="w-full h-full object-cover">
                                    @else
                                        <svg class="w-5 h-5 text-brand-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><polygon points="6 3 18 3 22 9 12 22 2 9 6 3"></polygon></svg>
                                    @endif
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="font-bold text-stone-900 text-xs truncate leading-snug" title="{{ $product->name }}">
                                        {{ $product->name }}
                                    </div>
                                    <div class="flex items-center gap-1.5 flex-wrap mt-1">
                                        <span class="px-2 py-0.5 bg-stone-100 text-stone-700 rounded-md font-semibold text-[10px] border border-stone-200/60">
                                            {{ $product->category?->name ?? 'Umum' }}
                                        </span>
                                        <span class="text-[10px] text-brand-700 font-mono font-medium">
                                            {{ $product->sku ? 'SKU: ' . $product->sku : 'Tanpa SKU' }}
                                        </span>
                                        <span class="text-[10px] text-stone-400 font-mono">
                                            • {{ $product->weight ?? 100 }}g
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </td>

                        <!-- Harga Dasar & Promo -->
                        <td class="px-4 py-3.5">
                            <div class="font-bold text-stone-800 font-sans text-xs">
                                Rp {{ number_format($product->price, 0, ',', '.') }}
                            </div>
                            @if($product->isDiscountActive())
                                <div class="flex items-center gap-1 mt-0.5">
                                    <span class="text-[9px] font-bold px-1.5 py-0.2 rounded bg-rose-50 text-rose-700 border border-rose-200">
                                        -{{ $product->discount_percent }}%
                                    </span>
                                    <span class="text-[10px] text-rose-600 font-semibold">
                                        Rp {{ number_format($product->final_price, 0, ',', '.') }}
                                    </span>
                                </div>
                            @endif
                        </td>

                        <!-- Varian & SKU Varian -->
                        <td class="px-4 py-3.5">
                            @if($product->variants->count() > 0)
                                <div class="space-y-1">
                                    @foreach($product->variants->take(2) as $v)
                                        <div class="text-stone-700 text-[11px] truncate flex items-center gap-1">
                                            <span class="font-semibold text-stone-800">{{ $v->variant_name }}</span>
                                            @if($v->additional_price > 0)
                                                <span class="text-brand-600 text-[10px] font-medium">(+{{ number_format($v->additional_price, 0, ',', '.') }})</span>
                                            @endif
                                            @if($v->sku)
                                                <span class="text-stone-400 font-mono text-[9px]">&bull; {{ $v->sku }}</span>
                                            @endif
                                        </div>
                                    @endforeach
                                    @if($product->variants->count() > 2)
                                        <div class="text-[10px] font-semibold text-brand-700 hover:underline">
                                            +{{ $product->variants->count() - 2 }} varian lainnya
                                        </div>
                                    @endif
                                </div>
                            @else
                                <span class="text-stone-400 text-[10px] italic">1 Varian Standar</span>
                            @endif
                        </td>

                        <!-- Total Stok -->
                        <td class="px-4 py-3.5 whitespace-nowrap">
                            @if($product->stock > 3)
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold whitespace-nowrap bg-emerald-50 text-emerald-700 border border-emerald-200/80">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 shrink-0"></span>
                                    <span class="whitespace-nowrap">{{ $product->stock }} pcs</span>
                                </span>
                            @elseif($product->stock > 0)
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold whitespace-nowrap bg-amber-50 text-amber-700 border border-amber-200/80">
                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse shrink-0"></span>
                                    <span class="whitespace-nowrap">Sisa {{ $product->stock }}</span>
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold whitespace-nowrap bg-rose-50 text-rose-700 border border-rose-200/80">
                                    <span class="w-1.5 h-1.5 rounded-full bg-rose-500 shrink-0"></span>
                                    <span class="whitespace-nowrap">Habis</span>
                                </span>
                            @endif
                        </td>

                        <!-- Status Aktif / Nonaktif Toggle -->
                        <td class="px-4 py-3.5 text-center whitespace-nowrap">
                            <form method="POST" action="{{ route('admin.products.toggle', $product) }}" class="inline-block m-0">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] font-bold transition cursor-pointer shadow-2xs {{ $product->is_active ? 'bg-emerald-50 text-emerald-700 border border-emerald-200 hover:bg-emerald-100 hover:border-emerald-300' : 'bg-stone-100 text-stone-500 border border-stone-200 hover:bg-stone-200 hover:border-stone-300' }}" title="Klik untuk mengubah status produk">
                                    <span class="w-1.5 h-1.5 rounded-full {{ $product->is_active ? 'bg-emerald-500' : 'bg-stone-400' }}"></span>
                                    <span>{{ $product->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                                </button>
                            </form>
                        </td>

                        <!-- Aksi (Edit, Hapus) -->
                        <td class="px-5 py-3.5 text-right whitespace-nowrap">
                            <div class="inline-flex items-center justify-end gap-1.5">
                                <!-- Edit button -->
                                <button type="button"
                                        @click="openEdit({{ $product->toJson() }}, '{{ route('admin.products.update', $product) }}')"
                                        title="Edit Produk"
                                        class="w-7 h-7 inline-flex items-center justify-center text-stone-500 hover:text-brand-700 rounded-lg hover:bg-brand-50 transition border border-transparent hover:border-brand-200/50 cursor-pointer">
                                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"></path></svg>
                                </button>

                                <!-- Delete button -->
                                <form method="POST" action="{{ route('admin.products.destroy', $product) }}" onsubmit="return confirm('Apakah Anda yakin ingin menghapus produk \"{{ $product->name }}\"?')" class="inline-block m-0">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" title="Hapus Produk" class="w-7 h-7 inline-flex items-center justify-center text-stone-400 hover:text-rose-600 rounded-lg hover:bg-rose-50 transition border border-transparent hover:border-rose-200/50 cursor-pointer">
                                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-6 py-12 text-center text-xs text-atelier-textMuted">
                            <div class="w-12 h-12 mx-auto mb-2 text-stone-300 flex items-center justify-center rounded-2xl bg-stone-50 border border-stone-100">
                                <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>
                            </div>
                            <div class="font-bold text-stone-700">Belum ada karya produk ditemukan.</div>
                            <div class="text-[11px] text-stone-400 mt-1">Gunakan tombol "Tambah Produk Baru" untuk menambahkan karya ke etalase toko.</div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($products->hasPages())
        <div class="p-4 border-t border-atelier-border bg-stone-50/50">
            {{ $products->links() }}
        </div>
        @endif
    </div>

    <!-- ======================================================== -->
    <!-- MODAL TAMBAH PRODUK BARU -->
    <!-- ======================================================== -->
    <div x-show="showAddModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs">
        <div class="bg-white rounded-2xl max-w-2xl w-full shadow-2xl border border-atelier-border overflow-hidden flex flex-col max-h-[90vh]" @click.away="showAddModal = false">
            <div class="p-5 border-b border-atelier-border flex items-center justify-between bg-stone-50/70 shrink-0">
                <div>
                    <h3 class="text-base font-bold text-atelier-textDark font-sans">Formulir Tambah Karya Produk</h3>
                    <p class="text-[11px] text-atelier-textMuted">Lengkapi info produk, varian, dan foto produk</p>
                </div>
                <button @click="showAddModal = false" class="p-1 text-atelier-textLight hover:text-stone-800 rounded-lg cursor-pointer">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                </button>
            </div>

            <!-- Tab Switcher -->
            <div class="flex border-b border-atelier-border px-6 bg-stone-50/40 shrink-0 text-xs">
                <button type="button" @click="activeTab = 'info'"
                        :class="activeTab === 'info' ? 'border-brand-500 text-brand-700 font-bold' : 'border-transparent text-atelier-textMuted font-medium'"
                        class="px-4 py-3 border-b-2 transition cursor-pointer">
                    Informasi Utama
                </button>
                <button type="button" @click="activeTab = 'variants'"
                        :class="activeTab === 'variants' ? 'border-brand-500 text-brand-700 font-bold' : 'border-transparent text-atelier-textMuted font-medium'"
                        class="px-4 py-3 border-b-2 transition cursor-pointer">
                    Varian Produk <span x-show="variants.length > 0" class="ml-1 px-1.5 py-0.5 rounded-full bg-brand-100 text-brand-700 text-[10px]" x-text="variants.length"></span>
                </button>
                <button type="button" @click="activeTab = 'photos'"
                        :class="activeTab === 'photos' ? 'border-brand-500 text-brand-700 font-bold' : 'border-transparent text-atelier-textMuted font-medium'"
                        class="px-4 py-3 border-b-2 transition cursor-pointer">
                    Foto Produk
                </button>
            </div>

            <form method="POST" action="{{ route('admin.products.store') }}" enctype="multipart/form-data" class="flex flex-col flex-1 overflow-hidden">
                @csrf
                <div class="p-6 overflow-y-auto flex-1 space-y-4 text-xs">
                    <!-- TAB 1: INFORMASI UTAMA -->
                    <div x-show="activeTab === 'info'" class="space-y-4">
                        <div>
                            <label class="block font-bold text-stone-800 uppercase tracking-wider text-[10px] mb-1">Nama Produk *</label>
                            <input type="text" name="name" required placeholder="cth: Gelang Emas Minimalis Rose Gold"
                                   class="w-full px-3.5 py-2.5 border border-stone-200 rounded-xl focus:outline-none focus:border-brand-400 bg-stone-50 hover:bg-white focus:bg-white transition">
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block font-bold text-stone-800 uppercase tracking-wider text-[10px] mb-1">Kategori *</label>
                                <select name="category_id" required class="w-full px-3.5 py-2.5 border border-stone-200 rounded-xl bg-stone-50 hover:bg-white focus:bg-white focus:outline-none focus:border-brand-400 font-semibold text-stone-700">
                                    <option value="">Pilih Kategori</option>
                                    @foreach($categories as $cat)
                                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block font-bold text-stone-800 uppercase tracking-wider text-[10px] mb-1">Base SKU (Opsional)</label>
                                <input type="text" name="sku" placeholder="cth: JW-BRC-001"
                                       class="w-full px-3.5 py-2.5 border border-stone-200 rounded-xl font-mono text-brand-700 focus:outline-none focus:border-brand-400 bg-stone-50 hover:bg-white focus:bg-white transition">
                            </div>
                        </div>

                        <div class="grid grid-cols-3 gap-4">
                            <div>
                                <label class="block font-bold text-stone-800 uppercase tracking-wider text-[10px] mb-1">Harga Dasar (Rp) *</label>
                                <input type="number" name="price" required min="0" placeholder="150000"
                                       class="w-full px-3.5 py-2.5 border border-stone-200 rounded-xl focus:outline-none focus:border-brand-400 bg-stone-50 hover:bg-white focus:bg-white transition font-sans">
                            </div>
                            <div>
                                <label class="block font-bold text-stone-800 uppercase tracking-wider text-[10px] mb-1">Stok Awal *</label>
                                <input type="number" name="stock" required min="0" value="10"
                                       class="w-full px-3.5 py-2.5 border border-stone-200 rounded-xl focus:outline-none focus:border-brand-400 bg-stone-50 hover:bg-white focus:bg-white transition">
                            </div>
                            <div>
                                <label class="block font-bold text-stone-800 uppercase tracking-wider text-[10px] mb-1">Berat (Gram) *</label>
                                <input type="number" name="weight" required min="1" value="100" placeholder="100"
                                       class="w-full px-3.5 py-2.5 border border-stone-200 rounded-xl focus:outline-none focus:border-brand-400 bg-stone-50 hover:bg-white focus:bg-white transition font-mono">
                            </div>
                        </div>

                        <!-- Promo Diskon -->
                        <div class="p-3.5 bg-stone-50 rounded-xl border border-stone-200 space-y-3">
                            <div class="font-bold text-stone-700 text-[11px]">Pengaturan Diskon Produk (Opsional)</div>
                            <div class="grid grid-cols-3 gap-3">
                                <div>
                                    <label class="block text-[10px] font-bold text-stone-500 uppercase">Tipe Diskon</label>
                                    <select name="discount_type" class="w-full mt-1 p-2 border border-stone-200 rounded-lg bg-white">
                                        <option value="none">Tidak Ada Diskon</option>
                                        <option value="percent">Persentase (%)</option>
                                        <option value="fixed">Nominal Potongan (Rp)</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-stone-500 uppercase">Nilai Diskon</label>
                                    <input type="number" name="discount_value" min="0" value="0" class="w-full mt-1 p-2 border border-stone-200 rounded-lg bg-white">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-stone-500 uppercase">Periode Diskon Selesai</label>
                                    <input type="date" name="discount_end" class="w-full mt-1 p-2 border border-stone-200 rounded-lg bg-white">
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="block font-bold text-stone-800 uppercase tracking-wider text-[10px] mb-1">Deskripsi Karya</label>
                            <textarea name="description" rows="3" placeholder="Jelaskan detail bahan, teknik kerajinan, dan filosofi karya..."
                                      class="w-full px-3.5 py-2.5 border border-stone-200 rounded-xl focus:outline-none focus:border-brand-400 bg-stone-50 hover:bg-white focus:bg-white transition"></textarea>
                        </div>

                        <div class="flex items-center gap-6 pt-2">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" name="is_active" value="1" checked class="w-4 h-4 rounded text-brand-600 focus:ring-brand-400">
                                <span class="font-bold text-stone-700">Aktifkan di Katalog Toko</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" name="is_featured" value="1" class="w-4 h-4 rounded text-brand-600 focus:ring-brand-400">
                                <span class="font-bold text-stone-700">Tampilkan sebagai Produk Unggulan</span>
                            </label>
                        </div>
                    </div>

                    <!-- TAB 2: VARIAN PRODUK -->
                    <div x-show="activeTab === 'variants'" class="space-y-4">
                        <div class="p-3 bg-brand-50/50 rounded-xl border border-brand-200 text-brand-900 leading-relaxed text-[11px]">
                            <strong>Varian Produk:</strong> Tambahkan variasi jika produk memiliki pilihan ukuran (cth: 16cm, 18cm) atau warna/material. Jika varian diisi, stok total akan otomatis disinkronkan.
                        </div>

                        <div class="space-y-2.5">
                            <template x-for="(v, idx) in variants" :key="idx">
                                <div class="p-3 rounded-xl border border-stone-200 bg-stone-50/60 grid grid-cols-12 gap-2.5 items-center">
                                    <div class="col-span-4">
                                        <label class="text-[10px] font-bold text-stone-500 uppercase">Nama Varian *</label>
                                        <input type="text" :name="'variants[' + idx + '][variant_name]'" x-model="v.variant_name" required placeholder="cth: 16 cm (Rose Gold)" class="w-full mt-1 px-2.5 py-1.5 border border-stone-200 rounded-lg bg-white text-xs">
                                    </div>
                                    <div class="col-span-3">
                                        <label class="text-[10px] font-bold text-stone-500 uppercase">SKU Varian</label>
                                        <input type="text" :name="'variants[' + idx + '][sku]'" x-model="v.sku" placeholder="cth: JW-001-RG-16" class="w-full mt-1 px-2.5 py-1.5 border border-stone-200 rounded-lg bg-white text-xs font-mono text-brand-700">
                                    </div>
                                    <div class="col-span-2">
                                        <label class="text-[10px] font-bold text-stone-500 uppercase">+Harga (Rp)</label>
                                        <input type="number" :name="'variants[' + idx + '][additional_price]'" x-model="v.additional_price" min="0" class="w-full mt-1 px-2.5 py-1.5 border border-stone-200 rounded-lg bg-white text-xs">
                                    </div>
                                    <div class="col-span-2">
                                        <label class="text-[10px] font-bold text-stone-500 uppercase">Stok</label>
                                        <input type="number" :name="'variants[' + idx + '][stock]'" x-model="v.stock" min="0" class="w-full mt-1 px-2.5 py-1.5 border border-stone-200 rounded-lg bg-white text-xs">
                                    </div>
                                    <div class="col-span-1 text-right pt-4">
                                        <button type="button" @click="removeVariantRow(idx)" class="p-1.5 text-stone-400 hover:text-rose-600 rounded-lg transition cursor-pointer" title="Hapus baris varian">
                                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                                        </button>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <button type="button" @click="addVariantRow()" class="w-full py-2.5 rounded-xl border border-dashed border-stone-300 hover:border-brand-500 text-stone-600 hover:text-brand-700 font-bold transition flex items-center justify-center gap-1.5 bg-stone-50/50 hover:bg-brand-50/30 cursor-pointer">
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                            <span>Tambah Baris Varian</span>
                        </button>
                    </div>

                    <!-- TAB 3: FOTO PRODUK -->
                    <div x-show="activeTab === 'photos'" class="space-y-4">
                        <div class="p-4 border-2 border-dashed border-stone-200 rounded-2xl bg-stone-50/50 space-y-3">
                            <div class="font-bold text-stone-800 text-xs flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-brand-500"></span>
                                <span>Foto Utama Produk</span>
                            </div>
                            <p class="text-[11px] text-stone-500">Foto utama yang akan tampil di katalog dan kartu depan toko. Format JPG, PNG, atau WebP (Maks. 2MB).</p>
                            <input type="file" name="primary_image" accept="image/jpeg,image/png,image/webp" class="block w-full text-xs text-stone-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-brand-50 file:text-brand-700 hover:file:bg-brand-100 cursor-pointer">
                        </div>

                        <div class="p-4 border-2 border-dashed border-stone-200 rounded-2xl bg-stone-50/50 space-y-3">
                            <div class="font-bold text-stone-800 text-xs flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-stone-400"></span>
                                <span>Foto Produk Tambahan (Sudut Pandang Lain)</span>
                            </div>
                            <p class="text-[11px] text-stone-500">Unggah beberapa foto detail perhiasan untuk dilihat pelanggan saat membuka halaman produk.</p>
                            <input type="file" name="additional_images[]" multiple accept="image/jpeg,image/png,image/webp" class="block w-full text-xs text-stone-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-stone-100 file:text-stone-700 hover:file:bg-stone-200 cursor-pointer">
                        </div>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="p-4 border-t border-atelier-border bg-stone-50/60 flex items-center justify-between shrink-0">
                    <div class="text-[11px] text-atelier-textLight">Pastikan semua data wajib (*) telah terisi</div>
                    <div class="flex items-center gap-2">
                        <button type="button" @click="showAddModal = false" class="px-4 py-2 border border-stone-200 text-stone-600 font-semibold rounded-xl hover:bg-stone-50 transition cursor-pointer">
                            Batal
                        </button>
                        <button type="submit" class="px-5 py-2 bg-brand-600 hover:bg-brand-700 text-white font-bold rounded-xl transition shadow-xs cursor-pointer">
                            Simpan Produk
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- ======================================================== -->
    <!-- MODAL EDIT PRODUK -->
    <!-- ======================================================== -->
    <div x-show="showEditModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs">
        <div class="bg-white rounded-2xl max-w-2xl w-full shadow-2xl border border-atelier-border overflow-hidden flex flex-col max-h-[90vh]" @click.away="showEditModal = false">
            <div class="p-5 border-b border-atelier-border flex items-center justify-between bg-stone-50/70 shrink-0">
                <div>
                    <h3 class="text-base font-bold text-atelier-textDark font-sans">Edit Karya Produk</h3>
                    <p class="text-[11px] text-atelier-textMuted" x-text="editProduct.name"></p>
                </div>
                <button @click="showEditModal = false" class="p-1 text-atelier-textLight hover:text-stone-800 rounded-lg cursor-pointer">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                </button>
            </div>

            <!-- Tab Switcher -->
            <div class="flex border-b border-atelier-border px-6 bg-stone-50/40 shrink-0 text-xs">
                <button type="button" @click="editTab = 'info'"
                        :class="editTab === 'info' ? 'border-brand-500 text-brand-700 font-bold' : 'border-transparent text-atelier-textMuted font-medium'"
                        class="px-4 py-3 border-b-2 transition cursor-pointer">
                    Informasi Utama
                </button>
                <button type="button" @click="editTab = 'variants'"
                        :class="editTab === 'variants' ? 'border-brand-500 text-brand-700 font-bold' : 'border-transparent text-atelier-textMuted font-medium'"
                        class="px-4 py-3 border-b-2 transition cursor-pointer">
                    Varian Produk <span x-show="editProduct.variants && editProduct.variants.length > 0" class="ml-1 px-1.5 py-0.5 rounded-full bg-brand-100 text-brand-700 text-[10px]" x-text="editProduct.variants.length"></span>
                </button>
                <button type="button" @click="editTab = 'photos'"
                        :class="editTab === 'photos' ? 'border-brand-500 text-brand-700 font-bold' : 'border-transparent text-atelier-textMuted font-medium'"
                        class="px-4 py-3 border-b-2 transition cursor-pointer">
                    Foto Produk
                </button>
            </div>

            <form method="POST" :action="editProduct.actionUrl" enctype="multipart/form-data" class="flex flex-col flex-1 overflow-hidden">
                @csrf
                @method('PUT')
                <div class="p-6 overflow-y-auto flex-1 space-y-4 text-xs">
                    <!-- TAB 1: INFORMASI UTAMA -->
                    <div x-show="editTab === 'info'" class="space-y-4">
                        <div>
                            <label class="block font-bold text-stone-800 uppercase tracking-wider text-[10px] mb-1">Nama Produk *</label>
                            <input type="text" name="name" x-model="editProduct.name" required
                                   class="w-full px-3.5 py-2.5 border border-stone-200 rounded-xl focus:outline-none focus:border-brand-400 bg-stone-50 hover:bg-white focus:bg-white transition">
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block font-bold text-stone-800 uppercase tracking-wider text-[10px] mb-1">Kategori *</label>
                                <select name="category_id" x-model="editProduct.category_id" required class="w-full px-3.5 py-2.5 border border-stone-200 rounded-xl bg-stone-50 hover:bg-white focus:bg-white focus:outline-none focus:border-brand-400 font-semibold text-stone-700">
                                    @foreach($categories as $cat)
                                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block font-bold text-stone-800 uppercase tracking-wider text-[10px] mb-1">Base SKU</label>
                                <input type="text" name="sku" x-model="editProduct.sku"
                                       class="w-full px-3.5 py-2.5 border border-stone-200 rounded-xl font-mono text-brand-700 focus:outline-none focus:border-brand-400 bg-stone-50 hover:bg-white focus:bg-white transition">
                            </div>
                        </div>

                        <div class="grid grid-cols-3 gap-4">
                            <div>
                                <label class="block font-bold text-stone-800 uppercase tracking-wider text-[10px] mb-1">Harga Dasar (Rp) *</label>
                                <input type="number" name="price" x-model="editProduct.price" required min="0"
                                       class="w-full px-3.5 py-2.5 border border-stone-200 rounded-xl focus:outline-none focus:border-brand-400 bg-stone-50 hover:bg-white focus:bg-white transition font-sans">
                            </div>
                            <div>
                                <label class="block font-bold text-stone-800 uppercase tracking-wider text-[10px] mb-1">Stok Utama *</label>
                                <input type="number" name="stock" x-model="editProduct.stock" required min="0"
                                       class="w-full px-3.5 py-2.5 border border-stone-200 rounded-xl focus:outline-none focus:border-brand-400 bg-stone-50 hover:bg-white focus:bg-white transition">
                            </div>
                            <div>
                                <label class="block font-bold text-stone-800 uppercase tracking-wider text-[10px] mb-1">Berat (Gram) *</label>
                                <input type="number" name="weight" x-model="editProduct.weight" required min="1"
                                       class="w-full px-3.5 py-2.5 border border-stone-200 rounded-xl focus:outline-none focus:border-brand-400 bg-stone-50 hover:bg-white focus:bg-white transition font-mono">
                            </div>
                        </div>

                        <!-- Promo Diskon -->
                        <div class="p-3.5 bg-stone-50 rounded-xl border border-stone-200 space-y-3">
                            <div class="font-bold text-stone-700 text-[11px]">Pengaturan Diskon Produk</div>
                            <div class="grid grid-cols-3 gap-3">
                                <div>
                                    <label class="block text-[10px] font-bold text-stone-500 uppercase">Tipe Diskon</label>
                                    <select name="discount_type" x-model="editProduct.discount_type" class="w-full mt-1 p-2 border border-stone-200 rounded-lg bg-white">
                                        <option value="none">Tidak Ada Diskon</option>
                                        <option value="percent">Persentase (%)</option>
                                        <option value="fixed">Nominal Potongan (Rp)</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-stone-500 uppercase">Nilai Diskon</label>
                                    <input type="number" name="discount_value" x-model="editProduct.discount_value" min="0" class="w-full mt-1 p-2 border border-stone-200 rounded-lg bg-white">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-stone-500 uppercase">Periode Diskon Selesai</label>
                                    <input type="date" name="discount_end" x-model="editProduct.discount_end" class="w-full mt-1 p-2 border border-stone-200 rounded-lg bg-white">
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="block font-bold text-stone-800 uppercase tracking-wider text-[10px] mb-1">Deskripsi Karya</label>
                            <textarea name="description" rows="3" x-model="editProduct.description"
                                      class="w-full px-3.5 py-2.5 border border-stone-200 rounded-xl focus:outline-none focus:border-brand-400 bg-stone-50 hover:bg-white focus:bg-white transition"></textarea>
                        </div>

                        <div class="flex items-center gap-6 pt-2">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" name="is_active" value="1" :checked="editProduct.is_active == 1" class="w-4 h-4 rounded text-brand-600 focus:ring-brand-400">
                                <span class="font-bold text-stone-700">Aktifkan di Katalog Toko</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" name="is_featured" value="1" :checked="editProduct.is_featured == 1" class="w-4 h-4 rounded text-brand-600 focus:ring-brand-400">
                                <span class="font-bold text-stone-700">Produk Unggulan</span>
                            </label>
                        </div>
                    </div>

                    <!-- TAB 2: VARIAN PRODUK -->
                    <div x-show="editTab === 'variants'" class="space-y-4">
                        <div class="p-3 bg-brand-50/50 rounded-xl border border-brand-200 text-brand-900 leading-relaxed text-[11px]">
                            <strong>Varian Produk:</strong> Tambahkan variasi ukuran atau material.
                        </div>

                        <div class="space-y-2.5">
                            <template x-for="(v, idx) in editProduct.variants" :key="idx">
                                <div class="p-3 rounded-xl border border-stone-200 bg-stone-50/60 grid grid-cols-12 gap-2.5 items-center">
                                    <input type="hidden" :name="'variants[' + idx + '][id]'" :value="v.id">
                                    <div class="col-span-4">
                                        <label class="text-[10px] font-bold text-stone-500 uppercase">Nama Varian *</label>
                                        <input type="text" :name="'variants[' + idx + '][variant_name]'" x-model="v.variant_name" required class="w-full mt-1 px-2.5 py-1.5 border border-stone-200 rounded-lg bg-white text-xs">
                                    </div>
                                    <div class="col-span-3">
                                        <label class="text-[10px] font-bold text-stone-500 uppercase">SKU Varian</label>
                                        <input type="text" :name="'variants[' + idx + '][sku]'" x-model="v.sku" class="w-full mt-1 px-2.5 py-1.5 border border-stone-200 rounded-lg bg-white text-xs font-mono text-brand-700">
                                    </div>
                                    <div class="col-span-2">
                                        <label class="text-[10px] font-bold text-stone-500 uppercase">+Harga (Rp)</label>
                                        <input type="number" :name="'variants[' + idx + '][additional_price]'" x-model="v.additional_price" min="0" class="w-full mt-1 px-2.5 py-1.5 border border-stone-200 rounded-lg bg-white text-xs">
                                    </div>
                                    <div class="col-span-2">
                                        <label class="text-[10px] font-bold text-stone-500 uppercase">Stok</label>
                                        <input type="number" :name="'variants[' + idx + '][stock]'" x-model="v.stock" min="0" class="w-full mt-1 px-2.5 py-1.5 border border-stone-200 rounded-lg bg-white text-xs">
                                    </div>
                                    <div class="col-span-1 text-right pt-4">
                                        <button type="button" @click="removeEditVariantRow(idx)" class="p-1.5 text-stone-400 hover:text-rose-600 rounded-lg transition cursor-pointer">
                                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                                        </button>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <button type="button" @click="addEditVariantRow()" class="w-full py-2.5 rounded-xl border border-dashed border-stone-300 hover:border-brand-500 text-stone-600 hover:text-brand-700 font-bold transition flex items-center justify-center gap-1.5 bg-stone-50/50 hover:bg-brand-50/30 cursor-pointer">
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                            <span>Tambah Baris Varian</span>
                        </button>
                    </div>

                    <!-- TAB 3: FOTO PRODUK -->
                    <div x-show="editTab === 'photos'" class="space-y-4">
                        <!-- Foto Utama Preview -->
                        <div class="p-4 border border-stone-200 rounded-2xl bg-stone-50/50 space-y-3">
                            <div class="font-bold text-stone-800 text-xs flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-brand-500"></span>
                                <span>Ganti Foto Utama Produk</span>
                            </div>
                            <template x-if="editProduct.primary_image_url">
                                <div class="flex items-center gap-3 p-2 bg-white rounded-xl border border-stone-200 w-fit">
                                    <img :src="editProduct.primary_image_url.startsWith('http') ? editProduct.primary_image_url : ('/storage/' + editProduct.primary_image_url)" class="w-14 h-14 object-cover rounded-lg">
                                    <span class="text-[11px] text-stone-500">Foto utama saat ini</span>
                                </div>
                            </template>
                            <input type="file" name="primary_image" accept="image/jpeg,image/png,image/webp" class="block w-full text-xs text-stone-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-brand-50 file:text-brand-700 hover:file:bg-brand-100 cursor-pointer">
                        </div>

                        <!-- Foto Tambahan -->
                        <div class="p-4 border border-stone-200 rounded-2xl bg-stone-50/50 space-y-3">
                            <div class="font-bold text-stone-800 text-xs flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-stone-400"></span>
                                <span>Tambah Foto Produk Tambahan</span>
                            </div>
                            <input type="file" name="additional_images[]" multiple accept="image/jpeg,image/png,image/webp" class="block w-full text-xs text-stone-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-stone-100 file:text-stone-700 hover:file:bg-stone-200 cursor-pointer">
                        </div>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="p-4 border-t border-atelier-border bg-stone-50/60 flex items-center justify-between shrink-0">
                    <div class="text-[11px] text-atelier-textLight">Simpan perubahan karya produk</div>
                    <div class="flex items-center gap-2">
                        <button type="button" @click="showEditModal = false" class="px-4 py-2 border border-stone-200 text-stone-600 font-semibold rounded-xl hover:bg-stone-50 transition cursor-pointer">
                            Batal
                        </button>
                        <button type="submit" class="px-5 py-2 bg-brand-600 hover:bg-brand-700 text-white font-bold rounded-xl transition shadow-xs cursor-pointer">
                            Perbarui Produk
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
