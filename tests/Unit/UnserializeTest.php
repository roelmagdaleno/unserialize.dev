<?php

use App\Enums\ConversionErrorCode;
use App\Exceptions\ConversionException;
use App\Services\Serialized;

it('converts serialized values to JSON', function (string $serializedData, string $expected) {
    $output = (new Serialized($serializedData))->output();

    expect($output)->toBe($expected);
})->with([
    'integer zero' => ['i:0;', '0'],
    'false' => ['b:0;', 'false'],
    'null' => ['N;', 'null'],
    'string' => ['s:5:"hello";', '"hello"'],
    'array' => ['a:1:{s:4:"name";s:6:"Chrome";}', "{\n    \"name\": \"Chrome\"\n}"],
]);

it('rejects invalid serialized data', function (string $serializedData) {
    (new Serialized($serializedData))->output();
})->throws(Exception::class, 'Invalid serialized data.')
    ->with([
        'empty input' => '',
        'missing terminator' => 'b:0',
        'plain text' => 'invalid',
    ]);

it('reports JSON encoding failures without internal details', function () {
    (new Serialized('a:1:{s:5:"value";d:NAN;}'))->output();
})->throws(Exception::class, 'Failed to encode the serialized data to JSON.');

it('rejects serialized objects without invoking magic methods', function () {
    SerializedObjectWithWakeup::$wasInvoked = false;
    $serializedData = serialize(new SerializedObjectWithWakeup);

    expect(fn () => (new Serialized($serializedData))->output())
        ->toThrow(Exception::class, 'Serialized objects are not supported.')
        ->and(SerializedObjectWithWakeup::$wasInvoked)->toBeFalse();
});

it('returns a typed conversion result with the native value and JSON', function () {
    $result = (new Serialized('a:1:{s:4:"name";s:6:"Chrome";}'))->convert();

    expect($result->value)->toBe(['name' => 'Chrome'])
        ->and($result->json)->toBe("{\n    \"name\": \"Chrome\"\n}");
});

it('assigns stable categories to conversion failures', function (string $serializedData, string $expectedCode) {
    try {
        (new Serialized($serializedData))->convert();
    } catch (ConversionException $exception) {
        expect($exception->errorCode->value)->toBe($expectedCode);

        return;
    }

    $this->fail('Expected conversion to fail.');
})->with([
    'invalid input' => ['invalid', 'invalid_input'],
    'unsupported object' => ['O:8:"stdClass":0:{}', 'unsupported_object'],
    'encoding failure' => ['a:1:{s:5:"value";d:NAN;}', 'encoding_failed'],
]);

it('rejects input larger than 256 KiB with a stable category', function () {
    $serializedData = serialize(str_repeat('a', (256 * 1024) + 1));

    expect(fn () => (new Serialized($serializedData))->convert())
        ->toThrow(function (ConversionException $exception) {
            expect($exception->errorCode)->toBe(ConversionErrorCode::InputTooLarge);
        });
});

class SerializedObjectWithWakeup
{
    public static bool $wasInvoked = false;

    public function __wakeup(): void
    {
        self::$wasInvoked = true;
    }
}
