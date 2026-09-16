<?php

namespace App\Services;

use App\Data\ScanOutcome;
use App\Data\SyntaxDiagnostic;
use App\Enums\SyntaxErrorCode;
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
            return ScanOutcome::invalid($this->diagnostics->trailingData($position, $length));
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

            return $this->diagnostics->depthLimitExceeded($position);
        }

        if ($position >= $length) {
            return $this->diagnostics->valueEndedEarly($length, $position);
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
            default => $this->diagnostics->unknownTypeMarker($position),
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
            return $this->diagnostics->booleanEndedBeforeValue($length, $start);
        }

        if ($data[$position] !== '0' && $data[$position] !== '1') {
            return $this->diagnostics->nonBinaryBoolean($position, $start);
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
            return $this->diagnostics->integerWithoutDigits($digitsStart, $position, $start);
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
            return $this->diagnostics->floatWithoutDigits($numberStart, $position, $start);
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
                return $this->diagnostics->floatExponentWithoutDigits($numberStart, $position, $start);
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
            return $this->diagnostics->nonNumericStringLength($lengthStart, $position, $tokenStart);
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
                    return $this->diagnostics->malformedEscape($cursor, $length, $tokenStart);
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
            return $this->diagnostics->unterminatedString($contentStart, $length, $tokenStart, $declared, $expectedTerminator);
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
            return $this->diagnostics->nonNumericElementCount($countStart, $position, $start);
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
                return $this->diagnostics->arrayShorterThanDeclared($start, $position, $declared, $seen, $countStart, $countEnd);
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
            return $this->diagnostics->arrayMissingClosingBrace($length, $start);
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

        return $this->diagnostics->arrayLongerThanDeclared(
            $position,
            min($cursor, $length),
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
    private function arrayKey(string $data, int $length, int &$position, int $contextStart): ?SyntaxDiagnostic
    {
        if ($position >= $length) {
            return $this->diagnostics->arrayEndedBeforeKey($length, $contextStart);
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

        return $this->diagnostics->invalidArrayKey($position, strpos($data, ';', $position), $contextStart);
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
            return $this->diagnostics->nonNumericPropertyCount($countStart, $position, $start);
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
            return $this->diagnostics->nonNumericPayloadLength($position, $start);
        }

        if (($delimiter = $this->delimiter($data, $length, $position, $start, ':')) !== null) {
            return $delimiter;
        }

        if (($delimiter = $this->delimiter($data, $length, $position, $start, '{')) !== null) {
            return $delimiter;
        }

        $position += $bodyLength;

        if ($position >= $length) {
            return $this->diagnostics->customObjectEndedBeforeBrace($length, $start);
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
            return $this->diagnostics->referenceWithoutTarget($digitsStart, $position, $start);
        }

        if (($terminator = $this->terminator($data, $length, $position, $start)) !== null) {
            return $terminator;
        }

        if ($target <= 0) {
            return $this->diagnostics->referenceToNothing($start, $position);
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
            return $this->diagnostics->endedBeforeByte($byte, $length, $contextStart);
        }

        if ($data[$position] !== $byte) {
            return $this->diagnostics->missingByte($byte, $position, $contextStart);
        }

        $position++;

        return null;
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
