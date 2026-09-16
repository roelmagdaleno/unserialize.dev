<?php

namespace App\Services;

use App\Data\UsageContext;
use App\Enums\ConversionInterface;
use App\Enums\ConversionOutcome;
use App\Enums\SyntaxErrorCode;
use App\Enums\UsageEventType;
use App\Models\UsageEvent;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Persists one immutable usage event, best effort.
 *
 * Every column is assigned by name below. There is no mass assignment and no
 * caller-supplied array, so the set of things this application can store is the
 * set of properties written here and nothing a future caller adds.
 */
readonly class UsageEventRecorder
{
    /**
     * Sanitizes the three untrusted values before they are stored.
     */
    public function __construct(private UsageMetadataNormalizer $normalizer) {}

    /**
     * Record one event.
     *
     * A write failure is swallowed: the interaction the caller is reporting has
     * already happened, so telemetry must not turn it into an error. The warning
     * carries the exception class and not its message, because a query exception
     * repeats the statement it failed on, and that statement holds the very
     * values this table is careful about.
     */
    public function record(
        ConversionInterface $interface,
        UsageEventType $event,
        ?UsageContext $context = null,
        ?ConversionOutcome $outcome = null,
        ?float $durationMs = null,
        ?string $inputSizeBucket = null,
        ?SyntaxErrorCode $diagnostic = null,
    ): void {
        try {
            $usageEvent = new UsageEvent;

            $usageEvent->occurred_at = CarbonImmutable::now('UTC');
            $usageEvent->interface = $interface;
            $usageEvent->event = $event;
            $usageEvent->outcome = $outcome?->value;
            $usageEvent->duration_ms = $durationMs;
            $usageEvent->input_size_bucket = $inputSizeBucket;
            $usageEvent->diagnostic = $diagnostic?->value;
            $usageEvent->result_type = $context?->resultType;
            $usageEvent->api_version = $context?->apiVersion;
            $usageEvent->http_status = $context?->httpStatus;
            $usageEvent->mcp_tool = $context?->mcpTool;
            $usageEvent->mcp_transport = $context?->mcpTransport;
            $usageEvent->mcp_protocol_version = $this->normalizer->protocolVersion($context?->mcpProtocolVersion);
            $usageEvent->user_agent = $this->normalizer->userAgent($context?->userAgent);
            $usageEvent->referrer_url = $this->normalizer->url($context?->referrerUrl);
            $usageEvent->request_url = $this->normalizer->url($context?->requestUrl);

            $usageEvent->save();
        } catch (Throwable $exception) {
            Log::warning('usage.event_write_failed', [
                'interface' => $interface->value,
                'event' => $event->value,
                'outcome' => $outcome?->value,
                'exception' => $exception::class,
            ]);
        }
    }
}
