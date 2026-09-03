<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ShopSetting;
use Illuminate\Http\Request;

/**
 * Admin: Order Auto-Complete Settings
 *
 * Allows the admin to configure how many days after the order is marked
 * "dikirim" (shipped) before it is automatically completed if the customer
 * does not confirm receipt.
 */
class OrderAutoCompleteController extends Controller
{
    /**
     * Display the order auto-complete settings page.
     */
    public function index()
    {
        $autoCompleteDays = (int) ShopSetting::get('order_auto_complete_days', 7);

        return view('admin.settings.order-auto-complete.index', compact('autoCompleteDays'));
    }

    /**
     * Update the order auto-complete day threshold.
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'auto_complete_days' => 'required|integer|min:1|max:30',
        ]);

        ShopSetting::set('order_auto_complete_days', (string) $validated['auto_complete_days']);

        return redirect()
            ->route('admin.settings.order-auto-complete.index')
            ->with('success', 'Pengaturan berhasil diperbarui: pesanan akan otomatis diselesaikan setelah ' . $validated['auto_complete_days'] . ' hari.');
    }
}
