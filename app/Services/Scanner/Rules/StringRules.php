<?php

namespace App\Services\Scanner\Rules;

use App\Data\SyntaxDiagnostic;
use App\Services\Scanner\ScannerCursor;
use App\Services\Scanner\TokenReader;

/**
 * Everything written as `<length>:"<contents>"`.
 *
 * This family owns more than the `s:` and `S:` values: a class name, an enum
 * case name and a custom object's class are all the same quoted payload, so
 * {@see ContainerRules} and {@see OpaqueRules} depend on this family rather
 * than restating the rule. That dependency is the reason strings are their own
 * family instead of sitting with the other scalars.
 */
readonly class StringRules
{
    public function __construct(private TokenReader $reader) {}

    public function string(ScannerCursor $cursor, bool $escaped): ?SyntaxDiagnostic
    {
        $start = $cursor->position;
        $marker = $cursor->data[$start];
        $cursor->position = $start + 1;

        if (($delimiter = $this->reader->delimiter($cursor, $start, ':')) !== null) {
            return $delimiter;
        }

        $payload = $this->quotedPayload($cursor, $start, $escaped, $marker.':');

        if ($payload !== null) {
            return $payload;
        }

        return $this->reader->terminator($cursor, $start);
    }

    /**
     * Consume `<length>:"<contents>"` starting at the first length digit.
     *
     * The closing quote is found by walking the declared byte count, never by
     * searching for the next quote: a quote inside the payload is legal, and
     * `s:3:"a"b";` must scan cleanly.
     *
     * @param  string|null  $fixPrefix  Token prefix used to phrase a length correction, or null when no correction can be phrased.
     */
    public function quotedPayload(ScannerCursor $cursor, int $tokenStart, bool $escaped, ?string $fixPrefix): ?SyntaxDiagnostic
    {
        $lengthStart = $cursor->position;
        $declared = $cursor->unsignedDigits();

        if ($declared === false) {
            return $this->reader->diagnostics->nonNumericStringLength($lengthStart, $cursor->position, $tokenStart);
        }

        $lengthEnd = $cursor->position;

        if (($delimiter = $this->reader->delimiter($cursor, $tokenStart, ':')) !== null) {
            return $delimiter;
        }

        if (($delimiter = $this->reader->delimiter($cursor, $tokenStart, '"')) !== null) {
            return $delimiter;
        }

        $contentStart = $cursor->position;
        $expectedTerminator = $escaped
            ? $this->decodedEnd($cursor, $contentStart, $declared, $tokenStart)
            : $contentStart + $declared;

        if ($expectedTerminator instanceof SyntaxDiagnostic) {
            return $expectedTerminator;
        }

        if ($expectedTerminator >= $cursor->length || $cursor->data[$expectedTerminator] !== '"') {
            return $this->lengthMismatch(
                $cursor,
                $tokenStart,
                $lengthStart,
                $lengthEnd,
                $contentStart,
                $declared,
                $expectedTerminator,
                $escaped ? null : $fixPrefix,
            );
        }

        $cursor->position = $expectedTerminator + 1;

        return null;
    }

    /**
     * Walk `$declared` decoded bytes of an escaped payload and report where they end.
     *
     * Returns a diagnostic instead of an offset when an escape is not the two
     * hexadecimal digits `S:` requires.
     */
    private function decodedEnd(ScannerCursor $cursor, int $contentStart, int $declared, int $tokenStart): int|SyntaxDiagnostic
    {
        $data = $cursor->data;
        $length = $cursor->length;
        $scan = $contentStart;
        $decoded = 0;

        while ($scan < $length && $decoded < $declared) {
            if ($data[$scan] !== '\\') {
                $scan++;
                $decoded++;

                continue;
            }

            if ($scan + 2 >= $length || ctype_xdigit($data[$scan + 1]) === false || ctype_xdigit($data[$scan + 2]) === false) {
                return $this->reader->diagnostics->malformedEscape($scan, $length, $tokenStart);
            }

            $scan += 3;
            $decoded++;
        }

        return $scan;
    }

    /**
     * Explain a string whose declared byte length does not meet its closing quote.
     *
     * This covers both halves of the canonical WordPress search-and-replace
     * corruption: a length left too short for the new contents and one left too
     * long. It is also the one case where PHP's own offset is reproducible
     * exactly, since PHP always blames the byte where the quote was expected.
     */
    private function lengthMismatch(
        ScannerCursor $cursor,
        int $tokenStart,
        int $lengthStart,
        int $lengthEnd,
        int $contentStart,
        int $declared,
        int $expectedTerminator,
        ?string $fixPrefix,
    ): SyntaxDiagnostic {
        $closingQuote = strpos($cursor->data, '";', $contentStart);
        $actual = $closingQuote === false ? null : $closingQuote - $contentStart;

        /**
         * Without a closing quote anywhere ahead, the payload is truncated
         * rather than mismeasured, and saying so is more useful than naming a
         * length that cannot be corrected.
         */
        if ($actual === null) {
            return $this->reader->diagnostics->unterminatedString($contentStart, $cursor->length, $tokenStart, $declared, $expectedTerminator);
        }

        return $this->reader->diagnostics->stringLengthMismatch(
            $tokenStart,
            $contentStart,
            $declared,
            $actual,
            $lengthStart,
            $lengthEnd,
            $expectedTerminator,
            $fixPrefix,
        );
    }
}
