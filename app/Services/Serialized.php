<?php

namespace App\Services;

use App\Data\ConversionResult;
use App\Data\EngineOutcome;
use App\Enums\ConversionErrorCode;
use App\Enums\EngineFailureKind;
use App\Exceptions\ConversionException;
use JsonException;
use ReflectionReference;
use Throwable;

class Serialized
{
    public const int MAX_INPUT_BYTES = 262144;

    public const int MAX_DEPTH = 512;

    private bool $hasDecoded = false;

    private mixed $decodedData;

    /**
     * Serialized constructor.
     *
     * @since 1.0.0
     */
    public function __construct(
        public string $serializedData,
        private ?SerializedDiagnostics $diagnostics = null,
    ) {}

    /**
     * The diagnostics service, built on demand so conversion never pays for it.
     */
    private function diagnostics(): SerializedDiagnostics
    {
        return $this->diagnostics ??= new SerializedDiagnostics(new SerializedScanner);
    }

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
     * @throws ConversionException If the serialized data cannot be converted.
     */
    private function decode(): mixed
    {
        if ($this->hasDecoded) {
            return $this->decodedData;
        }

        if (strlen($this->serializedData) > self::MAX_INPUT_BYTES) {
            throw new ConversionException(ConversionErrorCode::InputTooLarge);
        }

        /**
         * Every diagnostic is collected rather than collapsed into a boolean:
         * PHP raises benign notices for payloads it decodes perfectly well, and
         * it reports unrelated conditions through one shared warning. Only
         * {@see EngineOutcome} is allowed to decide which of them is a failure.
         */
        $warnings = [];
        $threw = false;

        set_error_handler(static function (int $number, string $message) use (&$warnings): bool {
            $warnings[] = $message;

            return true;
        });

        try {
            $decodedData = unserialize($this->serializedData, [
                'allowed_classes' => false,
                'max_depth' => self::MAX_DEPTH,
            ]);
        } catch (Throwable) {
            $threw = true;
            $decodedData = false;
        } finally {
            restore_error_handler();
        }

        $engine = $threw
            ? new EngineOutcome(EngineFailureKind::Opaque)
            : EngineOutcome::fromWarnings($warnings, $decodedData, $this->serializedData);

        if ($engine->kind === EngineFailureKind::ObjectUnserializer) {
            throw new ConversionException(ConversionErrorCode::UnsupportedObject);
        }

        if ($engine->kind === EngineFailureKind::DepthExceeded) {
            throw new ConversionException(
                ConversionErrorCode::DepthLimitExceeded,
                $this->diagnostics()->diagnose($this->serializedData, $engine),
            );
        }

        if ($engine->failed()) {
            throw new ConversionException(
                ConversionErrorCode::InvalidInput,
                $this->diagnostics()->diagnose($this->serializedData, $engine),
            );
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
