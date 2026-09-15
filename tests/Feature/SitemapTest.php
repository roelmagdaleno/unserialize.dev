<?php

it('lists only canonical public pages in the XML sitemap', function () {
    $response = $this->get(route('sitemap'));

    $response
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
        ->assertSee(route('home'), false)
        ->assertSee(route('privacy'), false)
        ->assertSee(route('developers'), false)
        ->assertDontSee(route('guides.serialization'), false)
        ->assertDontSee(route('security'), false)
        ->assertDontSee(route('guides.wordpress'), false)
        ->assertDontSee('/o/', false)
        ->assertDontSee('/api/', false)
        ->assertDontSee('/mcp/', false);

    expect(simplexml_load_string($response->getContent()))->not->toBeFalse();
});

it('publishes concise agent discovery with canonical documentation only', function () {
    $this->get(route('llms'))
        ->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->assertHeader('Cache-Control', 'max-age=3600, public')
        ->assertSee('https://unserialize.dev/developers', false)
        ->assertSee('https://unserialize.dev/openapi.json', false)
        ->assertSee('Accept: text/markdown', false)
        ->assertDontSee('/o/', false);
});

it('advertises the sitemap without blocking legacy output crawling', function () {
    $this->get('/robots.txt')
        ->assertSee('User-agent: *')
        ->assertSee('Allow: /')
        ->assertSee('Sitemap: https://unserialize.dev/sitemap.xml')
        ->assertDontSee('Disallow: /o/');
});

it('declares content signal preferences in robots.txt', function () {
    $this->get(route('robots'))
        ->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->assertSee('Content-Signal: ai-train=no, search=yes, ai-input=yes', false)
        ->assertSee('User-agent: *', false)
        ->assertSee('Sitemap: https://unserialize.dev/sitemap.xml', false);
});
