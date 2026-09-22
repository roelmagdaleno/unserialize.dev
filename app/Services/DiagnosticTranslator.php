<?php

namespace App\Services;

use App\Data\SyntaxDiagnostic;
use App\Enums\ConversionErrorCode;
use App\Enums\SyntaxErrorCode;
use Serialized\Diagnostics\Diagnostic;
use Serialized\Diagnostics\DiagnosticCode;
use Serialized\Exceptions\InvalidSerializedDataException;
use Serialized\Exceptions\JsonEncodingException;
use Serialized\Exceptions\LimitExceededException;
use Serialized\Exceptions\SerializedException;
use Serialized\Exceptions\UnrepresentableValueException;
use Serialized\Exceptions\UnsafeSerializedDataException;

/**
 * Restates a package refusal in this application's published vocabulary.
 *
 * The package names 27 kinds of problem; this application publishes 11, in an enum
 * that travels out through the HTTP API, the MCP output schema and
 * `public/openapi.json`. Collapsing one onto the other happens here and nowhere
 * else, so the published contract has a single place to drift from.
 *
 * Two rules hold for every sentence built here:
 *
 * - No byte of the submitted payload is ever quoted. Both the API and the MCP tool
 *   promise that submitted data is not returned, so the package's own `reason` and
 *   `fix` -- which interpolate the offending byte, literal or class name -- are
 *   never published. Only integers and the package's own closed label vocabularies
 *   reach a message. The one letter that does travel is a string's type prefix,
 *   whose alphabet the grammar closes and which this application already publishes.
 * - Every offset and span is forced inside the payload, so
 *   {@see DiagnosticPresenter} can index it without bounds-checking again.
 */
class DiagnosticTranslator
{
    /**
     * Refusals that name a whole payload rather than a byte inside it.
     *
     * Each one is published without a diagnostic, which is what this application has
     * always done for an object, an oversized input and a value JSON cannot carry.
     *
     * @var list<DiagnosticCode>
     */
    private const array UNLOCATED = [
        DiagnosticCode::DisallowedClass,
        DiagnosticCode::UnloadableClass,
        DiagnosticCode::UnrestorableClass,
        DiagnosticCode::NonUtf8String,
        DiagnosticCode::NonBackedEnum,
        DiagnosticCode::NonFiniteFloat,
        DiagnosticCode::MaxBytesExceeded,
    ];

    /**
     * Why the conversion was refused.
     */
    public function errorCodeFor(SerializedException $exception): ConversionErrorCode
    {
        $code = $exception->diagnostic()?->code;

        return match (true) {
            $exception instanceof JsonEncodingException => ConversionErrorCode::EncodingFailed,
            $exception instanceof UnrepresentableValueException => $this->unrepresentableErrorCode($code),
            $exception instanceof LimitExceededException => $this->limitErrorCode($code),
            $exception instanceof UnsafeSerializedDataException => $this->unsafeErrorCode($code),
            $exception instanceof InvalidSerializedDataException => ConversionErrorCode::InvalidInput,
            default => ConversionErrorCode::InvalidInput,
        };
    }

    /**
     * Where the payload broke, or null when the failure names no byte.
     */
    public function diagnosticFor(SerializedException $exception, int $payloadBytes): ?SyntaxDiagnostic
    {
        $diagnostic = $exception->diagnostic();

        if ($diagnostic === null || in_array($diagnostic->code, self::UNLOCATED, strict: true)) {
            return null;
        }

        return $this->translate($diagnostic, $payloadBytes);
    }

    /**
     * Restate one package diagnostic as the shape every surface publishes.
     */
    private function translate(Diagnostic $diagnostic, int $payloadBytes): SyntaxDiagnostic
    {
        if ($diagnostic->code === DiagnosticCode::LengthMismatch) {
            return $this->stringLengthMismatch($diagnostic, $payloadBytes);
        }

        $offset = $this->clampOffset($diagnostic->offset, $payloadBytes);

        return new SyntaxDiagnostic(
            $this->codeFor($diagnostic),
            $offset,
            $this->clampSpan($offset, $this->spanFor($diagnostic, $payloadBytes), $payloadBytes),
            $this->messageFor($diagnostic, $offset, $payloadBytes),
            $this->suggestionFor($diagnostic),
        );
    }

    /**
     * Which limit was exceeded.
     *
     * A byte limit is refused before the payload is read at all, so it keeps its own
     * error code; a depth limit is the category this application has always reported
     * separately from a syntax error.
     */
    private function limitErrorCode(?DiagnosticCode $code): ConversionErrorCode
    {
        return match ($code) {
            DiagnosticCode::MaxBytesExceeded => ConversionErrorCode::InputTooLarge,
            DiagnosticCode::MaxDepthExceeded => ConversionErrorCode::DepthLimitExceeded,
            default => ConversionErrorCode::InvalidInput,
        };
    }

    /**
     * Whether an unsafe payload names a class this application will not restore.
     *
     * Every case the package raises here is about a class, and no class is ever
     * allowed, so they all reduce to one answer.
     */
    private function unsafeErrorCode(?DiagnosticCode $code): ConversionErrorCode
    {
        return ConversionErrorCode::UnsupportedObject;
    }

    /**
     * Whether a value JSON cannot carry can be blamed on a byte.
     *
     * A value containing itself is the one case with a location to report: it is a
     * structural problem the caller can find and fix in the payload, so it is reported
     * as invalid input rather than as an encoding failure with nothing to point at.
     */
    private function unrepresentableErrorCode(?DiagnosticCode $code): ConversionErrorCode
    {
        return match ($code) {
            DiagnosticCode::ContainsReference => ConversionErrorCode::InvalidInput,
            default => ConversionErrorCode::EncodingFailed,
        };
    }

    /**
     * The published category for one package code.
     */
    private function codeFor(Diagnostic $diagnostic): SyntaxErrorCode
    {
        return match ($diagnostic->code) {
            DiagnosticCode::EmptyPayload,
            DiagnosticCode::TruncatedPayload,
            DiagnosticCode::UnclosedStructure => SyntaxErrorCode::UnexpectedEnd,

            DiagnosticCode::UnknownTypePrefix => SyntaxErrorCode::UnknownTypeMarker,

            DiagnosticCode::UnexpectedByte => $this->expected($diagnostic) === ';'
                ? SyntaxErrorCode::MissingTerminator
                : SyntaxErrorCode::MissingDelimiter,

            DiagnosticCode::MalformedValue,
            DiagnosticCode::MalformedLength,
            DiagnosticCode::MalformedElementCount => SyntaxErrorCode::MalformedNumber,

            DiagnosticCode::ElementCountMismatch,
            DiagnosticCode::ImpossibleElementCount => SyntaxErrorCode::ArrayCountMismatch,

            DiagnosticCode::NonScalarKey => SyntaxErrorCode::InvalidArrayKey,
            DiagnosticCode::TrailingBytes => SyntaxErrorCode::TrailingData,
            DiagnosticCode::LengthMismatch => SyntaxErrorCode::StringLengthMismatch,
            DiagnosticCode::MaxDepthExceeded => SyntaxErrorCode::DepthLimitExceeded,

            default => SyntaxErrorCode::UnknownSyntaxError,
        };
    }

    /**
     * How many bytes the panel should mark.
     *
     * A zero-length span marks a position rather than a range, which is what a
     * payload that simply ran out has to report.
     */
    private function spanFor(Diagnostic $diagnostic, int $payloadBytes): int
    {
        return match ($diagnostic->code) {
            DiagnosticCode::UnknownTypePrefix,
            DiagnosticCode::UnexpectedByte,
            DiagnosticCode::UnbalancedClose => 1,

            DiagnosticCode::MalformedValue,
            DiagnosticCode::MalformedLength,
            DiagnosticCode::MalformedElementCount => strlen($this->string($diagnostic, 'literal')),

            DiagnosticCode::ElementCountMismatch => $this->arrayHeaderWidth($diagnostic, 'declaredCount'),
            DiagnosticCode::ImpossibleElementCount => $this->arrayHeaderWidth($diagnostic, 'declaredCount'),

            DiagnosticCode::TrailingBytes => $payloadBytes - $diagnostic->offset,

            default => 0,
        };
    }

    /**
     * The sentence shown to the caller.
     */
    private function messageFor(Diagnostic $diagnostic, int $offset, int $payloadBytes): string
    {
        return match ($diagnostic->code) {
            DiagnosticCode::EmptyPayload => 'The value is empty.',

            DiagnosticCode::TruncatedPayload => sprintf(
                'The value ends before the expected "%s".',
                $this->expected($diagnostic),
            ),

            DiagnosticCode::UnclosedStructure => sprintf(
                'The array starting at byte %d is missing its closing brace.',
                $offset,
            ),

            DiagnosticCode::UnknownTypePrefix => sprintf('Byte %d is not a valid type marker.', $offset),

            DiagnosticCode::UnexpectedByte => sprintf(
                'A "%s" was expected at byte %d.',
                $this->expected($diagnostic),
                $offset,
            ),

            DiagnosticCode::MalformedValue => $this->malformedValueMessage($diagnostic, $offset),

            DiagnosticCode::MalformedLength => sprintf('The byte length at byte %d is not a number.', $offset),

            DiagnosticCode::MalformedElementCount => sprintf(
                'The element count at byte %d is not a number.',
                $offset,
            ),

            DiagnosticCode::ElementCountMismatch => sprintf(
                'The array starting at byte %d declares %d elements but contains %d.',
                $offset,
                $this->integer($diagnostic, 'declaredCount'),
                $this->integer($diagnostic, 'actualCount'),
            ),

            DiagnosticCode::ImpossibleElementCount => sprintf(
                'The array starting at byte %d declares %d elements, which the %d remaining bytes cannot hold.',
                $offset,
                $this->integer($diagnostic, 'declaredCount'),
                $this->integer($diagnostic, 'remainingByteCount'),
            ),

            DiagnosticCode::NonScalarKey => sprintf(
                'The array key at byte %d is neither an integer nor a string.',
                $offset,
            ),

            DiagnosticCode::TrailingBytes => sprintf(
                'The value is complete at byte %d but %d more bytes follow.',
                $offset,
                $payloadBytes - $offset,
            ),

            DiagnosticCode::UnbalancedClose => sprintf(
                'The closing brace at byte %d closes an array that was never opened.',
                $offset,
            ),

            DiagnosticCode::ContainsReference => sprintf(
                'The reference at byte %d points back into a value that contains it, so the value never ends.',
                $offset,
            ),

            DiagnosticCode::MaxDepthExceeded => sprintf(
                'The value nests deeper than the %d levels this converter decodes.',
                $this->integer($diagnostic, 'configuredLimit'),
            ),

            DiagnosticCode::MaxElementsExceeded => sprintf(
                'The value holds more than the %d elements this converter decodes.',
                $this->integer($diagnostic, 'configuredLimit'),
            ),

            default => sprintf('PHP stopped reading this value at byte %d.', $offset),
        };
    }

    /**
     * The correction offered alongside the message, when there is one to offer.
     */
    private function suggestionFor(Diagnostic $diagnostic): ?string
    {
        return match ($diagnostic->code) {
            DiagnosticCode::UnknownTypePrefix => 'Values start with N, b, i, d, s, a, or O.',

            DiagnosticCode::UnexpectedByte => sprintf(
                'Insert the missing "%s".',
                $this->expected($diagnostic),
            ),

            DiagnosticCode::MalformedValue => $this->malformedValueSuggestion($diagnostic),

            DiagnosticCode::MalformedLength => 'Write the byte length as a number, as in s:6:"Chrome";.',

            DiagnosticCode::MalformedElementCount => 'Write the element count as a number, as in a:2:{...}.',

            DiagnosticCode::ElementCountMismatch => sprintf(
                'Change `a:%d:` to `a:%d:`.',
                $this->integer($diagnostic, 'declaredCount'),
                $this->integer($diagnostic, 'actualCount'),
            ),

            DiagnosticCode::ImpossibleElementCount => 'Correct the declared count to the number of elements the array holds.',

            DiagnosticCode::NonScalarKey => 'Array keys use i: or s:.',

            DiagnosticCode::TrailingBytes => sprintf(
                'Remove everything from byte %d onwards.',
                $diagnostic->offset,
            ),

            DiagnosticCode::UnbalancedClose => 'Remove the brace, or add the array header it was meant to close.',

            DiagnosticCode::ContainsReference => 'Break the loop before serializing: a value that contains itself has no JSON form.',

            DiagnosticCode::MaxDepthExceeded,
            DiagnosticCode::MaxElementsExceeded => 'Convert a smaller part of the structure.',

            default => null,
        };
    }

    /**
     * Restate a declared string length that does not match the bytes present.
     *
     * The package blames the declared number; this application has always blamed the
     * whole string, from its type marker through its closing quote, because that is
     * the run of bytes a reader has to look at to see the disagreement. Both ends are
     * recovered from the declaration's own width rather than by reading the payload.
     */
    private function stringLengthMismatch(Diagnostic $diagnostic, int $payloadBytes): SyntaxDiagnostic
    {
        $prefix = $this->nullableString($diagnostic, 'prefix');
        $declared = $this->integer($diagnostic, 'declaredByteLength');
        $found = $this->integer($diagnostic, 'foundByteLength');

        if ($prefix === null) {
            $offset = $this->clampOffset($diagnostic->offset, $payloadBytes);

            return new SyntaxDiagnostic(
                SyntaxErrorCode::StringLengthMismatch,
                $offset,
                0,
                sprintf('The name at byte %d declares %d bytes but %d bytes precede the closing quote.', $offset, $declared, $found),
                sprintf('Change the declared length from %d to %d.', $declared, $found),
            );
        }

        /**
         * The package blames the first digit of the declared length, which sits just
         * past the type marker and its colon. Stepping back over those two reaches the
         * marker itself, and the span runs from there to the closing quote.
         */
        $markerWidth = strlen($prefix) + 1;
        $offset = $this->clampOffset($diagnostic->offset - $markerWidth, $payloadBytes);
        $span = $markerWidth + strlen((string) $declared) + 1 + 1 + $found + 1;

        return new SyntaxDiagnostic(
            SyntaxErrorCode::StringLengthMismatch,
            $offset,
            $this->clampSpan($offset, $span, $payloadBytes),
            sprintf(
                'The string starting at byte %d declares %d bytes but %d bytes precede the closing quote.',
                $offset,
                $declared,
                $found,
            ),
            sprintf('Change `%1$s:%2$d:` to `%1$s:%3$d:`.', $prefix, $declared, $found),
        );
    }

    /**
     * Name the malformed literal by the kind of value it was meant to be.
     */
    private function malformedValueMessage(Diagnostic $diagnostic, int $offset): string
    {
        return match ($this->string($diagnostic, 'typeLabel')) {
            'integer' => sprintf('The integer at byte %d has no digits.', $offset),
            'boolean' => sprintf('The boolean at byte %d must be 0 or 1.', $offset),
            'float' => sprintf('The float at byte %d has no digits.', $offset),
            'string' => sprintf('The escape at byte %d is not two hexadecimal digits.', $offset),
            default => sprintf('The value at byte %d is malformed.', $offset),
        };
    }

    /**
     * Spell the shape the malformed literal should have taken.
     */
    private function malformedValueSuggestion(Diagnostic $diagnostic): ?string
    {
        return match ($this->string($diagnostic, 'typeLabel')) {
            'integer' => 'Write integers as i:42; or i:-42;.',
            'boolean' => 'Use b:0; for false and b:1; for true.',
            'float' => 'Write floats as d:3.5;, d:INF;, d:-INF;, or d:NAN;.',
            'string' => 'Write each escape as a backslash and two hexadecimal digits.',
            default => null,
        };
    }

    /**
     * How wide the `a:COUNT:` header carrying a given count is.
     */
    private function arrayHeaderWidth(Diagnostic $diagnostic, string $key): int
    {
        return strlen(sprintf('a:%d:', $this->integer($diagnostic, $key)));
    }

    /**
     * The byte the grammar required, drawn from the package's own closed vocabulary.
     */
    private function expected(Diagnostic $diagnostic): string
    {
        return $this->string($diagnostic, 'expected');
    }

    /**
     * Read a textual fact the package recorded.
     */
    private function string(Diagnostic $diagnostic, string $key): string
    {
        return (string) ($diagnostic->context[$key] ?? '');
    }

    /**
     * Read a fact that is absent for some payloads, such as a class name's missing prefix.
     */
    private function nullableString(Diagnostic $diagnostic, string $key): ?string
    {
        $value = $diagnostic->context[$key] ?? null;

        return is_string($value) ? $value : null;
    }

    /**
     * Read a numeric fact the package recorded.
     */
    private function integer(Diagnostic $diagnostic, string $key): int
    {
        return (int) ($diagnostic->context[$key] ?? 0);
    }

    /**
     * Force an offset inside the payload.
     */
    private function clampOffset(int $offset, int $payloadBytes): int
    {
        return max(0, min($offset, $payloadBytes));
    }

    /**
     * Force a span to end inside the payload.
     */
    private function clampSpan(int $offset, int $span, int $payloadBytes): int
    {
        return max(0, min($span, $payloadBytes - $offset));
    }
}
