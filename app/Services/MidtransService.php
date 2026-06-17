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

            // ── Detail Pelanggan ────────────────────────────────────
            'customer_details' => [
                'first_name' => $customer['name'],
                'email'      => $customer['email'],
                'phone'      => $customer['phone'] ?? '',
            ],

            // ── Metode Pembayaran yang Diaktifkan ───────────────────
            // Sesuaikan dengan metode yang sudah diaktifkan di dashboard Midtrans
            'enabled_payments' => [
                'credit_card',
                'bca_va',
                'bni_va',
                'bri_va',
                'other_va',
                'gopay',
                'shopeepay',
                'qris',
                'indomaret',
                'alfamart',
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
}
