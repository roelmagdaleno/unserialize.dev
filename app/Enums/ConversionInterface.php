<?php

namespace App\Enums;

/**
 * The surface a conversion arrived through.
 *
 * Recorded on every usage event so the three surfaces can be compared without
 * inferring one from a user agent.
 */
enum ConversionInterface: string
{
    case Api = 'api';
    case Browser = 'browser';
    case Mcp = 'mcp';
}
