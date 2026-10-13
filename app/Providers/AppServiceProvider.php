<?php

namespace App\Providers;

use App\Services\SiteSettings;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

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
        // Admin-editable site settings (name, logo, contact, shipping...) override the defaults.
        SiteSettings::applyToConfig();

        if ($proxies = config('shop.trusted_proxies')) {
            \Illuminate\Http\Middleware\TrustProxies::at($proxies === '*' ? '*' : array_map('trim', explode(',', (string) $proxies)));
        }

        // On a live site whose address starts with https://, every link, form and asset URL
        // is https too, even behind Cloudflare / a load balancer that hides the https from PHP.
        // (Prevents "mixed content" blocked pictures and scripts.)
        if (! $this->app->environment('local', 'testing') && str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }
    }
}
