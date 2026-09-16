<?php

namespace App\Data;

readonly class ConversionResult
{
    public function __construct(
        public mixed $value,
        public string $json,
    ) {}
}
