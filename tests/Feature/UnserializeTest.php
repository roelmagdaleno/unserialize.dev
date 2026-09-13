<?php

use App\Livewire\Serialized;
use App\Models\Output;
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
    'valid serialized input' => ['invalid', 'The data is not valid serialized data.'],
]);

it('rejects serialized input larger than 256 KiB before persistence', function () {
    $serializedData = serialize(str_repeat('a', (256 * 1024) + 1));

    Livewire::test(Serialized::class)
        ->set('form.serializedData', $serializedData)
        ->call('unserialize')
        ->assertHasErrors(['form.serializedData' => 'max'])
        ->assertSee('The serialized data field must not be greater than 262144 characters.');

    $this->assertDatabaseCount('outputs', 0);
});

it('saves a valid conversion and redirects to its named output route', function () {
    $serializedData = 'a:1:{s:4:"name";s:6:"Chrome";}';

    $component = Livewire::test(Serialized::class)
        ->set('form.serializedData', $serializedData)
        ->call('unserialize');
    $output = Output::sole();

    $component->assertRedirectToRoute('outputs', $output);
    $this->assertDatabaseHas('outputs', [
        'id' => $output->id,
        'serialized' => $serializedData,
        'unserialized' => "{\n    \"name\": \"Chrome\"\n}",
        'output_format' => 'json',
    ]);
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
