<?php

namespace App\Services;

use App\Enums\ConversionInterface;
use App\Enums\SyntaxErrorCode;
use Illuminate\Support\Facades\Log;

class ConversionTelemetry
{
    /**
     * Record one conversion.
     *
     * Every field here has a small closed range: two enums, a duration, and a
     * size bucket. That is what makes the privacy claim checkable rather than
     * merely stated, so keep it true. In particular the diagnostic's byte offset
     * must never be logged: it is a direct measurement of the submitted value,
     * it has unbounded cardinality, and across repeated submissions it leaks the
     * shape of data the converter promises not to retain. The parameter is typed
     * as an enum so a fragment of user input cannot reach the logger by mistake.
     */
    public function record(
        ConversionInterface $interface,
        string $outcome,
        int $inputBytes,
        int $startedAt,
        ?SyntaxErrorCode $diagnostic = null,
    ): void {
        Log::info('conversion.completed', array_filter([
            'interface' => $interface->value,
            'outcome' => $outcome,
            'duration_ms' => round((hrtime(true) - $startedAt) / 1_000_000, 3),
            'input_size_bucket' => $this->inputSizeBucket($inputBytes),
            'diagnostic' => $diagnostic?->value,
        ], static fn (mixed $value): bool => $value !== null));
    }

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
