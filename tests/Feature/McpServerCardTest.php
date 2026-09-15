<?php

use App\Mcp\ServerCard;
use App\Mcp\Servers\UnserializeServer;
use Laravel\Mcp\Enums\ProtocolVersion;

test('the server card is published at the well-known discovery path', function () {
    $response = $this->get('/.well-known/mcp/server-card.json');

    $response
        ->assertOk()
        ->assertHeader('Content-Type', ServerCard::MEDIA_TYPE)
        ->assertHeader('Cache-Control', 'max-age=3600, public');

    expect($response->json())
        ->toHaveKey('$schema', ServerCard::SCHEMA)
        ->toHaveKey('name', ServerCard::IDENTIFIER)
        ->toHaveKey('version', UnserializeServer::VERSION)
        ->and($response->json('description'))->not->toBeEmpty();
});

test('the same card is served at the spec-recommended location beside the transport', function () {
    $wellKnown = $this->get('/.well-known/mcp/server-card.json')->assertOk();
    $besideTransport = $this->get('/mcp/unserialize/server-card')->assertOk();

    expect($besideTransport->json())->toEqual($wellKnown->json());
});

test('the card points an agent at the streamable http endpoint it can connect to', function () {
    $card = $this->get('/.well-known/mcp/server-card.json')->assertOk()->json();

    expect($card['remotes'])->toHaveCount(1)
        ->and($card['remotes'][0])
        ->toMatchArray([
            'type' => 'streamable-http',
            'url' => route('mcp.unserialize'),
        ])
        ->and($card['remotes'][0]['supportedProtocolVersions'])
        ->toEqualCanonicalizing(ProtocolVersion::supported())
        ->and($card['transport'])->toMatchArray([
            'type' => 'streamable-http',
            'endpoint' => route('mcp.unserialize'),
        ]);
});

test('the card advertises only the primitive kinds the server actually registers', function () {
    $capabilities = $this->get('/.well-known/mcp/server-card.json')->assertOk()->json('capabilities');

    expect($capabilities)->toHaveKey('tools')
        ->and($capabilities['tools'])->toMatchArray(['listChanged' => false])
        ->and($capabilities)->not->toHaveKey('resources')
        ->and($capabilities)->not->toHaveKey('prompts');
});

/**
 * The card is read before a client connects, so it must not contradict the
 * values the server reports once it does.
 */
test('the card identity matches the live initialization handshake', function () {
    $card = $this->get('/.well-known/mcp/server-card.json')->assertOk()->json();

    $handshake = $this->withHeader('Origin', config('app.url'))->postJson('/mcp/unserialize', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'initialize',
        'params' => [
            'protocolVersion' => '2025-11-25',
            'capabilities' => [],
            'clientInfo' => ['name' => 'test-client', 'version' => '1.0.0'],
        ],
    ])->assertOk();

    $handshake
        ->assertJsonPath('result.serverInfo.name', $card['serverInfo']['name'])
        ->assertJsonPath('result.serverInfo.version', $card['serverInfo']['version']);

    expect($handshake->json('result.protocolVersion'))
        ->toBeIn($card['remotes'][0]['supportedProtocolVersions'])
        ->and($card['title'])->toBe($handshake->json('result.serverInfo.name'));
});

test('the card is readable by browser-based clients from any origin', function () {
    $this->get('/.well-known/mcp/server-card.json')
        ->assertOk()
        ->assertHeader('Access-Control-Allow-Origin', '*')
        ->assertHeader('Access-Control-Allow-Methods', 'GET');
});

test('the public card exposes no credentials or private conversion paths', function () {
    $body = $this->get('/.well-known/mcp/server-card.json')->assertOk()->getContent();

    expect($body)
        ->not->toContain('/o/')
        ->and(strtolower($body))->not->toContain('secret')
        ->and(strtolower($body))->not->toContain('token')
        ->and(strtolower($body))->not->toContain('password');
});

/**
 * The source repository is not public yet, so the card must not advertise it.
 */
test('the card does not advertise a source repository', function () {
    $response = $this->get('/.well-known/mcp/server-card.json')->assertOk();

    expect($response->json())->not->toHaveKey('repository')
        ->and(strtolower($response->getContent()))->not->toContain('github');
});
