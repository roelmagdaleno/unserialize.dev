<?php

use App\Services\Serialized;

it('publishes a complete converter reference on the home page', function () {
    $this->get(route('home'))
        ->assertSee('How to convert PHP serialized data')
        ->assertSee('Supported PHP values')
        ->assertSee('Objects are rejected')
        ->assertSee('a:2:{s:4:"name";s:6:"Chrome";s:6:"active";b:1;}', false)
        ->assertSee('"active": true', false);
});

it('publishes tested PHP serialization mappings', function () {
    $this->get(route('guides.serialization'))
        ->assertSee('PHP serialization format and JSON mapping')
        ->assertSee('Indexed arrays become JSON arrays')
        ->assertSee('Associative arrays become JSON objects')
        ->assertSee('Serialized objects are not supported')
        ->assertSee('https://www.php.net/manual/en/function.serialize.php', false)
        ->assertSee(route('security'))
        ->assertSee('https://github.com/roelmagdaleno/unserialize', false);
});

it('publishes security guidance with implementation-backed safeguards', function () {
    $this->get(route('security'))
        ->assertSee('Treat serialized data as untrusted input')
        ->assertSee('allowed_classes')
        ->assertSee('262,144 bytes')
        ->assertSee('https://www.php.net/manual/en/function.unserialize.php', false)
        ->assertSee('Report a security issue');
});

it('publishes a safe WordPress inspection workflow', function () {
    $this->get(route('guides.wordpress'))
        ->assertSee('Inspect WordPress options and metadata safely')
        ->assertSee('Back up the database before making changes')
        ->assertSee('Redact secrets')
        ->assertSee('wp option get')
        ->assertSee('a:2:{s:10:"show_title";b:1;s:14:"posts_per_page";i:10;}', false);
});

it('keeps published examples aligned with the conversion service', function (string $serializedData, mixed $expectedValue) {
    expect((new Serialized($serializedData))->convert()->value)->toBe($expectedValue);
})->with([
    'homepage example' => ['a:2:{s:4:"name";s:6:"Chrome";s:6:"active";b:1;}', ['name' => 'Chrome', 'active' => true]],
    'WordPress example' => ['a:2:{s:10:"show_title";b:1;s:14:"posts_per_page";i:10;}', ['show_title' => true, 'posts_per_page' => 10]],
]);
