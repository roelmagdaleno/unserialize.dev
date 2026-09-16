<?php

namespace App\Services\Scanner\Rules;

use App\Data\SyntaxDiagnostic;
use App\Services\Scanner\ScannerCursor;
use App\Services\Scanner\TokenReader;

/**
 * The values PHP writes with no length and no body: `N`, `b`, `i`, `d`.
 *
 * Every rule here reads a fixed shape between a `:` and a `;`, which is what
 * makes them a family: none of them recurses, none of them declares a size, and
 * none of them can be left unverifiable.
 */
readonly class ScalarRules
{
    /**
     * @param  TokenReader  $reader  Supplies the shared byte expectation and the diagnostic factory.
     */
    public function __construct(private TokenReader $reader) {}

    /**
     * Consume `N;`.
     */
    public function nullValue(ScannerCursor $cursor): ?SyntaxDiagnostic
    {
        $start = $cursor->position;
        $cursor->position++;

        return $this->reader->terminator($cursor, $start);
    }

    /**
     * Consume `b:0;` or `b:1;`.
     */
    public function boolean(ScannerCursor $cursor): ?SyntaxDiagnostic
    {
        $start = $cursor->position;
        $cursor->position++;

        if (($delimiter = $this->reader->delimiter($cursor, $start, ':')) !== null) {
            return $delimiter;
        }

        if ($cursor->atEnd()) {
            return $this->reader->diagnostics->booleanEndedBeforeValue($cursor->length, $start);
        }

        $byte = $cursor->current();

        if ($byte !== '0' && $byte !== '1') {
            return $this->reader->diagnostics->nonBinaryBoolean($cursor->position, $start);
        }

        $cursor->position++;

        return $this->reader->terminator($cursor, $start);
    }

    /**
     * Consume an optionally signed integer.
     */
    public function integer(ScannerCursor $cursor): ?SyntaxDiagnostic
    {
        $start = $cursor->position;
        $cursor->position++;

        if (($delimiter = $this->reader->delimiter($cursor, $start, ':')) !== null) {
            return $delimiter;
        }

        $digitsStart = $cursor->position;
        $cursor->acceptSign();

        if ($cursor->digitRun() === 0) {
            return $this->reader->diagnostics->integerWithoutDigits($digitsStart, $cursor->position, $start);
        }

        return $this->reader->terminator($cursor, $start);
    }

    /**
     * Consume a float, including the `INF`, `-INF` and `NAN` literals.
     */
    public function double(ScannerCursor $cursor): ?SyntaxDiagnostic
    {
        $start = $cursor->position;
        $cursor->position++;

        if (($delimiter = $this->reader->delimiter($cursor, $start, ':')) !== null) {
            return $delimiter;
        }

        $numberStart = $cursor->position;

        foreach (['-INF', 'INF', 'NAN'] as $literal) {
            if ($cursor->acceptLiteral($literal)) {
                return $this->reader->terminator($cursor, $start);
            }
        }

        $cursor->acceptSign();
        $digits = $cursor->digitRun();

        if (! $cursor->atEnd() && $cursor->current() === '.') {
            $cursor->position++;
            $digits += $cursor->digitRun();
        }

        if ($digits === 0) {
            return $this->reader->diagnostics->floatWithoutDigits($numberStart, $cursor->position, $start);
        }

        if (! $cursor->atEnd() && ($cursor->current() === 'e' || $cursor->current() === 'E')) {
            $cursor->position++;
            $cursor->acceptSign();

            if ($cursor->digitRun() === 0) {
                return $this->reader->diagnostics->floatExponentWithoutDigits($numberStart, $cursor->position, $start);
            }
        }

        return $this->reader->terminator($cursor, $start);
    }
}
