<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\ReturnRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * RefundService — Orkestrasi pengembalian dana untuk retur pelanggan.
 *
 * Dua jalur eksekusi:
 *   processApiRefund() — memanggil Midtrans Refund API (POST /v2/{order_id}/refund)
 *   markManual()       — admin menandai refund yang dieksekusi di luar API
 *                        (Midtrans dashboard / transfer bank)
 *
 * PENTING: panggilan HTTP ke Midtrans SELALU berada di luar transaksi DB
 * (lock tidak boleh ditahan selama request eksternal). Persistensi hasil
 * dilakukan transaksi singkat dengan lockForUpdate + recheck agar aman
 * terhadap klik ganda / race.
 */
class RefundService
{
    public const API_SUPPORTED_METHODS = [
        'credit_card',
        'qris',
        'gopay',
        'shopeepay',
        'dana',
        'ovo',
        'linkaja',
        'kredivo',
        'akulaku',
    ];

    public function __construct(private readonly MidtransService $midtrans) {}

    /**
     * Proses refund via Midtrans Refund API untuk retur yang sudah disetujui.
     *
     * @return array{success: bool, message: string}
     */
    public function processApiRefund(ReturnRequest $return): array
    {
        $return->loadMissing('order.payment');

        $guard = $this->guard($return, isApi: true);
        if ($guard !== null) {
            return ['success' => false, 'message' => $guard];
        }

        $payment = $return->order?->payment;

        if ($payment?->status === 'refunded') {
            $persisted = $this->persistRefund($return->id, 'api');

            if (!$persisted) {
                return ['success' => false, 'message' => 'Retur ini sudah direfund oleh proses lain.'];
            }

            return [
                'success' => true,
                'message' => 'Pembayaran sudah direfund melalui Midtrans — status retur disinkronkan.',
            ];
        }

        $amount = (float) $return->refund_amount;

        // ── Panggil Midtrans DI LUAR transaksi DB ────────────────────────────
        $outcome = $this->midtrans->refundTransaction(
            $return->order->order_number,
            $amount,
            'Refund retur ' . $return->return_number,
            $return->return_number // refund_key idempoten — retry aman
        );

        if (!in_array($outcome['outcome'], ['refunded', 'duplicate'], true)) {
            Log::warning('RefundService::processApiRefund gagal', [
                'return_number' => $return->return_number,
                'order_number'  => $return->order->order_number,
                'outcome'       => $outcome['outcome'],
                'status_code'   => $outcome['status_code'],
                'message'       => $outcome['message'],
            ]);

            $message = match ($outcome['outcome']) {
                'rejected'  => 'Permintaan refund ditolak Midtrans: ' . ($outcome['message'] ?: 'periksa nominal atau metode pembayaran.'),
                'not_found' => 'Transaksi tidak ditemukan di Midtrans.',
                default     => 'Gagal menghubungi Midtrans: ' . ($outcome['message'] ?: 'periksa koneksi lalu coba lagi.'),
            };

            return ['success' => false, 'message' => $message];
        }

        $persisted = $this->persistRefund($return->id, 'api');

        if (!$persisted) {
            return ['success' => false, 'message' => 'Retur ini sudah direfund oleh proses lain.'];
        }

        return [
            'success' => true,
            'message' => 'Refund Rp ' . number_format($amount, 0, ',', '.') . ' berhasil diproses via Midtrans.',
        ];
    }

    /**
     * Tandai refund yang dieksekusi di luar API (dashboard Midtrans / transfer bank).
     *
     * @return array{success: bool, message: string}
     */
    public function markManual(ReturnRequest $return, string $notes): array
    {
        $return->loadMissing('order.payment');

        $guard = $this->guard($return, isApi: false);
        if ($guard !== null) {
            return ['success' => false, 'message' => $guard];
        }

        $payment = $return->order?->payment;

        if ($payment?->status === 'refunded') {
            $persisted = $this->persistRefund($return->id, 'api', $notes);

            if (!$persisted) {
                return ['success' => false, 'message' => 'Retur ini sudah direfund oleh proses lain.'];
            }

            return [
                'success' => true,
                'message' => 'Pembayaran sudah direfund melalui Midtrans — status retur disinkronkan.',
            ];
        }

        $persisted = $this->persistRefund($return->id, 'manual', $notes);

        if (!$persisted) {
            return ['success' => false, 'message' => 'Retur ini sudah direfund oleh proses lain.'];
        }

        return ['success' => true, 'message' => 'Refund manual dicatat dan retur ditandai selesai.'];
    }

    /**
     * Guard baca-saja sebelum eksekusi. Mengembalikan pesan error, atau null jika lolos.
     */
    private function guard(ReturnRequest $return, bool $isApi = false): ?string
    {
        if ($return->refund_status !== null) {
            return 'Retur ini sudah direfund.';
        }

        if ($return->resolution_type !== 'refund') {
            return 'Hanya retur dengan solusi refund yang dapat diproses.';
        }

        if (!in_array($return->status, ['disetujui', 'diproses'], true)) {
            return 'Retur tidak dalam status yang dapat direfund (harus Disetujui atau Diproses).';
        }

        $payment = $return->order?->payment;
        if (!$payment || !in_array($payment->status, ['success', 'refunded'], true)) {
            return 'Pembayaran pesanan tidak dalam status berhasil — refund tidak dapat diproses.';
        }

        if ((float) $return->refund_amount <= 0) {
            return 'Nominal refund tidak valid.';
        }

        if ($isApi && $payment->status !== 'refunded') {
            $rawMethod = $payment->payment_method ?: $payment->payment_type;
            $method = $rawMethod ? strtolower(trim($rawMethod)) : '';

            if (!in_array($method, self::API_SUPPORTED_METHODS, true)) {
                $displayMethod = $rawMethod ?: 'unknown';
                return "Metode pembayaran \"{$displayMethod}\" tidak didukung Refund API — gunakan tombol Refund Manual.";
            }
        }

        return null;
    }

    /**
     * Persist hasil refund: lock + recheck (idempotent), transaksi singkat.
     * Mengembalikan false jika retur sudah direfund di antara guard dan persist.
     */
    private function persistRefund(int $returnId, string $refundStatus, ?string $notes = null): bool
    {
        return DB::transaction(function () use ($returnId, $refundStatus, $notes) {
            $locked = ReturnRequest::lockForUpdate()->findOrFail($returnId);

            if ($locked->refund_status !== null) {
                return false;
            }

            $update = [
                'status'        => 'selesai',
                'refund_status' => $refundStatus,
                'refunded_at'   => now(),
            ];

            if ($notes !== null && $notes !== '') {
                $prefix = $refundStatus === 'manual' ? '[REFUND MANUAL] ' : '';
                $update['admin_notes'] = trim(
                    ($locked->admin_notes ? $locked->admin_notes . "\n" : '')
                    . $prefix . $notes
                );
            }

            $locked->update($update);

            Payment::where('order_id', $locked->order_id)->update(['status' => 'refunded']);

            return true;
        });
    }
}
