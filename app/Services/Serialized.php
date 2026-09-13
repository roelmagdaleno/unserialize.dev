<?php

namespace App\Services;

use App\Data\ConversionResult;
use App\Enums\ConversionErrorCode;
use App\Exceptions\ConversionException;
use JsonException;
use ReflectionReference;
use Throwable;

class Serialized
{
    public const int MAX_INPUT_BYTES = 262144;

    private bool $hasDecoded = false;

    private mixed $decodedData;

    /**
     * Serialized constructor.
     *
     * @since 1.0.0
     */
    public function __construct(
        public string $serializedData,
    ) {}

    /**
     * Output the serialized data.
     *
     * @since 1.0.0
     *
     * @throws Exception If the serialized data is invalid.
     */
    public function output(): string
    {
        return $this->convert()->json;
    }

    /**
     * Convert serialized PHP data into a native value and JSON representation.
     *
     * @throws ConversionException
     */
    public function convert(): ConversionResult
    {
        $decodedData = $this->decode();
        $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES;

        try {
            $json = json_encode($decodedData, $flags | JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new ConversionException(ConversionErrorCode::EncodingFailed);
        }

        return new ConversionResult($decodedData, $json);
    }

    /**
     * Transform the serialized data to a JSON string.
     *
     * @since 1.0.0
     *
     * @throws Exception If the serialized data is invalid.
     */
    public function toJson(): string
    {
        return $this->convert()->json;
    }

    /**
     * Check if the serialized data is valid.
     *
     * @since 1.0.0
     */
    public function isValid(): bool
    {
        try {
            $this->decode();
        } catch (ConversionException) {
            return false;
        }

        return true;
    }

    /**
     * Safely decode the serialized value once.
     *
     * @throws Exception If the serialized data is invalid or contains an object.
     */
    private function decode(): mixed
    {
        if ($this->hasDecoded) {
            return $this->decodedData;
        }

        if ($this->serializedData === '') {
            throw new ConversionException(ConversionErrorCode::InvalidInput);
        }

        if (strlen($this->serializedData) > self::MAX_INPUT_BYTES) {
            throw new ConversionException(ConversionErrorCode::InputTooLarge);
        }

        $unserializeFailed = false;
        set_error_handler(static function () use (&$unserializeFailed): bool {
            $unserializeFailed = true;

            return true;
        });

        try {
            $decodedData = unserialize($this->serializedData, [
                'allowed_classes' => false,
                'max_depth' => 512,
            ]);
        } catch (Throwable) {
            throw new ConversionException(ConversionErrorCode::InvalidInput);
        } finally {
            restore_error_handler();
        }

        if ($unserializeFailed || ($decodedData === false && $this->serializedData !== 'b:0;')) {
            throw new ConversionException(ConversionErrorCode::InvalidInput);
        }

        $visitedReferences = [];

        if ($this->containsObject($decodedData, $visitedReferences)) {
            throw new ConversionException(ConversionErrorCode::UnsupportedObject);
        }

        $this->decodedData = $decodedData;
        $this->hasDecoded = true;

        return $this->decodedData;
    }

    /**
     * Determine whether a decoded value contains an object without looping over references.
     *
     * @param  array<string, true>  $visitedReferences
     */
    private function containsObject(mixed &$value, array &$visitedReferences): bool
    {
        if (is_object($value)) {
            return true;
        }

        if (! is_array($value)) {
            return false;
        }

        foreach (array_keys($value) as $key) {
            $reference = ReflectionReference::fromArrayElement($value, $key);

            if ($reference !== null) {
                $referenceId = bin2hex($reference->getId());

                if (isset($visitedReferences[$referenceId])) {
                    continue;
                }

                $visitedReferences[$referenceId] = true;
            }

            if ($this->containsObject($value[$key], $visitedReferences)) {
                return true;
            }
        }

        return false;
    }
}
