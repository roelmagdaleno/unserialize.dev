<?php

namespace App\Services\Scanner\Rules;

use App\Data\SyntaxDiagnostic;
use App\Services\Scanner\ScannerCursor;
use App\Services\Scanner\TokenReader;
use App\Services\Scanner\ValueParser;

/**
 * The values that declare a count and then hold other values: `a` and `O`.
 *
 * This is the only family that recurses, so it is the only one that depends on
 * {@see ValueParser}. It also owns {@see self::arrayKey()}, because PHP's
 * restriction of keys to integers and strings is a containment rule rather than
 * a property of either scalars or strings -- which is why this family depends
 * on both of those in turn.
 */
readonly class ContainerRules
{
    public function __construct(
        private TokenReader $reader,
        private ScalarRules $scalars,
        private StringRules $strings,
        private ValueParser $parser,
    ) {}

    public function arrayValue(ScannerCursor $cursor, int $depth): ?SyntaxDiagnostic
    {
        $start = $cursor->position;
        $cursor->position++;

        if (($delimiter = $this->reader->delimiter($cursor, $start, ':')) !== null) {
            return $delimiter;
        }

        $countStart = $cursor->position;
        $declared = $cursor->unsignedDigits();

        if ($declared === false) {
            return $this->reader->diagnostics->nonNumericElementCount($countStart, $cursor->position, $start);
        }

        $countEnd = $cursor->position;

        if (($delimiter = $this->reader->delimiter($cursor, $start, ':')) !== null) {
            return $delimiter;
        }

        if (($delimiter = $this->reader->delimiter($cursor, $start, '{')) !== null) {
            return $delimiter;
        }

        for ($seen = 0; $seen < $declared; $seen++) {
            if ($cursor->position < $cursor->length && $cursor->data[$cursor->position] === '}') {
                return $this->reader->diagnostics->arrayShorterThanDeclared($start, $cursor->position, $declared, $seen, $countStart, $countEnd);
            }

            if (($member = $this->keyedMember($cursor, $start, $depth)) !== null) {
                return $member;
            }
        }

        if ($cursor->atEnd()) {
            return $this->reader->diagnostics->arrayMissingClosingBrace($cursor->length, $start);
        }

        if ($cursor->current() !== '}') {
            return $this->surplusElements($cursor, $start, $countStart, $countEnd, $declared, $depth);
        }

        $cursor->position++;

        return null;
    }

    /**
     * Consume a serialized object.
     *
     * A well-formed object is reported as structurally valid so it reaches the
     * dedicated unsupported-object path instead of being called malformed.
     */
    public function objectValue(ScannerCursor $cursor, int $depth): ?SyntaxDiagnostic
    {
        $start = $cursor->position;
        $cursor->position++;

        if (($delimiter = $this->reader->delimiter($cursor, $start, ':')) !== null) {
            return $delimiter;
        }

        if (($className = $this->strings->quotedPayload($cursor, $start, false, null)) !== null) {
            return $className;
        }

        if (($delimiter = $this->reader->delimiter($cursor, $start, ':')) !== null) {
            return $delimiter;
        }

        $countStart = $cursor->position;
        $declared = $cursor->unsignedDigits();

        if ($declared === false) {
            return $this->reader->diagnostics->nonNumericPropertyCount($countStart, $cursor->position, $start);
        }

        if (($delimiter = $this->reader->delimiter($cursor, $start, ':')) !== null) {
            return $delimiter;
        }

        if (($delimiter = $this->reader->delimiter($cursor, $start, '{')) !== null) {
            return $delimiter;
        }

        for ($seen = 0; $seen < $declared; $seen++) {
            if (($member = $this->keyedMember($cursor, $start, $depth)) !== null) {
                return $member;
            }
        }

        return $this->reader->delimiter($cursor, $start, '}');
    }

    /**
     * Consume one key-and-value pair, blaming the pair rather than the byte.
     *
     * A failure inside either half is widened to where the pair began, because
     * PHP rewinds to the start of the element it was reading before it reports
     * where it stopped.
     */
    private function keyedMember(ScannerCursor $cursor, int $contextStart, int $depth): ?SyntaxDiagnostic
    {
        $memberStart = $cursor->position;

        if (($key = $this->arrayKey($cursor, $contextStart)) !== null) {
            return $key->widenedTo($memberStart);
        }

        if (($element = $this->parser->value($cursor, $depth + 1)) !== null) {
            return $element->widenedTo($memberStart);
        }

        return null;
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
            return $this->reader->diagnostics->arrayEndedBeforeKey($cursor->length, $contextStart);
        }

        return match ($cursor->data[$position]) {
            'i' => $this->scalars->integer($cursor),
            's' => $this->strings->string($cursor, false),
            'S' => $this->strings->string($cursor, true),
            default => $this->reader->diagnostics->invalidArrayKey(
                $cursor->position,
                strpos($cursor->data, ';', $cursor->position),
                $contextStart,
            ),
        };
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
            if ($this->keyedMember($lookahead, $start, $depth) !== null) {
                $counted = false;

                break;
            }

            $surplus++;
        }

        $total = $counted && ! $lookahead->atEnd() ? $declared + $surplus : null;

        return $this->reader->diagnostics->arrayLongerThanDeclared(
            $position,
            min($lookahead->position, $cursor->length),
            $start,
            $countStart,
            $countEnd,
            $declared,
            $total,
        );
    }
}
