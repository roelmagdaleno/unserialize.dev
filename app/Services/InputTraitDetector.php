<?php

namespace App\Services;

use App\Enums\InputTrait;

/**
 * Recognizes the common ways a value gets damaged on its way to the converter.
 *
 * Pure: the value is read and only {@see InputTrait} cases come back, so no
 * fragment of it can leave through this class.
 */
class InputTraitDetector
{
    /**
     * The shortest value read as base64, so short tokens are not mistaken for it.
     */
    private const int MIN_BASE64_BYTES = 16;

    /**
     * Detect every trait the value shows, as it was submitted.
     *
     * @return list<InputTrait>
     */
    public function detect(string $value): array
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            return [];
        }

        return array_values(array_filter([
            $trimmed !== ltrim($value) ? InputTrait::LeadingWhitespace : null,
            $trimmed !== rtrim($value) ? InputTrait::TrailingWhitespace : null,
            $this->isWrappedInQuotes($trimmed) ? InputTrait::WrappedInQuotes : null,
            str_contains($trimmed, '\\"') ? InputTrait::EscapedQuotes : null,
            $this->looksLikeJson($trimmed) ? InputTrait::LooksLikeJson : null,
            $this->looksLikeBase64($trimmed) ? InputTrait::LooksLikeBase64 : null,
        ]));
    }

    /**
     * Whether the value opens and closes with the same quote character.
     *
     * A serialized value always opens with a type marker, so a leading quote is
     * never part of it.
     */
    private function isWrappedInQuotes(string $value): bool
    {
        return strlen($value) >= 2
            && in_array($value[0], ['"', "'", '`'], true)
            && $value[-1] === $value[0];
    }

    /**
     * Whether the value is a JSON object or array.
     */
    private function looksLikeJson(string $value): bool
    {
        return in_array($value[0], ['{', '['], true) && json_validate($value);
    }

    /**
     * Whether the value is strict base64 long enough to hold a serialized value.
     */
    private function looksLikeBase64(string $value): bool
    {
        return strlen($value) >= self::MIN_BASE64_BYTES
            && strlen($value) % 4 === 0
            && preg_match('~^[A-Za-z0-9+/]+={0,2}$~', $value) === 1
            && base64_decode($value, true) !== false;
    }
}
