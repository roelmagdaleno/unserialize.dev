<?php

use App\Enums\ConversionInterface;
use App\Enums\ConversionOutcome;
use App\Enums\SyntaxErrorCode;
use App\Models\ConversionMetric;
use App\Services\ConversionTelemetry;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class, LazilyRefreshDatabase::class);

/**
 * The diagnostic key is absent here, which is also what proves it is omitted
 * rather than logged as null when a failure has no located syntax problem.
 */
test('conversion telemetry records only bounded operational fields', function () {
    Log::spy();

    app(ConversionTelemetry::class)->record(
        ConversionInterface::Api,
        ConversionOutcome::UnsupportedObject,
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
        ConversionOutcome::InvalidInput,
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

    app(ConversionTelemetry::class)->record(ConversionInterface::Browser, ConversionOutcome::Success, $bytes, hrtime(true));

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

test('a first conversion creates one daily aggregate holding a single occurrence', function () {
    $this->travelTo('2026-09-14 08:15:30');

    app(ConversionTelemetry::class)->record(ConversionInterface::Api, ConversionOutcome::Success, 2048, hrtime(true));

    $this->assertDatabaseCount('conversion_metrics', 1);
    $this->assertDatabaseHas('conversion_metrics', [
        'date' => '2026-09-14',
        'interface' => 'api',
        'outcome' => 'success',
        'count' => 1,
        'last_occurred_at' => '2026-09-14 08:15:30',
    ]);
});

test('a repeated conversion increments the same aggregate and advances its latest occurrence', function () {
    $this->travelTo('2026-09-14 08:15:30');
    app(ConversionTelemetry::class)->record(ConversionInterface::Api, ConversionOutcome::Success, 2048, hrtime(true));

    $this->travelTo('2026-09-14 19:45:00');
    app(ConversionTelemetry::class)->record(ConversionInterface::Api, ConversionOutcome::Success, 4096, hrtime(true));

    $this->assertDatabaseCount('conversion_metrics', 1);
    $this->assertDatabaseHas('conversion_metrics', [
        'count' => 2,
        'last_occurred_at' => '2026-09-14 19:45:00',
    ]);
});

/**
 * Writes can land out of order under concurrency, so the latest occurrence is
 * resolved rather than overwritten. Without that the timestamp could move
 * backwards and report the application as less recently used than it is.
 */
test('an occurrence recorded out of order does not move the latest occurrence backwards', function () {
    $this->travelTo('2026-09-14 19:45:00');
    app(ConversionTelemetry::class)->record(ConversionInterface::Api, ConversionOutcome::Success, 2048, hrtime(true));

    $this->travelTo('2026-09-14 08:15:30');
    app(ConversionTelemetry::class)->record(ConversionInterface::Api, ConversionOutcome::Success, 2048, hrtime(true));

    $this->assertDatabaseHas('conversion_metrics', [
        'count' => 2,
        'last_occurred_at' => '2026-09-14 19:45:00',
    ]);
});

test('a different day, interface, or outcome is counted as a separate aggregate', function () {
    $this->travelTo('2026-09-14 08:15:30');
    app(ConversionTelemetry::class)->record(ConversionInterface::Api, ConversionOutcome::Success, 2048, hrtime(true));

    $this->travelTo('2026-09-15 08:15:30');
    app(ConversionTelemetry::class)->record(ConversionInterface::Api, ConversionOutcome::Success, 2048, hrtime(true));
    app(ConversionTelemetry::class)->record(ConversionInterface::Mcp, ConversionOutcome::Success, 2048, hrtime(true));
    app(ConversionTelemetry::class)->record(ConversionInterface::Api, ConversionOutcome::InvalidInput, 2048, hrtime(true));

    $this->assertDatabaseCount('conversion_metrics', 4);
    expect((int) ConversionMetric::query()->sum('count'))->toBe(4);
});

test('the aggregate key cannot hold two rows for the same day, interface, and outcome', function () {
    ConversionMetric::factory()->on('2026-09-14', ConversionInterface::Api, ConversionOutcome::Success)->create();

    ConversionMetric::factory()->on('2026-09-14', ConversionInterface::Api, ConversionOutcome::Success)->create();
})->throws(QueryException::class);

test('a durable aggregate stores only the allowed fields', function () {
    app(ConversionTelemetry::class)->record(
        ConversionInterface::Browser,
        ConversionOutcome::InvalidInput,
        2048,
        hrtime(true),
        SyntaxErrorCode::StringLengthMismatch,
    );

    expect(array_keys(ConversionMetric::query()->sole()->getAttributes()))->toEqualCanonicalizing([
        'id',
        'date',
        'interface',
        'outcome',
        'count',
        'last_occurred_at',
        'created_at',
        'updated_at',
    ]);
});

/**
 * Metrics are best-effort: the conversion has already produced a result by the
 * time the counter is written, so a failing write must stay a reported problem
 * instead of becoming the caller's error.
 */
test('a failed metrics write is logged without failing the conversion', function () {
    Log::spy();
    Schema::drop('conversion_metrics');

    app(ConversionTelemetry::class)->record(ConversionInterface::Mcp, ConversionOutcome::Success, 2048, hrtime(true));

    Log::shouldHaveReceived('info')->once()->withArgs(
        fn (string $message): bool => $message === 'conversion.completed',
    );
    Log::shouldHaveReceived('warning')->once()->withArgs(function (string $message, array $context): bool {
        expect($message)->toBe('conversion.metrics_write_failed')
            ->and($context)->toBe([
                'interface' => 'mcp',
                'outcome' => 'success',
                'exception' => QueryException::class,
            ]);

        return true;
    });
});
