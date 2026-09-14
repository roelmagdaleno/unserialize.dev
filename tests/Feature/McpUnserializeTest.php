<?php

use App\Http\Middleware\ValidateMcpRequestOrigin;
use App\Mcp\Servers\UnserializeServer;
use App\Mcp\Tools\ConvertSerializedDataTool;
use App\Models\Output;
use App\Services\Serialized;
use Illuminate\Http\Request;
use Laravel\Mcp\Facades\Mcp;

test('the mcp tool converts serialized input without persistence', function () {
    UnserializeServer::tool(ConvertSerializedDataTool::class, [
        'serialized' => 'a:2:{s:4:"name";s:5:"Codex";s:6:"active";b:1;}',
    ])->assertOk()->assertStructuredContent([
        'data' => [
            'value' => ['name' => 'Codex', 'active' => true],
            'format' => 'json',
        ],
        'meta' => ['retained' => false],
    ]);

    expect(Output::query()->count())->toBe(0);
});

test('the mcp tool exposes stable conversion errors', function (string $serialized, string $code) {
    UnserializeServer::tool(ConvertSerializedDataTool::class, [
        'serialized' => $serialized,
    ])->assertHasErrors()->assertStructuredContent(fn ($json) => $json
        ->where('error.code', $code)
        ->has('error.message'));
})->with([
    'invalid input' => ['not serialized', 'invalid_input'],
    'serialized object' => ['O:8:"stdClass":0:{}', 'unsupported_object'],
    'excessive input' => ['s:'.Serialized::MAX_INPUT_BYTES.':"'.str_repeat('x', Serialized::MAX_INPUT_BYTES).'";', 'input_too_large'],
]);

test('the mcp tool rejects missing, non-string, and unexpected arguments', function (array $arguments) {
    UnserializeServer::tool(ConvertSerializedDataTool::class, $arguments)
        ->assertHasErrors()
        ->assertStructuredContent(fn ($json) => $json
            ->where('error.code', 'validation_error')
            ->where('error.message', 'Provide exactly one string field named serialized.'));
})->with([
    'missing input' => [[]],
    'non-string input' => [['serialized' => 123]],
    'unexpected field' => [['serialized' => 'N;', 'extra' => true]],
]);

test('mcp discovery describes the single safe conversion tool', function () {
    $tool = app(ConvertSerializedDataTool::class)->toArray();

    expect($tool)
        ->toMatchArray([
            'name' => 'convert_php_serialized_data',
            'title' => 'Convert PHP Serialized Data',
            'annotations' => [
                'readOnlyHint' => true,
                'idempotentHint' => true,
                'destructiveHint' => false,
                'openWorldHint' => false,
            ],
        ])
        ->and($tool['description'])->toContain('262144 bytes', 'objects are rejected', 'not retained')
        ->and($tool['inputSchema']['properties']['serialized']['maxLength'])->toBe(Serialized::MAX_INPUT_BYTES)
        ->and($tool['inputSchema']['required'])->toBe(['serialized'])
        ->and($tool['inputSchema']['additionalProperties'])->toBeFalse()
        ->and($tool)->toHaveKey('outputSchema.properties.data')
        ->and($tool)->toHaveKey('outputSchema.properties.error');
});

test('the anonymous mcp transport is separately rate limited', function () {
    $route = Mcp::getWebServer('mcp/unserialize');

    expect($route)->not->toBeNull()
        ->and($route->middleware())->toContain('throttle:unserialize-mcp');
});

test('the mcp transport rejects a forged host', function () {
    $response = app(ValidateMcpRequestOrigin::class)->handle(
        Request::create('http://attacker.example/mcp/unserialize', 'POST'),
        fn () => response('', 200),
    );

    expect($response->getStatusCode())->toBe(403)
        ->and($response->getContent())->toContain('Forbidden origin or host.');
});

test('the mcp transport rejects a cross-origin request', function () {
    $this->withHeader('Origin', 'https://attacker.example')->postJson('/mcp/unserialize', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'initialize',
        'params' => [
            'protocolVersion' => '2025-11-25',
            'capabilities' => [],
            'clientInfo' => ['name' => 'test-client', 'version' => '1.0.0'],
        ],
    ])->assertForbidden()
        ->assertJsonPath('error.code', -32000)
        ->assertJsonPath('error.message', 'Forbidden origin or host.');
});

test('the mcp transport rejects a malformed origin with a path', function () {
    $this->withHeader('Origin', config('app.url').'/unexpected')->postJson('/mcp/unserialize', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'initialize',
        'params' => [
            'protocolVersion' => '2025-11-25',
            'capabilities' => [],
            'clientInfo' => ['name' => 'test-client', 'version' => '1.0.0'],
        ],
    ])->assertForbidden()
        ->assertJsonPath('error.code', -32000);
});

test('the mcp transport accepts the configured origin', function () {
    $this->withHeader('Origin', config('app.url'))->postJson('/mcp/unserialize', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'initialize',
        'params' => [
            'protocolVersion' => '2025-11-25',
            'capabilities' => [],
            'clientInfo' => ['name' => 'test-client', 'version' => '1.0.0'],
        ],
    ])->assertOk()
        ->assertJsonPath('result.serverInfo.name', 'Unserialize Server');
});

test('rate limiting is isolated from the sqlite application database', function () {
    expect(config('cache.limiter'))->toBe('array')
        ->and(file_get_contents(base_path('.env.example')))->toContain('CACHE_LIMITER=file');
});
