<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * RedirectGuestsWithModal
 *
 * Menggantikan perilaku redirect tamu ke /login.
 * Jika tamu mencoba mengakses halaman yang dilindungi auth, mereka TIDAK
 * diarahkan ke /login. Mereka tetap di halaman sebelumnya (redirect()->back())
 * dan modal login dibuka secara otomatis via session flash.
 */
class RedirectGuestsWithModal
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            return $next($request);
        }

        // Hanya berlaku untuk request GET (navigasi halaman).
        // Request POST/PATCH/DELETE akan ditangani oleh Laravel auth middleware
        // secara normal (mengembalikan JSON 401 atau redirect ke login sesuai konteks).
        if (!$request->isMethod('GET')) {
            return redirect()->route('login');
        }

        // Flash penanda agar storefront.blade.php membuka modal login setelah redirect.
        session()->flash('show_login_modal', true);

        // Kembalikan pengguna ke halaman sebelumnya, atau ke halaman utama
        // jika tidak ada referrer (misalnya, langsung mengetik URL di browser).
        $fallback = url()->previous(route('home'));

        // Hindari loop redirect jika halaman sebelumnya adalah halaman yang sama.
        if ($fallback === $request->url()) {
            $fallback = route('home');
        }

        return redirect($fallback);
    }
}
