<?php

namespace App\Enums;

/**
 * Categories of PHP serialization syntax problems.
 *
 * This enum is a public contract: every case is published in the HTTP API
 * response, the MCP output schema, and `public/openapi.json`. Keep it coarse
 * and add cases only when a new category is genuinely actionable.
 */
enum SyntaxErrorCode: string
{
    case ArrayCountMismatch = 'array_count_mismatch';
    case DepthLimitExceeded = 'depth_limit_exceeded';
    case InvalidArrayKey = 'invalid_array_key';
    case MalformedNumber = 'malformed_number';
    case MissingDelimiter = 'missing_delimiter';
    case MissingTerminator = 'missing_terminator';
    case StringLengthMismatch = 'string_length_mismatch';
    case TrailingData = 'trailing_data';
    case UnexpectedEnd = 'unexpected_end';
    case UnknownSyntaxError = 'unknown_syntax_error';
    case UnknownTypeMarker = 'unknown_type_marker';
}
