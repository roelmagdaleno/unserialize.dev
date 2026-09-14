<?php

namespace App\Http\Middleware;

use App\Enums\ConversionInterface;
use App\Services\ConversionTelemetry;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireJsonContentType
{
    public function __construct(private readonly ConversionTelemetry $telemetry) {}

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
                'unsupported_media_type',
                strlen($request->getContent()),
                $startedAt,
            );

            return response()->json([
                'error' => [
                    'code' => 'unsupported_media_type',
                    'message' => 'Content-Type must be application/json.',
                ],
            ], 415);
        }

        return $next($request);
    }
}
