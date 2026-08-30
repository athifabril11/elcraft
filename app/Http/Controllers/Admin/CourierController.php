<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Courier;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Admin: Courier Settings
 *
 * Allows the admin to view supported couriers and toggle their active/inactive status.
 * Only active couriers appear at checkout and pass server-side shipping cost validation.
 */
class CourierController extends Controller
{
    /**
     * Display the list of all couriers.
     */
    public function index(): View
    {
        $couriers = Courier::orderBy('id')->get();

        return view('admin.settings.couriers.index', compact('couriers'));
    }

    /**
     * Toggle the is_active status of a courier.
     */
    public function toggle(Courier $courier): RedirectResponse
    {
        $courier->update([
            'is_active' => ! $courier->is_active,
        ]);

        $status = $courier->is_active ? 'diaktifkan' : 'dinonaktifkan';

        return redirect()
            ->route('admin.settings.couriers.index')
            ->with('success', "Kurir {$courier->name} berhasil {$status}.");
    }
}
