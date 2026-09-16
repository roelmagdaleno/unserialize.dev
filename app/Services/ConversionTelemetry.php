<?php

namespace App\Services;

use App\Data\UsageContext;
use App\Enums\ConversionInterface;
use App\Enums\ConversionOutcome;
use App\Enums\SyntaxErrorCode;
use App\Enums\UsageEventType;
use App\Models\ConversionMetric;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Records what a conversion did, without recording what it converted.
 *
 * Every field written here has a small closed range -- two enums, a duration
 * and a size bucket -- which is what makes the privacy claim checkable rather
 * than merely stated.
 */
readonly class ConversionTelemetry
{
    /**
     * Persists the event row behind each recorded conversion.
     */
    public function __construct(private UsageEventRecorder $recorder) {}

    /**
     * Record one conversion to the log, the event table and the daily aggregate.
     *
     * - The diagnostic's byte offset must never be logged: it measures the
     *   submitted value directly and across repeated submissions leaks the shape
     *   of data the converter promises not to retain. The parameter is an enum
     *   so no fragment of user input can reach the logger by mistake.
     * - `$context` is a typed allowlist rather than an array; its three
     *   untrusted values are normalized on the way to the event table and never
     *   reach this log.
     * - The event insert and the aggregate increment are attempted
     *   independently, so a failure in either leaves the other alone and does
     *   not reach the caller.
     */
    public function record(
        ConversionInterface $interface,
        ConversionOutcome $outcome,
        int $inputBytes,
        int $startedAt,
        ?SyntaxErrorCode $diagnostic = null,
        ?UsageContext $context = null,
    ): void {
        $durationMs = round((hrtime(true) - $startedAt) / 1_000_000, 3);
        $inputSizeBucket = $this->inputSizeBucket($inputBytes);

        Log::info('conversion.completed', array_filter([
            'interface' => $interface->value,
            'outcome' => $outcome->value,
            'duration_ms' => $durationMs,
            'input_size_bucket' => $inputSizeBucket,
            'diagnostic' => $diagnostic?->value,
        ], static fn (mixed $value): bool => $value !== null));

        $this->recorder->record(
            $interface,
            UsageEventType::ConversionCompleted,
            $context,
            $outcome,
            $durationMs,
            $inputSizeBucket,
            $diagnostic,
        );

        $this->recordAggregate($interface, $outcome);
    }

    /**
     * Count this conversion in the durable daily aggregate.
     *
     * - A persistence failure is swallowed rather than turning a conversion the
     *   caller already completed into an error.
     * - The failure log carries the exception class, not its message: a query
     *   exception repeats the statement it failed on. It is not routed through
     *   {@see self::record()}, which would re-enter this same write.
     * - Nothing derived from the submitted value is passed on; the size bucket
     *   and diagnostic category stay in the short-lived log only.
     */
    private function recordAggregate(ConversionInterface $interface, ConversionOutcome $outcome): void
    {
        try {
            ConversionMetric::recordOccurrence($interface, $outcome, CarbonImmutable::now('UTC'));
        } catch (Throwable $exception) {
            Log::warning('conversion.metrics_write_failed', [
                'interface' => $interface->value,
                'outcome' => $outcome->value,
                'exception' => $exception::class,
            ]);
        }
    }

    /**
     * Bucket a payload size, so no exact byte count is ever recorded.
     */
    private function inputSizeBucket(int $bytes): string
    {
        return match (true) {
            $bytes <= 1024 => '0-1KiB',
            $bytes <= 16_384 => '1-16KiB',
            $bytes <= 65_536 => '16-64KiB',
            $bytes <= Serialized::MAX_INPUT_BYTES => '64-256KiB',
            default => 'over-256KiB',
        };
    }
}
