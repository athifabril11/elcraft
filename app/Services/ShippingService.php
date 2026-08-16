<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * ShippingService
 *
 * Integrasi dengan API RajaOngkir via Komerce (post-2025 migration).
 * Endpoint baru:
 *   GET  /destination/province          → daftar provinsi
 *   GET  /destination/city/{province_id}→ daftar kota per provinsi
 *   POST /calculate/domestic-cost       → kalkulasi ongkos kirim (form-data)
 *
 * Response schema baru: { "meta": {...}, "data": [...] }
 * Field province: { "id", "name" }
 * Field city:     { "id", "name" }
 * Field cost:     { "name", "code", "service", "description", "cost", "etd" } (flat per service)
 */
class ShippingService
{
    protected string $apiKey;
    protected string $baseUrl;
    protected string $origin;

    public function __construct()
    {
        $this->apiKey  = config('rajaongkir.api_key') ?? '';
        $this->baseUrl = config('rajaongkir.base_url') ?? 'https://rajaongkir.komerce.id/api/v1';
        $this->origin  = config('rajaongkir.origin') ?? '152';
    }

    /**
     * Get all provinces from RajaOngkir (Komerce API).
     * Returns array of [ 'id' => int, 'name' => string ].
     * Cache results for 7 days.
     */
    public function getProvinces(): array
    {
        return Cache::remember('rajaongkir:provinces:v3', now()->addDays(7), function () {
            try {
                $response = Http::withHeaders([
                    'key' => $this->apiKey,
                ])->get("{$this->baseUrl}/destination/province");

                if ($response->failed()) {
                    Log::error('RajaOngkir getProvinces failed', [
                        'status' => $response->status(),
                        'body'   => $response->body(),
                    ]);
                    return [];
                }

                $data = $response->json();
                $provinces = $data['data'] ?? [];

                // Sort provinces alphabetically by name (A-Z)
                usort($provinces, function ($a, $b) {
                    return strcasecmp($a['name'] ?? '', $b['name'] ?? '');
                });

                return $provinces;
            } catch (Exception $e) {
                Log::error('RajaOngkir getProvinces exception', ['message' => $e->getMessage()]);
                return [];
            }
        });
    }

    /**
     * Get all cities in a province from RajaOngkir (Komerce API).
     * Returns array of [ 'id' => int, 'name' => string ].
     * Cache results for 7 days per province.
     */
    public function getCities(int $provinceId): array
    {
        return Cache::remember("rajaongkir:cities:v3:{$provinceId}", now()->addDays(7), function () use ($provinceId) {
            try {
                $response = Http::withHeaders([
                    'key' => $this->apiKey,
                ])->get("{$this->baseUrl}/destination/city/{$provinceId}");

                if ($response->failed()) {
                    Log::error('RajaOngkir getCities failed', [
                        'province_id' => $provinceId,
                        'status'      => $response->status(),
                        'body'        => $response->body(),
                    ]);
                    return [];
                }

                $data = $response->json();
                $cities = $data['data'] ?? [];

                // Sort cities alphabetically by name (A-Z)
                usort($cities, function ($a, $b) {
                    return strcasecmp($a['name'] ?? '', $b['name'] ?? '');
                });

                return $cities;
            } catch (Exception $e) {
                Log::error('RajaOngkir getCities exception', [
                    'province_id' => $provinceId,
                    'message'     => $e->getMessage(),
                ]);
                return [];
            }
        });
    }

    /**
     * Calculate domestic shipping cost from origin to destination city.
     * Uses Komerce's /calculate/domestic-cost endpoint (POST as form-data).
     * Returns flat array of services: [ ['service', 'description', 'cost', 'etd', 'courier'], ... ]
     * Cache cost for 1 hour.
     */
    public function calculateCost(int $destinationCityId, int $weightGrams, string $courier): array
    {
        // Minimum weight of 1g to prevent API error
        $weightGrams = max(1, $weightGrams);
        $courier = strtolower($courier);

        // Komerce supports: jne, pos, tiki, jnt
        if (!in_array($courier, ['jne', 'pos', 'tiki', 'jnt'])) {
            $courier = 'jne';
        }

        $cacheKey = "rajaongkir:cost:v2:{$this->origin}:{$destinationCityId}:{$weightGrams}:{$courier}";

        return Cache::remember($cacheKey, now()->addHour(), function () use ($destinationCityId, $weightGrams, $courier) {
            try {
                // Komerce API requires form-data (not JSON)
                $response = Http::withHeaders([
                    'key' => $this->apiKey,
                ])->asForm()->post("{$this->baseUrl}/calculate/domestic-cost", [
                    'origin'      => $this->origin,
                    'destination' => $destinationCityId,
                    'weight'      => $weightGrams,
                    'courier'     => $courier,
                ]);

                if ($response->failed()) {
                    Log::error('RajaOngkir calculateCost failed', [
                        'origin'      => $this->origin,
                        'destination' => $destinationCityId,
                        'weight'      => $weightGrams,
                        'courier'     => $courier,
                        'status'      => $response->status(),
                        'body'        => $response->body(),
                    ]);
                    return [];
                }

                $data = $response->json();
                // Komerce returns flat array of services under 'data'
                return $data['data'] ?? [];
            } catch (Exception $e) {
                Log::error('RajaOngkir calculateCost exception', [
                    'destination' => $destinationCityId,
                    'weight'      => $weightGrams,
                    'courier'     => $courier,
                    'message'     => $e->getMessage(),
                ]);
                return [];
            }
        });
    }
}
