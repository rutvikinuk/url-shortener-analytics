<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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
        // Creating links writes to the database: keep it tight (per IP).
        RateLimiter::for('links-create', fn (Request $r) => Limit::perMinute(10)->by($r->ip()));

        // Reading the JSON API is cheap but still scrapeable.
        RateLimiter::for('api-read', fn (Request $r) => Limit::perMinute(120)->by($r->ip()));

        // Redirects are the hot path (and each one writes a click row).
        RateLimiter::for('redirect', fn (Request $r) => Limit::perMinute(300)->by($r->ip()));
    }
}
