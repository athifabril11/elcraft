@extends('admin.layouts.admin')

@section('title', 'Kelola Voucher Diskon')
@section('page-title', 'Kelola Voucher Diskon')
@section('page-subtitle', 'Pengaturan kode promo, diskon belanja, batasan kuota pemakaian, dan masa berlaku')

@section('content')

<div x-data="{
    showAddModal: false,
    showEditModal: false,
    editData: {
        id: '',
        code: '',
        description: '',
        discount_type: 'percent',
        discount_value: '',
        min_purchase: 0,
        max_discount: '',
        quota: 100,
        start_date: '',
        end_date: '',
        is_active: true
    },
    openEdit(voucher) {
        this.editData = { ...voucher };
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
<!-- SUMMARY STATS -->
<!-- ========================================================= -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-5 mb-6">
    <div class="admin-card p-5 bg-gradient-to-br from-white via-white to-brand-50/20">
        <div class="text-[11px] text-atelier-textLight font-bold uppercase tracking-wider">Voucher Aktif</div>
        <div class="text-2xl font-extrabold text-atelier-textDark mt-1 font-sans">{{ $totalActiveVouchers }} Kode Promo</div>
        <div class="text-[11px] text-emerald-600 mt-1">Dapat digunakan pelanggan saat checkout</div>
    </div>
    <div class="admin-card p-5">
        <div class="text-[11px] text-atelier-textLight font-bold uppercase tracking-wider">Total Terpakai</div>
        <div class="text-2xl font-extrabold text-brand-700 mt-1 font-sans">{{ number_format($totalUsagesCount) }} Transaksi</div>
        <div class="text-[11px] text-atelier-textMuted mt-1">Akumulasi seluruh pemakaian kode promo</div>
    </div>
    <div class="admin-card p-5">
        <div class="text-[11px] text-atelier-textLight font-bold uppercase tracking-wider">Mendekati Habis / Expired</div>
        <div class="text-2xl font-extrabold text-amber-700 mt-1 font-sans">{{ $expiringOrLowQuotaCount }} Kode</div>
        <div class="text-[11px] text-amber-700 mt-1">Sisa kuota &le; 10 atau berakhir &le; 7 hari</div>
    </div>
</div>

<!-- ========================================================= -->
<!-- SEARCH, FILTER, AND ACTION BAR -->
<!-- ========================================================= -->
<div class="admin-card p-5 mb-6">
    <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
        <form method="GET" action="{{ route('admin.vouchers.index') }}" class="flex-1 w-full sm:w-auto flex flex-wrap items-center gap-3 m-0">
            <div class="flex-1 min-w-[200px] relative">
                <span class="material-symbols-outlined absolute left-3 top-2.5 text-atelier-textLight text-sm">search</span>
                <input type="text" name="q" value="{{ $search }}" placeholder="Cari kode atau deskripsi promo..."
                       class="admin-input !pl-9 text-xs">
            </div>

            <select name="status" class="admin-input !w-auto text-xs">
                <option value="all" {{ $status === 'all' ? 'selected' : '' }}>Semua Status</option>
                <option value="active" {{ $status === 'active' ? 'selected' : '' }}>Aktif</option>
                <option value="inactive" {{ $status === 'inactive' ? 'selected' : '' }}>Nonaktif / Kedaluwarsa</option>
            </select>

            <button type="submit" class="admin-btn-primary text-xs !py-2 shrink-0">
                <span class="material-symbols-outlined !text-sm">filter_alt</span>
                <span>Filter</span>
            </button>

            @if($search || $status !== 'all')
                <a href="{{ route('admin.vouchers.index') }}" class="admin-btn-ghost text-xs !py-2 shrink-0" title="Reset filter">
                    <span class="material-symbols-outlined !text-sm">restart_alt</span>
                </a>
            @endif
        </form>

        <button @click="showAddModal = true" class="admin-btn-primary text-xs !py-2 shrink-0">
            <span class="material-symbols-outlined !text-sm">add_circle</span>
            <span>Buat Voucher Baru</span>
        </button>
    </div>
</div>

<!-- ========================================================= -->
<!-- VOUCHERS TABLE -->
<!-- ========================================================= -->
<div class="admin-card overflow-hidden">
    <div class="p-5 border-b border-atelier-border flex items-center justify-between">
        <div>
            <h3 class="text-xs font-bold uppercase tracking-wider text-atelier-textDark m-0">Daftar Kode Promo</h3>
            <p class="text-[11px] text-atelier-textMuted mt-0.5 m-0">Pengaturan diskon belanja dan kuota voucher toko</p>
        </div>
        <span class="text-xs font-semibold text-atelier-textMuted">
            Total: {{ $vouchers->total() }} voucher
        </span>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse text-xs">
            <thead>
                <tr class="border-b border-atelier-border bg-atelier-cream/40 text-[10px] uppercase font-bold text-atelier-textLight tracking-wider">
                    <th class="py-3 px-4">Kode Voucher</th>
                    <th class="py-3 px-4">Nilai Potongan</th>
                    <th class="py-3 px-4">Min. Belanja</th>
                    <th class="py-3 px-4">Kuota / Terpakai</th>
                    <th class="py-3 px-4">Masa Berlaku</th>
                    <th class="py-3 px-4 text-center">Status</th>
                    <th class="py-3 px-4 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-atelier-border">
                @forelse($vouchers as $item)
                    @php
                        $isExpired = $item->end_date < \Carbon\Carbon::today();
                        $pctUsed = $item->quota > 0 ? min(100, round(($item->used_count / $item->quota) * 100)) : 0;
                    @endphp
                    <tr class="hover:bg-brand-50/20 transition">
                        <td class="py-3.5 px-4">
                            <div class="font-mono font-bold text-sm text-brand-700 tracking-wide">{{ $item->code }}</div>
                            <div class="text-[10px] text-atelier-textMuted max-w-xs truncate">{{ $item->description ?? 'Tidak ada deskripsi' }}</div>
                        </td>
                        <td class="py-3.5 px-4 font-bold text-atelier-textDark">
                            @if($item->discount_type === 'percent')
                                Potongan {{ (int)$item->discount_value }}%
                                @if($item->max_discount)
                                    <div class="text-[10px] font-normal text-atelier-textMuted">Maks. Rp {{ number_format($item->max_discount, 0, ',', '.') }}</div>
                                @endif
                            @else
                                Potongan Rp {{ number_format($item->discount_value, 0, ',', '.') }}
                            @endif
                        </td>
                        <td class="py-3.5 px-4 text-atelier-textMuted">
                            Rp {{ number_format($item->min_purchase, 0, ',', '.') }}
                        </td>
                        <td class="py-3.5 px-4">
                            <div class="font-semibold text-atelier-textDark">{{ $item->used_count }} / {{ $item->quota }}</div>
                            <div class="w-24 bg-stone-100 rounded-full h-1.5 mt-1 overflow-hidden">
                                <div class="bg-brand-500 h-1.5 rounded-full" style="width: {{ $pctUsed }}%"></div>
                            </div>
                        </td>
                        <td class="py-3.5 px-4 text-atelier-textMuted">
                            <div>{{ $item->start_date->translatedFormat('d M Y') }}</div>
                            <div class="text-[10px] {{ $isExpired ? 'text-rose-600 font-bold' : '' }}">
                                s/d {{ $item->end_date->translatedFormat('d M Y') }}
                            </div>
                        </td>
                        <td class="py-3.5 px-4 text-center">
                            @if($isExpired)
                                <span class="badge-inactive !bg-stone-100 !text-stone-600">Kedaluwarsa</span>
                            @elseif($item->is_active)
                                <span class="badge-active">Aktif</span>
                            @else
                                <span class="badge-inactive !bg-rose-100 !text-rose-700">Nonaktif</span>
                            @endif
                        </td>
                        <td class="py-3.5 px-4 text-right">
                            <div class="flex items-center justify-end gap-1.5">
                                <button type="button" @click="openEdit({
                                    id: '{{ $item->id }}',
                                    code: '{{ $item->code }}',
                                    description: '{{ addslashes($item->description ?? '') }}',
                                    discount_type: '{{ $item->discount_type }}',
                                    discount_value: '{{ (float)$item->discount_value }}',
                                    min_purchase: '{{ (float)$item->min_purchase }}',
                                    max_discount: '{{ (float)$item->max_discount }}',
                                    quota: '{{ $item->quota }}',
                                    start_date: '{{ $item->start_date->format('Y-m-d') }}',
                                    end_date: '{{ $item->end_date->format('Y-m-d') }}',
                                    is_active: {{ $item->is_active ? 'true' : 'false' }}
                                })" class="p-1.5 text-atelier-textLight hover:text-brand-600 rounded-lg hover:bg-brand-50 transition" title="Edit Voucher">
                                    <span class="material-symbols-outlined !text-sm">edit</span>
                                </button>

                                <form method="POST" action="{{ route('admin.vouchers.toggle', $item->id) }}" class="inline m-0">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="p-1.5 {{ $item->is_active ? 'text-emerald-600 hover:text-rose-600' : 'text-stone-400 hover:text-emerald-600' }} rounded-lg hover:bg-brand-50 transition" title="Toggle Aktif/Nonaktif">
                                        <span class="material-symbols-outlined !text-sm">{{ $item->is_active ? 'toggle_on' : 'toggle_off' }}</span>
                                    </button>
                                </form>

                                <button type="button" title="Hapus" @click="$dispatch('confirm-delete', {
                                    action: '{{ route('admin.vouchers.destroy', $item->id) }}',
                                    title: 'Hapus Voucher?',
                                    message: 'Apakah Anda yakin ingin menghapus voucher &quot;{{ addslashes($item->code) }}&quot;? Jika voucher ini pernah digunakan, voucher akan dinonaktifkan alih-alih dihapus permanen.'
                                })" class="p-1.5 text-atelier-textLight hover:text-rose-600 rounded-lg hover:bg-rose-50 transition">
                                    <span class="material-symbols-outlined !text-sm">delete</span>
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="py-8 text-center text-atelier-textMuted text-xs">
                            Belum ada voucher diskon yang tersimpan.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($vouchers->hasPages())
        <div class="p-4 border-t border-atelier-border bg-white flex justify-end">
            {{ $vouchers->links() }}
        </div>
    @endif
</div>

<!-- ========================================================= -->
<!-- MODAL TAMBAH VOUCHER -->
<!-- ========================================================= -->
<div x-show="showAddModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs">
    <div class="bg-white rounded-2xl max-w-lg w-full shadow-2xl border border-atelier-border overflow-hidden" @click.away="showAddModal = false">
        <form method="POST" action="{{ route('admin.vouchers.store') }}" class="m-0">
            @csrf
            <div class="p-5 border-b border-atelier-border flex items-center justify-between bg-stone-50/60">
                <h3 class="text-sm font-bold text-atelier-textDark font-sans">Buat Voucher Promo Baru</h3>
                <button type="button" @click="showAddModal = false" class="text-atelier-textLight hover:text-stone-800">
                    <span class="material-symbols-outlined text-lg">close</span>
                </button>
            </div>

            <div class="p-6 space-y-4 text-xs">
                <div>
                    <label class="admin-label">Kode Voucher *</label>
                    <input type="text" name="code" required placeholder="cth: DISKONHEMAT" class="admin-input uppercase font-mono">
                </div>

                <div>
                    <label class="admin-label">Deskripsi Promo</label>
                    <input type="text" name="description" placeholder="cth: Diskon peresmian toko baru" class="admin-input">
                </div>

                <div class="grid grid-cols-2 gap-3" x-data="{ type: 'percent' }">
                    <div>
                        <label class="admin-label">Tipe Diskon *</label>
                        <select name="discount_type" x-model="type" class="admin-input">
                            <option value="percent">Persentase (%)</option>
                            <option value="nominal">Nominal Langsung (Rp)</option>
                        </select>
                    </div>
                    <div>
                        <label class="admin-label" x-text="type === 'percent' ? 'Nilai Persen (%) *' : 'Nominal Potongan (Rp) *'"></label>
                        <input type="number" name="discount_value" required min="1" placeholder="cth: 15" class="admin-input">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="admin-label">Min. Belanja (Rp)</label>
                        <input type="number" name="min_purchase" value="0" min="0" class="admin-input">
                    </div>
                    <div>
                        <label class="admin-label">Maks. Diskon (Rp, opsional)</label>
                        <input type="number" name="max_discount" placeholder="Kosongkan jika bebas" min="0" class="admin-input">
                    </div>
                </div>

                <div>
                    <label class="admin-label">Total Kuota Pemakaian *</label>
                    <input type="number" name="quota" required value="100" min="1" class="admin-input">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="admin-label">Tanggal Mulai *</label>
                        <input type="date" name="start_date" required value="{{ date('Y-m-d') }}" class="admin-input">
                    </div>
                    <div>
                        <label class="admin-label">Tanggal Selesai *</label>
                        <input type="date" name="end_date" required value="{{ date('Y-m-d', strtotime('+30 days')) }}" class="admin-input">
                    </div>
                </div>

                <div class="flex items-center gap-2 pt-2">
                    <input type="checkbox" name="is_active" id="add_is_active" value="1" checked class="rounded border-stone-300 text-brand-600 focus:ring-brand-400">
                    <label for="add_is_active" class="text-xs font-semibold text-atelier-textDark cursor-pointer">Langsung aktifkan voucher ini</label>
                </div>
            </div>

            <div class="p-4 border-t border-atelier-border bg-stone-50/60 flex items-center justify-end gap-2">
                <button type="button" @click="showAddModal = false" class="admin-btn-ghost text-xs">Batal</button>
                <button type="submit" class="admin-btn-primary text-xs">Simpan Voucher</button>
            </div>
        </form>
    </div>
</div>

<!-- ========================================================= -->
<!-- MODAL EDIT VOUCHER -->
<!-- ========================================================= -->
<div x-show="showEditModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs">
    <div class="bg-white rounded-2xl max-w-lg w-full shadow-2xl border border-atelier-border overflow-hidden" @click.away="showEditModal = false">
        <form method="POST" :action="'/admin/vouchers/' + editData.id" class="m-0">
            @csrf
            @method('PUT')
            <div class="p-5 border-b border-atelier-border flex items-center justify-between bg-stone-50/60">
                <h3 class="text-sm font-bold text-atelier-textDark font-sans">Edit Voucher Promo</h3>
                <button type="button" @click="showEditModal = false" class="text-atelier-textLight hover:text-stone-800">
                    <span class="material-symbols-outlined text-lg">close</span>
                </button>
            </div>

            <div class="p-6 space-y-4 text-xs">
                <div>
                    <label class="admin-label">Kode Voucher *</label>
                    <input type="text" name="code" x-model="editData.code" required class="admin-input uppercase font-mono">
                </div>

                <div>
                    <label class="admin-label">Deskripsi Promo</label>
                    <input type="text" name="description" x-model="editData.description" class="admin-input">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="admin-label">Tipe Diskon *</label>
                        <select name="discount_type" x-model="editData.discount_type" class="admin-input">
                            <option value="percent">Persentase (%)</option>
                            <option value="nominal">Nominal Langsung (Rp)</option>
                        </select>
                    </div>
                    <div>
                        <label class="admin-label" x-text="editData.discount_type === 'percent' ? 'Nilai Persen (%) *' : 'Nominal Potongan (Rp) *'"></label>
                        <input type="number" name="discount_value" x-model="editData.discount_value" required min="1" class="admin-input">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="admin-label">Min. Belanja (Rp)</label>
                        <input type="number" name="min_purchase" x-model="editData.min_purchase" min="0" class="admin-input">
                    </div>
                    <div>
                        <label class="admin-label">Maks. Diskon (Rp, opsional)</label>
                        <input type="number" name="max_discount" x-model="editData.max_discount" min="0" class="admin-input">
                    </div>
                </div>

                <div>
                    <label class="admin-label">Total Kuota Pemakaian *</label>
                    <input type="number" name="quota" x-model="editData.quota" required min="1" class="admin-input">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="admin-label">Tanggal Mulai *</label>
                        <input type="date" name="start_date" x-model="editData.start_date" required class="admin-input">
                    </div>
                    <div>
                        <label class="admin-label">Tanggal Selesai *</label>
                        <input type="date" name="end_date" x-model="editData.end_date" required class="admin-input">
                    </div>
                </div>

                <div class="flex items-center gap-2 pt-2">
                    <input type="checkbox" name="is_active" id="edit_is_active" value="1" :checked="editData.is_active" class="rounded border-stone-300 text-brand-600 focus:ring-brand-400">
                    <label for="edit_is_active" class="text-xs font-semibold text-atelier-textDark cursor-pointer">Voucher aktif dan dapat dipakai</label>
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
