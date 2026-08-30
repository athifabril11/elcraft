<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ShippingDiscountRule;
use Illuminate\Http\Request;

/**
 * Admin: Shipping Discount Rules
 *
 * CRUD management for shipping discount rules that are applied automatically
 * in ShippingService::calculateCost() for matching city or province destinations.
 */
class ShippingDiscountController extends Controller
{
    /**
     * Display the list of all shipping discount rules.
     */
    public function index()
    {
        $rules = ShippingDiscountRule::orderByDesc('created_at')->get();

        return view('admin.settings.shipping-discounts.index', compact('rules'));
    }

    /**
     * Store a new shipping discount rule.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'scope'           => 'required|in:province,city',
            'scope_id'        => 'required|integer|min:1',
            'scope_name'      => 'required|string|max:100',
            'discount_type'   => 'required|in:free,fixed',
            'discount_amount' => 'required_if:discount_type,fixed|nullable|integer|min:0',
            'description'     => 'nullable|string|max:255',
        ]);

        // Ensure discount_amount is null for 'free' type
        if ($validated['discount_type'] === 'free') {
            $validated['discount_amount'] = null;
        }

        ShippingDiscountRule::create($validated);

        return redirect()
            ->route('admin.settings.shipping-discounts.index')
            ->with('success', 'Aturan diskon ongkir berhasil ditambahkan.');
    }

    /**
     * Update an existing shipping discount rule.
     */
    public function update(Request $request, ShippingDiscountRule $shippingDiscount)
    {
        $validated = $request->validate([
            'scope'           => 'required|in:province,city',
            'scope_id'        => 'required|integer|min:1',
            'scope_name'      => 'required|string|max:100',
            'discount_type'   => 'required|in:free,fixed',
            'discount_amount' => 'required_if:discount_type,fixed|nullable|integer|min:0',
            'description'     => 'nullable|string|max:255',
        ]);

        if ($validated['discount_type'] === 'free') {
            $validated['discount_amount'] = null;
        }

        $shippingDiscount->update($validated);

        return redirect()
            ->route('admin.settings.shipping-discounts.index')
            ->with('success', 'Aturan diskon ongkir berhasil diperbarui.');
    }

    /**
     * Delete a shipping discount rule.
     */
    public function destroy(ShippingDiscountRule $shippingDiscount)
    {
        $shippingDiscount->delete();

        return redirect()
            ->route('admin.settings.shipping-discounts.index')
            ->with('success', 'Aturan diskon ongkir berhasil dihapus.');
    }

    /**
     * Toggle the is_active status of a shipping discount rule.
     */
    public function toggle(ShippingDiscountRule $shippingDiscount)
    {
        $shippingDiscount->update(['is_active' => ! $shippingDiscount->is_active]);

        $status = $shippingDiscount->is_active ? 'diaktifkan' : 'dinonaktifkan';

        return redirect()
            ->route('admin.settings.shipping-discounts.index')
            ->with('success', "Aturan diskon ongkir berhasil {$status}.");
    }
}
