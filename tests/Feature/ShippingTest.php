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
        Http::fake([
            '*/province*' => Http::response([
                'rajaongkir' => [
                    'results' => [
                        ['province_id' => '11', 'province' => 'Aceh'],
                        ['province_id' => '9',  'province' => 'Jawa Barat'],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->getJson('/shipping/provinces');

        $response->assertOk()
                 ->assertJson(['success' => true])
                 ->assertJsonCount(2, 'data');

        $this->assertEquals('Aceh',      $response->json('data.0.province'));
        $this->assertEquals('Jawa Barat', $response->json('data.1.province'));
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
                'rajaongkir' => [
                    'results' => [['province_id' => '9', 'province' => 'Jawa Barat']],
                ],
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
        Http::fake([
            '*/city*' => Http::response([
                'rajaongkir' => [
                    'results' => [
                        ['city_id' => '23', 'city_name' => 'Bandung', 'type' => 'Kota', 'postal_code' => '40111'],
                        ['city_id' => '24', 'city_name' => 'Bogor',   'type' => 'Kota', 'postal_code' => '16001'],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->getJson('/shipping/cities/9');

        $response->assertOk()
                 ->assertJson(['success' => true])
                 ->assertJsonCount(2, 'data');

        $this->assertEquals('Bandung', $response->json('data.0.city_name'));
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
        Http::fake([
            '*/cost*' => Http::response([
                'rajaongkir' => [
                    'results' => [
                        [
                            'code'  => 'jne',
                            'name'  => 'Jalur Nugraha Ekakurir (JNE)',
                            'costs' => [
                                [
                                    'service'     => 'OKE',
                                    'description' => 'Ongkos Kirim Ekonomis',
                                    'cost'        => [['value' => 15000, 'etd' => '3-4', 'note' => '']],
                                ],
                                [
                                    'service'     => 'REG',
                                    'description' => 'Layanan Reguler',
                                    'cost'        => [['value' => 20000, 'etd' => '2-3', 'note' => '']],
                                ],
                            ],
                        ],
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

        $this->assertEquals(15000, $response->json('data.0.cost'));
        $this->assertEquals('JNE', $response->json('data.0.courier'));
        $this->assertEquals('3-4', $response->json('data.0.etd'));
    }

    public function test_cost_validates_request_fields(): void
    {
        $response = $this->postJson('/shipping/cost', [
            'city_id' => 0,      // invalid: min:1
            'weight'  => -1,     // invalid: min:1
            'courier' => 'dhl',  // invalid: not in jne,pos,tiki
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
            '*/cost*' => Http::response([], 500),
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
