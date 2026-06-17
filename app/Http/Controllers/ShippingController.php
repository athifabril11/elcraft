<?php

namespace App\Http\Controllers;

use App\Services\ShippingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Class ShippingController
 *
 * Menyediakan endpoint AJAX untuk integrasi RajaOngkir:
 *  - GET  /shipping/provinces          → Daftar provinsi
 *  - GET  /shipping/cities/{province}  → Daftar kota dalam provinsi
 *  - POST /shipping/cost               → Kalkulasi ongkos kirim
 */
class ShippingController extends Controller
{
    public function __construct(private readonly ShippingService $shipping) {}

    /**
     * Mengembalikan daftar seluruh provinsi dari RajaOngkir.
     * Hasil di-cache 7 hari sehingga tidak membebani kuota API.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getProvinces(): JsonResponse
    {
        $provinces = $this->shipping->getProvinces();

        if (empty($provinces)) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat daftar provinsi. Silakan coba lagi.',
                'data'    => [],
            ], 503);
        }

        return response()->json([
            'success' => true,
            'data'    => $provinces,
        ]);
    }

    /**
     * Mengembalikan daftar kota/kabupaten berdasarkan province_id.
     * Hasil di-cache 7 hari per provinsi.
     *
     * @param  int  $provinceId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCities(int $provinceId): JsonResponse
    {
        if ($provinceId <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'ID provinsi tidak valid.',
                'data'    => [],
            ], 422);
        }

        $cities = $this->shipping->getCities($provinceId);

        if (empty($cities)) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat daftar kota. Silakan coba lagi.',
                'data'    => [],
            ], 503);
        }

        return response()->json([
            'success' => true,
            'data'    => $cities,
        ]);
    }

    /**
     * Menghitung ongkos kirim dari gudang ke kota tujuan.
     * Kurir yang didukung Starter Plan: jne, pos, tiki.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function calculateCost(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'city_id' => 'required|integer|min:1',
            'weight'  => 'required|integer|min:1',
            'courier' => 'required|string|in:jne,pos,tiki',
        ]);

        $results = $this->shipping->calculateCost(
            destinationCityId: (int) $validated['city_id'],
            weightGrams:       (int) $validated['weight'],
            courier:           $validated['courier'],
        );

        if (empty($results)) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghitung ongkos kirim. Periksa kota tujuan dan coba lagi.',
                'data'    => [],
            ], 503);
        }

        // Flatten results: ambil semua service dari hasil kurir
        $services = [];
        foreach ($results as $courierResult) {
            foreach ($courierResult['costs'] ?? [] as $cost) {
                $services[] = [
                    'service'     => $courierResult['code'] . '-' . $cost['service'],
                    'description' => $cost['description'],
                    'cost'        => $cost['cost'][0]['value'] ?? 0,
                    'etd'         => $cost['cost'][0]['etd']  ?? '-',
                    'courier'     => strtoupper($courierResult['code']),
                ];
            }
        }

        return response()->json([
            'success' => true,
            'data'    => $services,
        ]);
    }
}
