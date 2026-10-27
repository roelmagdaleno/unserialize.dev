<?php

namespace App\Livewire\Forms;

use App\Data\BrowserConversion;
use App\Data\ConversionResult;
use App\Enums\SyntaxErrorCode;
use App\Exceptions\ConversionException;
use App\Services\Serialized;
use Livewire\Form;

/**
 * The converter's single input, with its validation rules.
 */
class SerializedForm extends Form
{
    /**
     * The payload to convert, prefilled with a worked example.
     */
    public string $serializedData = 'a:10:{s:4:"name";s:6:"Chrome";s:7:"version";s:9:"103.0.0.0";s:8:"platform";s:7:"Windows";s:10:"update_url";s:29:"https://www.google.com/chrome";s:7:"img_src";s:44:"https://s.w.org/images/browsers/chrome.png?1";s:11:"img_src_ssl";s:44:"https://s.w.org/images/browsers/chrome.png?1";s:15:"current_version";s:2:"18";s:7:"upgrade";b:0;s:8:"insecure";b:0;s:6:"mobile";b:0;}';

    /**
     * Validate the payload and convert it, repairing what the browser changed.
     *
     * - Whitespace around the value is removed, and the field shows the trimmed
     *   value, so a diagnostic's byte offset indexes what the person sees. A
     *   serialized value never starts or ends with whitespace.
     * - A `<textarea>` reports every line break as `\n`, so a value copied with
     *   Windows line endings loses one byte per break inside its strings. Only
     *   the browser repairs this: the API and MCP receive the exact bytes sent.
     *
     * @throws ConversionException The failure of the value as submitted, when no repair converts it.
     */
    public function submit(): BrowserConversion
    {
        $this->serializedData = trim($this->serializedData);

        $this->validate();

        try {
            return new BrowserConversion(new Serialized($this->serializedData)->convert());
        } catch (ConversionException $exception) {
            $restored = $this->convertWithWindowsLineEndings($exception);

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
    private function convertWithWindowsLineEndings(ConversionException $exception): ?ConversionResult
    {
        if ($exception->diagnostic?->code !== SyntaxErrorCode::StringLengthMismatch
            || ! str_contains($this->serializedData, "\n")
            || str_contains($this->serializedData, "\r")) {
            return null;
        }

        try {
            return new Serialized(str_replace("\n", "\r\n", $this->serializedData))->convert();
        } catch (ConversionException) {
            return null;
        }
    }

    /**
     * @return array<string, list<string>>
     */
    protected function rules(): array
    {
        return [
            'serializedData' => ['bail', 'required', 'string'],
        ];
    }
}
