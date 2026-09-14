<?php

use App\Enums\ScanVerdict;
use App\Enums\SyntaxErrorCode;
use App\Services\SerializedScanner;

it('accepts every serialized scalar form', function (string $serializedData) {
    $outcome = (new SerializedScanner)->scan($serializedData);

    expect($outcome->verdict)->toBe(ScanVerdict::Valid)
        ->and($outcome->consumedBytes)->toBe(strlen($serializedData));
})->with([
    'null' => 'N;',
    'false' => 'b:0;',
    'true' => 'b:1;',
    'integer' => 'i:42;',
    'negative integer' => 'i:-42;',
    'signed integer' => 'i:+1;',
    'padded integer' => 'i:01;',
    'float' => 'd:3.5;',
    'exponent float' => 'd:1.0E+100;',
    'infinity' => 'd:INF;',
    'negative infinity' => 'd:-INF;',
    'not a number' => 'd:NAN;',
    'empty string' => 's:0:"";',
    'string' => 's:5:"hello";',
    'escaped string' => 'S:5:"\\68ello";',
    'list' => 'a:2:{i:0;s:3:"red";i:1;s:4:"blue";}',
    'map' => 'a:1:{s:4:"name";s:3:"Ada";}',
    'nested array' => 'a:1:{i:0;a:1:{s:1:"a";N;}}',
]);

it('reports a string whose declared length is too short', function () {
    $outcome = (new SerializedScanner)->scan('a:1:{s:4:"names";N;}');

    expect($outcome->verdict)->toBe(ScanVerdict::Invalid)
        ->and($outcome->diagnostic->code)->toBe(SyntaxErrorCode::StringLengthMismatch)
        ->and($outcome->diagnostic->suggestion)->toBe('Change `s:4:` to `s:5:`.');
});

it('reports a string whose declared length is too long', function () {
    $outcome = (new SerializedScanner)->scan('s:9:"hello";');

    expect($outcome->diagnostic->code)->toBe(SyntaxErrorCode::StringLengthMismatch)
        ->and($outcome->diagnostic->suggestion)->toBe('Change `s:9:` to `s:5:`.');
});

it('points at the byte where the closing quote was expected', function () {
    $serializedData = 'a:10:{s:4:"names";s:6:"Chrome";}';

    $diagnostic = (new SerializedScanner)->scan($serializedData)->diagnostic;

    expect($diagnostic->expectedTerminatorOffset)->toBe(15)
        ->and($diagnostic->offset)->toBe(6)
        ->and(substr($serializedData, $diagnostic->offset, $diagnostic->length))->toBe('s:4:"names"')
        ->and($diagnostic->message)->toBe(
            'The string starting at byte 6 declares 4 bytes but 5 bytes precede the closing quote.',
        );
});

it('measures string lengths in bytes rather than characters', function () {
    $outcome = (new SerializedScanner)->scan(serialize('café 😀'));

    expect($outcome->verdict)->toBe(ScanVerdict::Valid);
});

it('does not end a string at a quote inside its contents', function () {
    $outcome = (new SerializedScanner)->scan('s:3:"a"b";');

    expect($outcome->verdict)->toBe(ScanVerdict::Valid);
});

it('accepts a string containing a null byte', function () {
    $outcome = (new SerializedScanner)->scan("s:3:\"a\0b\";");

    expect($outcome->verdict)->toBe(ScanVerdict::Valid);
});

it('reports an array holding fewer elements than it declares', function () {
    $diagnostic = (new SerializedScanner)->scan('a:2:{s:4:"name";s:6:"Chrome";}')->diagnostic;

    expect($diagnostic->code)->toBe(SyntaxErrorCode::ArrayCountMismatch)
        ->and($diagnostic->suggestion)->toBe('Change `a:2:` to `a:1:`.');
});

it('reports an array holding more elements than it declares', function () {
    $diagnostic = (new SerializedScanner)->scan('a:1:{i:0;i:1;i:2;i:3;}')->diagnostic;

    expect($diagnostic->code)->toBe(SyntaxErrorCode::ArrayCountMismatch)
        ->and($diagnostic->suggestion)->toBe('Change `a:1:` to `a:2:`.');
});

it('reports an array key that is neither an integer nor a string', function () {
    $diagnostic = (new SerializedScanner)->scan('a:1:{d:1.5;i:1;}')->diagnostic;

    expect($diagnostic->code)->toBe(SyntaxErrorCode::InvalidArrayKey)
        ->and($diagnostic->offset)->toBe(5);
});

it('reports the first byte that is not a type marker', function () {
    $diagnostic = (new SerializedScanner)->scan('x:1;')->diagnostic;

    expect($diagnostic->code)->toBe(SyntaxErrorCode::UnknownTypeMarker)
        ->and($diagnostic->offset)->toBe(0);
});

it('reports bytes that follow a complete value', function () {
    $diagnostic = (new SerializedScanner)->scan('i:1;garbage')->diagnostic;

    expect($diagnostic->code)->toBe(SyntaxErrorCode::TrailingData)
        ->and($diagnostic->offset)->toBe(4)
        ->and($diagnostic->length)->toBe(7);
});

it('reports a value that ends before its terminator', function () {
    $diagnostic = (new SerializedScanner)->scan('b:0')->diagnostic;

    expect($diagnostic->code)->toBe(SyntaxErrorCode::UnexpectedEnd);
});

it('reports a boolean that is neither zero nor one', function () {
    $diagnostic = (new SerializedScanner)->scan('b:2;')->diagnostic;

    expect($diagnostic->code)->toBe(SyntaxErrorCode::MalformedNumber)
        ->and($diagnostic->offset)->toBe(2);
});

it('reports a number with no digits', function (string $serializedData) {
    $diagnostic = (new SerializedScanner)->scan($serializedData)->diagnostic;

    expect($diagnostic->code)->toBe(SyntaxErrorCode::MalformedNumber);
})->with([
    'integer' => 'i:;',
    'float' => 'd:;',
    'exponent' => 'd:1.0e;',
]);

it('reports an empty value', function () {
    $outcome = (new SerializedScanner)->scan('');

    expect($outcome->verdict)->toBe(ScanVerdict::Invalid)
        ->and($outcome->diagnostic->message)->toBe('The value is empty.');
});

it('treats a well-formed serialized object as structurally valid', function () {
    $outcome = (new SerializedScanner)->scan('O:8:"stdClass":1:{s:1:"a";i:1;}');

    expect($outcome->verdict)->toBe(ScanVerdict::Valid);
});

it('declines to judge tokens whose validity depends on runtime state', function (string $serializedData) {
    $outcome = (new SerializedScanner)->scan($serializedData);

    expect($outcome->verdict)->toBe(ScanVerdict::Unverifiable)
        ->and($outcome->consumedBytes)->toBe(strlen($serializedData));
})->with([
    'back reference' => 'a:2:{i:0;s:1:"a";i:1;R:2;}',
    'enum case' => 'E:11:"Suit:Hearts";',
    'custom serialized object' => 'C:3:"Foo":3:{abc}',
]);

it('declines to recurse past its own depth limit', function () {
    $depth = SerializedScanner::MAX_DEPTH + 200;
    $serializedData = str_repeat('a:1:{i:0;', $depth).'N;'.str_repeat('}', $depth);

    expect((new SerializedScanner)->scan($serializedData)->verdict)->toBe(ScanVerdict::Unverifiable);
});

it('scans a maximum-size payload without quadratic blow-up', function () {
    $serializedData = serialize(array_fill(0, 12_000, 'abcdefghijklmnopqrst'));
    $startedAt = hrtime(true);

    $outcome = (new SerializedScanner)->scan($serializedData);

    expect($outcome->verdict)->toBe(ScanVerdict::Valid)
        ->and(strlen($serializedData))->toBeGreaterThan(200_000)
        ->and((hrtime(true) - $startedAt) / 1_000_000_000)->toBeLessThan(2.0);
});
