<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class GoogleController extends Controller
{
    /**
     * Redirect the user to the Google OAuth authorisation page.
     */
    public function redirect()
    {
        return Socialite::driver('google')->redirect();
    }

    /**
     * Handle the Google OAuth callback.
     *
     * Strategy:
     *   1. Retrieve user via Socialite (with stateless fallback if state session is mismatched).
     *   2. Look up by google_id  → direct login (returning Google user).
     *   3. Look up by email      → link google_id to existing account (no duplicate).
     *   4. No match              → create a brand-new User record.
     */
    public function callback()
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Laravel\Socialite\Two\InvalidStateException $e) {
            return redirect()->route('home')
                ->with('show_login_modal', true)
                ->with('auth_modal_tab', 'login')
                ->with('error', 'Sesi login kedaluwarsa, silakan coba lagi.');
        } catch (\Throwable $e) {
            return redirect()->route('home')
                ->with('show_login_modal', true)
                ->with('auth_modal_tab', 'login')
                ->with('error', 'Gagal masuk dengan Google. Silakan coba lagi.');
        }

        // 1. Find by google_id (fastest path — returning Google-authenticated user)
        $user = User::where('google_id', $googleUser->getId())->first();

        if (! $user) {
            // 2. Find by email — account linking (user registered via email previously)
            $user = User::where('email', $googleUser->getEmail())->first();

            if ($user) {
                // Reject inactive accounts (e.g. anonymized after deletion with orders)
                if (! $user->is_active) {
                    return redirect()->route('home')
                        ->with('show_login_modal', true)
                        ->with('auth_modal_tab', 'login')
                        ->with('error', 'Akun ini sudah tidak aktif.');
                }

                // Link the Google identity without creating a duplicate record
                $user->update([
                    'google_id' => $googleUser->getId(),
                    'avatar'    => $googleUser->getAvatar(),
                ]);
            } else {
                // 3. Brand-new user — create account
                $user = User::create([
                    'name'              => $googleUser->getName(),
                    'email'             => $googleUser->getEmail(),
                    'google_id'         => $googleUser->getId(),
                    'avatar'            => $googleUser->getAvatar(),
                    // Securely random password — user cannot log in with it directly.
                    // They must use "forgot password" to set one if they ever need email login.
                    'password'          => Hash::make(Str::random(32)),
                    'email_verified_at' => now(),
                    'role'              => 'customer',
                    'is_active'         => true,
                ]);
            }
        } else {
            // Found by google_id — check is_active
            if (! $user->is_active) {
                return redirect()->route('home')
                    ->with('show_login_modal', true)
                    ->with('auth_modal_tab', 'login')
                    ->with('error', 'Akun ini sudah tidak aktif.');
            }
        }

        Auth::login($user);

        request()->session()->regenerate();

        // Use the same redirect logic as AuthenticatedSessionController@store
        if ($user->role === 'admin') {
            return redirect()->intended(route('admin.dashboard', absolute: false));
        }

        return redirect()->intended(route('home', absolute: false));
    }
}
