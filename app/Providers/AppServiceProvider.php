<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use App\Models\Address;
use App\Policies\AddressPolicy;
use App\View\Composers\StorefrontFooterComposer;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Force HTTPS scheme when accessed via ngrok/HTTPS proxy
        if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }

        // Daftarkan policy untuk model Address
        Gate::policy(Address::class, AddressPolicy::class);

        // Daftarkan policy untuk model Review
        Gate::policy(\App\Models\Review::class, \App\Policies\ReviewPolicy::class);

        // Daftarkan observer untuk Review
        \App\Models\Review::observe(\App\Observers\ReviewObserver::class);

        // Directive Blade untuk format Rupiah
        \Illuminate\Support\Facades\Blade::directive('rupiah', function ($expression) {
            return "<?php echo 'Rp ' . number_format((float) ($expression), 0, ',', '.'); ?>";
        });

        // View Composer untuk layout storefront
        View::composer('layouts.storefront', StorefrontFooterComposer::class);

        // Kustomisasi pesan email verifikasi akun pengguna
        VerifyEmail::toMailUsing(function (object $notifiable, string $url) {
            return (new MailMessage)
                ->subject('Verifikasi Alamat Email Anda - Elcraft')
                ->view('emails.verify-email', [
                    'user' => $notifiable,
                    'url'  => $url,
                ]);
        });

        // Rate limiter bernama untuk endpoint publik yang rawan disalahgunakan.
        // Dipakai lewat middleware throttle:<nama> di routes/web.php — bukan
        // throttle:30,1 numerik, agar tiap endpoint punya bucket terpisah.
        RateLimiter::for('shipping', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->id ?? $request->ip());
        });

        RateLimiter::for('voucher', function (Request $request) {
            return Limit::perMinute(10)->by($request->user()?->id ?? $request->ip());
        });

        RateLimiter::for('cart', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?? $request->ip());
        });
    }
}
