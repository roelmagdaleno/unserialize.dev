<?php

namespace App\Data;

use App\Enums\ScanVerdict;

class ScanOutcome
{
    public function __construct(
        public readonly ScanVerdict $verdict,
        public readonly ?SyntaxDiagnostic $diagnostic = null,
        public readonly int $consumedBytes = 0,
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
