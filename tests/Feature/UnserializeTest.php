<?php

use App\Livewire\Serialized;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

beforeEach(function () {
    RateLimiter::clear('unserialize:'.hash('sha256', '127.0.0.1'));
});

it('renders the converter on the home page', function () {
    $this->get('/')
        ->assertSeeLivewire(Serialized::class)
        ->assertDontSee('Output Format')
        ->assertDontSee('value="array"', false);
});

it('validates required and serialized input with user-visible messages', function (string $serializedData, string $message) {
    Livewire::test(Serialized::class)
        ->set('form.serializedData', $serializedData)
        ->call('unserialize')
        ->assertHasErrors('form.serializedData')
        ->assertSee($message);

    $this->assertDatabaseCount('outputs', 0);
})->with([
    'required input' => ['', 'The serialized data field is required.'],
    'invalid serialized input' => ['invalid', 'Invalid serialized data.'],
]);

it('rejects serialized input larger than 256 KiB before persistence', function () {
    $serializedData = serialize(str_repeat('a', (256 * 1024) + 1));

    Livewire::test(Serialized::class)
        ->set('form.serializedData', $serializedData)
        ->call('unserialize')
        ->assertHasErrors('form.serializedData')
        ->assertSee('The serialized data must not be greater than 262,144 bytes.');

    $this->assertDatabaseCount('outputs', 0);
});

it('displays a valid conversion without persisting it', function () {
    $serializedData = 'a:1:{s:4:"name";s:6:"Chrome";}';

    Livewire::test(Serialized::class)
        ->set('form.serializedData', $serializedData)
        ->call('unserialize')
        ->assertNoRedirect()
        ->assertSee('"name": "Chrome"')
        ->assertSee('This result is not retained by Unserialize.');

    $this->assertDatabaseCount('outputs', 0);
});

it('shows specific feedback for serialized objects without persistence', function () {
    Livewire::test(Serialized::class)
        ->set('form.serializedData', 'O:8:"stdClass":0:{}')
        ->call('unserialize')
        ->assertHasErrors('form.serializedData')
        ->assertSee('Serialized objects are not supported.');

    $this->assertDatabaseCount('outputs', 0);
});

it('blocks the eleventh conversion attempt for an IP address', function () {
    foreach (range(1, 10) as $attempt) {
        Livewire::test(Serialized::class)
            ->set('form.serializedData', 'invalid')
            ->call('unserialize')
            ->assertHasErrors('form.serializedData');
    }

    Livewire::test(Serialized::class)
        ->set('form.serializedData', 'i:1;')
        ->call('unserialize')
        ->assertHasErrors('form.serializedData')
        ->assertSee('Too many conversion attempts. Please try again in 60 seconds.');

    $this->assertDatabaseCount('outputs', 0);
});
