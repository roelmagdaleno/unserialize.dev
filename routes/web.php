<?php

use App\Http\Middleware\AddDiscoveryLinkHeaders;
use App\Http\Middleware\AddLegacyOutputNoIndexHeader;
use App\Http\Middleware\NegotiateMarkdownRepresentation;
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
    ->middleware([
        AddDiscoveryLinkHeaders::class,
        NegotiateMarkdownRepresentation::class.':markdown.home',
    ])
    ->withHead(
        title: ['value' => 'Unserialize Online – PHP Unserialize to JSON Converter', 'exact' => true],
        description: Serialized::META_DESCRIPTION,
        canonical: ['value' => $pageUrl('/'), 'forceHttps' => false],
        og: ['url' => $pageUrl('/'), 'description' => Serialized::SOCIAL_DESCRIPTION],
        twitter: ['description' => Serialized::SOCIAL_DESCRIPTION],
    );

Route::view('/privacy', 'privacy')
    ->name('privacy')
    ->middleware(NegotiateMarkdownRepresentation::class.':markdown.privacy')
    ->withHead(
        title: 'Privacy and Retention',
        description: 'Learn how Unserialize processes PHP serialized data and protects submitted values.',
        canonical: ['value' => $pageUrl('/privacy'), 'forceHttps' => false],
        og: ['url' => $pageUrl('/privacy')],
    );

Route::view('/developers', 'developers')
    ->name('developers')
    ->middleware(NegotiateMarkdownRepresentation::class.':markdown.developers')
    ->withHead(
        title: 'API and MCP Developer Guide',
        description: 'Integrate the stateless PHP serialized-data converter through its versioned JSON API or read-only MCP tool.',
        canonical: ['value' => $pageUrl('/developers'), 'forceHttps' => false],
        og: ['url' => $pageUrl('/developers')],
    );

/**
 * Build the `Article` structured data a guide publishes.
 *
 * The author is referenced by the `@id` the home page publishes, not repeated.
 *
 * @return array<string, mixed>
 */
$guideSchema = fn (string $path, string $headline, string $description, string $publishedAt, ?string $modifiedAt = null): array => [
    '@context' => 'https://schema.org',
    '@type' => 'TechArticle',
    'headline' => $headline,
    'description' => $description,
    'url' => $pageUrl($path),
    'mainEntityOfPage' => $pageUrl($path),
    'author' => ['@id' => url('/').'/#author'],
    'datePublished' => $publishedAt,
    'dateModified' => $modifiedAt ?? $publishedAt,
];

Route::view('/guides/wordpress-serialized-data', 'guides.wordpress-serialized-data')
    ->name('guides.wordpress-serialized-data')
    ->middleware(NegotiateMarkdownRepresentation::class.':markdown.guides.wordpress-serialized-data')
    ->withHead(
        title: ['value' => 'WordPress Serialized Data: Read, Convert and Edit Safely', 'exact' => true],
        description: 'Find where WordPress stores serialized PHP, read it as a PHP array or JSON, and edit options and post meta without breaking string lengths.',
        canonical: ['value' => $pageUrl('/guides/wordpress-serialized-data'), 'forceHttps' => false],
        og: ['url' => $pageUrl('/guides/wordpress-serialized-data'), 'type' => 'article'],
        schema: [$guideSchema(
            '/guides/wordpress-serialized-data',
            'WordPress serialized data: read, convert and edit it safely',
            'Find where WordPress stores serialized PHP, read it as a PHP array or JSON, and edit options and post meta without breaking string lengths.',
            '2026-10-05',
        )],
    );

Route::view('/guides/fix-broken-serialized-string', 'guides.fix-broken-serialized-string')
    ->name('guides.broken-serialized-string')
    ->middleware(NegotiateMarkdownRepresentation::class.':markdown.guides.fix-broken-serialized-string')
    ->withHead(
        title: ['value' => 'Fix a Broken PHP Serialized String (Error at Offset)', 'exact' => true],
        description: 'Repair a PHP serialized string that fails with an error at offset: wrong lengths after a search and replace, multibyte characters, and truncated values.',
        canonical: ['value' => $pageUrl('/guides/fix-broken-serialized-string'), 'forceHttps' => false],
        og: ['url' => $pageUrl('/guides/fix-broken-serialized-string'), 'type' => 'article'],
        schema: [$guideSchema(
            '/guides/fix-broken-serialized-string',
            'Fix a broken PHP serialized string',
            'Repair a PHP serialized string that fails with an error at offset: wrong lengths after a search and replace, multibyte characters, and truncated values.',
            '2026-10-05',
        )],
    );

/**
 * The WordPress guide that the home page absorbed and later split out again
 * keeps its old address working.
 */
Route::permanentRedirect('/guides/wordpress', '/guides/wordpress-serialized-data');

Route::get('/sitemap.xml', function () {
    return response()
        ->view('sitemap')
        ->header('Content-Type', 'application/xml; charset=UTF-8');
})->name('sitemap');

Route::get('/robots.txt', fn () => response(
    file_get_contents(public_path('robots.txt')),
    200,
    [
        'Content-Type' => 'text/plain',
    ],
))->name('robots');

Route::get('/openapi.json', fn () => response(
    file_get_contents(public_path('openapi.json')),
    200,
    [
        'Content-Type' => 'application/json',
        'Cache-Control' => 'public, max-age=3600',
    ],
))->name('openapi');

/**
 * Publish the RFC 9727 API catalog so agents can discover both machine interfaces.
 *
 * @see https://www.rfc-editor.org/rfc/rfc9727
 */
Route::get('/.well-known/api-catalog', function () {
    $linkset = [
        'linkset' => [
            [
                'anchor' => route('api.v1.unserialize'),
                'service-desc' => [
                    ['href' => route('openapi'), 'type' => 'application/openapi+json'],
                ],
                'service-doc' => [
                    ['href' => route('developers'), 'type' => 'text/html'],
                ],
                'status' => [
                    ['href' => url('/up'), 'type' => 'text/html'],
                ],
            ],
            [
                'anchor' => route('mcp.unserialize'),
                'service-doc' => [
                    ['href' => route('developers'), 'type' => 'text/html'],
                ],
                'status' => [
                    ['href' => url('/up'), 'type' => 'text/html'],
                ],
            ],
        ],
    ];

    return response()->json($linkset, 200, [
        'Content-Type' => 'application/linkset+json',
        'Cache-Control' => 'public, max-age=3600',
    ], JSON_UNESCAPED_SLASHES);
})->name('api-catalog');

Route::get('/llms.txt', fn () => response(
    file_get_contents(resource_path('llms.txt')),
    200,
    [
        'Content-Type' => 'text/markdown; charset=UTF-8',
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
