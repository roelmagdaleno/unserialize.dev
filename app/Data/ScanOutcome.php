<?php

namespace App\Data;

use App\Enums\ScanVerdict;

readonly class ScanOutcome
{
    public function __construct(
        public ScanVerdict $verdict,
        public ?SyntaxDiagnostic $diagnostic = null,
        public int $consumedBytes = 0,
    ) {}

    public static function valid(int $consumedBytes): self
    {
        return new self(ScanVerdict::Valid, null, $consumedBytes);
    }

    public static function invalid(SyntaxDiagnostic $diagnostic): self
    {
        return new self(ScanVerdict::Invalid, $diagnostic);
    }

    public static function unverifiable(int $consumedBytes = 0): self
    {
        return new self(ScanVerdict::Unverifiable, null, $consumedBytes);
    }
}
