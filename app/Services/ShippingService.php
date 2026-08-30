<?php

namespace App\Services;

use App\Models\Courier;
use App\Models\ShippingDiscountRule;
use App\Models\ShopSetting;
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

        // Origin city ID resolution priority:
        // 1. shop_settings table (admin-configurable)
        // 2. config('rajaongkir.origin')
        // 3. Hardcoded fallback '152'
        $this->origin = ShopSetting::get('shipping_origin_city_id')
            ?? config('rajaongkir.origin')
            ?? '152';
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
     *
     * After retrieving raw API data, applies any active ShippingDiscountRules
     * for the destination (city rules take priority over province rules).
     *
     * Returns flat array of services: [ ['service', 'description', 'cost', 'etd', 'courier'], ... ]
     * Cache cost for 1 hour (cache key includes province_id to reflect discount differences).
     *
     * @param  int       $destinationCityId      City ID (RajaOngkir)
     * @param  int       $weightGrams            Package weight in grams
     * @param  string    $courier                Courier code — must be a key in config('rajaongkir.couriers')
     * @param  int|null  $destinationProvinceId  Province ID (optional; required for province-level discount matching)
     * @return array
     */
    public function calculateCost(
        int $destinationCityId,
        int $weightGrams,
        string $courier,
        ?int $destinationProvinceId = null,
    ): array {
        // Minimum weight of 1g to prevent API error
        $weightGrams = max(1, $weightGrams);
        $courier = strtolower($courier);

        // Allowed couriers come from Courier::getActiveCodes() (database-backed).
        // This guard handles any unexpected value that bypasses controller validation.
        if (!in_array($courier, Courier::getActiveCodes(), true)) {
            $courier = 'jne';
        }

        // Include province_id in cache key so different discount configurations
        // for the same city vs province don't serve stale results.
        $provinceSegment = $destinationProvinceId ?? 'null';
        $cacheKey = "rajaongkir:cost:v3:{$this->origin}:{$destinationCityId}:{$provinceSegment}:{$weightGrams}:{$courier}";

        return Cache::remember($cacheKey, now()->addHour(), function () use ($destinationCityId, $weightGrams, $courier, $destinationProvinceId) {
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
                $services = $data['data'] ?? [];

                // Apply admin-configured shipping discount rules
                return $this->applyDiscountRules($services, $destinationCityId, $destinationProvinceId);
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

    /**
     * Apply active shipping discount rules to a set of shipping service results.
     *
     * Priority: city-level rule beats province-level rule.
     * - 'free'  → sets cost to 0 for all services
     * - 'fixed' → subtracts discount_amount from each service cost (floor 0)
     *
     * @param  array     $services        Raw service array from Komerce API
     * @param  int       $cityId          Destination city ID
     * @param  int|null  $provinceId      Destination province ID (null = province rules skipped)
     * @return array                      Services with discounted costs applied
     */
    protected function applyDiscountRules(array $services, int $cityId, ?int $provinceId): array
    {
        if (empty($services)) {
            return $services;
        }

        // Attempt city-level rule first (highest priority)
        $rule = ShippingDiscountRule::active()->forCity($cityId)->first();

        // Fall back to province-level rule only if no city rule matched and province is known
        if ($rule === null && $provinceId !== null) {
            $rule = ShippingDiscountRule::active()->forProvince($provinceId)->first();
        }

        if ($rule === null) {
            return $services;
        }

        return array_map(function (array $service) use ($rule): array {
            $originalCost = (int) ($service['cost'] ?? 0);

            $discountedCost = match ($rule->discount_type) {
                'free'  => 0,
                'fixed' => max(0, $originalCost - (int) ($rule->discount_amount ?? 0)),
                default => $originalCost,
            };

            $service['cost'] = $discountedCost;

            return $service;
        }, $services);
    }
}

