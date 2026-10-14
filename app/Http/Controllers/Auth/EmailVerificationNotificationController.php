<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class EmailVerificationNotificationController extends Controller
{
    /**
     * Send a new email verification notification.
     */
    public function store(Request $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            $redirectRoute = $request->user()->role === 'admin' ? 'admin.dashboard' : 'home';
            return redirect()->intended(route($redirectRoute, absolute: false));
        }

        try {
            $request->user()->sendEmailVerificationNotification();
        } catch (\Throwable $e) {
            Log::error('Email verifikasi gagal terkirim (resend_verification)', [
                'to_email' => $request->user()->email,
                'error'    => $e->getMessage(),
            ]);

            return back()->with('status', 'verification-link-failed');
        }

        return back()->with('status', 'verification-link-sent');
    }
}
