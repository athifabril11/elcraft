<?php

namespace Tests\Feature;

use App\Services\ShippingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * RajaOngkirConfigTest
 *
 * Menjaga dua hal terkait konfigurasi ongkir:
 *   1. Default base_url mengarah ke endpoint Komerce (bukan Starter lama).
 *   2. Bila base_url di-set ke endpoint Starter yang sudah mati, konstruktor
 *      ShippingService mengeluarkan Log::warning agar kesalahan konfigurasi
 *      terlihat di log operasional sebelum fitur ongkir lumpuh.
 *
 * Catatan environment: phpunit.xml dan .env.testing TIDAK menyetel
 * RAJAONGKIR_BASE_URL, sehingga config('rajaongkir.base_url') benar-benar
 * membaca nilai default dari config/rajaongkir.php pada test ini.
 */
class RajaOngkirConfigTest extends TestCase
{
    use RefreshDatabase;

    public function test_config_default_base_url_menggunakan_endpoint_komerce(): void
    {
        $this->assertSame(
            'https://rajaongkir.komerce.id/api/v1',
            config('rajaongkir.base_url'),
            'Default base_url harus endpoint Komerce — bukan RajaOngkir Starter lama'
        );
    }

    public function test_konstruktor_service_mengirim_warning_bila_base_url_starter_lama(): void
    {
        config()->set('rajaongkir.base_url', 'https://api.rajaongkir.com/starter');

        Log::spy();

        new ShippingService();

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(fn (string $message) => str_contains($message, 'Starter lama'));
    }

    public function test_konstruktor_service_mengirim_warning_bila_base_url_masih_domain_rajaongkir(): void
    {
        config()->set('rajaongkir.base_url', 'https://api.rajaongkir.com/v3');

        Log::spy();

        new ShippingService();

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(fn (string $message) => str_contains($message, 'Starter lama'));
    }

    public function test_konstruktor_service_tanpa_warning_untuk_url_komerce(): void
    {
        config()->set('rajaongkir.base_url', 'https://rajaongkir.komerce.id/api/v1');

        Log::spy();

        new ShippingService();

        Log::shouldNotHaveReceived('warning');
    }
}
