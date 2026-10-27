<?php

use App\Enums\ConversionInterface;
use App\Enums\ConversionOutcome;
use App\Models\ConversionMetric;
use App\Models\UsageEvent;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;

beforeEach(function () {
    $this->travelTo('2026-09-15 12:00:00');
});

it('deletes an event one second past the retention boundary', function () {
    UsageEvent::factory()->occurredAt('2026-08-16 11:59:59')->create();

    $this->artisan('model:prune', ['--model' => [UsageEvent::class]])->assertSuccessful();

    $this->assertDatabaseCount('usage_events', 0);
});

it('keeps an event exactly at the retention boundary', function () {
    UsageEvent::factory()->occurredAt('2026-08-16 12:00:00')->create();

    $this->artisan('model:prune', ['--model' => [UsageEvent::class]])->assertSuccessful();

    $this->assertDatabaseCount('usage_events', 1);
});

it('keeps an event inside the retention window', function () {
    UsageEvent::factory()->occurredAt('2026-09-15 11:59:00')->create();

    $this->artisan('model:prune', ['--model' => [UsageEvent::class]])->assertSuccessful();

    $this->assertDatabaseCount('usage_events', 1);
});

it('follows the configured retention window rather than a fixed one', function () {
    config()->set('telemetry.retention_days', 7);
    UsageEvent::factory()->occurredAt('2026-09-01 12:00:00')->create();

    $this->artisan('model:prune', ['--model' => [UsageEvent::class]])->assertSuccessful();

    $this->assertDatabaseCount('usage_events', 0);
});

it('deletes nothing more on a second run and never touches conversion aggregates', function () {
    UsageEvent::factory()->occurredAt('2026-08-01 12:00:00')->create();
    UsageEvent::factory()->occurredAt('2026-09-15 11:00:00')->create();
    ConversionMetric::factory()->on('2026-08-01', ConversionInterface::Browser, ConversionOutcome::Success)->counted(5)->create();

    $this->artisan('model:prune', ['--model' => [UsageEvent::class]])->assertSuccessful();
    $this->artisan('model:prune', ['--model' => [UsageEvent::class]])->assertSuccessful();

    $this->assertDatabaseCount('usage_events', 1);
    $this->assertDatabaseHas('conversion_metrics', ['date' => '2026-08-01', 'count' => 5]);
});

test('a daily prune limited to usage events is scheduled', function () {
    $commands = collect(app(Schedule::class)->events())
        ->map(fn (Event $event): string => $event->command ?? '')
        ->filter(fn (string $command): bool => str_contains($command, 'model:prune'));

    expect($commands)->toHaveCount(1)
        ->and($commands->sole())->toContain(UsageEvent::class)
        ->and(collect(app(Schedule::class)->events())
            ->first(fn (Event $event): bool => str_contains((string) $event->command, 'model:prune'))
            ->expression)->toBe('0 0 * * *');
});
