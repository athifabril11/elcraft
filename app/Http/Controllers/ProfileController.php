<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     * Hanya dapat diakses oleh pengguna yang sudah login (dilindungi oleh middleware guest.modal).
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Delete the user's account.
     *
     * If the user has existing orders, the account is anonymized (soft-wipe) to preserve
     * referential integrity. Otherwise, the row is hard-deleted. In both cases the user is
     * logged out and redirected to the home page.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();

        // ── Confirmation validation ───────────────────────────────────────────────
        if ($user->google_id !== null) {
            $request->validateWithBag('userDeletion', [
                'confirmation' => ['required', 'in:HAPUS'],
            ], [
                'confirmation.in'       => 'Ketik HAPUS untuk mengonfirmasi penghapusan akun.',
                'confirmation.required' => 'Ketik HAPUS untuk mengonfirmasi penghapusan akun.',
            ]);
        } else {
            $request->validateWithBag('userDeletion', [
                'password' => ['required', 'current_password'],
            ]);
        }

        // ── Log out BEFORE touching the user record ───────────────────────────────
        Auth::logout();

        // ── Anonymize or hard-delete ──────────────────────────────────────────────
        if ($user->orders()->exists()) {
            // User has order history — anonymize to preserve FK integrity
            $user->update([
                'name'          => 'Pengguna Terhapus',
                'email'         => 'deleted_' . $user->id . '_' . Str::random(10) . '@deleted.elcraft.local',
                'phone'         => null,
                'avatar'        => null,
                'google_id'     => null,
                'password'      => Hash::make(Str::random(40)),
                'is_active'     => false,
                'anonymized_at' => now(),
            ]);
        } else {
            // No orders — safe to hard-delete
            $user->delete();
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/')->with('status', 'account-deleted');
    }
}
