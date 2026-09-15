<?php

use App\Enums\ConversionInterface;
use App\Models\ConversionMetric;
use Illuminate\Support\Facades\Artisan;

/**
 * Three aggregates across two days, two interfaces, and two outcomes: enough
 * for every filter to have something it must keep and something it must drop.
 */
function seedAggregates(): void
{
    ConversionMetric::factory()->on('2026-09-14', ConversionInterface::Browser, 'success')->counted(12)->create();
    ConversionMetric::factory()->on('2026-09-15', ConversionInterface::Api, 'success')->counted(3)->create();
    ConversionMetric::factory()->on('2026-09-15', ConversionInterface::Api, 'invalid_input')->counted(2)->create();
}

it('reports every recorded aggregate when no filter is given', function () {
    seedAggregates();

    $this->artisan('usage:summary')
        ->expectsTable(['Date (UTC)', 'Interface', 'Outcome', 'Count', 'Last Occurred At (UTC)'], [
            ['2026-09-14', 'browser', 'success', 12, '2026-09-14 12:00:00'],
            ['2026-09-15', 'api', 'invalid_input', 2, '2026-09-15 12:00:00'],
            ['2026-09-15', 'api', 'success', 3, '2026-09-15 12:00:00'],
        ])
        ->assertSuccessful();
});

it('keeps the aggregates on both boundary days of a date range', function () {
    seedAggregates();
    ConversionMetric::factory()->on('2026-09-16', ConversionInterface::Mcp, 'success')->create();
    ConversionMetric::factory()->on('2026-09-13', ConversionInterface::Mcp, 'success')->create();

    $this->artisan('usage:summary', ['--from' => '2026-09-14', '--to' => '2026-09-15'])
        ->expectsTable(['Date (UTC)', 'Interface', 'Outcome', 'Count', 'Last Occurred At (UTC)'], [
            ['2026-09-14', 'browser', 'success', 12, '2026-09-14 12:00:00'],
            ['2026-09-15', 'api', 'invalid_input', 2, '2026-09-15 12:00:00'],
            ['2026-09-15', 'api', 'success', 3, '2026-09-15 12:00:00'],
        ])
        ->assertSuccessful();
});

it('reports only the requested interface', function () {
    seedAggregates();

    $this->artisan('usage:summary', ['--interface' => 'browser'])
        ->expectsTable(['Date (UTC)', 'Interface', 'Outcome', 'Count', 'Last Occurred At (UTC)'], [
            ['2026-09-14', 'browser', 'success', 12, '2026-09-14 12:00:00'],
        ])
        ->assertSuccessful();
});

it('reports only the requested outcome', function () {
    seedAggregates();

    $this->artisan('usage:summary', ['--outcome' => 'invalid_input'])
        ->expectsTable(['Date (UTC)', 'Interface', 'Outcome', 'Count', 'Last Occurred At (UTC)'], [
            ['2026-09-15', 'api', 'invalid_input', 2, '2026-09-15 12:00:00'],
        ])
        ->assertSuccessful();
});

it('reports the latest successful use overall and for each interface', function () {
    seedAggregates();

    $this->artisan('usage:summary')
        ->expectsOutputToContain('Latest successful use')
        ->expectsOutputToContain('2026-09-15 12:00:00')
        ->expectsOutputToContain('2026-09-14 12:00:00')
        ->expectsOutputToContain('never')
        ->assertSuccessful();
});

/**
 * The latest successful use answers whether the application still works at
 * all, so it reads the whole history rather than the report's date range.
 */
it('reports the latest successful use from outside the requested date range', function () {
    ConversionMetric::factory()->on('2026-09-14', ConversionInterface::Api, 'success')->create();

    $summary = jsonSummary(['--from' => '2026-09-20', '--to' => '2026-09-21']);

    expect($summary['metrics'])->toBe([])
        ->and($summary['latest_successful_use']['overall'])->toBe('2026-09-14 12:00:00')
        ->and($summary['latest_successful_use']['by_interface'])->toBe([
            'api' => '2026-09-14 12:00:00',
            'browser' => null,
            'mcp' => null,
        ]);
});

it('prints the same aggregate values as JSON', function () {
    seedAggregates();

    $summary = jsonSummary();

    expect($summary['metrics'])->toBe([
        ['date' => '2026-09-14', 'interface' => 'browser', 'outcome' => 'success', 'count' => 12, 'last_occurred_at' => '2026-09-14 12:00:00'],
        ['date' => '2026-09-15', 'interface' => 'api', 'outcome' => 'invalid_input', 'count' => 2, 'last_occurred_at' => '2026-09-15 12:00:00'],
        ['date' => '2026-09-15', 'interface' => 'api', 'outcome' => 'success', 'count' => 3, 'last_occurred_at' => '2026-09-15 12:00:00'],
    ])
        ->and($summary['latest_successful_use'])->toBe([
            'overall' => '2026-09-15 12:00:00',
            'by_interface' => [
                'api' => '2026-09-15 12:00:00',
                'browser' => '2026-09-14 12:00:00',
                'mcp' => null,
            ],
        ]);
});

it('succeeds and reports nothing when no metrics have been recorded', function () {
    $this->artisan('usage:summary')
        ->expectsOutputToContain('never')
        ->expectsOutputToContain('No conversion metrics match the given filters.')
        ->assertSuccessful();

    expect(jsonSummary()['metrics'])->toBe([]);
});

it('fails with a message when a filter is not usable', function (array $options, string $message) {
    seedAggregates();

    $this->artisan('usage:summary', $options)
        ->expectsOutputToContain($message)
        ->assertFailed();
})->with([
    'unparsable date' => [['--from' => 'last tuesday'], 'The from field must match the format Y-m-d.'],
    'inverted range' => [
        ['--from' => '2026-09-15', '--to' => '2026-09-14'],
        'The to field must be a date after or equal to from.',
    ],
    'unknown interface' => [['--interface' => 'cli'], 'The selected interface is invalid.'],
    'unknown outcome' => [['--outcome' => 'exploded'], 'The selected outcome is invalid.'],
]);

/**
 * @param  array<string, string>  $options
 * @return array<string, mixed>
 */
function jsonSummary(array $options = []): array
{
    expect(Artisan::call('usage:summary', [...$options, '--json' => true]))->toBe(0);

    return json_decode(Artisan::output(), associative: true);
}
