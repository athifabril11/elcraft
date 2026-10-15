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
     *   3. Look up by email      → link google_id to an existing account (no duplicate),
     *                              but only when Google verified the email; an
     *                              unverified local account is verified and its
     *                              pre-chosen password invalidated in the process.
     *   4. No match              → create a brand-new User record (verified Google email only).
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

        // Google's email_verified claim, read ONCE from the raw payload.
        // Socialite exposes the raw array the way Laravel\Socialite\AbstractUser
        // stores it — behind getRaw() (the public $user array). Only boolean
        // true or the string 'true' count as verified; missing or false means
        // NOT verified.
        $googleEmailVerified = in_array(
            $googleUser->getRaw()['email_verified'] ?? null,
            [true, 'true'],
            true
        );

        // 1. Find by google_id (fastest path — returning Google-authenticated user)
        $user = User::where('google_id', $googleUser->getId())->first();

        if (! $user) {
            // 2. Find by email — account linking (user registered via email previously)
            $user = User::where('email', $googleUser->getEmail())->first();

            if ($user) {
                // Reject inactive accounts (e.g. anonymized after deletion with orders)
                // — BEFORE any change to the record.
                if (! $user->is_active) {
                    return redirect()->route('home')
                        ->with('show_login_modal', true)
                        ->with('auth_modal_tab', 'login')
                        ->with('error', 'Akun ini sudah tidak aktif.');
                }

                // The login would now depend on this email address, so Google
                // must have verified it. Otherwise someone could pre-register
                // the victim's email and silently gain a linked account.
                if (! $googleEmailVerified) {
                    return redirect()->route('home')
                        ->with('show_login_modal', true)
                        ->with('auth_modal_tab', 'login')
                        ->with('error', 'Email Google kamu belum terverifikasi, silakan gunakan metode login lain.');
                }

                if (is_null($user->email_verified_at)) {
                    // The local email was NEVER verified — the account may have
                    // been pre-registered by someone who still knows its
                    // password. Take it over securely: verify the email,
                    // invalidate any pre-chosen password and rotate the
                    // remember token, together with the Google identity, in
                    // ONE update.
                    $user->forceFill([
                        'google_id'         => $googleUser->getId(),
                        'avatar'            => $googleUser->getAvatar(),
                        'email_verified_at' => now(),
                        'password'          => Hash::make(Str::random(40)),
                        'remember_token'    => Str::random(60),
                    ])->save();
                } else {
                    // Link the Google identity without creating a duplicate record
                    $user->update([
                        'google_id' => $googleUser->getId(),
                        'avatar'    => $googleUser->getAvatar(),
                    ]);
                }
            } else {
                // No local account — this email will be used to CREATE one,
                // so Google must have verified it first.
                if (! $googleEmailVerified) {
                    return redirect()->route('home')
                        ->with('show_login_modal', true)
                        ->with('auth_modal_tab', 'login')
                        ->with('error', 'Email Google kamu belum terverifikasi, silakan gunakan metode login lain.');
                }

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
