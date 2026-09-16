<?php

namespace App\Services;

use App\Data\ConversionEnvelope;
use App\Data\UsageContext;
use App\Enums\ConversionInterface;
use App\Enums\ConversionOutcome;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The one place a refused conversion is identified, counted and answered.
 *
 * The browser, the HTTP API and the MCP endpoint all identify the client and
 * count the refusal through this class. They answer differently -- the two HTTP
 * surfaces return a 429 envelope, the browser attaches an inline field error --
 * so the response stays with the caller.
 */
readonly class ConversionRateLimiter
{
    /**
     * Records every refusal as a `rate_limited` outcome.
     */
    public function __construct(private ConversionTelemetry $telemetry) {}

    /**
     * Identify a client without storing anything that identifies them.
     *
     * The address is hashed rather than kept: the limiter only needs to know
     * that two attempts came from the same place, never where that is.
     */
    public function key(Request $request): string
    {
        return hash('sha256', (string) $request->ip());
    }

    /**
     * The configured per-minute conversion allowance.
     */
    public function attemptsPerMinute(): int
    {
        return (int) config('conversion.rate_limit.per_minute');
    }

    /**
     * Count one refused attempt.
     *
     * @param  int  $startedAt  `hrtime(true)` reading from when the attempt began.
     */
    public function reject(ConversionInterface $interface, int $inputBytes, int $startedAt, UsageContext $context): void
    {
        $this->telemetry->record(
            $interface,
            ConversionOutcome::RateLimited,
            $inputBytes,
            $startedAt,
            null,
            $context,
        );
    }

    /**
     * Count one refused attempt and answer it with the shared 429 envelope.
     *
     * @param  array<string, mixed>  $headers  The limiter's own `Retry-After` and quota headers.
     */
    public function refuse(
        ConversionInterface $interface,
        int $inputBytes,
        UsageContext $context,
        string $message,
        array $headers,
    ): JsonResponse {
        $this->reject($interface, $inputBytes, hrtime(true), $context);

        return response()->json(
            ConversionEnvelope::error(ConversionOutcome::RateLimited->value, $message),
            429,
            $headers,
        );
    }
}
