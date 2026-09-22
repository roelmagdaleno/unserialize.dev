<?php

use App\Data\SyntaxDiagnostic;
use App\Enums\ConversionErrorCode;
use App\Enums\SyntaxErrorCode;
use App\Services\DiagnosticTranslator;
use App\Services\Serialized as Converter;
use Serialized\Diagnostics\Diagnostic;
use Serialized\Diagnostics\DiagnosticCode;
use Serialized\Exceptions\SerializedException;
use Serialized\Serialized as Package;

/**
 * A payload whose value contains itself, which is the one reference shape JSON has no
 * form for.
 */
function circularPayload(): string
{
    $loop = [];
    $loop['self'] = &$loop;

    return serialize($loop);
}

/**
 * Runs a payload through a converter configured the way the application configures
 * it, and hands back whatever it refused with.
 */
function refusalFor(string $payload): SerializedException
{
    try {
        Package::make()
            ->withMaxBytes(Converter::MAX_INPUT_BYTES)
            ->withMaxDepth(Converter::MAX_DEPTH)
            ->toJson($payload);
    } catch (SerializedException $exception) {
        return $exception;
    }

    throw new RuntimeException('Expected the payload to be refused.');
}

it('names the error code behind every kind of refusal', function (string $payload, ConversionErrorCode $errorCode) {
    $translator = new DiagnosticTranslator;

    expect($translator->errorCodeFor(refusalFor($payload)))->toBe($errorCode);
})->with([
    'malformed payload' => ['a:2:{i:0;i:1;}', ConversionErrorCode::InvalidInput],
    'serialized object' => ['O:8:"stdClass":0:{}', ConversionErrorCode::UnsupportedObject],
    'custom-serialized object' => ['C:11:"ArrayObject":0:{}', ConversionErrorCode::UnsupportedObject],
    'enum case' => ['E:11:"Suit:Hearts";', ConversionErrorCode::UnsupportedObject],
    'circular reference' => [circularPayload(), ConversionErrorCode::InvalidInput],
    'non-UTF-8 string' => ["s:2:\"\xff\xfe\";", ConversionErrorCode::EncodingFailed],
    'non-finite float' => ['d:NAN;', ConversionErrorCode::EncodingFailed],
    'oversized input' => [serialize(str_repeat('a', Converter::MAX_INPUT_BYTES)), ConversionErrorCode::InputTooLarge],
]);

it('reports excessive nesting as its own error code', function () {
    $depth = Converter::MAX_DEPTH + 100;
    $exception = refusalFor(str_repeat('a:1:{i:0;', $depth).'N;'.str_repeat('}', $depth));

    $translator = new DiagnosticTranslator;

    expect($translator->errorCodeFor($exception))->toBe(ConversionErrorCode::DepthLimitExceeded);
});

it('publishes no diagnostic for a refusal that names no byte', function (string $payload) {
    $exception = refusalFor($payload);

    $translator = new DiagnosticTranslator;

    expect($translator->diagnosticFor($exception, strlen($payload)))->toBeNull();
})->with([
    'serialized object' => 'O:8:"stdClass":0:{}',
    'enum case' => 'E:11:"Suit:Hearts";',
    'non-UTF-8 string' => "s:2:\"\xff\xfe\";",
    'non-finite float' => 'd:NAN;',
    'oversized input' => serialize(str_repeat('a', Converter::MAX_INPUT_BYTES)),
]);

it('translates every located failure into a published category', function (
    string $payload,
    SyntaxErrorCode $code,
    int $offset,
    int $length,
    string $message,
    ?string $suggestion,
) {
    $diagnostic = new DiagnosticTranslator()->diagnosticFor(refusalFor($payload), strlen($payload));

    expect($diagnostic->code)->toBe($code)
        ->and($diagnostic->offset)->toBe($offset)
        ->and($diagnostic->length)->toBe($length)
        ->and($diagnostic->message)->toBe($message)
        ->and($diagnostic->suggestion)->toBe($suggestion);
})->with([
    'empty payload' => [
        '', SyntaxErrorCode::UnexpectedEnd, 0, 0,
        'The value is empty.', null,
    ],
    'truncated value' => [
        'b:0', SyntaxErrorCode::UnexpectedEnd, 3, 0,
        'The value ends before the expected ";".', null,
    ],
    'unclosed array' => [
        'a:1:{i:0;i:1;', SyntaxErrorCode::UnexpectedEnd, 0, 0,
        'The array starting at byte 0 is missing its closing brace.', null,
    ],
    'unknown type marker' => [
        'x:1;', SyntaxErrorCode::UnknownTypeMarker, 0, 1,
        'Byte 0 is not a valid type marker.', 'Values start with N, b, i, d, s, a, or O.',
    ],
    'missing delimiter' => [
        'invalid', SyntaxErrorCode::MissingDelimiter, 1, 1,
        'A ":" was expected at byte 1.', 'Insert the missing ":".',
    ],
    'malformed integer' => [
        'i:abc;', SyntaxErrorCode::MalformedNumber, 2, 3,
        'The integer at byte 2 has no digits.', 'Write integers as i:42; or i:-42;.',
    ],
    'malformed boolean' => [
        'b:2;', SyntaxErrorCode::MalformedNumber, 2, 1,
        'The boolean at byte 2 must be 0 or 1.', 'Use b:0; for false and b:1; for true.',
    ],
    'malformed byte length' => [
        's:x:"ab";', SyntaxErrorCode::MalformedNumber, 2, 1,
        'The byte length at byte 2 is not a number.', 'Write the byte length as a number, as in s:6:"Chrome";.',
    ],
    'malformed element count' => [
        'a:x:{}', SyntaxErrorCode::MalformedNumber, 2, 1,
        'The element count at byte 2 is not a number.', 'Write the element count as a number, as in a:2:{...}.',
    ],
    'array count mismatch' => [
        'a:2:{i:0;i:1;}', SyntaxErrorCode::ArrayCountMismatch, 0, 4,
        'The array starting at byte 0 declares 2 elements but contains 1.', 'Change `a:2:` to `a:1:`.',
    ],
    'impossible element count' => [
        'a:1:{', SyntaxErrorCode::ArrayCountMismatch, 2, 3,
        'The array starting at byte 2 declares 1 elements, which the 0 remaining bytes cannot hold.',
        'Correct the declared count to the number of elements the array holds.',
    ],
    'invalid array key' => [
        'a:1:{a:0:{}i:1;}', SyntaxErrorCode::InvalidArrayKey, 5, 0,
        'The array key at byte 5 is neither an integer nor a string.', 'Array keys use i: or s:.',
    ],
    'trailing data' => [
        'i:1;i:2;', SyntaxErrorCode::TrailingData, 4, 4,
        'The value is complete at byte 4 but 4 more bytes follow.', 'Remove everything from byte 4 onwards.',
    ],
    'unbalanced close' => [
        'a:0:{}}', SyntaxErrorCode::UnknownSyntaxError, 6, 1,
        'The closing brace at byte 6 closes an array that was never opened.',
        'Remove the brace, or add the array header it was meant to close.',
    ],

]);

/**
 * The package locates a loop while normalizing, which is a path {@see Converter}
 * does not take: it encodes the decoded value itself rather than asking for JSON
 * twice, so a loop reaches it as a plain encoding failure. The mapping is pinned here
 * anyway, because it is the translator's job to have an answer for every code the
 * package can raise.
 */
it('locates a value that contains itself', function () {
    $payload = circularPayload();
    $diagnostic = new DiagnosticTranslator()->diagnosticFor(refusalFor($payload), strlen($payload));

    expect($diagnostic->code)->toBe(SyntaxErrorCode::UnknownSyntaxError)
        ->and($diagnostic->message)->toContain('never ends')
        ->and($diagnostic->offset)->toBeLessThanOrEqual(strlen($payload));
});

/**
 * A reference that names a value beside it is resolved, not refused: `unserialize()`
 * hands back the value it points at, and JSON carries it at each place it appears.
 */
it('converts a payload whose reference points sideways', function () {
    $shared = ['a' => 1];

    expect(new Converter(serialize(['first' => &$shared, 'second' => &$shared]))->convert()->value)
        ->toBe(['first' => ['a' => 1], 'second' => ['a' => 1]]);
});

/**
 * The tightest-pinned case in the suite: the application blames the whole string,
 * from its type marker through its closing quote, while the package blames the
 * declared number alone.
 */
it('blames the whole string when a declared byte length is wrong', function (string $payload, int $offset, string $span) {
    $diagnostic = new DiagnosticTranslator()->diagnosticFor(refusalFor($payload), strlen($payload));

    expect($diagnostic->code)->toBe(SyntaxErrorCode::StringLengthMismatch)
        ->and($diagnostic->offset)->toBe($offset)
        ->and(substr($payload, $diagnostic->offset, $diagnostic->length))->toBe($span);
})->with([
    'declared too long' => ['a:10:{s:4:"names";s:6:"Chrome";}', 6, 's:4:"names"'],
    'declared too short' => ['a:1:{s:4:"name";s:6:"Chrom";}', 16, 's:6:"Chrom"'],
]);

it('names the escaped-string prefix when its declared length is wrong', function () {
    $payload = 'S:5:"\\68el";';
    $diagnostic = new DiagnosticTranslator()->diagnosticFor(refusalFor($payload), strlen($payload));

    expect($diagnostic->code)->toBe(SyntaxErrorCode::StringLengthMismatch)
        ->and($diagnostic->suggestion)->toBe('Change `S:5:` to `S:3:`.');
});

it('reports excessive nesting with the limit it exceeded', function () {
    $depth = Converter::MAX_DEPTH + 100;
    $payload = str_repeat('a:1:{i:0;', $depth).'N;'.str_repeat('}', $depth);
    $diagnostic = new DiagnosticTranslator()->diagnosticFor(refusalFor($payload), strlen($payload));

    expect($diagnostic->code)->toBe(SyntaxErrorCode::DepthLimitExceeded)
        ->and($diagnostic->message)->toBe(sprintf(
            'The value nests deeper than the %d levels this converter decodes.',
            Converter::MAX_DEPTH,
        ))
        ->and($diagnostic->suggestion)->toBe('Convert a smaller part of the structure.');
});

/**
 * A truncated payload is blamed one byte past its end, which is where the missing
 * byte should have been. Every surface indexes the payload with these two numbers,
 * so both have to land inside it.
 */
it('keeps every offset and span inside the payload', function (string $payload) {
    $diagnostic = new DiagnosticTranslator()->diagnosticFor(refusalFor($payload), strlen($payload));

    expect($diagnostic->offset)->toBeGreaterThanOrEqual(0)
        ->and($diagnostic->offset)->toBeLessThanOrEqual(strlen($payload))
        ->and($diagnostic->length)->toBeGreaterThanOrEqual(0)
        ->and($diagnostic->offset + $diagnostic->length)->toBeLessThanOrEqual(strlen($payload));
})->with([
    'truncated string' => 's:2:"ab"',
    'truncated array' => 'a:1:{',
    'empty payload' => '',
    'lone marker' => 's',
    'length overruns the payload' => 's:900:"ab";',
    'count overruns the payload' => 'a:99999:{}',
]);

/**
 * The privacy promise the API and the MCP tool both publish.
 *
 * Every message is assembled from templates plus integers, so a recognizable run of
 * bytes in the payload cannot come back out inside one. The type prefix is the sole
 * exception, and the grammar closes its alphabet.
 */
it('never quotes a byte of the submitted payload', function (string $payload) {
    $diagnostic = new DiagnosticTranslator()->diagnosticFor(refusalFor($payload), strlen($payload));

    expect($diagnostic?->message ?? '')->not->toContain('SECRET')
        ->and($diagnostic?->suggestion ?? '')->not->toContain('SECRET');
})->with([
    'in a malformed integer' => 'i:SECRET;',
    'in an unknown marker' => 'SECRET',
    'in a string that outruns its length' => 'a:1:{s:4:"SECRET";i:1;}',
    'in a class name' => 'O:6:"SECRET":0:{}',
    'in an escaped string' => 'S:2:"\\zzSECRET";',
    'after a complete value' => 'i:1;SECRET',
]);

/**
 * Every package code has to land somewhere, including ones this application cannot
 * reach today. A code added by a future release falls to the catch-all rather than
 * throwing an unhandled match.
 */
it('maps every package diagnostic code to a published category', function (DiagnosticCode $code) {
    $exception = new class(new Diagnostic($code, 'i:1;', 0, contextFor($code))) extends Exception implements SerializedException
    {
        public function __construct(private readonly Diagnostic $carried)
        {
            parent::__construct('refused');
        }

        public function diagnostic(): Diagnostic
        {
            return $this->carried;
        }
    };

    $translator = new DiagnosticTranslator;
    $diagnostic = $translator->diagnosticFor($exception, 4);

    expect($diagnostic === null || $diagnostic instanceof SyntaxDiagnostic)->toBeTrue()
        ->and($translator->errorCodeFor($exception))->toBeInstanceOf(ConversionErrorCode::class);
})->with(array_map(
    static fn (DiagnosticCode $code): array => [$code],
    DiagnosticCode::cases(),
));

/**
 * The facts each code needs, so a synthetic diagnostic can be built for it.
 *
 * @return array<string, string|int|bool|null>
 */
function contextFor(DiagnosticCode $code): array
{
    return match ($code) {
        DiagnosticCode::UnknownTypePrefix => ['prefix' => 'x'],
        DiagnosticCode::TruncatedPayload => ['expected' => ';'],
        DiagnosticCode::UnexpectedByte => ['expected' => ':', 'found' => 'x'],
        DiagnosticCode::MalformedValue => ['typeLabel' => 'integer', 'literal' => 'x'],
        DiagnosticCode::MalformedLength, DiagnosticCode::MalformedElementCount => ['literal' => 'x'],
        DiagnosticCode::ElementCountMismatch => ['structureLabel' => 'array', 'className' => null, 'declaredCount' => 2, 'actualCount' => 1],
        DiagnosticCode::ImpossibleElementCount => ['declaredCount' => '9', 'remainingByteCount' => 0],
        DiagnosticCode::UnclosedStructure => ['structureLabel' => 'array'],
        DiagnosticCode::NonScalarKey => ['keyTypeLabel' => 'array', 'keySlot' => 'an array key'],
        DiagnosticCode::RejectedByPhp => ['phpMessage' => 'nope'],
        DiagnosticCode::LengthMismatch => ['declaredByteLength' => 6, 'foundByteLength' => 5, 'prefix' => 's'],
        DiagnosticCode::DisallowedClass, DiagnosticCode::UnloadableClass, DiagnosticCode::UnrestorableClass => ['className' => 'stdClass'],
        DiagnosticCode::NonBackedEnum => ['caseName' => 'Suit::Hearts'],
        DiagnosticCode::NonFiniteFloat => ['literal' => 'NAN'],
        DiagnosticCode::MaxBytesExceeded => ['actualBytes' => 9, 'configuredLimit' => 4],
        DiagnosticCode::MaxDepthExceeded => ['actualDepth' => 9, 'configuredLimit' => 4],
        DiagnosticCode::MaxElementsExceeded => ['configuredLimit' => 4],
        default => [],
    };
}
