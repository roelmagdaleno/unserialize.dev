<?php

use App\Services\Serialized;

it('converts serialized values to JSON', function (string $serializedData, string $expected) {
    $output = (new Serialized($serializedData, 'json'))->output();

    expect($output)->toBe($expected);
})->with([
    'integer zero' => ['i:0;', '0'],
    'false' => ['b:0;', 'false'],
    'null' => ['N;', 'null'],
    'string' => ['s:5:"hello";', '"hello"'],
    'array' => ['a:1:{s:4:"name";s:6:"Chrome";}', "{\n    \"name\": \"Chrome\"\n}"],
]);

it('converts serialized values to PHP arrays', function (string $serializedData, string $expected) {
    $output = (new Serialized($serializedData, 'array'))->output();

    expect($output)->toBe($expected);
})->with([
    'integer zero' => ['i:0;', '0'],
    'false' => ['b:0;', 'false'],
    'null' => ['N;', 'null'],
    'string' => ['s:5:"hello";', "'hello'"],
    'array' => ['a:1:{s:4:"name";s:6:"Chrome";}', "[\n    'name' => 'Chrome'\n]"],
]);

it('rejects an unsupported output format', function () {
    (new Serialized('i:0;', 'invalid'))->output();
})->throws(Exception::class, 'Invalid output format.');

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

class SerializedObjectWithWakeup
{
    public static bool $wasInvoked = false;

    public function __wakeup(): void
    {
        self::$wasInvoked = true;
    }
}
