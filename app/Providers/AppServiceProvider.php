<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\Address;
use App\Policies\AddressPolicy;

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
    }
}
