<?php

namespace App\Services;

use App\Data\ScanOutcome;
use App\Data\SyntaxDiagnostic;
use App\Enums\SyntaxErrorCode;
use App\Services\Scanner\ScannerCursor;
use App\Services\Scanner\SyntaxDiagnosticFactory;

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
 *
 * Every rule below takes a {@see ScannerCursor} and advances it past what it
 * consumed, returning null on success and a diagnostic on failure. The one
 * exception is {@see self::surplusElements()}, which looks ahead on a fork.
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
     * Defaulted rather than injected: the scanner is built directly with
     * `new SerializedScanner` in {@see Serialized} and across the
     * test suite, and the factory is stateless, so there is nothing to wire.
     */
    public function __construct(
        private SyntaxDiagnosticFactory $diagnostics = new SyntaxDiagnosticFactory,
    ) {}

    /**
     * Scan a payload and report the first problem that breaks the grammar.
     */
    public function scan(string $data): ScanOutcome
    {
        $length = strlen($data);

        if ($length === 0) {
            return ScanOutcome::invalid($this->diagnostics->emptyValue());
        }

        $cursor = new ScannerCursor($data, $length);
        $diagnostic = $this->value($cursor, 0);

        if ($diagnostic !== null && $diagnostic->code === SyntaxErrorCode::DepthLimitExceeded) {
            return ScanOutcome::unverifiable();
        }

        if ($diagnostic !== null) {
            return ScanOutcome::invalid($diagnostic);
        }

        if ($cursor->position < $length) {
            return ScanOutcome::invalid($this->diagnostics->trailingData($cursor->position, $length));
        }

        return $cursor->unverifiable
            ? ScanOutcome::unverifiable($cursor->position)
            : ScanOutcome::valid($cursor->position);
    }

    /**
     * Consume one serialized value starting at the cursor.
     */
    private function value(ScannerCursor $cursor, int $depth): ?SyntaxDiagnostic
    {
        if ($depth > self::MAX_DEPTH) {
            $cursor->unverifiable = true;

            return $this->diagnostics->depthLimitExceeded($cursor->position);
        }

        $position = $cursor->position;

        if ($position >= $cursor->length) {
            return $this->diagnostics->valueEndedEarly($cursor->length, $position);
        }

        return match ($cursor->data[$position]) {
            'N' => $this->nullValue($cursor),
            'b' => $this->boolean($cursor),
            'i' => $this->integer($cursor),
            'd' => $this->double($cursor),
            's' => $this->string($cursor, false),
            'S' => $this->string($cursor, true),
            'a' => $this->arrayValue($cursor, $depth),
            'O' => $this->objectValue($cursor, $depth),
            'C' => $this->customObject($cursor),
            'E' => $this->enumValue($cursor),
            'r', 'R' => $this->reference($cursor),
            default => $this->diagnostics->unknownTypeMarker($cursor->position),
        };
    }

    private function nullValue(ScannerCursor $cursor): ?SyntaxDiagnostic
    {
        $start = $cursor->position;
        $cursor->position++;

        return $this->terminator($cursor, $start);
    }

    private function boolean(ScannerCursor $cursor): ?SyntaxDiagnostic
    {
        $start = $cursor->position;
        $cursor->position++;

        if (($delimiter = $this->delimiter($cursor, $start, ':')) !== null) {
            return $delimiter;
        }

        if ($cursor->atEnd()) {
            return $this->diagnostics->booleanEndedBeforeValue($cursor->length, $start);
        }

        $byte = $cursor->current();

        if ($byte !== '0' && $byte !== '1') {
            return $this->diagnostics->nonBinaryBoolean($cursor->position, $start);
        }

        $cursor->position++;

        return $this->terminator($cursor, $start);
    }

    private function integer(ScannerCursor $cursor): ?SyntaxDiagnostic
    {
        $start = $cursor->position;
        $cursor->position++;

        if (($delimiter = $this->delimiter($cursor, $start, ':')) !== null) {
            return $delimiter;
        }

        $digitsStart = $cursor->position;

        if (! $cursor->atEnd() && ($cursor->current() === '-' || $cursor->current() === '+')) {
            $cursor->position++;
        }

        if ($this->digitRun($cursor) === 0) {
            return $this->diagnostics->integerWithoutDigits($digitsStart, $cursor->position, $start);
        }

        return $this->terminator($cursor, $start);
    }

    private function double(ScannerCursor $cursor): ?SyntaxDiagnostic
    {
        $start = $cursor->position;
        $cursor->position++;

        if (($delimiter = $this->delimiter($cursor, $start, ':')) !== null) {
            return $delimiter;
        }

        $numberStart = $cursor->position;

        foreach (['-INF', 'INF', 'NAN'] as $literal) {
            if (substr($cursor->data, $cursor->position, strlen($literal)) === $literal) {
                $cursor->position += strlen($literal);

                return $this->terminator($cursor, $start);
            }
        }

        if (! $cursor->atEnd() && ($cursor->current() === '-' || $cursor->current() === '+')) {
            $cursor->position++;
        }

        $digits = $this->digitRun($cursor);

        if (! $cursor->atEnd() && $cursor->current() === '.') {
            $cursor->position++;
            $digits += $this->digitRun($cursor);
        }

        if ($digits === 0) {
            return $this->diagnostics->floatWithoutDigits($numberStart, $cursor->position, $start);
        }

        if (! $cursor->atEnd() && ($cursor->current() === 'e' || $cursor->current() === 'E')) {
            $cursor->position++;

            if (! $cursor->atEnd() && ($cursor->current() === '-' || $cursor->current() === '+')) {
                $cursor->position++;
            }

            if ($this->digitRun($cursor) === 0) {
                return $this->diagnostics->floatExponentWithoutDigits($numberStart, $cursor->position, $start);
            }
        }

        return $this->terminator($cursor, $start);
    }

    private function string(ScannerCursor $cursor, bool $escaped): ?SyntaxDiagnostic
    {
        $start = $cursor->position;
        $marker = $cursor->data[$start];
        $cursor->position = $start + 1;

        if (($delimiter = $this->delimiter($cursor, $start, ':')) !== null) {
            return $delimiter;
        }

        $payload = $this->quotedPayload($cursor, $start, $escaped, $marker.':');

        if ($payload !== null) {
            return $payload;
        }

        return $this->terminator($cursor, $start);
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
    private function quotedPayload(ScannerCursor $cursor, int $tokenStart, bool $escaped, ?string $fixPrefix): ?SyntaxDiagnostic
    {
        $lengthStart = $cursor->position;
        $declared = $this->unsignedDigits($cursor);

        if ($declared === false) {
            return $this->diagnostics->nonNumericStringLength($lengthStart, $cursor->position, $tokenStart);
        }

        $lengthEnd = $cursor->position;

        if (($delimiter = $this->delimiter($cursor, $tokenStart, ':')) !== null) {
            return $delimiter;
        }

        if (($delimiter = $this->delimiter($cursor, $tokenStart, '"')) !== null) {
            return $delimiter;
        }

        $data = $cursor->data;
        $length = $cursor->length;
        $contentStart = $cursor->position;

        if ($escaped) {
            $scan = $contentStart;
            $decoded = 0;

            while ($scan < $length && $decoded < $declared) {
                if ($data[$scan] !== '\\') {
                    $scan++;
                    $decoded++;

                    continue;
                }

                if ($scan + 2 >= $length || ctype_xdigit($data[$scan + 1]) === false || ctype_xdigit($data[$scan + 2]) === false) {
                    return $this->diagnostics->malformedEscape($scan, $length, $tokenStart);
                }

                $scan += 3;
                $decoded++;
            }

            $expectedTerminator = $scan;
        } else {
            $expectedTerminator = $contentStart + $declared;
        }

        if ($expectedTerminator >= $length || $data[$expectedTerminator] !== '"') {
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
            return $this->diagnostics->unterminatedString($contentStart, $cursor->length, $tokenStart, $declared, $expectedTerminator);
        }

        return $this->diagnostics->stringLengthMismatch(
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

    private function arrayValue(ScannerCursor $cursor, int $depth): ?SyntaxDiagnostic
    {
        $start = $cursor->position;
        $cursor->position++;

        if (($delimiter = $this->delimiter($cursor, $start, ':')) !== null) {
            return $delimiter;
        }

        $countStart = $cursor->position;
        $declared = $this->unsignedDigits($cursor);

        if ($declared === false) {
            return $this->diagnostics->nonNumericElementCount($countStart, $cursor->position, $start);
        }

        $countEnd = $cursor->position;

        if (($delimiter = $this->delimiter($cursor, $start, ':')) !== null) {
            return $delimiter;
        }

        if (($delimiter = $this->delimiter($cursor, $start, '{')) !== null) {
            return $delimiter;
        }

        for ($seen = 0; $seen < $declared; $seen++) {
            if ($cursor->position < $cursor->length && $cursor->data[$cursor->position] === '}') {
                return $this->diagnostics->arrayShorterThanDeclared($start, $cursor->position, $declared, $seen, $countStart, $countEnd);
            }

            $elementStart = $cursor->position;

            if (($key = $this->arrayKey($cursor, $start)) !== null) {
                return $key->widenedTo($elementStart);
            }

            if (($element = $this->value($cursor, $depth + 1)) !== null) {
                return $element->widenedTo($elementStart);
            }
        }

        if ($cursor->atEnd()) {
            return $this->diagnostics->arrayMissingClosingBrace($cursor->length, $start);
        }

        if ($cursor->current() !== '}') {
            return $this->surplusElements($cursor, $start, $countStart, $countEnd, $declared, $depth);
        }

        $cursor->position++;

        return null;
    }

    /**
     * Explain an array that holds more elements than it declares.
     *
     * The surplus is counted by parsing ahead so the correction can name the
     * real total; when the remainder does not parse, the count is omitted
     * rather than guessed.
     *
     * The walk runs on a fork: it must not move the real cursor, which still
     * has to frame the diagnostic from where the surplus began, and it must not
     * publish `unverifiable`, since a reference or custom object among the
     * surplus says nothing about the payload as a whole.
     */
    private function surplusElements(
        ScannerCursor $cursor,
        int $start,
        int $countStart,
        int $countEnd,
        int $declared,
        int $depth,
    ): SyntaxDiagnostic {
        $position = $cursor->position;
        $lookahead = $cursor->fork();
        $surplus = 0;
        $counted = true;

        while (! $lookahead->atEnd() && $lookahead->current() !== '}') {
            if ($this->arrayKey($lookahead, $start) !== null) {
                $counted = false;

                break;
            }

            if ($this->value($lookahead, $depth + 1) !== null) {
                $counted = false;

                break;
            }

            $surplus++;
        }

        $total = $counted && ! $lookahead->atEnd() ? $declared + $surplus : null;

        return $this->diagnostics->arrayLongerThanDeclared(
            $position,
            min($lookahead->position, $cursor->length),
            $start,
            $countStart,
            $countEnd,
            $declared,
            $total,
        );
    }

    /**
     * Consume an array key, which PHP restricts to integers and strings.
     *
     * A rejected key spans to the end of the offending token rather than a
     * single byte, because PHP blames the byte after the token it could not
     * accept as a key and the two locations must overlap.
     */
    private function arrayKey(ScannerCursor $cursor, int $contextStart): ?SyntaxDiagnostic
    {
        $position = $cursor->position;

        if ($position >= $cursor->length) {
            return $this->diagnostics->arrayEndedBeforeKey($cursor->length, $contextStart);
        }

        return match ($cursor->data[$position]) {
            'i' => $this->integer($cursor),
            's' => $this->string($cursor, false),
            'S' => $this->string($cursor, true),
            default => $this->diagnostics->invalidArrayKey(
                $cursor->position,
                strpos($cursor->data, ';', $cursor->position),
                $contextStart,
            ),
        };
    }

    /**
     * Consume a serialized object.
     *
     * A well-formed object is reported as structurally valid so it reaches the
     * dedicated unsupported-object path instead of being called malformed.
     */
    private function objectValue(ScannerCursor $cursor, int $depth): ?SyntaxDiagnostic
    {
        $start = $cursor->position;
        $cursor->position++;

        if (($delimiter = $this->delimiter($cursor, $start, ':')) !== null) {
            return $delimiter;
        }

        if (($className = $this->quotedPayload($cursor, $start, false, null)) !== null) {
            return $className;
        }

        if (($delimiter = $this->delimiter($cursor, $start, ':')) !== null) {
            return $delimiter;
        }

        $countStart = $cursor->position;
        $declared = $this->unsignedDigits($cursor);

        if ($declared === false) {
            return $this->diagnostics->nonNumericPropertyCount($countStart, $cursor->position, $start);
        }

        if (($delimiter = $this->delimiter($cursor, $start, ':')) !== null) {
            return $delimiter;
        }

        if (($delimiter = $this->delimiter($cursor, $start, '{')) !== null) {
            return $delimiter;
        }

        for ($seen = 0; $seen < $declared; $seen++) {
            $propertyStart = $cursor->position;

            if (($key = $this->arrayKey($cursor, $start)) !== null) {
                return $key->widenedTo($propertyStart);
            }

            if (($element = $this->value($cursor, $depth + 1)) !== null) {
                return $element->widenedTo($propertyStart);
            }
        }

        return $this->delimiter($cursor, $start, '}');
    }

    /**
     * Consume a custom-serialized object, whose body is opaque by definition.
     */
    private function customObject(ScannerCursor $cursor): ?SyntaxDiagnostic
    {
        $start = $cursor->position;
        $cursor->position++;
        $cursor->unverifiable = true;

        if (($delimiter = $this->delimiter($cursor, $start, ':')) !== null) {
            return $delimiter;
        }

        if (($className = $this->quotedPayload($cursor, $start, false, null)) !== null) {
            return $className;
        }

        if (($delimiter = $this->delimiter($cursor, $start, ':')) !== null) {
            return $delimiter;
        }

        $bodyLength = $this->unsignedDigits($cursor);

        if ($bodyLength === false) {
            return $this->diagnostics->nonNumericPayloadLength($cursor->position, $start);
        }

        if (($delimiter = $this->delimiter($cursor, $start, ':')) !== null) {
            return $delimiter;
        }

        if (($delimiter = $this->delimiter($cursor, $start, '{')) !== null) {
            return $delimiter;
        }

        $cursor->position += $bodyLength;

        if ($cursor->atEnd()) {
            return $this->diagnostics->customObjectEndedBeforeBrace($cursor->length, $start);
        }

        return $this->delimiter($cursor, $start, '}');
    }

    /**
     * Consume a serialized enum case, whose validity needs class resolution.
     */
    private function enumValue(ScannerCursor $cursor): ?SyntaxDiagnostic
    {
        $start = $cursor->position;
        $cursor->position++;
        $cursor->unverifiable = true;

        if (($delimiter = $this->delimiter($cursor, $start, ':')) !== null) {
            return $delimiter;
        }

        if (($name = $this->quotedPayload($cursor, $start, false, null)) !== null) {
            return $name;
        }

        return $this->terminator($cursor, $start);
    }

    /**
     * Consume a back reference, checked for shape only.
     */
    private function reference(ScannerCursor $cursor): ?SyntaxDiagnostic
    {
        $start = $cursor->position;
        $cursor->position++;
        $cursor->unverifiable = true;

        if (($delimiter = $this->delimiter($cursor, $start, ':')) !== null) {
            return $delimiter;
        }

        $digitsStart = $cursor->position;
        $target = $this->unsignedDigits($cursor);

        if ($target === false) {
            return $this->diagnostics->referenceWithoutTarget($digitsStart, $cursor->position, $start);
        }

        if (($terminator = $this->terminator($cursor, $start)) !== null) {
            return $terminator;
        }

        if ($target <= 0) {
            return $this->diagnostics->referenceToNothing($start, $cursor->position);
        }

        return null;
    }

    private function terminator(ScannerCursor $cursor, int $contextStart): ?SyntaxDiagnostic
    {
        return $this->delimiter($cursor, $contextStart, ';');
    }

    private function delimiter(ScannerCursor $cursor, int $contextStart, string $byte): ?SyntaxDiagnostic
    {
        $position = $cursor->position;

        if ($position >= $cursor->length) {
            return $this->diagnostics->endedBeforeByte($byte, $cursor->length, $contextStart);
        }

        if ($cursor->data[$position] !== $byte) {
            return $this->diagnostics->missingByte($byte, $position, $contextStart);
        }

        $cursor->position = $position + 1;

        return null;
    }

    /**
     * Consume a run of digits and report how many there were.
     */
    private function digitRun(ScannerCursor $cursor): int
    {
        $data = $cursor->data;
        $length = $cursor->length;
        $position = $cursor->position;
        $start = $position;

        while ($position < $length && $data[$position] >= '0' && $data[$position] <= '9') {
            $position++;
        }

        $cursor->position = $position;

        return $position - $start;
    }

    /**
     * Read an unsigned digit run, refusing runs long enough to overflow an int.
     */
    private function unsignedDigits(ScannerCursor $cursor): int|false
    {
        $data = $cursor->data;
        $length = $cursor->length;
        $position = $cursor->position;
        $start = $position;

        while ($position < $length
            && $position - $start < self::MAX_LENGTH_DIGITS
            && $data[$position] >= '0'
            && $data[$position] <= '9'
        ) {
            $position++;
        }

        $cursor->position = $position;

        if ($position === $start) {
            return false;
        }

        if ($position < $length && $data[$position] >= '0' && $data[$position] <= '9') {
            return false;
        }

        return (int) substr($data, $start, $position - $start);
    }
}
