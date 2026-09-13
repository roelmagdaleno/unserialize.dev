<?php

use App\Models\Output;

it('renders unique self-canonical metadata on every indexable page', function (string $routeName, string $title, string $description) {
    $url = route($routeName);

    $this->get($url)
        ->assertSee("<title>{$title}</title>", false)
        ->assertSee('<meta name="description" content="'.e($description).'">', false)
        ->assertSee('<link rel="canonical" href="'.$url.'">', false)
        ->assertSee('<meta property="og:url" content="'.$url.'">', false);
})->with([
    'home' => ['home', 'PHP Unserialize to JSON Converter | Unserialize', 'Convert PHP serialized data to readable JSON without storing your input. Includes tested mappings, limits, and object-safety guidance.'],
    'privacy' => ['privacy', 'Privacy and Retention | Unserialize', 'Learn how Unserialize processes PHP serialized data, protects submitted values, and handles legacy output links.'],
    'serialization guide' => ['guides.serialization', 'PHP Serialization Format Guide | Unserialize', 'A tested reference for PHP serialized syntax and its mapping to JSON values.'],
    'security' => ['security', 'PHP Unserialize Security Guide | Unserialize', 'Safely inspect untrusted PHP serialized data with object rejection, size limits, and private processing.'],
    'WordPress guide' => ['guides.wordpress', 'WordPress Serialized Data Guide | Unserialize', 'A practical, cautious workflow for inspecting serialized WordPress options and metadata.'],
]);

it('publishes accurate application structured data on the home page', function () {
    $this->get(route('home'))
        ->assertSee('"@type": "WebApplication"', false)
        ->assertSee('"applicationCategory": "DeveloperApplication"', false)
        ->assertSee('"price": 0', false);
});

it('keeps legacy output metadata private and non-canonical', function () {
    $output = Output::factory()->create();

    $this->get(route('outputs', $output))
        ->assertSee('<title>Legacy conversion output | Unserialize</title>', false)
        ->assertSee('<meta name="robots" content="noindex, nofollow, noarchive">', false)
        ->assertDontSee('<link rel="canonical"', false)
        ->assertDontSee('"@type": "WebApplication"', false);
});
