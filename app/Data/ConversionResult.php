<?php

namespace App\Data;

/**
 * A successfully converted value in both the forms the surfaces need.
 */
readonly class ConversionResult
{
    /**
     * @param  mixed  $value  The decoded PHP value.
     * @param  string  $json  The same value encoded as JSON for display and transport.
     */
    public function __construct(
        public mixed $value,
        public string $json,
    ) {}
}
