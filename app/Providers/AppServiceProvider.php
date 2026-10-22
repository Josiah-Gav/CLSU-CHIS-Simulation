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
        // Per token when one is sent (the contract's "per token"), otherwise per
        // IP. The token is hashed so it never ends up as a cache key in clear.
        RateLimiter::for('chis-api', function (Request $request) {
            $token = $request->bearerToken();

            return Limit::perMinute(config('chis.rate_limit_per_minute'))
                ->by($token ? 'token:'.hash('sha256', $token) : 'ip:'.$request->ip());
        });
    }
}
