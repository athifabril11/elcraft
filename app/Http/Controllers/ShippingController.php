<?php

namespace App\Http\Controllers;

use App\Services\ShippingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

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
            'courier' => 'required|string|in:jne,pos,tiki,jnt',
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

        // Komerce API returns a flat array: each element is already one service
        // Shape: { "name", "code", "service", "description", "cost", "etd" }
        $services = [];
        foreach ($results as $item) {
            $courierLabel   = strtoupper($item['code'] ?? '');
            $rawDescription = trim($item['description'] ?? $item['service'] ?? '');

            // Some Komerce services already include the courier name in 'description'
            // (e.g. "JNE City Courier"). Avoid duplicating when building the label.
            if ($courierLabel !== '' && stripos($rawDescription, $courierLabel) === 0) {
                $displayName = $rawDescription;
            } else {
                $displayName = $courierLabel !== ''
                    ? $courierLabel . ' ' . $rawDescription
                    : $rawDescription;
            }

            // Komerce already appends the English word "day" / "days" to etd
            // (e.g. "2 day", "2-3 days"). Strip it so the view can append
            // the Indonesian " hari" without producing "2 day hari".
            $rawEtd = $item['etd'] ?? '-';
            if ($rawEtd !== '' && $rawEtd !== '-') {
                $cleanedEtd = trim(preg_replace('/\bdays?\b/i', '', $rawEtd));
                $cleanedEtd = trim($cleanedEtd, ' -');
                $rawEtd     = $cleanedEtd !== '' ? $cleanedEtd : '-';
            }

            $services[] = [
                'service'      => ($item['code'] ?? '') . '-' . ($item['service'] ?? ''),
                'display_name' => $displayName,
                'description'  => $rawDescription,
                'cost'         => (int) ($item['cost'] ?? 0),
                'etd'          => $rawEtd,
                'courier'      => $courierLabel,
            ];
        }

        return response()->json([
            'success' => true,
            'data'    => $services,
        ]);
    }
}
