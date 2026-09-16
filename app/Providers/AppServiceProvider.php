<?php

namespace App\Providers;

use App\Data\UsageContext;
use App\Enums\ConversionInterface;
use App\Http\Controllers\Api\V1\UnserializeController;
use App\Mcp\Tools\ConvertSerializedDataTool;
use App\Services\ConversionRateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Head\Enums\ImageType;
use Laravel\Head\Enums\OgType;
use Laravel\Head\Enums\TwitterCard;
use Laravel\Head\Facades\Head;
use Laravel\Head\HeadBuilder;
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
        $this->registerHeadDefaults();

        $this->registerConversionRateLimiters();

        Nightwatch::redactRequests(function (NightwatchRequest $request): bool {
            if ($request->routeName === 'outputs') {
                $request->url = url('/o/[redacted]');
            }

            return true;
        });
    }

    /**
     * Register the per-client limit each conversion surface answers to.
     *
     * Both limiters refuse at the same rate, identify the client the same way
     * and record the refusal the same way; only the wording of the refusal
     * differs, because one speaks to a JSON client and the other to an MCP one.
     */
    private function registerConversionRateLimiters(): void
    {
        $limiter = app(ConversionRateLimiter::class);

        RateLimiter::for('unserialize-api', fn (Request $request): Limit => Limit::perMinute($limiter->attemptsPerMinute())
            ->by($limiter->key($request))
            ->response(fn (Request $request, array $headers) => $limiter->refuse(
                ConversionInterface::Api,
                strlen((string) $request->input('serialized', '')),
                UsageContext::fromRequest(
                    $request,
                    apiVersion: UnserializeController::API_VERSION,
                    httpStatus: 429,
                ),
                'Too many conversion attempts. Try again later.',
                $headers,
            )));

        RateLimiter::for('unserialize-mcp', fn (Request $request): Limit => Limit::perMinute($limiter->attemptsPerMinute())
            ->by($limiter->key($request))
            ->response(fn (Request $request, array $headers) => $limiter->refuse(
                ConversionInterface::Mcp,
                0,
                /**
                 * The limit is reached before the request is dispatched, so
                 * the tool is named from the one tool this endpoint exposes
                 * rather than from anything in the rejected payload.
                 */
                UsageContext::fromRequest(
                    $request,
                    httpStatus: 429,
                    mcpTool: ConvertSerializedDataTool::NAME,
                    mcpTransport: ConvertSerializedDataTool::TRANSPORT,
                ),
                'Too many MCP requests. Try again later.',
                $headers,
            )));
    }

    /**
     * Register the site-wide document head metadata.
     *
     * Pages layer their own title, description, canonical URL, and robots
     * directives on top of these defaults through their route metadata.
     */
    private function registerHeadDefaults(): void
    {
        Head::defaults(fn (HeadBuilder $head) => $head
            ->title('Unserialize', suffix: ' | Unserialize')
            ->description('Convert PHP serialized data to readable JSON.')
            ->applicationName('Unserialize')
            ->viewport('width=device-width, initial-scale=1.0')
            ->colorScheme('light dark')
            ->searchableByRobots()
            ->og(type: OgType::Website, siteName: 'Unserialize')
            ->ogImage(asset('images/social.png'), alt: 'Unserialize', width: 2400, height: 1200, type: ImageType::Png)
            ->twitter(card: TwitterCard::SummaryWithLargeImage, site: '@roelmagdaleno', creator: '@roelmagdaleno')
            ->preconnect('https://fonts.bunny.net'));
    }
}
