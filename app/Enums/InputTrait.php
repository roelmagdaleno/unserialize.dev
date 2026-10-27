<?php

namespace App\Enums;

/**
 * The closed set of formatting traits a submitted value can show.
 *
 * Each case is a yes-or-no fact about how the value was pasted, never a part of
 * it, which is what lets these travel to telemetry without breaking the promise
 * that submitted data is not retained. A case is added only when it explains a
 * failure a person can fix.
 */
enum InputTrait: string
{
    case LeadingWhitespace = 'leading_whitespace';
    case TrailingWhitespace = 'trailing_whitespace';
    case LineEndingsRestored = 'line_endings_restored';
    case WrappedInQuotes = 'wrapped_in_quotes';
    case EscapedQuotes = 'escaped_quotes';
    case LooksLikeJson = 'looks_like_json';
    case LooksLikeBase64 = 'looks_like_base64';

    /**
     * The fixed advice shown beside a failed conversion with this trait.
     *
     * Traits the form repairs by itself have no advice.
     */
    public function hint(): ?string
    {
        return match ($this) {
            self::WrappedInQuotes => 'The value is wrapped in quotes, as SQL or code would print it. Remove the quote at each end.',
            self::EscapedQuotes => 'The value contains escaped quotes (\"), as a log, dump or JSON string would print it. Replace every \" with ".',
            self::LooksLikeJson => 'This looks like JSON, not PHP serialized data. It is already readable as it is.',
            self::LooksLikeBase64 => 'This looks like base64. Decode it first, then paste the serialized value it contains.',
            default => null,
        };
    }
}
