<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * ShippingTest
 *
 * Menguji tiga endpoint AJAX RajaOngkir:
 *   GET  /shipping/provinces
 *   GET  /shipping/cities/{provinceId}
 *   POST /shipping/cost
 *
 * Semua request ke RajaOngkir di-mock dengan Http::fake()
 * sehingga test bisa berjalan offline tanpa mengkonsumsi kuota API.
 *
 * Response format mengikuti Komerce API (rajaongkir.komerce.id/api/v1):
 *   Province: { "data": [{ "id": int, "name": string }, ...] }
 *   City:     { "data": [{ "id": int, "name": string }, ...] }
 *   Cost:     { "data": [{ "code": string, "service": string, "description": string, "cost": int, "etd": string }, ...] }
 */
class ShippingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Bersihkan cache sebelum setiap test agar tidak ada data stale
        Cache::flush();
    }

    // ─────────────────────────────────────────────────────────────
    // GET /shipping/provinces
    // ─────────────────────────────────────────────────────────────

    public function test_it_returns_provinces_from_rajaongkir(): void
    {
        // Komerce API format: { "data": [{ "id": int, "name": string }] }
        Http::fake([
            '*/province*' => Http::response([
                'meta' => ['code' => 200],
                'data' => [
                    ['id' => 11, 'name' => 'Aceh'],
                    ['id' => 9,  'name' => 'Jawa Barat'],
                ],
            ], 200),
        ]);

        $response = $this->getJson('/shipping/provinces');

        $response->assertOk()
                 ->assertJson(['success' => true])
                 ->assertJsonCount(2, 'data');

        // Sorted A-Z by ShippingService: Aceh before Jawa Barat
        $this->assertEquals('Aceh',      $response->json('data.0.name'));
        $this->assertEquals('Jawa Barat', $response->json('data.1.name'));
    }

    public function test_provinces_returns_503_when_api_fails(): void
    {
        Http::fake([
            '*/province*' => Http::response([], 500),
        ]);

        $response = $this->getJson('/shipping/provinces');

        $response->assertStatus(503)
                 ->assertJson(['success' => false]);
    }

    public function test_provinces_result_is_cached(): void
    {
        Http::fake([
            '*/province*' => Http::response([
                'meta' => ['code' => 200],
                'data' => [['id' => 9, 'name' => 'Jawa Barat']],
            ], 200),
        ]);

        // Panggil dua kali — request HTTP hanya boleh terjadi sekali (cache hit)
        $this->getJson('/shipping/provinces');
        $this->getJson('/shipping/provinces');

        Http::assertSentCount(1);
    }

    // ─────────────────────────────────────────────────────────────
    // GET /shipping/cities/{provinceId}
    // ─────────────────────────────────────────────────────────────

    public function test_it_returns_cities_for_a_province(): void
    {
        // Komerce API format: { "data": [{ "id": int, "name": string }] }
        Http::fake([
            '*/city*' => Http::response([
                'meta' => ['code' => 200],
                'data' => [
                    ['id' => 23, 'name' => 'Bandung'],
                    ['id' => 24, 'name' => 'Bogor'],
                ],
            ], 200),
        ]);

        $response = $this->getJson('/shipping/cities/9');

        $response->assertOk()
                 ->assertJson(['success' => true])
                 ->assertJsonCount(2, 'data');

        $this->assertEquals('Bandung', $response->json('data.0.name'));
    }

    public function test_cities_returns_422_for_invalid_province_id(): void
    {
        $response = $this->getJson('/shipping/cities/0');

        $response->assertStatus(422)
                 ->assertJson(['success' => false]);
    }

    public function test_cities_returns_503_when_api_fails(): void
    {
        Http::fake([
            '*/city*' => Http::response([], 500),
        ]);

        $response = $this->getJson('/shipping/cities/9');

        $response->assertStatus(503)
                 ->assertJson(['success' => false]);
    }

    // ─────────────────────────────────────────────────────────────
    // POST /shipping/cost
    // ─────────────────────────────────────────────────────────────

    public function test_it_calculates_shipping_cost(): void
    {
        // Komerce API format: { "data": [{ "code", "service", "description", "cost", "etd" }] }
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
                    [
                        'code'        => 'jne',
                        'name'        => 'Jalur Nugraha Ekakurir (JNE)',
                        'service'     => 'REG',
                        'description' => 'Layanan Reguler',
                        'cost'        => 20000,
                        'etd'         => '2-3',
                    ],
                ],
            ], 200),
        ]);

        $response = $this->postJson('/shipping/cost', [
            'city_id' => 23,
            'weight'  => 1000,
            'courier' => 'jne',
        ]);

        $response->assertOk()
                 ->assertJson(['success' => true])
                 ->assertJsonCount(2, 'data');

        // ShippingController transforms: service = code + '-' + service
        $this->assertEquals('jne-OKE', $response->json('data.0.service'));
        $this->assertEquals(15000,     $response->json('data.0.cost'));
        $this->assertEquals('JNE',     $response->json('data.0.courier'));
        $this->assertEquals('3-4',     $response->json('data.0.etd'));
    }

    public function test_cost_validates_request_fields(): void
    {
        $response = $this->postJson('/shipping/cost', [
            'city_id' => 0,      // invalid: min:1
            'weight'  => -1,     // invalid: min:1
            'courier' => 'dhl',  // invalid: not in jne,pos,tiki,jnt
        ]);

        $response->assertStatus(422);

        $errors = $response->json('errors');
        $this->assertIsArray($errors);
        $this->assertArrayHasKey('city_id', $errors);
        $this->assertArrayHasKey('weight',  $errors);
        $this->assertArrayHasKey('courier', $errors);
    }

    public function test_cost_returns_503_when_api_fails(): void
    {
        Http::fake([
            '*/domestic-cost*' => Http::response([], 500),
        ]);

        $response = $this->postJson('/shipping/cost', [
            'city_id' => 23,
            'weight'  => 1000,
            'courier' => 'jne',
        ]);

        $response->assertStatus(503)
                 ->assertJson(['success' => false]);
    }

    public function test_cost_rejects_missing_required_fields(): void
    {
        $response = $this->postJson('/shipping/cost', []);

        $response->assertStatus(422);

        $errors = $response->json('errors');
        $this->assertIsArray($errors);
        $this->assertArrayHasKey('city_id', $errors);
        $this->assertArrayHasKey('weight',  $errors);
        $this->assertArrayHasKey('courier', $errors);
    }
}
