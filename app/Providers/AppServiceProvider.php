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
        RateLimiter::for('unserialize-api', function (Request $request) {
            return Limit::perMinute(10)
                ->by(hash('sha256', $request->ip()))
                ->response(fn (Request $request, array $headers) => response()->json([
                    'error' => [
                        'code' => 'rate_limited',
                        'message' => 'Too many conversion attempts. Try again later.',
                    ],
                ], 429, $headers));
        });

        RateLimiter::for('unserialize-mcp', fn (Request $request): Limit => Limit::perMinute(10)
            ->by(hash('sha256', $request->ip())));
    }
}
