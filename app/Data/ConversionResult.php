<?php

namespace App\Data;

class ConversionResult
{
    public function __construct(
        public readonly mixed $value,
        public readonly string $json,
    ) {}
}
