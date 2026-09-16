<?php

namespace App\Services\Scanner\Rules;

use App\Data\SyntaxDiagnostic;
use App\Services\Scanner\ScannerCursor;
use App\Services\Scanner\TokenReader;

/**
 * The tokens validated for shape only: `C`, `E`, `r` and `R`.
 *
 * What these have in common is not their syntax but their verdict. Their real
 * validity depends on class resolution or on PHP's internal value numbering,
 * and reproducing either on untrusted input is a larger accuracy risk than
 * declining to judge them -- so every rule here marks the cursor unverifiable.
 * That shared consequence is what makes them a family.
 */
readonly class OpaqueRules
{
    /**
     * @param  StringRules  $strings  Reads the quoted class and case names these tokens carry.
     */
    public function __construct(
        private TokenReader $reader,
        private StringRules $strings,
    ) {}

    /**
     * Consume a custom-serialized object, whose body is opaque by definition.
     */
    public function customObject(ScannerCursor $cursor): ?SyntaxDiagnostic
    {
        $start = $cursor->position;
        $cursor->position++;
        $cursor->unverifiable = true;

        if (($delimiter = $this->reader->delimiter($cursor, $start, ':')) !== null) {
            return $delimiter;
        }

        if (($className = $this->strings->quotedPayload($cursor, $start, false, null)) !== null) {
            return $className;
        }

        if (($delimiter = $this->reader->delimiter($cursor, $start, ':')) !== null) {
            return $delimiter;
        }

        $bodyLength = $cursor->unsignedDigits();

        if ($bodyLength === false) {
            return $this->reader->diagnostics->nonNumericPayloadLength($cursor->position, $start);
        }

        if (($delimiter = $this->reader->delimiter($cursor, $start, ':')) !== null) {
            return $delimiter;
        }

        if (($delimiter = $this->reader->delimiter($cursor, $start, '{')) !== null) {
            return $delimiter;
        }

        $cursor->position += $bodyLength;

        if ($cursor->atEnd()) {
            return $this->reader->diagnostics->customObjectEndedBeforeBrace($cursor->length, $start);
        }

        return $this->reader->delimiter($cursor, $start, '}');
    }

    /**
     * Consume a serialized enum case, whose validity needs class resolution.
     */
    public function enumValue(ScannerCursor $cursor): ?SyntaxDiagnostic
    {
        $start = $cursor->position;
        $cursor->position++;
        $cursor->unverifiable = true;

        if (($delimiter = $this->reader->delimiter($cursor, $start, ':')) !== null) {
            return $delimiter;
        }

        if (($name = $this->strings->quotedPayload($cursor, $start, false, null)) !== null) {
            return $name;
        }

        return $this->reader->terminator($cursor, $start);
    }

    /**
     * Consume a back reference, checked for shape only.
     */
    public function reference(ScannerCursor $cursor): ?SyntaxDiagnostic
    {
        $start = $cursor->position;
        $cursor->position++;
        $cursor->unverifiable = true;

        if (($delimiter = $this->reader->delimiter($cursor, $start, ':')) !== null) {
            return $delimiter;
        }

        $digitsStart = $cursor->position;
        $target = $cursor->unsignedDigits();

        if ($target === false) {
            return $this->reader->diagnostics->referenceWithoutTarget($digitsStart, $cursor->position, $start);
        }

        if (($terminator = $this->reader->terminator($cursor, $start)) !== null) {
            return $terminator;
        }

        if ($target <= 0) {
            return $this->reader->diagnostics->referenceToNothing($start, $cursor->position);
        }

        return null;
    }
}
