<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Exception;

class ShippingService
{
    protected string $apiKey;
    protected string $baseUrl;
    protected string $origin;

    public function __construct()
    {
        $this->apiKey  = config('rajaongkir.api_key') ?? '';
        $this->baseUrl = config('rajaongkir.base_url') ?? 'https://api.rajaongkir.com/starter';
        $this->origin  = config('rajaongkir.origin') ?? '152';
    }

    /**
     * Get all provinces from RajaOngkir. Cache results for 7 days.
     */
    public function getProvinces(): array
    {
        return Cache::remember('rajaongkir:provinces', now()->addDays(7), function () {
            try {
                $response = Http::withHeaders([
                    'key' => $this->apiKey,
                ])->get("{$this->baseUrl}/province");

                if ($response->failed()) {
                    Log::error('RajaOngkir getProvinces failed', ['body' => $response->body()]);
                    return [];
                }

                $data = $response->json();
                return $data['rajaongkir']['results'] ?? [];
            } catch (Exception $e) {
                Log::error('RajaOngkir getProvinces exception', ['message' => $e->getMessage()]);
                return [];
            }
        });
    }

    /**
     * Get all cities in a province. Cache results for 7 days.
     */
    public function getCities(int $provinceId): array
    {
        return Cache::remember("rajaongkir:cities:{$provinceId}", now()->addDays(7), function () use ($provinceId) {
            try {
                $response = Http::withHeaders([
                    'key' => $this->apiKey,
                ])->get("{$this->baseUrl}/city", [
                    'province' => $provinceId,
                ]);

                if ($response->failed()) {
                    Log::error('RajaOngkir getCities failed', ['province_id' => $provinceId, 'body' => $response->body()]);
                    return [];
                }

                $data = $response->json();
                return $data['rajaongkir']['results'] ?? [];
            } catch (Exception $e) {
                Log::error('RajaOngkir getCities exception', ['province_id' => $provinceId, 'message' => $e->getMessage()]);
                return [];
            }
        });
    }

    /**
     * Calculate cost from warehouse origin to destination city. Cache cost for 1 hour.
     */
    public function calculateCost(int $destinationCityId, int $weightGrams, string $courier): array
    {
        // Fallback for minimum weight is 1 gram to prevent API error
        $weightGrams = max(1, $weightGrams);
        $courier = strtolower($courier);

        // Limit courier list to those supported by RajaOngkir Starter
        if (!in_array($courier, ['jne', 'pos', 'tiki'])) {
            $courier = 'jne';
        }

        $cacheKey = "rajaongkir:cost:{$this->origin}:{$destinationCityId}:{$weightGrams}:{$courier}";

        return Cache::remember($cacheKey, now()->addHour(), function () use ($destinationCityId, $weightGrams, $courier) {
            try {
                $response = Http::withHeaders([
                    'key' => $this->apiKey,
                ])->post("{$this->baseUrl}/cost", [
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
                        'body'        => $response->body()
                    ]);
                    return [];
                }

                $data = $response->json();
                return $data['rajaongkir']['results'] ?? [];
            } catch (Exception $e) {
                Log::error('RajaOngkir calculateCost exception', [
                    'destination' => $destinationCityId,
                    'weight'      => $weightGrams,
                    'courier'     => $courier,
                    'message'     => $e->getMessage()
                ]);
                return [];
            }
        });
    }
}
