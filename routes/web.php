<?php

use App\Http\Middleware\AddLegacyOutputNoIndexHeader;
use App\Livewire\Output;
use App\Livewire\Serialized;
use Illuminate\Support\Facades\Route;
use Laravel\Head\Enums\RobotsRule;

/**
 * Build the self-referencing URL a page publishes as its canonical and og:url.
 *
 * The root path keeps its trailing slash so both tags agree on one spelling.
 */
$pageUrl = fn (string $path): string => $path === '/' ? url('/').'/' : url($path);

Route::get('/', Serialized::class)
    ->name('home')
    ->withHead(
        title: 'PHP Unserialize to JSON Converter',
        description: Serialized::META_DESCRIPTION,
        canonical: ['value' => $pageUrl('/'), 'forceHttps' => false],
        og: ['url' => $pageUrl('/')],
    );

Route::view('/privacy', 'privacy')
    ->name('privacy')
    ->withHead(
        title: 'Privacy and Retention',
        description: 'Learn how Unserialize processes PHP serialized data, and protects submitted values.',
        canonical: ['value' => $pageUrl('/privacy'), 'forceHttps' => false],
        og: ['url' => $pageUrl('/privacy')],
    );

Route::view('/developers', 'developers')
    ->name('developers')
    ->withHead(
        title: 'API and MCP Developer Guide',
        description: 'Integrate the stateless PHP serialized-data converter through its versioned JSON API or read-only MCP tool.',
        canonical: ['value' => $pageUrl('/developers'), 'forceHttps' => false],
        og: ['url' => $pageUrl('/developers')],
    );

Route::get('/sitemap.xml', function () {
    return response()
        ->view('sitemap')
        ->header('Content-Type', 'application/xml');
})->name('sitemap');

Route::get('/robots.txt', fn () => response("User-agent: *\nAllow: /\n\nSitemap: https://unserialize.dev/sitemap.xml\n", 200, [
    'Content-Type' => 'text/plain',
]));

Route::get('/openapi.json', fn () => response(
    file_get_contents(public_path('openapi.json')),
    200,
    [
        'Content-Type' => 'application/json',
        'Cache-Control' => 'public, max-age=3600',
    ],
))->name('openapi');

Route::get('/llms.txt', fn () => response(
    file_get_contents(public_path('llms.txt')),
    200,
    [
        'Content-Type' => 'text/plain; charset=UTF-8',
        'Cache-Control' => 'public, max-age=3600',
    ],
))->name('llms');

Route::get('/o', fn () => redirect('/'));
Route::get('/o/{output}', Output::class)
    ->middleware(AddLegacyOutputNoIndexHeader::class)
    ->name('outputs')
    ->withHead(
        title: 'Legacy conversion output',
        description: 'A private legacy conversion output.',
        robots: [RobotsRule::NoIndex, RobotsRule::NoFollow, RobotsRule::NoArchive],
    )
    ->missing(fn () => response('Not Found', 404)->header('X-Robots-Tag', 'noindex, nofollow, noarchive'));
