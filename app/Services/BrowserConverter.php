<?php

namespace App\Services;

use App\Data\BrowserConversion;
use App\Data\ConversionResult;
use App\Enums\SyntaxErrorCode;
use App\Exceptions\ConversionException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Converts the converter field's value, repairing what the browser changed.
 */
class BrowserConverter
{
    /**
     * Validate the value and convert it.
     *
     * A `<textarea>` reports every line break as `\n`, so a value copied with
     * Windows line endings loses one byte per break inside its strings. Only the
     * browser repairs this: the API and MCP receive the exact bytes sent.
     *
     * @param  string  $serializedData  The trimmed field value.
     *
     * @throws ValidationException When the field is empty.
     * @throws ConversionException The failure of the value as submitted, when no repair converts it.
     */
    public function convert(string $serializedData): BrowserConversion
    {
        Validator::make(
            ['serializedData' => $serializedData],
            ['serializedData' => ['bail', 'required', 'string']],
        )->validate();

        try {
            return new BrowserConversion(new Serialized($serializedData)->convert());
        } catch (ConversionException $exception) {
            $restored = $this->convertWithWindowsLineEndings($serializedData, $exception);

            if ($restored === null) {
                throw $exception;
            }

            return new BrowserConversion($restored, lineEndingsRestored: true);
        }
    }

    /**
     * Retry a string length failure with every `\n` read back as `\r\n`.
     *
     * Only a value with line breaks and no carriage return is retried, because
     * that is the shape a `<textarea>` leaves behind.
     */
    private function convertWithWindowsLineEndings(string $serializedData, ConversionException $exception): ?ConversionResult
    {
        if ($exception->diagnostic?->code !== SyntaxErrorCode::StringLengthMismatch
            || ! str_contains($serializedData, "\n")
            || str_contains($serializedData, "\r")) {
            return null;
        }

        try {
            return new Serialized(str_replace("\n", "\r\n", $serializedData))->convert();
        } catch (ConversionException) {
            return null;
        }
    }
}
