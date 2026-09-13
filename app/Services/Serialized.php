<?php

namespace App\Services;

use App\Enums\OutputFormat;
use Brick\VarExporter\VarExporter;
use Exception;
use JsonException;
use ReflectionReference;
use Throwable;

class Serialized
{
    private bool $hasDecoded = false;

    private mixed $decodedData;

    /**
     * Serialized constructor.
     *
     * @since 1.0.0
     */
    public function __construct(
        public string $serializedData,
        public string $outputFormat = OutputFormat::JSON->value,
    ) {}

    /**
     * Output the serialized data.
     *
     * @since 1.0.0
     *
     * @throws Exception If the output format is invalid.
     */
    public function output(): string
    {
        $method = 'to'.ucfirst($this->outputFormat);

        if (! method_exists($this, $method)) {
            throw new Exception('Invalid output format.');
        }

        return $this->$method();
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
        $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES;

        try {
            return json_encode($this->decode(), $flags | JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new Exception('Failed to encode the serialized data to JSON.');
        }
    }

    /**
     * Transform the serialized data to an array.
     *
     * @since 1.0.0
     *
     * @throws Exception If the serialized data is invalid.
     */
    public function toArray(): string
    {
        return VarExporter::export($this->decode());
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
        } catch (Exception) {
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
            throw new Exception('Invalid serialized data.');
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
            throw new Exception('Invalid serialized data.');
        } finally {
            restore_error_handler();
        }

        if ($unserializeFailed || ($decodedData === false && $this->serializedData !== 'b:0;')) {
            throw new Exception('Invalid serialized data.');
        }

        $visitedReferences = [];

        if ($this->containsObject($decodedData, $visitedReferences)) {
            throw new Exception('Serialized objects are not supported.');
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
