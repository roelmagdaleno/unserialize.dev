<?php

namespace App\Data;

/**
 * A successful browser conversion and whether the form repaired the payload
 * the browser sent before it converted.
 */
readonly class BrowserConversion
{
    /**
     * @param  bool  $lineEndingsRestored  Whether the value only converted once its `\n` were read back as `\r\n`.
     */
    public function __construct(
        public ConversionResult $result,
        public bool $lineEndingsRestored = false,
    ) {}
}
