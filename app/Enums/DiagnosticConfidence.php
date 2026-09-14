<?php

namespace App\Enums;

/**
 * How closely a diagnostic's location was corroborated by PHP's own parser.
 */
enum DiagnosticConfidence: string
{
    /**
     * The scanner located the problem and PHP blames a byte inside that region.
     */
    case Exact = 'exact';

    /**
     * The scanner and PHP disagreed, so PHP's offset was used instead.
     */
    case Approximate = 'approximate';

    /**
     * Only PHP's offset is available; the scanner could not explain the failure.
     */
    case Fallback = 'fallback';
}
