<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthenticatedSessionController extends Controller
{
    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse|JsonResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        if ($request->wantsJson()) {
            $redirectUrl = $request->user()->role === 'admin'
                ? route('admin.dashboard')
                : null;

            return response()->json([
                'success'  => true,
                'redirect' => $redirectUrl,
            ]);
        }

        if ($request->user()->role === 'admin') {
            $intended = session('url.intended');
            if (!$intended || $intended === url('/') || $intended === route('home')) {
                session()->forget('url.intended');
                return redirect()->route('admin.dashboard');
            }
            return redirect()->intended(route('admin.dashboard'));
        }

        return redirect()->intended(route('home', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
