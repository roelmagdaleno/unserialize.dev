<?php

use App\Enums\ConversionInterface;
use App\Services\ConversionTelemetry;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

uses(TestCase::class);

test('conversion telemetry records only bounded operational fields', function () {
    Log::spy();

    app(ConversionTelemetry::class)->record(
        ConversionInterface::Api,
        'unsupported_object',
        2048,
        hrtime(true),
    );

    Log::shouldHaveReceived('info')->once()->withArgs(function (string $message, array $context): bool {
        expect($message)->toBe('conversion.completed')
            ->and($context)->toHaveKeys(['interface', 'outcome', 'duration_ms', 'input_size_bucket'])
            ->and($context)->toHaveCount(4)
            ->and($context['interface'])->toBe('api')
            ->and($context['outcome'])->toBe('unsupported_object')
            ->and($context['duration_ms'])->toBeFloat()->toBeGreaterThanOrEqual(0)
            ->and($context['input_size_bucket'])->toBe('1-16KiB');

        return true;
    });
});

test('input sizes are recorded as buckets instead of exact values', function (int $bytes, string $bucket) {
    Log::spy();

    app(ConversionTelemetry::class)->record(ConversionInterface::Browser, 'success', $bytes, hrtime(true));

    Log::shouldHaveReceived('info')->withArgs(
        fn (string $message, array $context): bool => $context['input_size_bucket'] === $bucket,
    );
})->with([
    'small' => [1024, '0-1KiB'],
    'medium' => [16_384, '1-16KiB'],
    'large' => [65_536, '16-64KiB'],
    'maximum' => [262_144, '64-256KiB'],
    'over limit' => [262_145, 'over-256KiB'],
]);

test('nightwatch cannot capture conversion request bodies', function () {
    expect(config('nightwatch.capture_request_payload'))->toBeFalse()
        ->and(config('nightwatch.redact_payload_fields'))->toContain('serialized', 'serializedData');
});
