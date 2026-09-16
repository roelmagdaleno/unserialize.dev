<?php

namespace App\Services\Scanner;

use App\Data\SyntaxDiagnostic;
use App\Enums\SyntaxErrorCode;

/**
 * Every sentence the scanner can say about a broken payload.
 *
 * The grammar decides *that* something is wrong and *where*; this class decides
 * how to say it. Keeping the two apart means a wording change never touches a
 * byte offset, and an offset change never touches a message.
 *
 * Two rules hold for every method here:
 *
 * - Parameters are integers and scalars only, never a cursor. A cursor could
 *   have moved between the moment the grammar found the problem and the moment
 *   the message is built, and the resulting offset would be silently wrong.
 * - Where a correction is offered, the `suggestion` and the `fix` that backs it
 *   are built in the same method. A published suggestion must come with an edit
 *   that provably changes the complaint, and co-locating them makes that
 *   structural rather than a convention someone has to remember.
 *
 * No method interpolates a byte taken from the submitted value, which is what
 * lets these strings travel out through the HTTP API and the MCP tool.
 */
readonly class SyntaxDiagnosticFactory
{
    /**
     * Nothing was submitted.
     */
    public function emptyValue(): SyntaxDiagnostic
    {
        return new SyntaxDiagnostic(
            SyntaxErrorCode::UnexpectedEnd,
            0,
            0,
            'The value is empty.',
        );
    }

    /**
     * A complete value is followed by bytes that are not part of it.
     */
    public function trailingData(int $position, int $length): SyntaxDiagnostic
    {
        return new SyntaxDiagnostic(
            SyntaxErrorCode::TrailingData,
            $position,
            $length - $position,
            sprintf('The value is complete at byte %d but %d more bytes follow.', $position, $length - $position),
            sprintf('Remove everything from byte %d onwards.', $position),
            contextStart: $position,
        );
    }

    /**
     * The value nests past the depth the scanner inspects.
     */
    public function depthLimitExceeded(int $position): SyntaxDiagnostic
    {
        return new SyntaxDiagnostic(
            SyntaxErrorCode::DepthLimitExceeded,
            $position,
            0,
            'The value nests deeper than the scanner inspects.',
            contextStart: $position,
        );
    }

    /**
     * A value starts with a byte that names no type.
     */
    public function unknownTypeMarker(int $position): SyntaxDiagnostic
    {
        return new SyntaxDiagnostic(
            SyntaxErrorCode::UnknownTypeMarker,
            $position,
            1,
            sprintf('Byte %d is not a valid type marker.', $position),
            'Values start with N, b, i, d, s, a, or O.',
            contextStart: $position,
        );
    }

    /**
     * A boolean carries something other than `0` or `1`.
     */
    public function nonBinaryBoolean(int $position, int $start): SyntaxDiagnostic
    {
        return new SyntaxDiagnostic(
            SyntaxErrorCode::MalformedNumber,
            $position,
            1,
            sprintf('The boolean at byte %d must be 0 or 1.', $start),
            'Use b:0; for false and b:1; for true.',
            contextStart: $start,
        );
    }

    /**
     * An integer token declares no digits.
     */
    public function integerWithoutDigits(int $digitsStart, int $position, int $start): SyntaxDiagnostic
    {
        return new SyntaxDiagnostic(
            SyntaxErrorCode::MalformedNumber,
            $digitsStart,
            max(1, $position - $digitsStart),
            sprintf('The integer at byte %d has no digits.', $start),
            'Write integers as i:42; or i:-42;.',
            contextStart: $start,
        );
    }

    /**
     * A float token declares no digits.
     */
    public function floatWithoutDigits(int $numberStart, int $position, int $start): SyntaxDiagnostic
    {
        return new SyntaxDiagnostic(
            SyntaxErrorCode::MalformedNumber,
            $numberStart,
            max(1, $position - $numberStart),
            sprintf('The float at byte %d has no digits.', $start),
            'Write floats as d:3.5;, d:INF;, d:-INF;, or d:NAN;.',
            contextStart: $start,
        );
    }

    /**
     * A float's exponent declares no digits.
     */
    public function floatExponentWithoutDigits(int $numberStart, int $position, int $start): SyntaxDiagnostic
    {
        return new SyntaxDiagnostic(
            SyntaxErrorCode::MalformedNumber,
            $numberStart,
            max(1, $position - $numberStart),
            sprintf('The float exponent at byte %d has no digits.', $start),
            'Write an exponent as d:1.0e10;.',
            contextStart: $start,
        );
    }

    /**
     * A string's declared byte length is not a number.
     */
    public function nonNumericStringLength(int $lengthStart, int $position, int $tokenStart): SyntaxDiagnostic
    {
        return new SyntaxDiagnostic(
            SyntaxErrorCode::MalformedNumber,
            $lengthStart,
            max(1, $position - $lengthStart),
            sprintf('The byte length at byte %d is not a number.', $lengthStart),
            'Declare the length in digits, for example s:5:"hello";.',
            contextStart: $tokenStart,
        );
    }

    /**
     * An escaped string holds an escape that is not two hex digits.
     */
    public function malformedEscape(int $position, int $length, int $tokenStart): SyntaxDiagnostic
    {
        return new SyntaxDiagnostic(
            SyntaxErrorCode::MalformedNumber,
            $position,
            min(3, $length - $position),
            sprintf('The escape at byte %d is not two hexadecimal digits.', $position),
            'Escaped strings use \\41 style two-digit escapes.',
            contextStart: $tokenStart,
        );
    }

    /**
     * A string whose declared length never meets a closing quote at all.
     *
     * Saying the payload is truncated is more useful than naming a length that
     * cannot be corrected, so no suggestion is offered.
     */
    public function unterminatedString(int $contentStart, int $length, int $tokenStart, int $declared, int $expectedTerminator): SyntaxDiagnostic
    {
        return new SyntaxDiagnostic(
            SyntaxErrorCode::UnexpectedEnd,
            $contentStart,
            $length - $contentStart,
            sprintf(
                'The string starting at byte %d declares %d bytes and never closes.',
                $tokenStart,
                $declared,
            ),
            contextStart: $tokenStart,
            expectedTerminatorOffset: $expectedTerminator,
        );
    }

    /**
     * A string whose declared byte length disagrees with its closing quote.
     *
     * @param  string|null  $fixPrefix  Token prefix used to phrase the correction, or null when none can be phrased.
     */
    public function stringLengthMismatch(
        int $tokenStart,
        int $contentStart,
        int $declared,
        int $actual,
        int $lengthStart,
        int $lengthEnd,
        int $expectedTerminator,
        ?string $fixPrefix,
    ): SyntaxDiagnostic {
        $suggestion = null;
        $fix = null;

        if ($fixPrefix !== null) {
            $suggestion = sprintf('Change `%s%d:` to `%s%d:`.', $fixPrefix, $declared, $fixPrefix, $actual);
            $fix = [
                'offset' => $lengthStart,
                'length' => $lengthEnd - $lengthStart,
                'replacement' => (string) $actual,
            ];
        }

        /**
         * The span runs from the type marker through the closing quote, so the
         * declared length and the bytes it fails to describe are framed together.
         */
        $lastByte = $contentStart + $actual;

        return new SyntaxDiagnostic(
            SyntaxErrorCode::StringLengthMismatch,
            $tokenStart,
            max(1, $lastByte - $tokenStart + 1),
            sprintf(
                'The string starting at byte %d declares %d bytes but %d bytes precede the closing quote.',
                $tokenStart,
                $declared,
                $actual,
            ),
            $suggestion,
            contextStart: $tokenStart,
            expectedTerminatorOffset: $expectedTerminator,
            fix: $fix,
        );
    }

    /**
     * An array's declared element count is not a number.
     */
    public function nonNumericElementCount(int $countStart, int $position, int $start): SyntaxDiagnostic
    {
        return new SyntaxDiagnostic(
            SyntaxErrorCode::MalformedNumber,
            $countStart,
            max(1, $position - $countStart),
            sprintf('The element count at byte %d is not a number.', $countStart),
            'Declare the count in digits, for example a:2:{...}.',
            contextStart: $start,
        );
    }

    /**
     * An array holds fewer elements than it declares.
     */
    public function arrayShorterThanDeclared(int $start, int $position, int $declared, int $seen, int $countStart, int $countEnd): SyntaxDiagnostic
    {
        return new SyntaxDiagnostic(
            SyntaxErrorCode::ArrayCountMismatch,
            $start,
            $position - $start + 1,
            sprintf(
                'The array starting at byte %d declares %d elements but contains %d.',
                $start,
                $declared,
                $seen,
            ),
            sprintf('Change `a:%d:` to `a:%d:`.', $declared, $seen),
            contextStart: $start,
            fix: [
                'offset' => $countStart,
                'length' => $countEnd - $countStart,
                'replacement' => (string) $seen,
            ],
        );
    }

    /**
     * An array holding more elements than it declares.
     *
     * @param  int  $surplusEnd  Where the lookahead stopped, clamped to the payload.
     * @param  int|null  $total  Real element total, or null when the remainder did not parse and the count cannot be named.
     */
    public function arrayLongerThanDeclared(
        int $position,
        int $surplusEnd,
        int $start,
        int $countStart,
        int $countEnd,
        int $declared,
        ?int $total,
    ): SyntaxDiagnostic {
        if ($total === null) {
            return new SyntaxDiagnostic(
                SyntaxErrorCode::ArrayCountMismatch,
                $position,
                max(1, $surplusEnd - $position),
                sprintf(
                    'The array starting at byte %d declares %d elements but more follow at byte %d.',
                    $start,
                    $declared,
                    $position,
                ),
                contextStart: $start,
            );
        }

        return new SyntaxDiagnostic(
            SyntaxErrorCode::ArrayCountMismatch,
            $position,
            max(1, $surplusEnd - $position),
            sprintf(
                'The array starting at byte %d declares %d elements but contains %d.',
                $start,
                $declared,
                $total,
            ),
            sprintf('Change `a:%d:` to `a:%d:`.', $declared, $total),
            contextStart: $start,
            fix: [
                'offset' => $countStart,
                'length' => $countEnd - $countStart,
                'replacement' => (string) $total,
            ],
        );
    }

    /**
     * An array key is neither an integer nor a string.
     *
     * @param  int|false  $tokenEnd  Offset of the `;` ending the offending token, as returned by `strpos()`.
     */
    public function invalidArrayKey(int $position, int|false $tokenEnd, int $contextStart): SyntaxDiagnostic
    {
        return new SyntaxDiagnostic(
            SyntaxErrorCode::InvalidArrayKey,
            $position,
            $tokenEnd === false ? 1 : $tokenEnd - $position + 1,
            sprintf('The array key at byte %d is neither an integer nor a string.', $position),
            'Array keys use i: or s:.',
            contextStart: $contextStart,
        );
    }

    /**
     * An object's declared property count is not a number.
     */
    public function nonNumericPropertyCount(int $countStart, int $position, int $start): SyntaxDiagnostic
    {
        return new SyntaxDiagnostic(
            SyntaxErrorCode::MalformedNumber,
            $countStart,
            max(1, $position - $countStart),
            sprintf('The property count at byte %d is not a number.', $countStart),
            'Declare the count in digits.',
            contextStart: $start,
        );
    }

    /**
     * A custom-serialized object's declared payload length is not a number.
     */
    public function nonNumericPayloadLength(int $position, int $start): SyntaxDiagnostic
    {
        return new SyntaxDiagnostic(
            SyntaxErrorCode::MalformedNumber,
            $position,
            1,
            sprintf('The payload length at byte %d is not a number.', $position),
            'Declare the length in digits.',
            contextStart: $start,
        );
    }

    /**
     * A reference token names no target value.
     */
    public function referenceWithoutTarget(int $digitsStart, int $position, int $start): SyntaxDiagnostic
    {
        return new SyntaxDiagnostic(
            SyntaxErrorCode::MalformedNumber,
            $digitsStart,
            max(1, $position - $digitsStart),
            sprintf('The reference at byte %d has no target.', $start),
            'References count values from 1, for example r:2;.',
            contextStart: $start,
        );
    }

    /**
     * A reference points past the values the payload defines.
     *
     * The whole token is framed, because PHP accepts a reference token before
     * deciding it points nowhere and then blames the byte after it.
     */
    public function referenceToNothing(int $start, int $position): SyntaxDiagnostic
    {
        return new SyntaxDiagnostic(
            SyntaxErrorCode::MalformedNumber,
            $start,
            $position - $start,
            sprintf('The reference at byte %d does not point at a value.', $start),
            'References count values from 1, for example r:2;.',
            contextStart: $start,
        );
    }

    /**
     * An expected delimiter or terminator is not where it should be.
     */
    public function missingByte(string $byte, int $position, int $contextStart): SyntaxDiagnostic
    {
        return new SyntaxDiagnostic(
            $byte === ';' ? SyntaxErrorCode::MissingTerminator : SyntaxErrorCode::MissingDelimiter,
            $position,
            1,
            sprintf('A "%s" was expected at byte %d.', $byte, $position),
            sprintf('Insert the missing "%s".', $byte),
            contextStart: $contextStart,
        );
    }

    /**
     * The payload stops where another value was expected.
     */
    public function valueEndedEarly(int $length, int $contextStart): SyntaxDiagnostic
    {
        return $this->endedEarly($length, $contextStart, 'The value ends where another value was expected.');
    }

    /**
     * The payload stops between a boolean marker and its value.
     */
    public function booleanEndedBeforeValue(int $length, int $contextStart): SyntaxDiagnostic
    {
        return $this->endedEarly($length, $contextStart, 'The boolean ends before its value.');
    }

    /**
     * The payload stops before an array closes.
     */
    public function arrayMissingClosingBrace(int $length, int $start): SyntaxDiagnostic
    {
        return $this->endedEarly(
            $length,
            $start,
            sprintf('The array starting at byte %d is missing its closing brace.', $start),
        );
    }

    /**
     * The payload stops where an array key was expected.
     */
    public function arrayEndedBeforeKey(int $length, int $contextStart): SyntaxDiagnostic
    {
        return $this->endedEarly($length, $contextStart, 'The array ends where a key was expected.');
    }

    /**
     * The payload stops before a custom-serialized payload closes.
     */
    public function customObjectEndedBeforeBrace(int $length, int $contextStart): SyntaxDiagnostic
    {
        return $this->endedEarly($length, $contextStart, 'The custom-serialized payload ends before its closing brace.');
    }

    /**
     * The payload stops before a specific expected byte.
     */
    public function endedBeforeByte(string $byte, int $length, int $contextStart): SyntaxDiagnostic
    {
        return $this->endedEarly(
            $length,
            $contextStart,
            sprintf('The value ends before the expected "%s".', $byte),
        );
    }

    /**
     * A payload that stops where the grammar still expected something.
     *
     * The region is anchored at the end of the payload with zero length: there
     * is no byte to highlight, only a place where one is missing.
     */
    private function endedEarly(int $length, int $contextStart, string $message): SyntaxDiagnostic
    {
        return new SyntaxDiagnostic(
            SyntaxErrorCode::UnexpectedEnd,
            $length,
            0,
            $message,
            contextStart: $contextStart,
        );
    }
}
