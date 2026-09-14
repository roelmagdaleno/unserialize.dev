<?php

namespace App\Services;

use App\Enums\ConversionInterface;
use Illuminate\Support\Facades\Log;

class ConversionTelemetry
{
    public function record(
        ConversionInterface $interface,
        string $outcome,
        int $inputBytes,
        int $startedAt,
    ): void {
        Log::info('conversion.completed', [
            'interface' => $interface->value,
            'outcome' => $outcome,
            'duration_ms' => round((hrtime(true) - $startedAt) / 1_000_000, 3),
            'input_size_bucket' => $this->inputSizeBucket($inputBytes),
        ]);
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
