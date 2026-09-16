<?php

namespace App\Enums;

use App\Services\Serialized;

enum ConversionErrorCode: string
{
    case DepthLimitExceeded = 'depth_limit_exceeded';
    case EncodingFailed = 'encoding_failed';
    case InputTooLarge = 'input_too_large';
    case InvalidInput = 'invalid_input';
    case UnsupportedObject = 'unsupported_object';

    public function message(): string
    {
        return match ($this) {
            self::DepthLimitExceeded => 'The serialized data nests too deeply to convert.',
            self::EncodingFailed => 'Failed to encode the serialized data to JSON.',
            self::InputTooLarge => sprintf(
                'The serialized data must not be greater than %s bytes.',
                number_format(Serialized::MAX_INPUT_BYTES),
            ),
            self::InvalidInput => 'Invalid serialized data.',
            self::UnsupportedObject => 'Serialized objects are not supported.',
        };
    }
}
