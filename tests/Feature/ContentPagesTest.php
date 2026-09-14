<?php

use App\Services\Serialized;

it('publishes the complete converter guide on the home page', function () {
    $this->get(route('home'))
        ->assertSee('How to convert PHP serialized data')
        ->assertSee('PHP serialization format')
        ->assertSee('Why I built Unserialize')
        ->assertSee('Working with WordPress serialized data')
        ->assertSee('Security and privacy')
        ->assertSee('allowed_classes')
        ->assertSee('Back up the database before making changes')
        ->assertSee('https://www.php.net/manual/en/function.serialize.php', false)
        ->assertSee('a:2:{s:4:"name";s:6:"Chrome";s:6:"active";b:1;}', false)
        ->assertSee('"active": true', false);
});

it('redirects retired guide pages to their home page sections', function (string $routeName, string $section) {
    $this->get(route($routeName))
        ->assertMovedPermanently()
        ->assertRedirect('/#'.$section);
})->with([
    'serialization guide' => ['guides.serialization', 'format'],
    'WordPress guide' => ['guides.wordpress', 'wordpress'],
    'security guide' => ['security', 'security'],
]);

it('does not link to retired guide pages from the home page', function () {
    $this->get(route('home'))
        ->assertDontSee(route('guides.serialization'), false)
        ->assertDontSee(route('guides.wordpress'), false)
        ->assertDontSee(route('security'), false);
});

it('keeps published examples aligned with the conversion service', function (string $serializedData, mixed $expectedValue) {
    expect((new Serialized($serializedData))->convert()->value)->toBe($expectedValue);
})->with([
    'homepage example' => ['a:2:{s:4:"name";s:6:"Chrome";s:6:"active";b:1;}', ['name' => 'Chrome', 'active' => true]],
    'WordPress example' => ['a:2:{s:10:"show_title";b:1;s:14:"posts_per_page";i:10;}', ['show_title' => true, 'posts_per_page' => 10]],
]);

it('publishes developer instructions for both stateless interfaces', function () {
    $this->get(route('developers'))
        ->assertOk()
        ->assertSee('API and MCP developer guide')
        ->assertSee(url('/api/v1/unserialize'))
        ->assertSee(url('/mcp/unserialize'))
        ->assertSee('convert_php_serialized_data')
        ->assertSee('262,144 input bytes')
        ->assertSee('10 requests per minute')
        ->assertSee(route('openapi'))
        ->assertSee(route('privacy'));
});
