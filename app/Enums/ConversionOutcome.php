<?php

namespace App\Enums;

/**
 * Every way a conversion attempt can end.
 *
 * This is the closed vocabulary the `usage_events.outcome` column and the daily
 * aggregate are keyed on, shared by the browser, HTTP and MCP surfaces so one
 * category cannot split in two.
 *
 * The failure cases mirror {@see ConversionErrorCode} one for one, plus the
 * three refusals that happen before conversion is ever attempted.
 */
enum ConversionOutcome: string
{
    case Success = 'success';

    case RateLimited = 'rate_limited';

    case UnsupportedMediaType = 'unsupported_media_type';

    case ValidationError = 'validation_error';

    case DepthLimitExceeded = 'depth_limit_exceeded';

    case EncodingFailed = 'encoding_failed';

    case InputTooLarge = 'input_too_large';

    case InvalidInput = 'invalid_input';

    case UnsupportedObject = 'unsupported_object';

    /**
     * The outcome that records a conversion failing with this error.
     *
     * Every {@see ConversionErrorCode} has a case here with the same value, so
     * this cannot fail at runtime for a code the enum declares.
     */
    public static function fromErrorCode(ConversionErrorCode $code): self
    {
        return self::from($code->value);
    }
}
