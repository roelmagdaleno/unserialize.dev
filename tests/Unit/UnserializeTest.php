<?php

use App\Enums\ConversionErrorCode;
use App\Enums\SyntaxErrorCode;
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
    expect(fn () => (new Serialized($serializedData))->output())
        ->toThrow(function (ConversionException $exception) {
            expect($exception->errorCode)->toBe(ConversionErrorCode::InvalidInput);
        });
})->with([
    'empty input' => '',
    'missing terminator' => 'b:0',
    'plain text' => 'invalid',
]);

it('explains where invalid serialized data breaks', function (string $serializedData, string $message) {
    try {
        (new Serialized($serializedData))->output();
    } catch (ConversionException $exception) {
        expect($exception->getMessage())->toBe('Invalid serialized data.')
            ->and($exception->diagnostic->message)->toBe($message);

        return;
    }

    $this->fail('Expected conversion to fail.');
})->with([
    'empty input' => ['', 'The value is empty.'],
    'missing terminator' => ['b:0', 'The value ends before the expected ";".'],
    'plain text' => ['invalid', 'A ":" was expected at byte 1.'],
]);

it('locates a string whose declared byte length no longer matches its contents', function () {
    $serializedData = 'a:10:{s:4:"names";s:6:"Chrome";}';

    try {
        (new Serialized($serializedData))->output();
    } catch (ConversionException $exception) {
        expect($exception->errorCode)->toBe(ConversionErrorCode::InvalidInput)
            ->and($exception->diagnostic->code)->toBe(SyntaxErrorCode::StringLengthMismatch)
            ->and($exception->diagnostic->offset)->toBe(6)
            ->and(substr($serializedData, $exception->diagnostic->offset, $exception->diagnostic->length))->toBe('s:4:"names"')
            ->and($exception->diagnostic->suggestion)->toBe('Change `s:4:` to `s:5:`.');

        return;
    }

    $this->fail('Expected conversion to fail.');
});

it('leaves no diagnostic on failures that have no byte position', function (string $serializedData) {
    try {
        (new Serialized($serializedData))->output();
    } catch (ConversionException $exception) {
        expect($exception->diagnostic)->toBeNull();

        return;
    }

    $this->fail('Expected conversion to fail.');
})->with([
    'serialized object' => 'O:8:"stdClass":0:{}',
    'oversized input' => serialize(str_repeat('a', (256 * 1024) + 1)),
]);

it('converts values PHP decodes despite raising a diagnostic', function (string $serializedData, mixed $expected) {
    expect((new Serialized($serializedData))->convert()->value)->toBe($expected);
})->with([
    'deprecated escaped string format' => ['S:5:"\\68ello";', 'hello'],
    'integer beyond the platform maximum' => ['i:99999999999999999999999;', PHP_INT_MAX],
]);

it('reports excessive nesting as its own category', function () {
    $depth = Serialized::MAX_DEPTH + 100;
    $serializedData = str_repeat('a:1:{i:0;', $depth).'N;'.str_repeat('}', $depth);

    try {
        (new Serialized($serializedData))->convert();
    } catch (ConversionException $exception) {
        expect($exception->errorCode)->toBe(ConversionErrorCode::DepthLimitExceeded)
            ->and($exception->diagnostic->code)->toBe(SyntaxErrorCode::DepthLimitExceeded);

        return;
    }

    $this->fail('Expected conversion to fail.');
});

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
