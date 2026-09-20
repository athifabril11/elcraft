<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ShopSetting;
use Illuminate\Http\Request;

/**
 * Admin: WhatsApp Number Settings
 *
 * Allows the admin to view and update the store's admin WhatsApp number,
 * which is used for the storefront footer WhatsApp link and other
 * customer-facing WhatsApp CTAs across the site.
 */
class WhatsAppSettingController extends Controller
{
    /**
     * Display the WhatsApp number settings page.
     */
    public function index()
    {
        $waNumber = ShopSetting::get('admin_whatsapp_number', '');

        return view('admin.settings.whatsapp.index', compact('waNumber'));
    }

    /**
     * Update the admin WhatsApp number.
     */
    public function update(Request $request)
    {
        $request->validate([
            'wa_number' => 'required|string|max:20',
        ]);

        // Strip all non-digit characters, same as the storefront footer composer
        $digitsOnly = preg_replace('/\D/', '', $request->input('wa_number'));

        if (strlen($digitsOnly) < 10) {
            return back()
                ->withInput()
                ->withErrors([
                    'wa_number' => 'Nomor WhatsApp tidak valid. Harus mengandung minimal 10 digit angka.',
                ]);
        }

        ShopSetting::set('admin_whatsapp_number', $digitsOnly);

        return redirect()
            ->route('admin.settings.whatsapp.index')
            ->with('success', 'Nomor WhatsApp berhasil diperbarui ke "' . $digitsOnly . '".');
    }
}
