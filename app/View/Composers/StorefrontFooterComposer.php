<?php

namespace App\View\Composers;

use App\Models\ShopSetting;
use Illuminate\View\View;

class StorefrontFooterComposer
{
    /**
     * Bind data to the view.
     */
    public function compose(View $view): void
    {
        $adminWhatsapp = ShopSetting::get('admin_whatsapp_number', '');
        $digitsOnly    = preg_replace('/\D/', '', (string) $adminWhatsapp);

        if (strlen($digitsOnly) >= 10) {
            $footerWaLink = 'https://wa.me/' . $digitsOnly;

            if (preg_match('/[ +]/', (string) $adminWhatsapp)) {
                $footerWaDisplay = trim((string) $adminWhatsapp);
            } elseif (str_starts_with($digitsOnly, '62')) {
                $remainder = substr($digitsOnly, 5);
                $footerWaDisplay = '+62 ' . substr($digitsOnly, 2, 3) . '-' . substr($remainder, 0, 4) . (strlen($remainder) > 4 ? '-' . substr($remainder, 4) : '');
            } elseif (str_starts_with($digitsOnly, '0')) {
                $remainder = substr($digitsOnly, 4);
                $footerWaDisplay = substr($digitsOnly, 0, 4) . '-' . substr($remainder, 0, 4) . (strlen($remainder) > 4 ? '-' . substr($remainder, 4) : '');
            } else {
                $footerWaDisplay = '+' . $digitsOnly;
            }
        } else {
            $footerWaLink    = null;
            $footerWaDisplay = null;
        }

        $view->with([
            'footerWaLink'    => $footerWaLink,
            'footerWaDisplay' => $footerWaDisplay,
        ]);
    }
}
