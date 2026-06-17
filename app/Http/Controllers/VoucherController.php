<?php

namespace App\Http\Controllers;

use App\Models\Voucher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * VoucherController — Validasi dan kalkulasi diskon voucher.
 */
class VoucherController extends Controller
{
    /**
     * Validasi kode voucher dan hitung potongan harga.
     *
     * POST /checkout/voucher/apply
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function apply(Request $request): JsonResponse
    {
        $request->validate([
            'code'     => 'required|string|max:50',
            'subtotal' => 'required|numeric|min:0',
        ]);

        $voucher = Voucher::where('code', strtoupper(trim($request->code)))->first();

        if (!$voucher) {
            return response()->json([
                'valid'   => false,
                'message' => 'Kode voucher tidak ditemukan.',
            ], 422);
        }

        if (!$voucher->isValid((float) $request->subtotal)) {
            // Berikan pesan error yang spesifik agar UX lebih baik
            if (!$voucher->is_active) {
                $message = 'Voucher ini tidak aktif.';
            } elseif ($voucher->used_count >= $voucher->quota) {
                $message = 'Kuota voucher sudah habis.';
            } elseif (now()->toDateString() < $voucher->start_date->toDateString()) {
                $message = 'Voucher belum berlaku.';
            } elseif (now()->toDateString() > $voucher->end_date->toDateString()) {
                $message = 'Voucher sudah kadaluarsa.';
            } elseif ((float) $request->subtotal < (float) $voucher->min_purchase) {
                $message = 'Minimum pembelian untuk voucher ini adalah ' . number_format($voucher->min_purchase, 0, ',', '.');
            } else {
                $message = 'Voucher tidak valid.';
            }

            return response()->json([
                'valid'   => false,
                'message' => $message,
            ], 422);
        }

        $discount = $voucher->calculateDiscount((float) $request->subtotal);

        return response()->json([
            'valid'             => true,
            'voucher_id'        => $voucher->id,
            'code'              => $voucher->code,
            'description'       => $voucher->description,
            'discount_amount'   => $discount,
            'discount_type'     => $voucher->discount_type,
            'discount_value'    => $voucher->discount_value,
            'message'           => 'Voucher berhasil diterapkan! Hemat Rp ' . number_format($discount, 0, ',', '.'),
        ]);
    }
}
