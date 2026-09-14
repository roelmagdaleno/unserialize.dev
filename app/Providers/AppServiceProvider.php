<?php

namespace App\Providers;

use App\Enums\ConversionInterface;
use App\Services\ConversionTelemetry;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Nightwatch\Facades\Nightwatch;
use Laravel\Nightwatch\Records\Request as NightwatchRequest;

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
                ->response(function (Request $request, array $headers) {
                    app(ConversionTelemetry::class)->record(
                        ConversionInterface::Api,
                        'rate_limited',
                        strlen((string) $request->input('serialized', '')),
                        hrtime(true),
                    );

                    return response()->json([
                        'error' => [
                            'code' => 'rate_limited',
                            'message' => 'Too many conversion attempts. Try again later.',
                        ],
                    ], 429, $headers);
                });
        });

        RateLimiter::for('unserialize-mcp', fn (Request $request): Limit => Limit::perMinute(10)
            ->by(hash('sha256', $request->ip()))
            ->response(function (Request $request, array $headers) {
                app(ConversionTelemetry::class)->record(
                    ConversionInterface::Mcp,
                    'rate_limited',
                    0,
                    hrtime(true),
                );

                return response()->json([
                    'error' => [
                        'code' => 'rate_limited',
                        'message' => 'Too many MCP requests. Try again later.',
                    ],
                ], 429, $headers);
            }));

        Nightwatch::redactRequests(function (NightwatchRequest $request): bool {
            if ($request->routeName === 'outputs') {
                $request->url = url('/o/[redacted]');
            }

            return true;
        });
    }
}
