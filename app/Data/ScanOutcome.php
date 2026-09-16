<?php

namespace App\Data;

use App\Enums\ScanVerdict;

/**
 * What a scan concluded about a payload.
 *
 * A diagnostic accompanies an invalid verdict only. `consumedBytes` reports how
 * far the grammar got, which is what locates trailing data after a valid value.
 */
readonly class ScanOutcome
{
    /**
     * @param  int  $consumedBytes  How far the grammar parsed before stopping.
     */
    public function __construct(
        public ScanVerdict $verdict,
        public ?SyntaxDiagnostic $diagnostic = null,
        public int $consumedBytes = 0,
    ) {}

    /**
     * The payload parsed cleanly for its first `$consumedBytes` bytes.
     */
    public static function valid(int $consumedBytes): self
    {
        return new self(ScanVerdict::Valid, null, $consumedBytes);
    }

    /**
     * The payload breaks the grammar, and the diagnostic says where and how.
     */
    public static function invalid(SyntaxDiagnostic $diagnostic): self
    {
        return new self(ScanVerdict::Invalid, $diagnostic);
    }

    /**
     * The scanner declines to judge the payload, so no diagnostic is published.
     */
    public static function unverifiable(int $consumedBytes = 0): self
    {
        return new self(ScanVerdict::Unverifiable, null, $consumedBytes);
    }
}
