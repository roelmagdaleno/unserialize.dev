<?php

namespace App\Services;

use App\Data\ScanOutcome;
use App\Data\SyntaxDiagnostic;
use App\Enums\SyntaxErrorCode;

/**
 * Recursive-descent scanner over the PHP serialization grammar.
 *
 * The scanner exists to explain a failure, never to decide one. It is consulted
 * only after `unserialize()` has already rejected a payload, so a bug in this
 * grammar can degrade a message but can never reject a value PHP accepts.
 *
 * Two limits are deliberate rather than accidental:
 *
 * - Exact offset agreement with PHP is not achievable in general. PHP reports
 *   wherever its own lexer stopped, which is the element start for `b:2;` and
 *   one byte past the token for `r:1;`. The guarantee this class supports is
 *   containment: the byte PHP blames falls inside {@see SyntaxDiagnostic::claimInterval()}.
 *   Exact equality holds only for string length mismatches and trailing data.
 * - `E:`, `C:`, `r:`, and `R:` are validated for shape only. Their real validity
 *   depends on class resolution and PHP's internal value numbering, and
 *   reproducing either on untrusted input is a larger accuracy risk than
 *   declining to judge them.
 * - A suggested string length is measured to the first `";` ahead of the
 *   contents. When the contents themselves hold that pair the suggestion
 *   restores a value that decodes rather than the value the author meant, since
 *   nothing in a corrupted payload records the original intent. The message
 *   always names the same byte count it suggests, so the two never disagree.
 */
class SerializedScanner
{
    /**
     * Above `unserialize()`'s own limit on purpose: depth is diagnosed from
     * PHP's warning, never by this scanner guessing at the boundary.
     */
    public const int MAX_DEPTH = 1024;

    /**
     * Long enough for any real length, short enough that `(int)` cannot overflow.
     */
    private const int MAX_LENGTH_DIGITS = 18;

    /**
     * Scan a payload and report the first problem that breaks the grammar.
     */
    public function scan(string $data): ScanOutcome
    {
        $length = strlen($data);

        if ($length === 0) {
            return ScanOutcome::invalid(new SyntaxDiagnostic(
                SyntaxErrorCode::UnexpectedEnd,
                0,
                0,
                'The value is empty.',
            ));
        }

        $position = 0;
        $unverifiable = false;
        $diagnostic = $this->value($data, $length, $position, 0, $unverifiable);

        if ($diagnostic !== null && $diagnostic->code === SyntaxErrorCode::DepthLimitExceeded) {
            return ScanOutcome::unverifiable();
        }

        if ($diagnostic !== null) {
            return ScanOutcome::invalid($diagnostic);
        }

        if ($position < $length) {
            return ScanOutcome::invalid(new SyntaxDiagnostic(
                SyntaxErrorCode::TrailingData,
                $position,
                $length - $position,
                sprintf('The value is complete at byte %d but %d more bytes follow.', $position, $length - $position),
                sprintf('Remove everything from byte %d onwards.', $position),
                contextStart: $position,
            ));
        }

        return $unverifiable ? ScanOutcome::unverifiable($position) : ScanOutcome::valid($position);
    }

    /**
     * Consume one serialized value starting at the cursor.
     */
    private function value(string $data, int $length, int &$position, int $depth, bool &$unverifiable): ?SyntaxDiagnostic
    {
        if ($depth > self::MAX_DEPTH) {
            $unverifiable = true;

            return new SyntaxDiagnostic(
                SyntaxErrorCode::DepthLimitExceeded,
                $position,
                0,
                'The value nests deeper than the scanner inspects.',
                contextStart: $position,
            );
        }

        if ($position >= $length) {
            return $this->unexpectedEnd($length, $position, 'The value ends where another value was expected.');
        }

        return match ($data[$position]) {
            'N' => $this->nullValue($data, $length, $position),
            'b' => $this->boolean($data, $length, $position),
            'i' => $this->integer($data, $length, $position),
            'd' => $this->double($data, $length, $position),
            's' => $this->string($data, $length, $position, false),
            'S' => $this->string($data, $length, $position, true),
            'a' => $this->arrayValue($data, $length, $position, $depth, $unverifiable),
            'O' => $this->objectValue($data, $length, $position, $depth, $unverifiable),
            'C' => $this->customObject($data, $length, $position, $unverifiable),
            'E' => $this->enumValue($data, $length, $position, $unverifiable),
            'r', 'R' => $this->reference($data, $length, $position, $unverifiable),
            default => new SyntaxDiagnostic(
                SyntaxErrorCode::UnknownTypeMarker,
                $position,
                1,
                sprintf('Byte %d is not a valid type marker.', $position),
                'Values start with N, b, i, d, s, a, or O.',
                contextStart: $position,
            ),
        };
    }

    private function nullValue(string $data, int $length, int &$position): ?SyntaxDiagnostic
    {
        $start = $position;
        $position++;

        return $this->terminator($data, $length, $position, $start);
    }

    private function boolean(string $data, int $length, int &$position): ?SyntaxDiagnostic
    {
        $start = $position;
        $position++;

        if (($delimiter = $this->delimiter($data, $length, $position, $start, ':')) !== null) {
            return $delimiter;
        }

        if ($position >= $length) {
            return $this->unexpectedEnd($length, $start, 'The boolean ends before its value.');
        }

        if ($data[$position] !== '0' && $data[$position] !== '1') {
            return new SyntaxDiagnostic(
                SyntaxErrorCode::MalformedNumber,
                $position,
                1,
                sprintf('The boolean at byte %d must be 0 or 1.', $start),
                'Use b:0; for false and b:1; for true.',
                contextStart: $start,
            );
        }

        $position++;

        return $this->terminator($data, $length, $position, $start);
    }

    private function integer(string $data, int $length, int &$position): ?SyntaxDiagnostic
    {
        $start = $position;
        $position++;

        if (($delimiter = $this->delimiter($data, $length, $position, $start, ':')) !== null) {
            return $delimiter;
        }

        $digitsStart = $position;

        if ($position < $length && ($data[$position] === '-' || $data[$position] === '+')) {
            $position++;
        }

        $digits = 0;

        while ($position < $length && $data[$position] >= '0' && $data[$position] <= '9') {
            $position++;
            $digits++;
        }

        if ($digits === 0) {
            return new SyntaxDiagnostic(
                SyntaxErrorCode::MalformedNumber,
                $digitsStart,
                max(1, $position - $digitsStart),
                sprintf('The integer at byte %d has no digits.', $start),
                'Write integers as i:42; or i:-42;.',
                contextStart: $start,
            );
        }

        return $this->terminator($data, $length, $position, $start);
    }

    private function double(string $data, int $length, int &$position): ?SyntaxDiagnostic
    {
        $start = $position;
        $position++;

        if (($delimiter = $this->delimiter($data, $length, $position, $start, ':')) !== null) {
            return $delimiter;
        }

        $numberStart = $position;

        foreach (['-INF', 'INF', 'NAN'] as $literal) {
            if (substr($data, $position, strlen($literal)) === $literal) {
                $position += strlen($literal);

                return $this->terminator($data, $length, $position, $start);
            }
        }

        if ($position < $length && ($data[$position] === '-' || $data[$position] === '+')) {
            $position++;
        }

        $digits = 0;

        while ($position < $length && $data[$position] >= '0' && $data[$position] <= '9') {
            $position++;
            $digits++;
        }

        if ($position < $length && $data[$position] === '.') {
            $position++;

            while ($position < $length && $data[$position] >= '0' && $data[$position] <= '9') {
                $position++;
                $digits++;
            }
        }

        if ($digits === 0) {
            return new SyntaxDiagnostic(
                SyntaxErrorCode::MalformedNumber,
                $numberStart,
                max(1, $position - $numberStart),
                sprintf('The float at byte %d has no digits.', $start),
                'Write floats as d:3.5;, d:INF;, d:-INF;, or d:NAN;.',
                contextStart: $start,
            );
        }

        if ($position < $length && ($data[$position] === 'e' || $data[$position] === 'E')) {
            $position++;

            if ($position < $length && ($data[$position] === '-' || $data[$position] === '+')) {
                $position++;
            }

            $exponentDigits = 0;

            while ($position < $length && $data[$position] >= '0' && $data[$position] <= '9') {
                $position++;
                $exponentDigits++;
            }

            if ($exponentDigits === 0) {
                return new SyntaxDiagnostic(
                    SyntaxErrorCode::MalformedNumber,
                    $numberStart,
                    max(1, $position - $numberStart),
                    sprintf('The float exponent at byte %d has no digits.', $start),
                    'Write an exponent as d:1.0e10;.',
                    contextStart: $start,
                );
            }
        }

        return $this->terminator($data, $length, $position, $start);
    }

    private function string(string $data, int $length, int &$position, bool $escaped): ?SyntaxDiagnostic
    {
        $start = $position;
        $marker = $data[$position];
        $position++;

        if (($delimiter = $this->delimiter($data, $length, $position, $start, ':')) !== null) {
            return $delimiter;
        }

        $payload = $this->quotedPayload($data, $length, $position, $start, $escaped, $marker.':');

        if ($payload !== null) {
            return $payload;
        }

        return $this->terminator($data, $length, $position, $start);
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
    private function quotedPayload(string $data, int $length, int &$position, int $tokenStart, bool $escaped, ?string $fixPrefix): ?SyntaxDiagnostic
    {
        $lengthStart = $position;
        $declared = $this->unsignedDigits($data, $length, $position);

        if ($declared === false) {
            return new SyntaxDiagnostic(
                SyntaxErrorCode::MalformedNumber,
                $lengthStart,
                max(1, $position - $lengthStart),
                sprintf('The byte length at byte %d is not a number.', $lengthStart),
                'Declare the length in digits, for example s:5:"hello";.',
                contextStart: $tokenStart,
            );
        }

        $lengthEnd = $position;

        if (($delimiter = $this->delimiter($data, $length, $position, $tokenStart, ':')) !== null) {
            return $delimiter;
        }

        if (($delimiter = $this->delimiter($data, $length, $position, $tokenStart, '"')) !== null) {
            return $delimiter;
        }

        $contentStart = $position;

        if ($escaped) {
            $cursor = $contentStart;
            $decoded = 0;

            while ($cursor < $length && $decoded < $declared) {
                if ($data[$cursor] !== '\\') {
                    $cursor++;
                    $decoded++;

                    continue;
                }

                if ($cursor + 2 >= $length || ctype_xdigit($data[$cursor + 1]) === false || ctype_xdigit($data[$cursor + 2]) === false) {
                    return new SyntaxDiagnostic(
                        SyntaxErrorCode::MalformedNumber,
                        $cursor,
                        min(3, $length - $cursor),
                        sprintf('The escape at byte %d is not two hexadecimal digits.', $cursor),
                        'Escaped strings use \\41 style two-digit escapes.',
                        contextStart: $tokenStart,
                    );
                }

                $cursor += 3;
                $decoded++;
            }

            $expectedTerminator = $cursor;
        } else {
            $expectedTerminator = $contentStart + $declared;
        }

        if ($expectedTerminator >= $length || $data[$expectedTerminator] !== '"') {
            return $this->lengthMismatch(
                $data,
                $length,
                $tokenStart,
                $lengthStart,
                $lengthEnd,
                $contentStart,
                $declared,
                $expectedTerminator,
                $escaped ? null : $fixPrefix,
            );
        }

        $position = $expectedTerminator + 1;

        return null;
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
        string $data,
        int $length,
        int $tokenStart,
        int $lengthStart,
        int $lengthEnd,
        int $contentStart,
        int $declared,
        int $expectedTerminator,
        ?string $fixPrefix,
    ): SyntaxDiagnostic {
        $closingQuote = strpos($data, '";', $contentStart);
        $actual = $closingQuote === false ? null : $closingQuote - $contentStart;

        /**
         * Without a closing quote anywhere ahead, the payload is truncated
         * rather than mismeasured, and saying so is more useful than naming a
         * length that cannot be corrected.
         */
        if ($actual === null) {
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

    private function arrayValue(string $data, int $length, int &$position, int $depth, bool &$unverifiable): ?SyntaxDiagnostic
    {
        $start = $position;
        $position++;

        if (($delimiter = $this->delimiter($data, $length, $position, $start, ':')) !== null) {
            return $delimiter;
        }

        $countStart = $position;
        $declared = $this->unsignedDigits($data, $length, $position);

        if ($declared === false) {
            return new SyntaxDiagnostic(
                SyntaxErrorCode::MalformedNumber,
                $countStart,
                max(1, $position - $countStart),
                sprintf('The element count at byte %d is not a number.', $countStart),
                'Declare the count in digits, for example a:2:{...}.',
                contextStart: $start,
            );
        }

        $countEnd = $position;

        if (($delimiter = $this->delimiter($data, $length, $position, $start, ':')) !== null) {
            return $delimiter;
        }

        if (($delimiter = $this->delimiter($data, $length, $position, $start, '{')) !== null) {
            return $delimiter;
        }

        for ($seen = 0; $seen < $declared; $seen++) {
            if ($position < $length && $data[$position] === '}') {
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

            $elementStart = $position;

            if (($key = $this->arrayKey($data, $length, $position, $start)) !== null) {
                return $key->widenedTo($elementStart);
            }

            if (($element = $this->value($data, $length, $position, $depth + 1, $unverifiable)) !== null) {
                return $element->widenedTo($elementStart);
            }
        }

        if ($position >= $length) {
            return $this->unexpectedEnd(
                $length,
                $start,
                sprintf('The array starting at byte %d is missing its closing brace.', $start),
            );
        }

        if ($data[$position] !== '}') {
            return $this->surplusElements($data, $length, $position, $start, $countStart, $countEnd, $declared, $depth, $unverifiable);
        }

        $position++;

        return null;
    }

    /**
     * Explain an array that holds more elements than it declares.
     *
     * The surplus is counted by parsing ahead so the correction can name the
     * real total; when the remainder does not parse, the count is omitted
     * rather than guessed.
     */
    private function surplusElements(
        string $data,
        int $length,
        int $position,
        int $start,
        int $countStart,
        int $countEnd,
        int $declared,
        int $depth,
        bool $unverifiable,
    ): SyntaxDiagnostic {
        $cursor = $position;
        $surplus = 0;
        $counted = true;

        while ($cursor < $length && $data[$cursor] !== '}') {
            if ($this->arrayKey($data, $length, $cursor, $start) !== null) {
                $counted = false;

                break;
            }

            if ($this->value($data, $length, $cursor, $depth + 1, $unverifiable) !== null) {
                $counted = false;

                break;
            }

            $surplus++;
        }

        $total = $counted && $cursor < $length ? $declared + $surplus : null;

        return new SyntaxDiagnostic(
            SyntaxErrorCode::ArrayCountMismatch,
            $position,
            max(1, min($cursor, $length) - $position),
            $total !== null
                ? sprintf(
                    'The array starting at byte %d declares %d elements but contains %d.',
                    $start,
                    $declared,
                    $total,
                )
                : sprintf(
                    'The array starting at byte %d declares %d elements but more follow at byte %d.',
                    $start,
                    $declared,
                    $position,
                ),
            $total !== null ? sprintf('Change `a:%d:` to `a:%d:`.', $declared, $total) : null,
            contextStart: $start,
            fix: $total !== null ? [
                'offset' => $countStart,
                'length' => $countEnd - $countStart,
                'replacement' => (string) $total,
            ] : null,
        );
    }

    /**
     * Consume an array key, which PHP restricts to integers and strings.
     *
     * A rejected key spans to the end of the offending token rather than a
     * single byte, because PHP blames the byte after the token it could not
     * accept as a key and the two locations must overlap.
     */
    private function arrayKey(string $data, int $length, int &$position, int $contextStart): ?SyntaxDiagnostic
    {
        if ($position >= $length) {
            return $this->unexpectedEnd($length, $contextStart, 'The array ends where a key was expected.');
        }

        if ($data[$position] === 'i') {
            return $this->integer($data, $length, $position);
        }

        if ($data[$position] === 's') {
            return $this->string($data, $length, $position, false);
        }

        if ($data[$position] === 'S') {
            return $this->string($data, $length, $position, true);
        }

        $tokenEnd = strpos($data, ';', $position);

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
     * Consume a serialized object.
     *
     * A well-formed object is reported as structurally valid so it reaches the
     * dedicated unsupported-object path instead of being called malformed.
     */
    private function objectValue(string $data, int $length, int &$position, int $depth, bool &$unverifiable): ?SyntaxDiagnostic
    {
        $start = $position;
        $position++;

        if (($delimiter = $this->delimiter($data, $length, $position, $start, ':')) !== null) {
            return $delimiter;
        }

        if (($className = $this->quotedPayload($data, $length, $position, $start, false, null)) !== null) {
            return $className;
        }

        if (($delimiter = $this->delimiter($data, $length, $position, $start, ':')) !== null) {
            return $delimiter;
        }

        $countStart = $position;
        $declared = $this->unsignedDigits($data, $length, $position);

        if ($declared === false) {
            return new SyntaxDiagnostic(
                SyntaxErrorCode::MalformedNumber,
                $countStart,
                max(1, $position - $countStart),
                sprintf('The property count at byte %d is not a number.', $countStart),
                'Declare the count in digits.',
                contextStart: $start,
            );
        }

        if (($delimiter = $this->delimiter($data, $length, $position, $start, ':')) !== null) {
            return $delimiter;
        }

        if (($delimiter = $this->delimiter($data, $length, $position, $start, '{')) !== null) {
            return $delimiter;
        }

        for ($seen = 0; $seen < $declared; $seen++) {
            $propertyStart = $position;

            if (($key = $this->arrayKey($data, $length, $position, $start)) !== null) {
                return $key->widenedTo($propertyStart);
            }

            if (($element = $this->value($data, $length, $position, $depth + 1, $unverifiable)) !== null) {
                return $element->widenedTo($propertyStart);
            }
        }

        return $this->delimiter($data, $length, $position, $start, '}');
    }

    /**
     * Consume a custom-serialized object, whose body is opaque by definition.
     */
    private function customObject(string $data, int $length, int &$position, bool &$unverifiable): ?SyntaxDiagnostic
    {
        $start = $position;
        $position++;
        $unverifiable = true;

        if (($delimiter = $this->delimiter($data, $length, $position, $start, ':')) !== null) {
            return $delimiter;
        }

        if (($className = $this->quotedPayload($data, $length, $position, $start, false, null)) !== null) {
            return $className;
        }

        if (($delimiter = $this->delimiter($data, $length, $position, $start, ':')) !== null) {
            return $delimiter;
        }

        $bodyLength = $this->unsignedDigits($data, $length, $position);

        if ($bodyLength === false) {
            return new SyntaxDiagnostic(
                SyntaxErrorCode::MalformedNumber,
                $position,
                1,
                sprintf('The payload length at byte %d is not a number.', $position),
                'Declare the length in digits.',
                contextStart: $start,
            );
        }

        if (($delimiter = $this->delimiter($data, $length, $position, $start, ':')) !== null) {
            return $delimiter;
        }

        if (($delimiter = $this->delimiter($data, $length, $position, $start, '{')) !== null) {
            return $delimiter;
        }

        $position += $bodyLength;

        if ($position >= $length) {
            return $this->unexpectedEnd($length, $start, 'The custom-serialized payload ends before its closing brace.');
        }

        return $this->delimiter($data, $length, $position, $start, '}');
    }

    /**
     * Consume a serialized enum case, whose validity needs class resolution.
     */
    private function enumValue(string $data, int $length, int &$position, bool &$unverifiable): ?SyntaxDiagnostic
    {
        $start = $position;
        $position++;
        $unverifiable = true;

        if (($delimiter = $this->delimiter($data, $length, $position, $start, ':')) !== null) {
            return $delimiter;
        }

        if (($name = $this->quotedPayload($data, $length, $position, $start, false, null)) !== null) {
            return $name;
        }

        return $this->terminator($data, $length, $position, $start);
    }

    /**
     * Consume a back reference, checked for shape only.
     */
    private function reference(string $data, int $length, int &$position, bool &$unverifiable): ?SyntaxDiagnostic
    {
        $start = $position;
        $position++;
        $unverifiable = true;

        if (($delimiter = $this->delimiter($data, $length, $position, $start, ':')) !== null) {
            return $delimiter;
        }

        $digitsStart = $position;
        $target = $this->unsignedDigits($data, $length, $position);

        if ($target === false) {
            return new SyntaxDiagnostic(
                SyntaxErrorCode::MalformedNumber,
                $digitsStart,
                max(1, $position - $digitsStart),
                sprintf('The reference at byte %d has no target.', $start),
                'References count values from 1, for example r:2;.',
                contextStart: $start,
            );
        }

        if (($terminator = $this->terminator($data, $length, $position, $start)) !== null) {
            return $terminator;
        }

        /**
         * The whole token is framed, because PHP accepts a reference token
         * before deciding it points nowhere and then blames the byte after it.
         */
        if ($target <= 0) {
            return new SyntaxDiagnostic(
                SyntaxErrorCode::MalformedNumber,
                $start,
                $position - $start,
                sprintf('The reference at byte %d does not point at a value.', $start),
                'References count values from 1, for example r:2;.',
                contextStart: $start,
            );
        }

        return null;
    }

    private function terminator(string $data, int $length, int &$position, int $contextStart): ?SyntaxDiagnostic
    {
        return $this->delimiter($data, $length, $position, $contextStart, ';');
    }

    private function delimiter(string $data, int $length, int &$position, int $contextStart, string $byte): ?SyntaxDiagnostic
    {
        if ($position >= $length) {
            return $this->unexpectedEnd(
                $length,
                $contextStart,
                sprintf('The value ends before the expected "%s".', $byte),
            );
        }

        if ($data[$position] !== $byte) {
            return new SyntaxDiagnostic(
                $byte === ';' ? SyntaxErrorCode::MissingTerminator : SyntaxErrorCode::MissingDelimiter,
                $position,
                1,
                sprintf('A "%s" was expected at byte %d.', $byte, $position),
                sprintf('Insert the missing "%s".', $byte),
                contextStart: $contextStart,
            );
        }

        $position++;

        return null;
    }

    private function unexpectedEnd(int $length, int $contextStart, string $message): SyntaxDiagnostic
    {
        return new SyntaxDiagnostic(
            SyntaxErrorCode::UnexpectedEnd,
            $length,
            0,
            $message,
            contextStart: $contextStart,
        );
    }

    /**
     * Read an unsigned digit run, refusing runs long enough to overflow an int.
     */
    private function unsignedDigits(string $data, int $length, int &$position): int|false
    {
        $start = $position;

        while ($position < $length
            && $position - $start < self::MAX_LENGTH_DIGITS
            && $data[$position] >= '0'
            && $data[$position] <= '9'
        ) {
            $position++;
        }

        if ($position === $start) {
            return false;
        }

        if ($position < $length && $data[$position] >= '0' && $data[$position] <= '9') {
            return false;
        }

        return (int) substr($data, $start, $position - $start);
    }
}
