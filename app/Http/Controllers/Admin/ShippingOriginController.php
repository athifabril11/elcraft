<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ShopSetting;
use Illuminate\Http\Request;

/**
 * Admin: Shipping Origin Settings
 *
 * Allows the admin to view and update the store's shipping origin city,
 * which is used as the 'origin' parameter in all RajaOngkir cost calculations.
 */
class ShippingOriginController extends Controller
{
    /**
     * Display the shipping origin settings page.
     */
    public function index()
    {
        $originCityId   = ShopSetting::get('shipping_origin_city_id', '256');
        $originCityName = ShopSetting::get('shipping_origin_city_name', 'Jember');

        return view('admin.settings.shipping.index', compact('originCityId', 'originCityName'));
    }

    /**
     * Update the shipping origin city.
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'origin_city_id'   => 'required|integer|min:1',
            'origin_city_name' => 'required|string|max:100',
        ]);

        ShopSetting::set('shipping_origin_city_id',   (string) $validated['origin_city_id']);
        ShopSetting::set('shipping_origin_city_name', $validated['origin_city_name']);

        return redirect()
            ->route('admin.settings.shipping.index')
            ->with('success', 'Kota asal pengiriman berhasil diperbarui ke "' . $validated['origin_city_name'] . '".');
    }
}
