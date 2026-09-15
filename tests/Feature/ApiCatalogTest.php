<?php

it('publishes an RFC 9727 API catalog for both machine interfaces', function () {
    $response = $this->get('/.well-known/api-catalog');

    $response
        ->assertOk()
        ->assertHeader('Content-Type', 'application/linkset+json')
        ->assertHeader('Cache-Control', 'max-age=3600, public');

    $linkset = $response->json('linkset');

    expect($linkset)->toHaveCount(2);

    expect($linkset[0])
        ->toMatchArray(['anchor' => route('api.v1.unserialize')])
        ->and($linkset[0]['service-desc'][0]['href'])->toBe(route('openapi'))
        ->and($linkset[0]['service-doc'][0]['href'])->toBe(route('developers'))
        ->and($linkset[0]['status'][0]['href'])->toBe(url('/up'));

    expect($linkset[1])
        ->toMatchArray(['anchor' => route('mcp.unserialize')])
        ->and($linkset[1]['service-doc'][0]['href'])->toBe(route('developers'))
        ->and($linkset[1]['status'][0]['href'])->toBe(url('/up'));
});

it('keeps the catalog reachable without leaking private conversion paths', function () {
    $this->get('/.well-known/api-catalog')
        ->assertOk()
        ->assertDontSee('/o/', false);
});
