<?php

use App\Enums\ConversionInterface;
use App\Enums\SyntaxErrorCode;
use App\Services\ConversionTelemetry;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

uses(TestCase::class);

/**
 * The diagnostic key is absent here, which is also what proves it is omitted
 * rather than logged as null when a failure has no located syntax problem.
 */
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

test('conversion telemetry records the diagnostic category and nothing measured from the payload', function () {
    Log::spy();

    app(ConversionTelemetry::class)->record(
        ConversionInterface::Browser,
        'invalid_input',
        2048,
        hrtime(true),
        SyntaxErrorCode::StringLengthMismatch,
    );

    Log::shouldHaveReceived('info')->once()->withArgs(function (string $message, array $context): bool {
        expect($context)->toHaveKeys(['interface', 'outcome', 'duration_ms', 'input_size_bucket', 'diagnostic'])
            ->and($context)->toHaveCount(5)
            ->and($context['diagnostic'])->toBe('string_length_mismatch')
            ->and($context)->not->toHaveKey('offset')
            ->and($context)->not->toHaveKey('length')
            ->and($context)->not->toHaveKey('suggestion')
            ->and($context)->not->toHaveKey('excerpt');

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
