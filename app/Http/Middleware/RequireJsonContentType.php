<?php

namespace App\Http\Middleware;

use App\Data\ConversionEnvelope;
use App\Data\UsageContext;
use App\Enums\ConversionInterface;
use App\Enums\ConversionOutcome;
use App\Http\Controllers\Api\V1\UnserializeController;
use App\Services\ConversionTelemetry;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

readonly class RequireJsonContentType
{
    public function __construct(private ConversionTelemetry $telemetry) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = hrtime(true);

        if (! $request->isJson()) {
            $this->telemetry->record(
                ConversionInterface::Api,
                ConversionOutcome::UnsupportedMediaType,
                strlen($request->getContent()),
                $startedAt,
                null,
                UsageContext::fromRequest(
                    $request,
                    apiVersion: UnserializeController::API_VERSION,
                    httpStatus: 415,
                ),
            );

            return response()->json(ConversionEnvelope::error(
                ConversionOutcome::UnsupportedMediaType->value,
                'Content-Type must be application/json.',
            ), 415);
        }

        return $next($request);
    }
}
