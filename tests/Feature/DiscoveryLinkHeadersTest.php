<?php

it('advertises the machine-readable resources in a Link header on the homepage', function () {
    $link = $this->get('/')->assertOk()->headers->get('Link');

    expect($link)
        ->toContain('</.well-known/api-catalog>; rel="api-catalog"; type="application/linkset+json"')
        ->toContain('</openapi.json>; rel="service-desc"; type="application/openapi+json"')
        ->toContain('</developers>; rel="service-doc"; type="text/html"')
        ->toContain('</llms.txt>; rel="describedby"; type="text/markdown"');
});

it('keeps the Link header on the negotiated Markdown representation', function () {
    $response = $this->withHeaders(['Accept' => 'text/markdown'])->get('/');

    $response
        ->assertOk()
        ->assertHeader('Content-Type', 'text/markdown; charset=UTF-8');

    expect($response->headers->get('Link'))->toContain('rel="api-catalog"');
});
