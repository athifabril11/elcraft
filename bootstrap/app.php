<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schedule;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
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
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Kembalikan JSON untuk semua request yang menginginkan JSON (AJAX, API, test postJson)
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->expectsJson(),
        );
    })->create();
