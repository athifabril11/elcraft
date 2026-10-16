<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * TrustedProxiesTest
 *
 * Memverifikasi trustProxies di bootstrap/app.php HANYA mempercayai
 * loopback (127.0.0.1, ::1). Klien publik tidak boleh bisa memalsukan
 * IP maupun skema lewat header X-Forwarded-*.
 *
 * Kasus yang ditangkap (regresi trustProxies(at: '*')):
 *   - X-Forwarded-For berganti tiap request menghindari rate limiter
 *     'shipping' yang di-key berdasarkan IP request()->ip()
 *   - request()->ip() mengembalikan header spoof dari klien publik
 *   - X-Forwarded-Proto: https membuat request http dianggap secure
 *
 * Loopback tetap dipercaya (ngrok / reverse proxy lokal), jadi dua
 * pengujian terakhir juga memastikan perilaku itu TIDAK hilang.
 *
 * Request eksternal (Komerce domestic-cost) di-fake dengan Http::fake —
 * meniru pendekatan RateLimitingTest. Tidak ada panggilan API sungguhan.
 */
class TrustedProxiesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Bucket rate limiter (cache array di phpunit.xml) harus bersih
        // agar tiap test berdiri sendiri — sama seperti RateLimitingTest.
        Cache::flush();
    }

    private function fakeKomerceCost(): void
    {
        Http::fake([
            '*/domestic-cost*' => Http::response([
                'meta' => ['code' => 200],
                'data' => [
                    [
                        'code'        => 'jne',
                        'name'        => 'Jalur Nugraha Ekakurir (JNE)',
                        'service'     => 'OKE',
                        'description' => 'Ongkos Kirim Ekonomis',
                        'cost'        => 15000,
                        'etd'         => '3-4',
                    ],
                ],
            ], 200),
        ]);
    }

    private function registerProbeRoutes(): void
    {
        Route::get('/__ip', fn (Request $request) => $request->ip());
        Route::get('/__secure', fn (Request $request) => $request->isSecure() ? 'secure' : 'plain');
    }

    // ── 1. Rate limiter 'shipping' tidak bisa dihindari lewat header ───────

    public function test_rotating_x_forwarded_for_cannot_evade_shipping_rate_limit_from_public_client(): void
    {
        $this->fakeKomerceCost();
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10']);

        $payload = ['city_id' => 23, 'weight' => 1000, 'courier' => 'jne'];

        // 35 request, tiap request X-Forwarded-For berbeda (10.0.0.1 ... 10.0.0.35).
        $responses = [];
        for ($i = 1; $i <= 35; $i++) {
            $responses[$i] = $this
                ->withHeaders(['X-Forwarded-For' => "10.0.0.{$i}"])
                ->postJson('/shipping/cost', $payload);
        }

        // 30 pertama tidak boleh kena throttle (key limiter harus tetap
        // satu IP yang sama untuk semua request ini).
        for ($i = 1; $i <= 30; $i++) {
            $this->assertNotSame(
                429,
                $responses[$i]->status(),
                "Request #{$i} unexpectedly throttled."
            );
        }

        // Request ke-31 wajib 429 — membuktikan rotasi header tidak
        // membuat bucket baru.
        $this->assertSame(
            429,
            $responses[31]->status(),
            'Request #31 must be throttled despite rotating X-Forwarded-For.'
        );
    }

    // ── 2. request()->ip() mengabaikan header spoof dari klien publik ──────

    public function test_request_ip_ignores_spoofed_header_from_public_client(): void
    {
        $this->registerProbeRoutes();

        $response = $this
            ->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
            ->withHeaders(['X-Forwarded-For' => '10.9.9.9'])
            ->get('/__ip');

        $this->assertSame('203.0.113.10', $response->getContent());
    }

    // ── 3. Proxy loopback tetap dihormati ──────────────────────────────────

    public function test_request_ip_honors_x_forwarded_for_from_loopback_proxy(): void
    {
        $this->registerProbeRoutes();

        $response = $this
            ->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->withHeaders(['X-Forwarded-For' => '198.51.100.7'])
            ->get('/__ip');

        $this->assertSame('198.51.100.7', $response->getContent());
    }

    // ── 4. Skema palsu dari klien publik diabaikan ─────────────────────────

    public function test_fake_scheme_from_public_client_is_ignored(): void
    {
        $this->registerProbeRoutes();

        $response = $this
            ->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
            ->withHeaders(['X-Forwarded-Proto' => 'https'])
            ->get('/__secure');

        $this->assertSame('plain', $response->getContent());
    }

    // ── 5. Skema dari proxy loopback dihormati ─────────────────────────────

    public function test_scheme_from_loopback_proxy_is_honored(): void
    {
        $this->registerProbeRoutes();

        $response = $this
            ->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->withHeaders(['X-Forwarded-Proto' => 'https'])
            ->get('/__secure');

        $this->assertSame('secure', $response->getContent());
    }
}
