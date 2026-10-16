<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Console\Scheduling\Schedule;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Percayai hanya loopback (ngrok / reverse proxy lokal); server produksi
        // dengan nginx yang langsung menghadapi internet tidak perlu daftar lain;
        // bila CDN/load balancer dipasang di depannya, ganti daftar ini dengan
        // rentang IP milik CDN/load balancer tersebut.
        $middleware->trustProxies(at: ['127.0.0.1', '::1']);
        $middleware->validateCsrfTokens(except: [
            'midtrans/webhook',
            'shipping/cost',
        ]);

        // Alias middleware tamu — menggantikan redirect ke /login dengan
        // redirect ke halaman sebelumnya + modal login otomatis.
        $middleware->alias([
            'guest.modal' => \App\Http\Middleware\RedirectGuestsWithModal::class,
            'admin'       => \App\Http\Middleware\EnsureUserIsAdmin::class,
        ]);
    })
    ->withSchedule(function (Schedule $schedule): void {
        // Auto-complete orders stuck at 'dikirim' beyond the configured threshold.
        // Runs once per day. Threshold is configurable via the admin
        // Pengaturan → Auto-Selesai page (shop_setting key: order_auto_complete_days).
        $schedule->command('orders:auto-complete')->daily();

        // Auto-cancel pending orders whose payment deadline has passed.
        // Releases the checkout stock reservation, cancels the Midtrans
        // transaction, and emails the customer. Hourly keeps the gap
        // between expiry and cancellation small without hammering Midtrans.
        // withoutOverlapping(30): cegah dua run saling tumpang-tindih bila
        // satu run tersendat (API Midtrans lambat); mutex kedaluwarsa 30 menit.
        $schedule->command('orders:auto-cancel-expired')->hourly()->withoutOverlapping(30);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Kembalikan JSON untuk semua request yang menginginkan JSON (AJAX, API, test postJson)
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->expectsJson(),
        );
    })->create();
