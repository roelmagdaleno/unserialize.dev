<?php

use App\Enums\ConversionErrorCode;
use App\Exceptions\ConversionException;
use App\Services\Serialized;

/**
 * Pins what the converter publishes, byte for byte, across the whole diagnostic
 * vocabulary.
 *
 * Every surface -- the browser panel, the HTTP API and the MCP tool -- reads the
 * same `ConversionErrorCode` and `SyntaxDiagnostic` pair, so pinning it once here
 * pins all three. The dataset carries one payload per reachable
 * `SyntaxErrorCode`, plus the three refusals that publish no diagnostic at all.
 *
 * This is the regression net for the swap onto `roelmagdaleno/serialized`. It was
 * written against the engine that was replaced and now pins the package's answers,
 * so a row moving again is a contract change that has to be argued for rather than
 * absorbed. Every row that did move carries a MOVED note saying what it reported
 * before, because those are the differences a caller can observe.
 */
it('publishes a stable diagnostic for every located failure', function (
    string $serializedData,
    ConversionErrorCode $errorCode,
    ?array $diagnostic,
) {
    try {
        new Serialized($serializedData)->convert();
    } catch (ConversionException $exception) {
        expect($exception->errorCode)->toBe($errorCode)
            ->and($exception->diagnostic?->toArray())->toBe($diagnostic);

        return;
    }

    $this->fail('Expected the conversion to fail.');
})->with([
    /**
     * MOVED: the span was the whole array, 14 bytes. It is now the header that
     * carries the wrong number, which is the part a reader has to change.
     */
    'array count mismatch' => [
        'a:2:{i:0;i:1;}',
        ConversionErrorCode::InvalidInput,
        [
            'code' => 'array_count_mismatch',
            'message' => 'The array starting at byte 0 declares 2 elements but contains 1.',
            'offset' => 0,
            'length' => 4,
            'suggestion' => 'Change `a:2:` to `a:1:`.',
        ],
    ],

    /**
     * MOVED: the span was 10 bytes covering the key and its value. It is now a
     * caret at the byte where the key begins.
     */
    'invalid array key' => [
        'a:1:{a:0:{}i:1;}',
        ConversionErrorCode::InvalidInput,
        [
            'code' => 'invalid_array_key',
            'message' => 'The array key at byte 5 is neither an integer nor a string.',
            'offset' => 5,
            'length' => 0,
            'suggestion' => 'Array keys use i: or s:.',
        ],
    ],

    /**
     * MOVED: the span was one byte. It now covers the whole literal that is not a
     * number, and the message names that byte rather than the value's first.
     */
    'malformed number' => [
        'i:abc;',
        ConversionErrorCode::InvalidInput,
        [
            'code' => 'malformed_number',
            'message' => 'The integer at byte 2 has no digits.',
            'offset' => 2,
            'length' => 3,
            'suggestion' => 'Write integers as i:42; or i:-42;.',
        ],
    ],

    'missing delimiter' => [
        'invalid',
        ConversionErrorCode::InvalidInput,
        [
            'code' => 'missing_delimiter',
            'message' => 'A ":" was expected at byte 1.',
            'offset' => 1,
            'length' => 1,
            'suggestion' => 'Insert the missing ":".',
        ],
    ],

    'missing terminator' => [
        's:2:"ab"x',
        ConversionErrorCode::InvalidInput,
        [
            'code' => 'missing_terminator',
            'message' => 'A ";" was expected at byte 8.',
            'offset' => 8,
            'length' => 1,
            'suggestion' => 'Insert the missing ";".',
        ],
    ],

    /**
     * MOVED: `i:1,` reported `missing_terminator` at byte 3. The package reads the
     * stray byte as part of the integer and runs off the end looking for the
     * terminator, so it is now an unexpected end one byte later.
     */
    'a value with a stray byte where its terminator belongs' => [
        'i:1,',
        ConversionErrorCode::InvalidInput,
        [
            'code' => 'unexpected_end',
            'message' => 'The value ends before the expected ";".',
            'offset' => 4,
            'length' => 0,
        ],
    ],

    'string length mismatch' => [
        'a:10:{s:4:"names";s:6:"Chrome";}',
        ConversionErrorCode::InvalidInput,
        [
            'code' => 'string_length_mismatch',
            'message' => 'The string starting at byte 6 declares 4 bytes but 5 bytes precede the closing quote.',
            'offset' => 6,
            'length' => 11,
            'suggestion' => 'Change `s:4:` to `s:5:`.',
        ],
    ],

    'trailing data' => [
        'i:1;i:2;',
        ConversionErrorCode::InvalidInput,
        [
            'code' => 'trailing_data',
            'message' => 'The value is complete at byte 4 but 4 more bytes follow.',
            'offset' => 4,
            'length' => 4,
            'suggestion' => 'Remove everything from byte 4 onwards.',
        ],
    ],

    /**
     * MOVED: this reported `trailing_data` at the same byte. Trailing bytes that do
     * not start a value of their own are now named for what they are.
     */
    'trailing bytes that are not a value' => [
        'i:1;xx',
        ConversionErrorCode::InvalidInput,
        [
            'code' => 'unknown_type_marker',
            'message' => 'Byte 4 is not a valid type marker.',
            'offset' => 4,
            'length' => 1,
            'suggestion' => 'Values start with N, b, i, d, s, a, or O.',
        ],
    ],

    /**
     * MOVED: this reported `trailing_data` at the same byte. A stray closing brace
     * is now named for what it is.
     */
    'a brace closing an array that was never opened' => [
        'a:0:{}}',
        ConversionErrorCode::InvalidInput,
        [
            'code' => 'unknown_syntax_error',
            'message' => 'The closing brace at byte 6 closes an array that was never opened.',
            'offset' => 6,
            'length' => 1,
            'suggestion' => 'Remove the brace, or add the array header it was meant to close.',
        ],
    ],

    /**
     * MOVED: this reported `unexpected_end` at byte 5, where the payload runs out.
     * The blame is now on byte 2, where the array declares a count the remaining
     * bytes cannot hold.
     */
    'an array declaring more than it can hold' => [
        'a:1:{',
        ConversionErrorCode::InvalidInput,
        [
            'code' => 'array_count_mismatch',
            'message' => 'The array starting at byte 2 declares 1 elements, which the 0 remaining bytes cannot hold.',
            'offset' => 2,
            'length' => 3,
            'suggestion' => 'Correct the declared count to the number of elements the array holds.',
        ],
    ],

    'unexpected end of a value' => [
        'b:0',
        ConversionErrorCode::InvalidInput,
        [
            'code' => 'unexpected_end',
            'message' => 'The value ends before the expected ";".',
            'offset' => 3,
            'length' => 0,
        ],
    ],

    /**
     * MOVED: the offset was byte 13, where the payload ends. It is now byte 0,
     * where the array that never closes opens.
     */
    'unclosed array' => [
        'a:1:{i:0;i:1;',
        ConversionErrorCode::InvalidInput,
        [
            'code' => 'unexpected_end',
            'message' => 'The array starting at byte 0 is missing its closing brace.',
            'offset' => 0,
            'length' => 0,
        ],
    ],

    'unknown type marker' => [
        'x:1;',
        ConversionErrorCode::InvalidInput,
        [
            'code' => 'unknown_type_marker',
            'message' => 'Byte 0 is not a valid type marker.',
            'offset' => 0,
            'length' => 1,
            'suggestion' => 'Values start with N, b, i, d, s, a, or O.',
        ],
    ],
]);

/**
 * The refusals that name a whole payload rather than a byte inside it.
 */
it('publishes no diagnostic for a failure with no byte position', function (
    string $serializedData,
    ConversionErrorCode $errorCode,
) {
    try {
        new Serialized($serializedData)->convert();
    } catch (ConversionException $exception) {
        expect($exception->errorCode)->toBe($errorCode)
            ->and($exception->diagnostic)->toBeNull();

        return;
    }

    $this->fail('Expected the conversion to fail.');
})->with([
    'serialized object' => ['O:8:"stdClass":0:{}', ConversionErrorCode::UnsupportedObject],
    'oversized input' => [serialize(str_repeat('a', Serialized::MAX_INPUT_BYTES)), ConversionErrorCode::InputTooLarge],
    'non-UTF-8 string' => ["s:2:\"\xff\xfe\";", ConversionErrorCode::EncodingFailed],
]);

/**
 * Excessive nesting is its own category, separate from a syntax error, because
 * the payload is well formed -- it is only too deep to decode.
 */
it('reports excessive nesting as its own category', function () {
    $depth = Serialized::MAX_DEPTH + 100;

    try {
        new Serialized(str_repeat('a:1:{i:0;', $depth).'N;'.str_repeat('}', $depth))->convert();
    } catch (ConversionException $exception) {
        expect($exception->errorCode)->toBe(ConversionErrorCode::DepthLimitExceeded)
            ->and($exception->diagnostic?->code->value)->toBe('depth_limit_exceeded');

        return;
    }

    $this->fail('Expected the conversion to fail.');
});

/**
 * Payloads PHP decodes while still raising a diagnostic.
 *
 * Both warnings are classified as benign today, so both values convert. The
 * package rejects both, which is the open question blocking the swap.
 */
it('converts values PHP decodes despite raising a diagnostic', function (string $serializedData, string $expected) {
    expect(new Serialized($serializedData)->output())->toBe($expected);
})->with([
    'deprecated escaped string format' => ['S:5:"\\68ello";', '"hello"'],
    'integer beyond the platform maximum' => ['i:99999999999999999999999;', (string) PHP_INT_MAX],
]);

/**
 * MOVED: this escaped non-ASCII as `\u00e9`. The package encodes with
 * JSON_UNESCAPED_UNICODE, so the character now survives into the output.
 */
it('leaves non-ASCII characters unescaped in its JSON output', function () {
    expect(new Serialized('s:5:"café";')->output())->toBe('"café"');
});

/**
 * A back reference names a value the payload already carries. `unserialize()` resolves
 * it, and JSON writes that value out at each place it appears.
 */
it('converts a payload holding a back reference', function () {
    expect(new Serialized('a:2:{i:0;a:0:{}i:1;R:2;}')->convert()->value)->toBe([[], []]);
});

/**
 * A value containing itself has no JSON form at all.
 *
 * It is reported as an encoding failure rather than a located one, because the
 * converter decodes the payload and encodes the value itself rather than asking the
 * package for JSON a second time. That is the same answer this application gave before
 * the package existed, and the case needs a PHP reference to construct at all.
 */
it('refuses a value that contains itself', function () {
    $loop = [];
    $loop['self'] = &$loop;

    try {
        new Serialized(serialize($loop))->convert();
    } catch (ConversionException $exception) {
        expect($exception->errorCode)->toBe(ConversionErrorCode::EncodingFailed)
            ->and($exception->diagnostic)->toBeNull();

        return;
    }

    $this->fail('Expected the conversion to fail.');
});
