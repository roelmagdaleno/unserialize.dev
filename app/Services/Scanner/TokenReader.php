<?php

namespace App\Services\Scanner;

use App\Data\SyntaxDiagnostic;

/**
 * The single byte expectation every rule family shares.
 *
 * Splitting the grammar into families left exactly one helper that all four
 * need: "the next byte must be this one, and here is the diagnostic when it is
 * not". Rather than duplicate it per family or reintroduce a base class, it
 * lives here and is injected.
 *
 * The diagnostic factory rides along because a byte expectation is the one
 * shared helper that has to phrase a failure, and every family needs the
 * factory anyway.
 */
readonly class TokenReader
{
    public function __construct(
        public SyntaxDiagnosticFactory $diagnostics = new SyntaxDiagnosticFactory,
    ) {}

    public function terminator(ScannerCursor $cursor, int $contextStart): ?SyntaxDiagnostic
    {
        return $this->delimiter($cursor, $contextStart, ';');
    }

    public function delimiter(ScannerCursor $cursor, int $contextStart, string $byte): ?SyntaxDiagnostic
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
}
