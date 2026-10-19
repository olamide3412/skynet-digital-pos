<?php

namespace App\Modules\PublicCatalog;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class PublicCatalogServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/Config/public_catalog.php',
            'public_catalog'
        );
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Define IP-based rate limiter (default 60 req/min)
        RateLimiter::for('public-catalog', function (Request $request) {
            $limit = (int) config('public_catalog.rate_limit_per_minute', 60);
            return Limit::perMinute($limit)->by($request->ip() ?: 'anonymous');
        });
    }
}
