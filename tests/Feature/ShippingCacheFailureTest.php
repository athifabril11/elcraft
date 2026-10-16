<?php

namespace Tests\Feature;

use App\Services\ShippingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * ShippingCacheFailureTest
 *
 * Menjaga bahwa ShippingService TIDAK PERNAH meng-cache hasil gagal:
 *   - Gagal HTTP (5xx)          → return [] tanpa menyimpan ke cache
 *   - Exception / timeout       → return [] tanpa menyimpan ke cache
 *   - Sukses tapi data kosong   → return [] tanpa menyimpan ke cache
 *   - Sukses dengan data        → TETAP di-cache (regression guard)
 *
 * Pola: panggil pertama (gagal) lalu panggil kedua (sukses fake) —
 * panggilan kedua harus mengembalikan data, membuktikan gagal tidak di-cache.
 *
 * Cache store: array (phpunit.xml), Cache::flush() di setUp.
 */
class ShippingCacheFailureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    // ── (a) Provinces: gagal HTTP tidak di-cache ─────────────────────────────

    public function test_provinces_http_failure_is_not_cached(): void
    {
        Http::fake([
            '*/destination/province*' => Http::sequence()
                ->push([], 500)
                ->push([
                    'meta' => ['code' => 200],
                    'data' => [['id' => 1, 'name' => 'Aceh']],
                ], 200),
        ]);

        $service = new ShippingService();

        // Panggilan pertama: 500 → failure value []
        $this->assertSame([], $service->getProvinces());

        // Panggilan kedua: sukses → HARUS mengembalikan data (bukan [] dari cache)
        $this->assertCount(1, $service->getProvinces(),
            'Hasil gagal tidak boleh di-cache — panggilan kedua harus fetch ulang dan mengembalikan data');

        Http::assertSentCount(2);
    }

    // ── (b) Cities: gagal HTTP tidak di-cache ────────────────────────────────

    public function test_cities_http_failure_is_not_cached(): void
    {
        Http::fake([
            '*/destination/city/9*' => Http::sequence()
                ->push([], 500)
                ->push([
                    'meta' => ['code' => 200],
                    'data' => [['id' => 23, 'name' => 'Bandung']],
                ], 200),
        ]);

        $service = new ShippingService();

        $this->assertSame([], $service->getCities(9));

        $this->assertCount(1, $service->getCities(9),
            'Hasil gagal tidak boleh di-cache — panggilan kedua harus fetch ulang dan mengembalikan data');

        Http::assertSentCount(2);
    }

    // ── (c) Cost: gagal HTTP tidak di-cache ──────────────────────────────────

    public function test_cost_http_failure_is_not_cached(): void
    {
        Http::fake([
            '*/calculate/domestic-cost*' => Http::sequence()
                ->push([], 500)
                ->push([
                    'meta' => ['code' => 200],
                    'data' => [
                        ['code' => 'jne', 'service' => 'REG', 'description' => 'Layanan Reguler', 'cost' => 20000, 'etd' => '2-3'],
                    ],
                ], 200),
        ]);

        $service = new ShippingService();

        $this->assertSame([], $service->calculateCost(23, 1000, 'jne'));

        $this->assertCount(1, $service->calculateCost(23, 1000, 'jne'),
            'Hasil gagal tidak boleh di-cache — panggilan kedua harus fetch ulang dan mengembalikan data');

        Http::assertSentCount(2);
    }

    // ── (d) Sukses dengan data TETAP di-cache (regression guard) ─────────────

    public function test_successful_provinces_response_is_cached(): void
    {
        Http::fake([
            '*/destination/province*' => Http::response([
                'meta' => ['code' => 200],
                'data' => [['id' => 1, 'name' => 'Aceh']],
            ], 200),
        ]);

        $service = new ShippingService();

        $this->assertCount(1, $service->getProvinces());
        $this->assertCount(1, $service->getProvinces());

        Http::assertSentCount(1);
    }

    // ── (e) Sukses tapi data kosong TIDAK di-cache ───────────────────────────

    public function test_successful_response_with_empty_data_is_not_cached(): void
    {
        Http::fake([
            '*/destination/province*' => Http::sequence()
                ->push([
                    'meta' => ['code' => 200],
                    'data' => [],
                ], 200)
                ->push([
                    'meta' => ['code' => 200],
                    'data' => [['id' => 1, 'name' => 'Aceh']],
                ], 200),
        ]);

        $service = new ShippingService();

        $this->assertSame([], $service->getProvinces(),
            'Sukses 200 dengan data kosong harus mengembalikan [] (failure value)');

        $this->assertCount(1, $service->getProvinces(),
            'Data kosong tidak boleh di-cache — panggilan kedua harus fetch ulang');

        Http::assertSentCount(2);
    }

    // ── (f) ConnectionException tidak di-cache ───────────────────────────────

    public function test_connection_exception_is_not_cached(): void
    {
        $attempts = 0;
        Http::fake(function () use (&$attempts) {
            $attempts++;

            if ($attempts === 1) {
                throw new ConnectionException('Connection timed out');
            }

            return Http::response([
                'meta' => ['code' => 200],
                'data' => [['id' => 1, 'name' => 'Aceh']],
            ], 200);
        });

        $service = new ShippingService();

        $this->assertSame([], $service->getProvinces(),
            'ConnectionException harus menghasilkan failure value []');

        $this->assertCount(1, $service->getProvinces(),
            'ConnectionException tidak boleh di-cache — panggilan kedua harus fetch ulang');
    }
}
