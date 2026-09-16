<?php

namespace App\Services\Scanner;

use App\Data\SyntaxDiagnostic;

/**
 * The single byte expectation every rule family shares: the next byte must be
 * this one, and here is the diagnostic when it is not.
 *
 * The diagnostic factory rides along because a byte expectation is the one
 * shared helper that has to phrase a failure, and every family needs the
 * factory anyway.
 */
readonly class TokenReader
{
    /**
     * @param  SyntaxDiagnosticFactory  $diagnostics  Shared with every family through this reader.
     */
    public function __construct(
        public SyntaxDiagnosticFactory $diagnostics = new SyntaxDiagnosticFactory,
    ) {}

    /**
     * Consume the `;` that closes a value, or report its absence.
     */
    public function terminator(ScannerCursor $cursor, int $contextStart): ?SyntaxDiagnostic
    {
        return $this->delimiter($cursor, $contextStart, ';');
    }

    /**
     * Consume one expected byte, or report what stands in its place.
     *
     * `$contextStart` is the first byte of the element being parsed, which is
     * what lets the diagnostic claim the whole element rather than one byte.
     */
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
