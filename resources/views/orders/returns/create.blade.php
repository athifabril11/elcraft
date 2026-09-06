@extends('layouts.storefront')

@section('title', 'Ajukan Retur ' . $order->order_number . ' | el Craft')
@section('meta_description', 'Form pengajuan retur untuk pesanan ' . $order->order_number . ' dari el Craft.')

@section('content')
<div class="bg-white min-h-screen pb-24 md:pb-0">

    {{-- ─── HEADER ────────────────────────────────── --}}
    <x-page-header title="Ajukan Retur" :back-url="route('orders.show', $order->order_number)" />

    {{-- Flash Messages --}}
    @if(session('error'))
        <div class="max-w-[1280px] mx-auto px-5 md:px-8 lg:px-16 pt-6">
            <div class="bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3 rounded-card flex items-center space-x-3">
                <span class="material-symbols-outlined !text-[18px]">error</span>
                <span>{{ session('error') }}</span>
            </div>
        </div>
    @endif

    {{-- Validation Errors --}}
    @if($errors->any())
        <div class="max-w-[1280px] mx-auto px-5 md:px-8 lg:px-16 pt-6">
            <div class="bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3 rounded-card">
                <div class="flex items-center space-x-2 mb-2">
                    <span class="material-symbols-outlined !text-[18px]">error</span>
                    <span class="font-semibold">Terdapat kesalahan pada form:</span>
                </div>
                <ul class="list-disc list-inside space-y-1 text-xs">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    <section class="max-w-[1280px] mx-auto px-5 md:px-8 lg:px-16 py-10">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

            {{-- ── LEFT: Form ─────────────────────── --}}
            <div class="lg:col-span-2">
                <form id="return-form"
                      method="POST"
                      action="{{ route('orders.returns.store', $order->order_number) }}"
                      enctype="multipart/form-data"
                      x-data="{
                          imagePreview: null,
                          handleImageChange(e) {
                              const file = e.target.files[0];
                              if (file) {
                                  if (file.size > 2 * 1024 * 1024) {
                                      alert('Ukuran file maksimal adalah 2MB');
                                      e.target.value = '';
                                      this.imagePreview = null;
                                      return;
                                  }
                                  const reader = new FileReader();
                                  reader.onload = (ev) => { this.imagePreview = ev.target.result; };
                                  reader.readAsDataURL(file);
                              } else {
                                  this.imagePreview = null;
                              }
                          }
                      }">
                    @csrf

                    {{-- Nomor Pesanan Info --}}
                    <div class="bg-warmCream rounded-card border border-warmLightGrey px-5 py-4 mb-6 flex items-center gap-3">
                        <span class="material-symbols-outlined !text-[20px] text-brand">receipt_long</span>
                        <div>
                            <p class="text-[11px] font-semibold text-warmGrey uppercase tracking-wider">Pesanan</p>
                            <p class="text-sm font-bold text-brandDark font-mono tracking-wide">{{ $order->order_number }}</p>
                        </div>
                    </div>

                    {{-- ── Pilih Produk yang Diretur ── --}}
                    <div class="bg-white rounded-card border border-warmLightGrey overflow-hidden mb-6">
                        <div class="px-5 py-4 border-b border-warmLightGrey/60 bg-warmCream">
                            <h2 class="text-sm font-semibold text-warmBlack flex items-center gap-2">
                                <span class="material-symbols-outlined !text-[18px] text-brand">shopping_bag</span>
                                Pilih Produk yang Ingin Diretur
                            </h2>
                            <p class="text-[11px] text-warmGrey mt-0.5">Centang produk dan tentukan jumlah yang akan dikembalikan.</p>
                        </div>

                        <div class="divide-y divide-warmLightGrey/50"
                             x-data="{ selected: {} }">
                            @foreach($order->items as $item)
                                <div class="px-5 py-4"
                                     x-data="{ checked: false, maxQty: {{ $item->quantity }} }">
                                    <div class="flex items-start gap-4">
                                        {{-- Checkbox --}}
                                        <div class="flex items-center pt-1">
                                            <input type="checkbox"
                                                   id="item-check-{{ $item->id }}"
                                                   x-model="checked"
                                                   class="w-4 h-4 rounded border-warmGrey text-brand focus:ring-brand/30 cursor-pointer">
                                        </div>

                                        {{-- Thumbnail --}}
                                        <div class="w-14 h-14 rounded-img bg-warmCream flex-shrink-0 overflow-hidden border border-warmLightGrey/50">
                                            @if($item->product && $item->product->primary_image)
                                                <img src="{{ $item->product->primary_image }}" alt="{{ $item->product_name }}" class="w-full h-full object-cover">
                                            @else
                                                <div class="w-full h-full flex items-center justify-center">
                                                    <span class="material-symbols-outlined text-warmGrey !text-[22px]">image</span>
                                                </div>
                                            @endif
                                        </div>

                                        {{-- Details --}}
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm font-medium text-warmBlack truncate">{{ $item->product_name }}</p>
                                            @if($item->variant_name)
                                                <p class="text-xs text-warmGrey">Varian: {{ $item->variant_name }}</p>
                                            @endif
                                            <p class="text-xs text-warmGrey mt-0.5">@rupiah($item->price) × {{ $item->quantity }}</p>

                                            {{-- Quantity Selector (only visible when checked) --}}
                                            <div x-show="checked" x-cloak class="mt-3">
                                                <label class="text-[10px] uppercase font-semibold text-warmGrey tracking-wider block mb-1">
                                                    Jumlah yang diretur
                                                </label>
                                                <div class="flex items-center gap-3">
                                                    <input type="number"
                                                           name="items[{{ $item->id }}]"
                                                           min="1"
                                                           max="{{ $item->quantity }}"
                                                           value="1"
                                                           x-bind:required="checked"
                                                           class="w-20 px-3 py-1.5 text-xs border border-warmLightGrey rounded-[6px] focus:border-brand focus:ring-1 focus:ring-brand/30 outline-none text-center font-medium">
                                                    <span class="text-xs text-warmGrey">dari {{ $item->quantity }} item</span>
                                                </div>
                                            </div>
                                        </div>

                                        {{-- Subtotal --}}
                                        <p class="text-sm font-semibold text-warmBlack flex-shrink-0 pt-1">@rupiah($item->subtotal)</p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- ── Jenis Resolusi ── --}}
                    <div class="bg-white rounded-card border border-warmLightGrey p-5 mb-6">
                        <h2 class="text-sm font-semibold text-warmBlack mb-4 flex items-center gap-2">
                            <span class="material-symbols-outlined !text-[18px] text-brand">tune</span>
                            Jenis Penyelesaian
                        </h2>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            {{-- Tukar Barang --}}
                            <label class="flex items-center gap-3 p-4 rounded-card border-2 border-warmLightGrey cursor-pointer hover:border-brand/40 transition-colors has-[:checked]:border-brand has-[:checked]:bg-warmCream/60">
                                <input type="radio" name="resolution_type" id="res-tukar" value="tukar_barang"
                                       class="text-brand focus:ring-brand/30"
                                       {{ old('resolution_type') === 'tukar_barang' || !old('resolution_type') ? 'checked' : '' }}>
                                <div>
                                    <p class="text-sm font-semibold text-warmBlack">Tukar Barang</p>
                                    <p class="text-[11px] text-warmGrey mt-0.5">Minta penggantian produk baru</p>
                                </div>
                            </label>

                            {{-- Refund --}}
                            <label class="flex items-center gap-3 p-4 rounded-card border-2 border-warmLightGrey cursor-pointer hover:border-brand/40 transition-colors has-[:checked]:border-brand has-[:checked]:bg-warmCream/60">
                                <input type="radio" name="resolution_type" id="res-refund" value="refund"
                                       class="text-brand focus:ring-brand/30"
                                       {{ old('resolution_type') === 'refund' ? 'checked' : '' }}>
                                <div>
                                    <p class="text-sm font-semibold text-warmBlack">Refund</p>
                                    <p class="text-[11px] text-warmGrey mt-0.5">Minta pengembalian dana</p>
                                </div>
                            </label>
                        </div>
                        @error('resolution_type')
                            <p class="text-xs text-red-500 mt-2">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- ── Alasan Retur ── --}}
                    <div class="bg-white rounded-card border border-warmLightGrey p-5 mb-6">
                        <h2 class="text-sm font-semibold text-warmBlack mb-4 flex items-center gap-2">
                            <span class="material-symbols-outlined !text-[18px] text-brand">description</span>
                            Alasan Retur
                        </h2>
                        <div x-data="{ count: {{ strlen(old('reason', '')) }} }">
                            <div class="flex justify-between items-center mb-1.5">
                                <label for="reason" class="text-[10px] uppercase font-semibold text-warmGrey tracking-wider">
                                    Jelaskan alasan retur Anda <span class="text-red-500">*</span>
                                </label>
                                <span class="text-[10px] text-warmGrey/60" x-text="(1000 - count) + ' karakter tersisa'"></span>
                            </div>
                            <textarea id="reason"
                                      name="reason"
                                      rows="4"
                                      maxlength="1000"
                                      @input="count = $el.value.length"
                                      placeholder="Contoh: Produk cacat, warna tidak sesuai, dll..."
                                      class="w-full text-xs border border-warmLightGrey rounded-[6px] p-3 focus:border-brand focus:ring-1 focus:ring-brand/30 outline-none resize-none font-sans placeholder-warmGrey/40 leading-relaxed @error('reason') border-red-300 @enderror">{{ old('reason') }}</textarea>
                        </div>
                        @error('reason')
                            <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- ── Foto Bukti ── --}}
                    <div class="bg-white rounded-card border border-warmLightGrey p-5 mb-6">
                        <h2 class="text-sm font-semibold text-warmBlack mb-4 flex items-center gap-2">
                            <span class="material-symbols-outlined !text-[18px] text-brand">add_a_photo</span>
                            Foto Bukti <span class="text-warmGrey font-normal text-xs ml-1">(Opsional)</span>
                        </h2>
                        <div class="flex items-center space-x-4">
                            <label class="flex flex-col items-center justify-center w-20 h-20 border border-dashed border-warmGrey/40 rounded-[8px] cursor-pointer hover:border-brand hover:bg-warmCream/20 transition-all duration-200">
                                <span class="material-symbols-outlined text-warmGrey !text-[24px]">add_a_photo</span>
                                <span class="text-[9px] text-warmGrey font-medium mt-1">Pilih Foto</span>
                                <input type="file" name="evidence_photo" id="evidence_photo"
                                       accept="image/jpg,image/jpeg,image/png,image/webp"
                                       class="hidden"
                                       @change="handleImageChange">
                            </label>

                            <div x-show="imagePreview" class="relative w-20 h-20 rounded-[8px] overflow-hidden border border-warmLightGrey" x-cloak>
                                <img :src="imagePreview" class="w-full h-full object-cover">
                                <button type="button"
                                        @click="imagePreview = null; $refs.photoInput.value = ''"
                                        class="absolute -top-1 -right-1 bg-warmBlack/70 text-white rounded-full p-0.5 flex items-center justify-center hover:bg-red-500 transition-colors">
                                    <span class="material-symbols-outlined !text-[13px]">close</span>
                                </button>
                            </div>
                        </div>
                        <p class="text-[10px] text-warmGrey mt-2 leading-normal">Maksimal 2MB (format: JPG, JPEG, PNG, WEBP). Foto akan disimpan di sistem dan dapat dilihat oleh admin.</p>
                        @error('evidence_photo')
                            <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- ── Submit ── --}}
                    <div class="flex flex-col sm:flex-row gap-3">
                        <a href="{{ route('orders.show', $order->order_number) }}"
                           class="flex-1 block text-center py-3 border border-warmLightGrey text-warmGrey hover:bg-warmCream font-semibold text-xs uppercase tracking-wider rounded-btn transition-colors duration-200">
                            Batal
                        </a>
                        <button type="submit" id="btn-submit-return"
                                class="flex-1 flex items-center justify-center gap-2 py-3 bg-rose-500 hover:bg-rose-600 text-white font-semibold text-xs uppercase tracking-wider rounded-btn transition-colors duration-200">
                            <span class="material-symbols-outlined !text-[16px]">assignment_return</span>
                            Kirim Pengajuan Retur
                        </button>
                    </div>
                </form>
            </div>

            {{-- ── RIGHT: Info Panel ── --}}
            <div class="space-y-5">
                <div class="bg-warmCream rounded-card border border-warmLightGrey p-5">
                    <h3 class="text-sm font-semibold text-warmBlack mb-3 flex items-center gap-2">
                        <span class="material-symbols-outlined !text-[18px] text-brand">info</span>
                        Informasi Retur
                    </h3>
                    <ul class="space-y-3 text-xs text-warmGrey leading-relaxed">
                        <li class="flex items-start gap-2">
                            <span class="material-symbols-outlined !text-[14px] text-brand mt-0.5 flex-shrink-0">check_circle</span>
                            Retur hanya dapat diajukan untuk pesanan berstatus <strong class="text-warmBlack">Sedang Dikirim</strong> atau <strong class="text-warmBlack">Pesanan Diterima</strong>.
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="material-symbols-outlined !text-[14px] text-brand mt-0.5 flex-shrink-0">check_circle</span>
                            Setiap pesanan hanya dapat mengajukan satu retur.
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="material-symbols-outlined !text-[14px] text-brand mt-0.5 flex-shrink-0">check_circle</span>
                            Pilih minimal satu produk untuk diretur dan sesuaikan jumlahnya.
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="material-symbols-outlined !text-[14px] text-brand mt-0.5 flex-shrink-0">check_circle</span>
                            Setelah pengajuan berhasil, hubungi admin via WhatsApp untuk proses lebih lanjut.
                        </li>
                    </ul>
                </div>

                <div class="bg-white rounded-card border border-warmLightGrey p-5">
                    <h3 class="text-sm font-semibold text-warmBlack mb-3 flex items-center gap-2">
                        <span class="material-symbols-outlined !text-[18px] text-brand">summarize</span>
                        Ringkasan Pesanan
                    </h3>
                    <div class="space-y-2 text-xs text-warmGrey">
                        <div class="flex justify-between">
                            <span>Nomor Pesanan</span>
                            <span class="font-mono font-medium text-warmBlack">{{ $order->order_number }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Status</span>
                            <span class="font-medium text-warmBlack capitalize">{{ $order->status }}</span>
                        </div>
                        <div class="border-t border-warmLightGrey pt-2 flex justify-between font-semibold text-warmBlack">
                            <span>Total</span>
                            <span class="text-brandDark">@rupiah($order->total_amount)</span>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </section>
</div>
@endsection
