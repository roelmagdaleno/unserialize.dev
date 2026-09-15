<?php

use App\Models\Output;

it('returns markdown when a client accepts it', function (string $routeName, string $heading) {
    $response = $this->get(route($routeName), ['Accept' => 'text/markdown']);

    $response->assertOk()
        ->assertHeader('Content-Type', 'text/markdown; charset=UTF-8')
        ->assertSee($heading, false)
        ->assertDontSee('<!DOCTYPE html>', false);

    expect($response->headers->get('Vary'))->toContain('Accept');
    expect((int) $response->headers->get('X-Markdown-Tokens'))->toBeGreaterThan(0);
})->with([
    'home' => ['home', '# Unserialize — PHP Unserialize to JSON Converter'],
    'privacy' => ['privacy', '# Privacy and retention'],
    'developer guide' => ['developers', '# API and MCP developer guide'],
]);

it('keeps html the default for browsers and unspecific clients', function (string $accept) {
    $response = $this->get(route('home'), ['Accept' => $accept]);

    $response->assertOk()
        ->assertSee('<!DOCTYPE html>', false)
        ->assertHeaderMissing('X-Markdown-Tokens');

    expect($response->headers->get('Content-Type'))->toContain('text/html');
    expect($response->headers->get('Vary'))->toContain('Accept');
})->with([
    'browser' => ['text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8'],
    'wildcard only' => ['*/*'],
    'text wildcard' => ['text/*'],
    'no preference' => [''],
    'markdown refused' => ['text/markdown;q=0'],
    'html preferred over markdown' => ['text/html,text/markdown;q=0.5'],
]);

it('serves markdown when it ranks at or above html', function (string $accept) {
    $this->get(route('home'), ['Accept' => $accept])
        ->assertOk()
        ->assertHeader('Content-Type', 'text/markdown; charset=UTF-8');
})->with([
    'markdown only' => ['text/markdown'],
    'markdown listed alongside html' => ['text/markdown,text/html'],
    'markdown outranks html' => ['text/html;q=0.5,text/markdown'],
]);

it('keeps the markdown representations aligned with the published pages', function () {
    $this->get(route('home'), ['Accept' => 'text/markdown'])
        ->assertSee('How to convert PHP serialized data')
        ->assertSee('Why I built Unserialize')
        ->assertSee('Working with WordPress serialized data')
        ->assertSee('allowed_classes')
        ->assertSee('Back up the database before making changes')
        ->assertSee('a:2:{s:4:"name";s:6:"Chrome";s:6:"active";b:1;}', false)
        ->assertSee(route('privacy'), false);

    $this->get(route('developers'), ['Accept' => 'text/markdown'])
        ->assertSee(url('/api/v1/unserialize'), false)
        ->assertSee(url('/mcp/unserialize'), false)
        ->assertSee('convert_php_serialized_data')
        ->assertSee('262,144 input bytes')
        ->assertSee('10 requests per minute');

    $this->get(route('privacy'), ['Accept' => 'text/markdown'])
        ->assertSee('Technical usage metadata')
        ->assertSee(config('telemetry.retention_days').' days');
});

it('does not negotiate markdown for private legacy output pages', function () {
    $output = Output::factory()->create();

    $this->get(route('outputs', $output), ['Accept' => 'text/markdown'])
        ->assertOk()
        ->assertSee('<!DOCTYPE html>', false);
});

it('leaves markdown free of html escaping artifacts', function (string $routeName) {
    $body = $this->get(route($routeName), ['Accept' => 'text/markdown'])->getContent();

    expect($body)->not->toContain('&quot;')
        ->and($body)->not->toContain('&#039;')
        ->and($body)->not->toContain('&amp;');
})->with(['home', 'privacy', 'developers']);
