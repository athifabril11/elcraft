<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * MidtransService — Pengelola integrasi Midtrans Snap
 *
 * Kelas ini bertanggung jawab untuk membuat Snap Token secara server-side.
 * Server TIDAK PERNAH menyentuh data kartu kredit mentah — seluruh proses
 * pengisian data kartu dilakukan di dalam iframe/popup Midtrans Snap.
 *
 * Dokumentasi: https://docs.midtrans.com/reference/snap-overview
 */
class MidtransService
{
    private string $serverKey;
    private string $baseUrl;
    private bool   $isProduction;

    public function __construct()
    {
        $this->isProduction = config('midtrans.is_production', false);
        $this->serverKey    = $this->isProduction
            ? config('midtrans.server_key_production')
            : config('midtrans.server_key_sandbox');

        // URL API berbeda untuk Sandbox dan Production
        $this->baseUrl = $this->isProduction
            ? 'https://app.midtrans.com/snap/v1/transactions'
            : 'https://app.sandbox.midtrans.com/snap/v1/transactions';
    }

    /**
     * Buat Snap Token untuk satu sesi pembayaran.
     *
     * Token ini dikirim ke frontend untuk membuka popup Midtrans Snap.
     * Token bersifat satu kali pakai dan kedaluwarsa setelah 24 jam.
     *
     * @param  array  $order   Data pesanan (lihat struktur di bawah)
     * @param  array  $customer Data pelanggan
     * @return array  ['snap_token' => string, 'redirect_url' => string]
     *
     * @throws \RuntimeException Jika API Midtrans mengembalikan error
     */
    public function createSnapToken(array $order, array $customer): array
    {
        $payload = [
            // ── Identifikasi Transaksi ──────────────────────────────
            'transaction_details' => [
                'order_id'     => $order['id'],       // ID unik — gunakan UUID/nano-id
                'gross_amount' => (int) $order['total'], // Harus integer (tidak ada desimal)
            ],

            // ── Batas Waktu Pembayaran (Sinkron dengan Sistem) ───────
            'expiry' => [
                'start_time' => now()->setTimezone('Asia/Jakarta')->format('Y-m-d H:i:s O'),
                'duration'   => 24,
                'unit'       => 'hour',
            ],

            // ── Detail Pelanggan ────────────────────────────────────
            'customer_details' => [
                'first_name' => $customer['name'],
                'email'      => $customer['email'],
                'phone'      => $customer['phone'] ?? '',
            ],

            // ── Konfigurasi Kredit Kard (Opsional) ─────────────────
            'credit_card' => [
                'secure' => true, // Wajib aktifkan 3DS untuk PCI-DSS
            ],

            // ── URL Notifikasi & Redirect ───────────────────────────
            'callbacks' => [
                'finish'  => route('checkout.finish'),
                'unfinish' => route('checkout.unfinish'),
                'error'   => route('checkout.error'),
            ],
        ];

        // Tambahkan item detail jika ada (meningkatkan konversi checkout)
        if (!empty($order['items'])) {
            $payload['item_details'] = collect($order['items'])
                ->map(fn ($item) => [
                    'id'       => (string) ($item['id'] ?? $item['product_id'] ?? ''),
                    'price'    => (int) $item['price'],
                    'quantity' => (int) $item['quantity'],
                    'name'     => Str::limit($item['name'], 50), // Maks 50 karakter
                ])
                ->toArray();
        }

        // Kirim request ke Midtrans Snap API dengan Basic Auth (server key)
        $response = Http::withBasicAuth($this->serverKey, '')
            ->timeout(30)
            ->post($this->baseUrl, $payload);

        if ($response->failed()) {
            Log::error('Midtrans API Error', [
                'status'  => $response->status(),
                'body'    => $response->json(),
                'order_id' => $order['id'],
            ]);

            throw new \RuntimeException(
                'Gagal membuat token pembayaran. Silakan coba lagi. ' .
                '(Status: ' . $response->status() . ')'
            );
        }

        return $response->json(); // Berisi snap_token dan redirect_url
    }

    /**
     * Map kode metode pembayaran internal ke array channel Midtrans Snap (enabled_payments).
     *
     * @param  string  $method
     * @return array
     */
    public function mapPaymentMethod(string $method): array
    {
        return match (strtolower(trim($method))) {
            'credit_card' => ['credit_card'],
            'bca_va'      => ['bca_va'],
            'bni_va'      => ['bni_va'],
            'bri_va'      => ['bri_va'],
            'mandiri_va'  => ['echannel'],
            'permata_va'  => ['permata_va'],
            'cimb_va'     => ['cimb_va'],
            'other_va'    => ['other_va'],
            'gopay'       => ['gopay'],
            'shopeepay'   => ['shopeepay'],
            'qris'        => ['qris'],
            'indomaret'   => ['indomaret'],
            'alfamart'    => ['alfamart'],
            'akulaku'     => ['akulaku'],
            'kredivo'     => ['kredivo'],
            default       => [],
        };
    }

    /**
     * Verifikasi signature webhook dari Midtrans untuk mencegah pemalsuan notifikasi.
     *
     * Algoritma: SHA-512(orderId + statusCode + grossAmount + serverKey)
     *
     * @param  array  $notification  Payload webhook dari Midtrans
     * @return bool   true jika signature valid
     */
    public function verifySignature(array $notification): bool
    {
        $expectedSignature = hash('sha512',
            $notification['order_id'] .
            $notification['status_code'] .
            $notification['gross_amount'] .
            $this->serverKey
        );

        return hash_equals($expectedSignature, $notification['signature_key'] ?? '');
    }

    /**
     * Batalkan transaksi di sisi Midtrans melalui Core API.
     *
     * Mengirim POST ke {coreApiBase}/v2/{orderId}/cancel dan mengembalikan
     * array ternormalisasi tanpa pernah melempar exception.
     *
     * @param  string  $orderId  Nomor pesanan yang dikirim ke Midtrans
     * @return array  ['outcome' => string, 'status_code' => int|null, 'message' => string]
     *                outcome: 'cancelled' | 'not_found' | 'cannot_modify' | 'error'
     */
    public function cancelTransaction(string $orderId): array
    {
        $coreApiBase = $this->isProduction
            ? 'https://api.midtrans.com'
            : 'https://api.sandbox.midtrans.com';

        $url = $coreApiBase . '/v2/' . $orderId . '/cancel';

        try {
            $response = Http::withBasicAuth($this->serverKey, '')
                ->timeout(8)
                ->post($url);

            $body       = $response->json() ?? [];
            $statusCode = isset($body['status_code'])
                ? (int) $body['status_code']
                : $response->status();

            $message = $body['status_message'] ?? $body['message'] ?? '';

            $outcome = match ($statusCode) {
                200     => 'cancelled',
                404     => 'not_found',
                412     => 'cannot_modify',
                default => 'error',
            };

            return [
                'outcome'     => $outcome,
                'status_code' => $statusCode,
                'message'     => $message,
            ];
        } catch (\Throwable $e) {
            Log::warning('MidtransService::cancelTransaction exception', [
                'order_id'  => $orderId,
                'exception' => $e->getMessage(),
            ]);

            return [
                'outcome'     => 'error',
                'status_code' => null,
                'message'     => $e->getMessage(),
            ];
        }
    }

    /**
     * Minta pengembalian dana (refund) transaksi yang sudah Settlement
     * melalui Core API: POST /v2/{orderId}/refund.
     *
     * refund_key bersifat idempoten — permintaan ulang dengan key yang sama
     * dibalas 406 (Duplicate refund ID), sehingga retry aman tanpa risiko
     * refund ganda.
     *
     * @param  string  $orderId   Nomor pesanan di Midtrans
     * @param  int|float  $amount  Nominal yang dikembalikan (≤ gross amount)
     * @param  string  $reason    Alasan refund (wajib untuk beberapa acquirer)
     * @param  string  $refundKey Kunci unik idempoten (mis. return_number)
     * @return array  ['outcome' => string, 'status_code' => int|null, 'message' => string]
     *                outcome: 'refunded' | 'duplicate' | 'rejected' | 'not_found' | 'error'
     */
    public function refundTransaction(string $orderId, int|float $amount, string $reason, string $refundKey): array
    {
        $coreApiBase = $this->isProduction
            ? 'https://api.midtrans.com'
            : 'https://api.sandbox.midtrans.com';

        $url = $coreApiBase . '/v2/' . $orderId . '/refund';

        try {
            $response = Http::withBasicAuth($this->serverKey, '')
                ->timeout(15)
                ->post($url, [
                    'refund_key' => $refundKey,
                    'amount'     => (int) round($amount),
                    'reason'     => $reason,
                ]);

            $body       = $response->json() ?? [];
            $statusCode = isset($body['status_code'])
                ? (int) $body['status_code']
                : $response->status();

            $message = $body['status_message'] ?? $body['message'] ?? '';

            $outcome = match ($statusCode) {
                200     => 'refunded',
                404     => 'not_found',
                406     => 'duplicate',
                414     => 'rejected',
                default => 'error',
            };

            return [
                'outcome'     => $outcome,
                'status_code' => $statusCode,
                'message'     => $message,
            ];
        } catch (\Throwable $e) {
            Log::warning('MidtransService::refundTransaction exception', [
                'order_id'  => $orderId,
                'refund_key' => $refundKey,
                'exception' => $e->getMessage(),
            ]);

            return [
                'outcome'     => 'error',
                'status_code' => null,
                'message'     => $e->getMessage(),
            ];
        }
    }

    /**
     * Cek status transaksi di Midtrans via Core API GET /v2/{orderId}/status.
     *
     * Digunakan untuk rekonsiliasi saat webhook tidak tiba atau auto-cancel
     * mendapat 412 (cannot_modify). Mengembalikan status aktual transaksi
     * di Midtrans tanpa mengubah data lokal.
     *
     * @param  string  $orderId  Nomor pesanan di Midtrans
     * @return array  ['status' => string, 'status_code' => int, 'message' => string]
     *                status: 'settlement' | 'pending' | 'deny' | 'expire' | 'cancel' | 'refund' | 'unknown'
     */
    public function getTransactionStatus(string $orderId): array
    {
        $coreApiBase = $this->isProduction
            ? 'https://api.midtrans.com'
            : 'https://api.sandbox.midtrans.com';

        $url = $coreApiBase . '/v2/' . $orderId . '/status';

        try {
            $response = Http::withBasicAuth($this->serverKey, '')
                ->timeout(10)
                ->get($url);

            $body       = $response->json() ?? [];
            $statusCode = $response->status();

            $status = $body['transaction_status'] ?? 'unknown';
            $message = $body['status_message'] ?? '';

            return [
                'status'       => $status,
                'status_code'  => $statusCode,
                'message'      => $message,
                'raw'          => $body,
            ];
        } catch (\Throwable $e) {
            Log::warning('MidtransService::getTransactionStatus exception', [
                'order_id'  => $orderId,
                'exception' => $e->getMessage(),
            ]);

            return [
                'status'       => 'error',
                'status_code'  => null,
                'message'      => $e->getMessage(),
                'raw'          => [],
            ];
        }
    }
}
