<?php

use App\Livewire\Serialized;
use App\Models\Output;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('gets the serialized component on home page', function () {
    $this->get('/')->assertSeeLivewire(Serialized::class);
});

it('sets the form properties', function () {
    $serializedData = 'a:10:{s:4:"name";s:6:"Chrome";s:7:"version";s:9:"103.0.0.0";s:8:"platform";s:7:"Windows";s:10:"update_url";s:29:"https://www.google.com/chrome";s:7:"img_src";s:44:"https://s.w.org/images/browsers/chrome.png?1";s:11:"img_src_ssl";s:44:"https://s.w.org/images/browsers/chrome.png?1";s:15:"current_version";s:2:"18";s:7:"upgrade";b:0;s:8:"insecure";b:0;s:6:"mobile";b:0;}';

    // `$serializedData` is set in the `SerializedForm` class.
    Livewire::test(Serialized::class)
        ->set('form.serializedData', $serializedData)
        ->assertSet('form.serializedData', $serializedData);

    // `$outputFormat` is set in the `SerializedForm` class.
    Livewire::test(Serialized::class)
        ->set('form.outputFormat', 'json')
        ->assertSet('form.outputFormat', 'json');
});

it('looks the form properties for errors', function () {
    // The `serializedData` property is required.
    Livewire::test(Serialized::class)
        ->set('form.serializedData', '')
        ->call('unserialize')
        ->assertHasErrors('form.serializedData');

    // The `serializedData` property must be a valid-serialized string.
    Livewire::test(Serialized::class)
        ->set('form.serializedData', 'invalid')
        ->call('unserialize')
        ->assertHasErrors('form.serializedData');
});

it('redirects to output component after submit form', function () {
    Livewire::test(Serialized::class)
        ->call('unserialize')
        ->assertRedirect();
});

it('saves the output to the database', function () {
    $serializedData = 'a:1:{s:4:"name";s:6:"Chrome";}';

    Livewire::test(Serialized::class)
        ->set('form.serializedData', $serializedData)
        ->set('form.outputFormat', 'json')
        ->call('unserialize');

    // Get last output from the database.
    $output = Output::latest()->first();

    // Check if the output is saved to the database.
    $this->assertDatabaseCount('outputs', 1);

    expect($output->serialized)->toBe($serializedData)
        ->and($output->id)->toBeUuid();
});
