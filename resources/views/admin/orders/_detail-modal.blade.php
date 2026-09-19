{{--
    _detail-modal.blade.php
    Alpine.js modal for "Detail Pesanan" — fetches order JSON via GET admin.orders.detail
    and displays full order info. Also handles the "Tandai Dikirim" form for diproses orders.

    Usage: @include('admin.orders._detail-modal') — placed once at the bottom of index.blade.php.
--}}

<div
    x-data="{
        open: false,
        loading: false,
        orderData: null,
        orderId: null,
        submitting: false,
        trackingNumber: '',
        errorMessage: null,

        openDetail(id) {
            this.orderId = id;
            this.orderData = null;
            this.trackingNumber = '';
            this.errorMessage = null;
            this.loading = true;
            this.open = true;

            const url = '{{ rtrim(route('admin.orders.detail', ['order' => '__ID__']), '') }}'.replace('__ID__', id);

            fetch(url, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(r => {
                if (!r.ok) throw new Error('HTTP ' + r.status);
                return r.json();
            })
            .then(data => {
                this.orderData = data;
                this.loading = false;
            })
            .catch(err => {
                this.errorMessage = 'Gagal memuat detail pesanan. Coba lagi.';
                this.loading = false;
            });
        },

        closeModal() {
            this.open = false;
            this.orderData = null;
            this.orderId = null;
            this.trackingNumber = '';
            this.errorMessage = null;
            this.submitting = false;
        },

        formatRupiah(amount) {
            return 'Rp ' + Number(amount).toLocaleString('id-ID');
        },

        get fullAddress() {
            if (!this.orderData || !this.orderData.customer) return '—';
            const c = this.orderData.customer;
            return [c.full_address, c.district, c.city, c.province, c.postal_code]
                .filter(Boolean).join(', ');
        }
    }"
    x-on:keydown.escape.window="closeModal()"
    x-on:open-order-detail.window="openDetail($event.detail.id)"
    x-init="
        const urlParams = new URLSearchParams(window.location.search);
        const highlightId = urlParams.get('highlight');
        if (highlightId) {
            $nextTick(() => {
                openDetail(highlightId);
            });
        }
    "
    id="order-detail-modal-root"
>
    {{-- Backdrop + Modal wrapper --}}
    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-50 flex items-start justify-center pt-12 pb-6 px-4 overflow-y-auto"
        style="display:none;"
    >
        {{-- Dark backdrop --}}
        <div
            class="fixed inset-0 bg-black/40 backdrop-blur-[2px]"
            x-on:click="closeModal()"
        ></div>

        {{-- Modal panel --}}
        <div
            x-show="open"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-4 scale-[0.97]"
            x-transition:enter-end="opacity-100 translate-y-0 scale-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 translate-y-0 scale-100"
            x-transition:leave-end="opacity-0 translate-y-4 scale-[0.97]"
            class="relative z-10 w-full max-w-2xl bg-white rounded-2xl shadow-2xl border border-atelier-border overflow-hidden"
            style="display:none;"
        >
            {{-- Modal header --}}
            <div class="flex items-center justify-between px-6 py-4 border-b border-atelier-border bg-[#FAF7F5]">
                <div>
                    <h2 class="text-sm font-bold text-atelier-textDark tracking-tight">Detail Pesanan</h2>
                    <p x-show="orderData" class="text-[11px] text-atelier-textMuted mt-0.5 font-mono" x-text="orderData?.order_number"></p>
                </div>
                <button
                    type="button"
                    x-on:click="closeModal()"
                    class="w-8 h-8 flex items-center justify-center rounded-xl text-atelier-textMuted hover:bg-brand-50 hover:text-brand-600 transition-colors"
                    aria-label="Tutup"
                >
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M18 6 6 18M6 6l12 12"/></svg>
                </button>
            </div>

            {{-- Modal body --}}
            <div class="px-6 py-5 max-h-[70vh] overflow-y-auto">

                {{-- Loading state --}}
                <div x-show="loading" class="py-10 text-center text-sm text-atelier-textMuted">
                    <div class="inline-block w-5 h-5 border-2 border-brand-300 border-t-brand-600 rounded-full animate-spin mb-3"></div>
                    <p>Memuat detail pesanan...</p>
                </div>

                {{-- Error state --}}
                <div x-show="!loading && errorMessage" class="py-8 text-center">
                    <p class="text-sm text-red-600" x-text="errorMessage"></p>
                    <button
                        type="button"
                        x-on:click="openDetail(orderId)"
                        class="mt-3 text-xs text-brand-600 hover:underline font-semibold"
                    >Coba lagi</button>
                </div>

                {{-- Loaded content --}}
                <div x-show="!loading && orderData && !errorMessage" class="space-y-5">

                    {{-- ── Order Header ── --}}
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="text-[11px] text-atelier-textMuted font-semibold uppercase tracking-wide mb-1">Tanggal Pesanan</div>
                            <div class="text-sm text-atelier-textDark font-medium" x-text="orderData?.created_at"></div>
                        </div>
                        <div class="text-right">
                            <div class="text-[11px] text-atelier-textMuted font-semibold uppercase tracking-wide mb-1">Total</div>
                            <div class="text-base font-bold text-atelier-textDark" x-text="formatRupiah(orderData?.total_amount)"></div>
                        </div>
                    </div>

                    {{-- ── Customer Info ── --}}
                    <div class="bg-[#FAF7F5] rounded-xl border border-atelier-border p-4 space-y-2">
                        <div class="text-[10px] font-bold uppercase tracking-widest text-atelier-textLight mb-2">Info Penerima</div>
                        <div class="grid grid-cols-2 gap-y-2 gap-x-4 text-xs">
                            <div>
                                <span class="text-atelier-textMuted block">Nama Penerima</span>
                                <span class="font-semibold text-atelier-textDark" x-text="orderData?.customer?.recipient_name ?? '—'"></span>
                            </div>
                            <div>
                                <span class="text-atelier-textMuted block">No. Telepon</span>
                                <span class="font-semibold text-atelier-textDark" x-text="orderData?.customer?.phone ?? '—'"></span>
                            </div>
                            <div class="col-span-2">
                                <span class="text-atelier-textMuted block">Alamat Lengkap</span>
                                <span class="font-medium text-atelier-textDark" x-text="fullAddress"></span>
                            </div>
                        </div>
                    </div>

                    {{-- ── Courier & Payment ── --}}
                    <div class="grid grid-cols-2 gap-3">
                        <div class="bg-amber-50 border border-amber-200 rounded-xl p-3.5">
                            <div class="text-[10px] font-bold uppercase tracking-widest text-amber-600 mb-1.5">Kurir</div>
                            <div class="text-sm font-bold text-amber-900" x-text="orderData?.formatted_courier ?? '—'"></div>
                            <div class="text-[11px] text-amber-700 mt-1" x-text="'Ongkir: ' + formatRupiah(orderData?.shipping_cost ?? 0)"></div>
                        </div>
                        <div class="bg-stone-50 border border-stone-200 rounded-xl p-3.5">
                            <div class="text-[10px] font-bold uppercase tracking-widest text-stone-500 mb-1.5">Pembayaran</div>
                            <div class="text-sm font-bold text-stone-800" x-text="orderData?.payment?.formatted_method ?? 'Belum Ada'"></div>
                            <div class="text-[11px] mt-1">
                                <template x-if="orderData?.payment?.status === 'success'">
                                    <span class="text-emerald-700 font-semibold">Lunas</span>
                                </template>
                                <template x-if="orderData?.payment?.status && orderData?.payment?.status !== 'success'">
                                    <span class="text-stone-500" x-text="orderData?.payment?.status"></span>
                                </template>
                            </div>
                        </div>
                    </div>

                    {{-- ── Items Table ── --}}
                    <div>
                        <div class="text-[10px] font-bold uppercase tracking-widest text-atelier-textLight mb-2">Item Pesanan</div>
                        <div class="rounded-xl border border-atelier-border overflow-hidden">
                            <table class="w-full text-xs text-left">
                                <thead>
                                    <tr class="bg-[#FAF7F5] border-b border-atelier-border">
                                        <th class="px-3.5 py-2.5 font-bold text-atelier-textMuted uppercase text-[10px] tracking-wide">Produk</th>
                                        <th class="px-3.5 py-2.5 font-bold text-atelier-textMuted uppercase text-[10px] tracking-wide text-center">Qty</th>
                                        <th class="px-3.5 py-2.5 font-bold text-atelier-textMuted uppercase text-[10px] tracking-wide text-right">Harga</th>
                                        <th class="px-3.5 py-2.5 font-bold text-atelier-textMuted uppercase text-[10px] tracking-wide text-right">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-stone-100">
                                    <template x-for="(item, index) in orderData?.items ?? []" :key="index">
                                        <tr class="hover:bg-brand-50/20 transition-colors">
                                            <td class="px-3.5 py-2.5 text-atelier-textDark">
                                                <span class="font-medium" x-text="item.product_name"></span>
                                                <span x-show="item.variant_name" class="block text-[11px] text-atelier-textMuted" x-text="item.variant_name"></span>
                                            </td>
                                            <td class="px-3.5 py-2.5 text-center text-atelier-textMuted font-semibold" x-text="item.quantity"></td>
                                            <td class="px-3.5 py-2.5 text-right text-atelier-textDark font-mono" x-text="formatRupiah(item.price)"></td>
                                            <td class="px-3.5 py-2.5 text-right text-atelier-textDark font-bold font-mono" x-text="formatRupiah(item.subtotal)"></td>
                                        </tr>
                                    </template>
                                </tbody>
                                <tfoot>
                                    <tr class="bg-[#FAF7F5] border-t border-atelier-border">
                                        <td colspan="3" class="px-3.5 py-2.5 font-bold text-atelier-textDark text-right text-[11px] uppercase tracking-wide">Total</td>
                                        <td class="px-3.5 py-2.5 font-extrabold text-brand-700 text-right font-mono" x-text="formatRupiah(orderData?.total_amount)"></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    {{-- ── Existing Resi (already shipped) ── --}}
                    <template x-if="orderData?.shipment?.tracking_number">
                        <div class="bg-blue-50 border border-blue-200 rounded-xl p-4">
                            <div class="text-[10px] font-bold uppercase tracking-widest text-blue-600 mb-1">Nomor Resi</div>
                            <div class="text-sm font-extrabold text-blue-900 font-mono tracking-wider" x-text="orderData.shipment.tracking_number"></div>
                            <div class="text-[11px] text-blue-600 mt-1" x-show="orderData?.shipment?.shipped_at">
                                Dikirim: <span x-text="orderData?.shipment?.shipped_at"></span>
                            </div>
                        </div>
                    </template>

                    {{-- ── Tandai Dikirim form (only when status === 'diproses' and no resi yet) ── --}}
                    <template x-if="orderData?.status === 'diproses' && !orderData?.shipment?.tracking_number">
                        <div class="bg-brand-50 border border-brand-200 rounded-xl p-4">
                            <div class="text-[10px] font-bold uppercase tracking-widest text-brand-600 mb-3">Tandai Dikirim</div>
                            <form
                                :action="'{{ rtrim(url('/admin/orders'), '/') }}/' + orderId + '/advance-status'"
                                method="POST"
                                x-on:submit="submitting = true"
                                class="space-y-3 m-0"
                            >
                                @csrf
                                @method('PATCH')
                                <div>
                                    <label class="admin-label">Nomor Resi <span class="text-red-500">*</span></label>
                                    <input
                                        type="text"
                                        name="tracking_number"
                                        x-model="trackingNumber"
                                        placeholder="Contoh: JD0123456789"
                                        maxlength="100"
                                        required
                                        class="admin-input"
                                        autocomplete="off"
                                    >
                                </div>
                                <button
                                    type="submit"
                                    :disabled="submitting || !trackingNumber.trim()"
                                    class="admin-btn-primary w-full justify-center disabled:opacity-60 disabled:cursor-not-allowed"
                                    style="font-size:0.8rem;"
                                >
                                    <span x-show="!submitting">
                                        <svg class="w-3.5 h-3.5 inline -mt-0.5 mr-1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 2 11 13"/><path d="M22 2 15 22 11 13 2 9l20-7z"/></svg>
                                        Tandai Dikirim &amp; Kirim Email Resi
                                    </span>
                                    <span x-show="submitting">
                                        <span class="inline-block w-3.5 h-3.5 border-2 border-white/40 border-t-white rounded-full animate-spin mr-1"></span>
                                        Mengirim...
                                    </span>
                                </button>
                            </form>
                        </div>
                    </template>

                </div>
            </div>

            {{-- Modal footer --}}
            <div class="px-6 py-3.5 border-t border-atelier-border bg-[#FAF7F5] flex justify-end">
                <button
                    type="button"
                    x-on:click="closeModal()"
                    class="admin-btn-ghost"
                    style="font-size:0.75rem; padding:0.4rem 0.9rem;"
                >Tutup</button>
            </div>
        </div>
    </div>
</div>
