<?php

use Illuminate\Support\Facades\Exceptions;
use Illuminate\Testing\TestResponse;
use Livewire\Exceptions\MethodNotFoundException;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Livewire\Mechanisms\HandleComponents\CorruptComponentPayloadException;

beforeEach(function () {
    config(['app.debug' => false]);
    Exceptions::fake();
});

/**
 * Read the converter's snapshot from the home page, as a browser would.
 */
function converterSnapshot(): string
{
    $html = test()->get('/')->assertOk()->getContent();

    preg_match('/wire:snapshot="([^"]+)"/', $html, $matches);

    return html_entity_decode($matches[1], ENT_QUOTES);
}

/**
 * Send one component update to the Livewire endpoint.
 *
 * @param  array<string, mixed>  $updates
 * @param  list<array{path: string, method: string, params: list<mixed>}>  $calls
 */
function postLivewireUpdate(string $snapshot, array $updates = [], array $calls = []): TestResponse
{
    return test()->postJson(Livewire::getUpdateUri(), [
        'components' => [[
            'snapshot' => $snapshot,
            'updates' => (object) $updates,
            'calls' => $calls,
        ]],
    ], ['X-Livewire' => 'true']);
}

it('answers 400 without reporting a call to a method the converter does not have', function () {
    postLivewireUpdate(converterSnapshot(), calls: [['path' => '', 'method' => '_xNonExist', 'params' => []]])
        ->assertBadRequest()
        ->assertExactJson(['message' => 'Bad Request']);

    Exceptions::assertNotReported(MethodNotFoundException::class);
});

it('answers 400 without reporting an update to the locked diagnostic', function () {
    postLivewireUpdate(converterSnapshot(), updates: ['diagnostic' => ['message' => 'Tampered']])
        ->assertBadRequest()
        ->assertExactJson(['message' => 'Bad Request']);

    Exceptions::assertNotReported(CannotUpdateLockedPropertyException::class);
});

it('answers 400 without reporting a snapshot whose checksum does not match', function () {
    $snapshot = json_decode(converterSnapshot(), true);
    $snapshot['data']['result'] = 'Tampered';

    postLivewireUpdate(json_encode($snapshot))
        ->assertBadRequest()
        ->assertExactJson(['message' => 'Bad Request']);

    Exceptions::assertNotReported(CorruptComponentPayloadException::class);
});
