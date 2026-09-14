<?php

use App\Enums\ConversionErrorCode;
use App\Enums\SyntaxErrorCode;
use App\Models\Output;

it('returns 200 with a structured native value without persistence', function () {
    Output::factory()->create();

    $this->postJson('/api/v1/unserialize', [
        'serialized' => 'a:2:{s:4:"name";s:6:"Chrome";s:6:"active";b:1;}',
    ])
        ->assertOk()
        ->assertExactJson([
            'data' => [
                'value' => ['name' => 'Chrome', 'active' => true],
                'format' => 'json',
            ],
            'meta' => ['retained' => false],
        ])
        ->assertCookieMissing(config('session.cookie'));

    $this->assertDatabaseCount('outputs', 1);
});

it('returns 422 with the stable validation envelope for a missing field', function () {
    $this->postJson('/api/v1/unserialize')
        ->assertUnprocessable()
        ->assertExactJson([
            'error' => [
                'code' => 'validation_error',
                'message' => 'The request data is invalid.',
                'details' => [
                    'serialized' => ['The serialized field is required.'],
                ],
            ],
        ]);
});

it('returns 422 when the request contains an undocumented field', function () {
    $this->postJson('/api/v1/unserialize', [
        'serialized' => 'i:1;',
        'persist' => true,
    ])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'validation_error')
        ->assertJsonPath('error.details.persist.0', 'This field is not supported.');

    $this->assertDatabaseCount('outputs', 0);
});

it('returns 422 with stable conversion error codes', function (string $serializedData, string $code, string $message) {
    $this->postJson('/api/v1/unserialize', ['serialized' => $serializedData])
        ->assertUnprocessable()
        ->assertExactJson([
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ]);

    $this->assertDatabaseCount('outputs', 0);
})->with([
    'unsupported object' => ['O:8:"stdClass":0:{}', 'unsupported_object', 'Serialized objects are not supported.'],
    'encoding failure' => ['a:1:{s:5:"value";d:NAN;}', 'encoding_failed', 'Failed to encode the serialized data to JSON.'],
]);

it('returns 422 with a located diagnostic for invalid serialized input', function () {
    $this->postJson('/api/v1/unserialize', ['serialized' => 'a:10:{s:4:"names";s:6:"Chrome";}'])
        ->assertUnprocessable()
        ->assertExactJson([
            'error' => [
                'code' => 'invalid_input',
                'message' => 'Invalid serialized data.',
                'diagnostic' => [
                    'code' => 'string_length_mismatch',
                    'message' => 'The string starting at byte 6 declares 4 bytes but 5 bytes precede the closing quote.',
                    'offset' => 6,
                    'length' => 11,
                    'suggestion' => 'Change `s:4:` to `s:5:`.',
                ],
            ],
        ]);

    $this->assertDatabaseCount('outputs', 0);
});

it('locates input that is not serialized data at all', function () {
    $this->postJson('/api/v1/unserialize', ['serialized' => 'invalid'])
        ->assertUnprocessable()
        ->assertJsonPath('error.diagnostic.code', 'missing_delimiter')
        ->assertJsonPath('error.diagnostic.offset', 1)
        ->assertJsonPath('error.diagnostic.length', 1);
});

it('omits the diagnostic for failures that have no byte position', function (string $serializedData, string $code) {
    $this->postJson('/api/v1/unserialize', ['serialized' => $serializedData])
        ->assertJsonPath('error.code', $code)
        ->assertJsonMissingPath('error.diagnostic');
})->with([
    'unsupported object' => ['O:8:"stdClass":0:{}', 'unsupported_object'],
    'encoding failure' => ['a:1:{s:5:"value";d:NAN;}', 'encoding_failed'],
]);

it('never echoes submitted bytes back in the diagnostic', function () {
    $response = $this->postJson('/api/v1/unserialize', [
        'serialized' => 'a:1:{s:3:"keys";s:11:"SUPERSECRET";}',
    ]);

    $response->assertUnprocessable()->assertJsonPath('error.diagnostic.code', 'string_length_mismatch');

    expect($response->getContent())->not->toContain('SUPERSECRET');
});

it('keeps the validation details shape separate from the diagnostic shape', function () {
    $this->postJson('/api/v1/unserialize')
        ->assertJsonPath('error.details.serialized.0', 'The serialized field is required.')
        ->assertJsonMissingPath('error.diagnostic');

    $this->postJson('/api/v1/unserialize', ['serialized' => 'invalid'])
        ->assertJsonMissingPath('error.details')
        ->assertJsonPath('error.diagnostic.code', 'missing_delimiter');
});

it('returns 413 when the serialized value exceeds 262144 bytes', function () {
    $this->postJson('/api/v1/unserialize', [
        'serialized' => serialize(str_repeat('a', (256 * 1024) + 1)),
    ])
        ->assertStatus(413)
        ->assertJsonPath('error.code', 'input_too_large')
        ->assertJsonPath('error.message', 'The serialized data must not be greater than 262,144 bytes.');

    $this->assertDatabaseCount('outputs', 0);
});

it('returns 415 when the request content type is not JSON', function () {
    $this->call(
        'POST',
        '/api/v1/unserialize',
        server: ['CONTENT_TYPE' => 'text/plain', 'HTTP_ACCEPT' => 'application/json'],
        content: 'a:1:{i:0;s:3:"one";}',
    )
        ->assertUnsupportedMediaType()
        ->assertExactJson([
            'error' => [
                'code' => 'unsupported_media_type',
                'message' => 'Content-Type must be application/json.',
            ],
        ]);
});

it('returns 429 with retry guidance after ten requests from one IP', function () {
    foreach (range(1, 10) as $attempt) {
        $this->postJson('/api/v1/unserialize', ['serialized' => 'i:1;'])->assertOk();
    }

    $this->postJson('/api/v1/unserialize', ['serialized' => 'i:1;'])
        ->assertTooManyRequests()
        ->assertHeader('Retry-After')
        ->assertJsonPath('error.code', 'rate_limited')
        ->assertJsonPath('error.message', 'Too many conversion attempts. Try again later.');
});

it('publishes the OpenAPI contract from a stable URL', function () {
    $response = $this->getJson('/openapi.json');

    $response
        ->assertOk()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonPath('openapi', '3.1.0')
        ->assertJsonPath('paths./api/v1/unserialize.post.operationId', 'convertSerializedPhpToJson')
        ->assertJsonPath('paths./api/v1/unserialize.post.responses.429.headers.Retry-After.required', true);
});

it('publishes every diagnostic code in the OpenAPI contract', function () {
    $document = $this->getJson('/openapi.json')->json();

    expect($document['info']['version'])->toBe('1.1.0')
        ->and($document['components']['schemas']['Diagnostic']['additionalProperties'])->toBeFalse()
        ->and($document['components']['schemas']['Diagnostic']['required'])
        ->toEqualCanonicalizing(['code', 'message', 'offset', 'length'])
        ->and($document['components']['schemas']['Diagnostic']['properties']['code']['enum'])
        ->toEqualCanonicalizing(array_map(
            static fn (SyntaxErrorCode $code): string => $code->value,
            SyntaxErrorCode::cases(),
        ))
        ->and($document['components']['schemas']['Error']['properties']['diagnostic'])
        ->toBe(['$ref' => '#/components/schemas/Diagnostic'])
        ->and($document['components']['schemas']['Error']['required'])->not->toContain('diagnostic');
});

it('publishes every conversion error code in the OpenAPI contract', function () {
    $published = $this->getJson('/openapi.json')->json('components.schemas.Error.properties.code.enum');

    foreach (ConversionErrorCode::cases() as $code) {
        expect($published)->toContain($code->value);
    }
});
