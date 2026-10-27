<?php

use App\Enums\InputTrait;
use App\Livewire\Serialized;
use App\Models\ErrorReport;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

beforeEach(function () {
    RateLimiter::clear('unserialize:'.hash('sha256', '127.0.0.1'));
    RateLimiter::clear('error-report:'.hash('sha256', '127.0.0.1'));
});

function failedConversion(string $serializedData = 'a:1:{s:4:"names";N;}')
{
    return Livewire::test(Serialized::class)
        ->set('form.serializedData', $serializedData)
        ->call('unserialize')
        ->assertSet('canReport', true);
}

it('offers a report only after a failed conversion', function () {
    Livewire::test(Serialized::class)
        ->set('form.serializedData', 'a:1:{s:4:"name";s:6:"Chrome";}')
        ->call('unserialize')
        ->assertSet('canReport', false)
        ->assertDontSee('Send this value to the developer');

    failedConversion()->assertSee('Send this value to the developer');
});

it('stores nothing without consent', function () {
    failedConversion()
        ->call('reportFailure')
        ->assertHasErrors(['reportConsent' => 'accepted']);

    $this->assertDatabaseCount('error_reports', 0);
});

it('stores the failed value, its category, traits and note with consent', function () {
    Log::spy();

    failedConversion("'a:1:{s:4:\"names\";N;}'")
        ->set('reportConsent', true)
        ->set('reportNote', '  From wp_options  ')
        ->call('reportFailure')
        ->assertHasNoErrors()
        ->assertSet('canReport', false)
        ->assertSee('Report sent. It will be deleted after 30 days.');

    $report = ErrorReport::query()->sole();

    expect($report->input)->toBe("'a:1:{s:4:\"names\";N;}'")
        ->and($report->outcome)->toBe('invalid_input')
        ->and($report->diagnostic)->not->toBeNull()
        ->and($report->input_traits->all())->toBe([InputTrait::WrappedInQuotes])
        ->and($report->note)->toBe('From wp_options')
        ->and(array_keys($report->getAttributes()))->toEqualCanonicalizing([
            'id', 'reported_at', 'outcome', 'diagnostic', 'input_traits', 'input', 'note',
        ]);

    Log::shouldHaveReceived('warning')->once()->withArgs(function (string $message, array $context) use ($report): bool {
        expect($message)->toBe('error_report.received')
            ->and($context)->toBe(['id' => $report->id, 'outcome' => 'invalid_input', 'diagnostic' => $report->diagnostic])
            ->and(json_encode($context))->not->toContain('names');

        return true;
    });
});

it('does not store a value that converts when it is reported', function () {
    failedConversion()
        ->set('form.serializedData', 'a:1:{s:4:"name";s:6:"Chrome";}')
        ->set('reportConsent', true)
        ->call('reportFailure')
        ->assertSet('canReport', false)
        ->assertSee('This value converts now, so there is nothing to report.');

    $this->assertDatabaseCount('error_reports', 0);
});

it('refuses a report the page never offered', function () {
    Livewire::test(Serialized::class)
        ->set('reportConsent', true)
        ->call('reportFailure');

    $this->assertDatabaseCount('error_reports', 0);
});

it('limits one client to three reports an hour', function () {
    foreach (range(1, 3) as $ignored) {
        failedConversion()->set('reportConsent', true)->call('reportFailure')->assertSee('Report sent.');
    }

    failedConversion()
        ->set('reportConsent', true)
        ->call('reportFailure')
        ->assertSee('Too many reports. Please try again later.');

    $this->assertDatabaseCount('error_reports', 3);
});

it('rejects a note longer than 500 characters', function () {
    failedConversion()
        ->set('reportConsent', true)
        ->set('reportNote', str_repeat('a', 501))
        ->call('reportFailure')
        ->assertHasErrors(['reportNote' => 'max']);
});

it('prunes reports once the retention window passes', function () {
    $this->travelTo('2026-10-27 12:00:00');
    ErrorReport::factory()->create(['reported_at' => '2026-09-26 11:59:00']);
    ErrorReport::factory()->create(['reported_at' => '2026-09-27 12:01:00']);

    $this->artisan('model:prune', ['--model' => [ErrorReport::class]])->assertSuccessful();

    expect(ErrorReport::query()->sole()->reported_at->toDateTimeString())->toBe('2026-09-27 12:01:00');
});
