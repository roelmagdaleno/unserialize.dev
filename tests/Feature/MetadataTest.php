<?php

use App\Models\Output;

it('renders unique self-canonical metadata on every indexable page', function (string $routeName, string $path, string $title, string $description) {
    $url = $path === '/' ? url('/').'/' : url($path);

    $this->get(route($routeName))
        ->assertSee("<title>{$title}</title>", false)
        ->assertSee('<meta name="description" content="'.e($description).'">', false)
        ->assertSee('<link rel="canonical" href="'.$url.'">', false)
        ->assertSee('<meta name="robots" content="all">', false)
        ->assertSee('<meta property="og:url" content="'.$url.'">', false)
        ->assertSee('<meta property="og:title" content="'.e($title).'">', false)
        ->assertSee('<meta property="og:description" content="'.e($description).'">', false)
        ->assertSee('<meta name="twitter:card" content="summary_large_image">', false)
        ->assertSee('<meta property="og:image" content="'.asset('images/social.png').'">', false);
})->with([
    'home' => ['home', '/', 'PHP Unserialize to JSON Converter | Unserialize', 'Convert PHP serialized data to readable JSON without storing your input. Includes tested mappings, limits, and object-safety guidance.'],
    'privacy' => ['privacy', '/privacy', 'Privacy and Retention | Unserialize', 'Learn how Unserialize processes PHP serialized data and protects submitted values.'],
    'developer guide' => ['developers', '/developers', 'API and MCP Developer Guide | Unserialize', 'Integrate the stateless PHP serialized-data converter through its versioned JSON API or read-only MCP tool.'],
]);

it('publishes accurate application structured data on the home page', function () {
    $this->get(route('home'))
        ->assertSee('"@type":"WebApplication"', false)
        ->assertSee('"applicationCategory":"DeveloperApplication"', false)
        ->assertSee('"url":"'.url('/').'/"', false)
        ->assertSee('"offers":{"@type":"Offer","price":0,"priceCurrency":"USD"}', false);
});

it('keeps legacy output metadata private and non-canonical', function () {
    $output = Output::factory()->create();

    $this->get(route('outputs', $output))
        ->assertSee('<title>Legacy conversion output | Unserialize</title>', false)
        ->assertSee('<meta name="robots" content="noindex, nofollow, noarchive">', false)
        ->assertDontSee('<link rel="canonical"', false)
        ->assertDontSee('<meta property="og:url"', false)
        ->assertDontSee('"@type":"WebApplication"', false);
});
