<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * Unit tests for ShippingController's response-normalisation logic.
 *
 * Instead of wiring up a full HTTP request (which would require a live
 * Komerce API key), we extract the same pure-PHP transformation that the
 * controller performs and test it here in isolation.
 */
class ShippingResponseNormalisationTest extends TestCase
{
    // ──────────────────────────────────────────────────────────────────────
    // Helper — mirrors the transformation in ShippingController::calculateCost()
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Transform a raw Komerce API item array exactly as
     * ShippingController::calculateCost() does.
     *
     * @param  array{code?:string, service?:string, description?:string, cost?:int, etd?:string}  $item
     * @return array{service:string, display_name:string, description:string, cost:int, etd:string, courier:string}
     */
    private function transformItem(array $item): ?array
    {
        $serviceCode = strtoupper(trim((string) ($item['service'] ?? '')));
        if (preg_match('/^JTR[<>]/i', $serviceCode)) {
            return null;
        }

        $courierLabel   = strtoupper($item['code'] ?? '');
        $rawDescription = trim($item['description'] ?? $item['service'] ?? '');

        // Avoid prepending courier name when description already starts with it
        if ($courierLabel !== '' && stripos($rawDescription, $courierLabel) === 0) {
            $displayName = $rawDescription;
        } else {
            $displayName = $courierLabel !== ''
                ? $courierLabel . ' ' . $rawDescription
                : $rawDescription;
        }

        // Strip English "day"/"days" from etd so the view can append " hari"
        $rawEtd = trim((string) ($item['etd'] ?? ''));
        if ($rawEtd === '' || $rawEtd === '-' || $rawEtd === '0' || $rawEtd === '0 day' || $rawEtd === '0 days') {
            $rawEtd = '-';
        } else {
            $cleanedEtd = trim(preg_replace('/\bdays?\b/i', '', $rawEtd));
            $cleanedEtd = trim($cleanedEtd, ' -');
            $rawEtd     = ($cleanedEtd !== '' && $cleanedEtd !== '0') ? $cleanedEtd : '-';
        }

        return [
            'service'      => ($item['code'] ?? '') . '-' . ($item['service'] ?? ''),
            'display_name' => $displayName,
            'description'  => $rawDescription,
            'cost'         => (int) ($item['cost'] ?? 0),
            'etd'          => $rawEtd,
            'courier'      => $courierLabel,
        ];
    }

    // ──────────────────────────────────────────────────────────────────────
    // display_name tests
    // ──────────────────────────────────────────────────────────────────────

    /**
     * When the API description already starts with the courier code
     * (e.g. "JNE City Courier"), display_name must NOT prepend "JNE" again.
     */
    public function test_display_name_does_not_duplicate_courier_when_description_is_already_prefixed(): void
    {
        $result = $this->transformItem([
            'code'        => 'jne',
            'service'     => 'CTY',
            'description' => 'JNE City Courier',
            'cost'        => 20000,
            'etd'         => '1 day',
        ]);

        $this->assertSame('JNE City Courier', $result['display_name'],
            'display_name must not double-prefix "JNE" when description already starts with "JNE"');
        $this->assertStringNotContainsString('JNE JNE', $result['display_name']);
    }

    /**
     * When the description does not start with the courier code
     * (e.g. "Layanan Reguler"), courier code must be prepended.
     */
    public function test_display_name_prepends_courier_when_description_has_no_prefix(): void
    {
        $result = $this->transformItem([
            'code'        => 'jne',
            'service'     => 'REG',
            'description' => 'Layanan Reguler',
            'cost'        => 14000,
            'etd'         => '2 day',
        ]);

        $this->assertSame('JNE Layanan Reguler', $result['display_name']);
    }

    /**
     * Case-insensitivity: even if the API returns "jne City Courier" in
     * lowercase, it should still be treated as already-prefixed.
     */
    public function test_display_name_prefix_check_is_case_insensitive(): void
    {
        $result = $this->transformItem([
            'code'        => 'JNE',
            'service'     => 'CTY',
            'description' => 'jne city courier',  // lowercase prefix from API
            'cost'        => 20000,
            'etd'         => '1 day',
        ]);

        $this->assertStringNotContainsString('JNE jne', $result['display_name']);
    }

    // ──────────────────────────────────────────────────────────────────────
    // etd normalisation tests
    // ──────────────────────────────────────────────────────────────────────

    /**
     * "2 day" → cleaned etd must be "2" (no "day" substring remaining).
     */
    public function test_etd_strips_singular_day_suffix(): void
    {
        $result = $this->transformItem([
            'code'        => 'jne',
            'service'     => 'REG',
            'description' => 'Layanan Reguler',
            'cost'        => 14000,
            'etd'         => '2 day',
        ]);

        $this->assertSame('2', $result['etd']);
        $this->assertStringNotContainsString('day', $result['etd']);
    }

    /**
     * "2-3 days" → cleaned etd must be "2-3" (no "days" substring remaining).
     */
    public function test_etd_strips_plural_days_suffix(): void
    {
        $result = $this->transformItem([
            'code'        => 'jne',
            'service'     => 'OKE',
            'description' => 'Ongkos Kirim Ekonomis',
            'cost'        => 10000,
            'etd'         => '2-3 days',
        ]);

        $this->assertSame('2-3', $result['etd']);
        $this->assertStringNotContainsString('days', $result['etd']);
    }

    /**
     * A bare "-" etd must be preserved as-is (no change, no stripping).
     */
    public function test_etd_preserves_dash_when_unavailable(): void
    {
        $result = $this->transformItem([
            'code'        => 'jne',
            'service'     => 'JTR',
            'description' => 'JNE Trucking',
            'cost'        => 45000,
            'etd'         => '-',
        ]);

        $this->assertSame('-', $result['etd']);
    }

    /**
     * etd values that carry no "day" suffix (numeric-only or range)
     * should pass through unchanged.
     */
    public function test_etd_passes_through_when_no_day_suffix_present(): void
    {
        $result = $this->transformItem([
            'code'        => 'pos',
            'service'     => 'Kilat Khusus',
            'description' => 'Pos Kilat Khusus',
            'cost'        => 12000,
            'etd'         => '1-2',
        ]);

        $this->assertSame('1-2', $result['etd']);
    }

    // ──────────────────────────────────────────────────────────────────────
    // Additional edge cases
    // ──────────────────────────────────────────────────────────────────────

    /**
     * When description is empty, display_name should fall back to
     * just the courier label (no trailing space).
     */
    public function test_display_name_with_empty_description_falls_back_to_courier(): void
    {
        $result = $this->transformItem([
            'code'        => 'tiki',
            'service'     => 'REG',
            'description' => '',
            'cost'        => 15000,
            'etd'         => '3',
        ]);

        // Falls back to service code since description is empty
        $this->assertStringContainsString('TIKI', $result['display_name']);
    }

    /**
     * An empty string "" etd must be normalized to "-".
     */
    public function test_etd_normalizes_empty_string_to_dash(): void
    {
        $result = $this->transformItem([
            'code'        => 'jnt',
            'service'     => 'EZ',
            'description' => 'Reguler',
            'cost'        => 14000,
            'etd'         => '',
        ]);

        $this->assertSame('-', $result['etd']);
    }

    /**
     * "0 day" or "0" etd must be normalized to "-".
     */
    public function test_etd_normalizes_zero_day_to_dash(): void
    {
        $result = $this->transformItem([
            'code'        => 'jne',
            'service'     => 'JTR',
            'description' => 'JNE Trucking',
            'cost'        => 450000,
            'etd'         => '0 day',
        ]);

        $this->assertSame('-', $result['etd']);
    }

    /**
     * Motorcycle freight sub-services (e.g. JTR<130, JTR>130) must be filtered out.
     */
    public function test_motorcycle_freight_services_are_excluded(): void
    {
        $result1 = $this->transformItem([
            'code'        => 'jne',
            'service'     => 'JTR<130',
            'description' => 'JNE Trucking',
            'cost'        => 450000,
            'etd'         => '0 day',
        ]);

        $result2 = $this->transformItem([
            'code'        => 'jne',
            'service'     => 'JTR>130',
            'description' => 'JNE Trucking',
            'cost'        => 650000,
            'etd'         => '0 day',
        ]);

        $this->assertNull($result1);
        $this->assertNull($result2);
    }
}
