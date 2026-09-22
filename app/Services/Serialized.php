<?php

namespace App\Services;

use App\Data\ConversionResult;
use App\Enums\ConversionErrorCode;
use App\Exceptions\ConversionException;
use JsonException;
use Serialized\Exceptions\SerializedException;
use Serialized\Options;
use Serialized\Serialized as Package;
use Serialized\SerializedConverter;

/**
 * Converts one serialized payload, and explains it when it cannot.
 *
 * The grammar, the safety rules and the byte-level diagnostics all belong to
 * `roelmagdaleno/serialized`. What stays here is this application's side of the
 * contract: the limits it publishes, the error vocabulary its three surfaces
 * share, and the refusal to restore an object from a payload.
 *
 * Conversion happens at most once per instance. The result or the failure that
 * stopped it is kept, so a second call never re-reads the payload.
 */
class Serialized
{
    /**
     * The largest payload accepted, in bytes.
     */
    public const int MAX_INPUT_BYTES = 262144;

    /**
     * The deepest nesting decoded.
     */
    public const int MAX_DEPTH = 512;

    /**
     * The successful conversion, kept so a second call does not repeat it.
     */
    private ?ConversionResult $result = null;

    /**
     * The failure this payload already produced, kept for the same reason.
     */
    private ?ConversionException $failure = null;

    /**
     * @param  string  $serializedData  The payload to convert.
     * @param  DiagnosticTranslator|null  $translator  Built on demand when omitted.
     */
    public function __construct(
        public string $serializedData,
        private ?DiagnosticTranslator $translator = null,
    ) {}

    /**
     * Output the serialized data as JSON.
     *
     * @throws ConversionException If the serialized data cannot be converted.
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
        if ($this->result !== null) {
            return $this->result;
        }

        if ($this->failure !== null) {
            throw $this->failure;
        }

        try {
            $value = self::converter()->toArray($this->serializedData);
        } catch (SerializedException $exception) {
            throw $this->fail($exception);
        }

        return $this->result = new ConversionResult($value, $this->encode($value));
    }

    /**
     * The converter every surface shares, configured to this application's limits.
     *
     * No class is ever allowed, so no payload can reach PHP's object instantiation
     * and nothing from a payload is ever constructed.
     */
    private static function converter(): SerializedConverter
    {
        return Package::make()
            ->withMaxBytes(self::MAX_INPUT_BYTES)
            ->withMaxDepth(self::MAX_DEPTH);
    }

    /**
     * Encode a decoded value with the package's own JSON flags.
     *
     * The value is encoded here rather than by a second call to the package, which
     * would re-read and re-validate the whole payload to reach a value already in
     * hand. Nothing is lost by doing so: the package normalizes objects on its way
     * to JSON, and this application allows none, so every value reaching this point
     * is a scalar, an array or null. `ConversionEncodingTest` pins the two outputs
     * against each other, so a normalization that ever does start to matter fails
     * there rather than silently changing what the site returns.
     *
     * One case does differ, and deliberately. The package spots a value that contains
     * itself while normalizing and names the byte the loop starts from; reaching that
     * would cost a second read of every payload to catch a shape that needs a PHP
     * reference to build. Here `json_encode()` reports it instead, so the payload is
     * refused as an encoding failure -- which is the answer this application gave for
     * it before the package existed.
     *
     * @throws ConversionException If the value cannot be encoded.
     */
    private function encode(mixed $value): string
    {
        try {
            return json_encode($value, Options::DEFAULT_JSON_FLAGS);
        } catch (JsonException) {
            throw $this->failure = new ConversionException(ConversionErrorCode::EncodingFailed);
        }
    }

    /**
     * Remember why this payload could not be converted, and hand back the
     * exception for the caller to throw.
     */
    private function fail(SerializedException $exception): ConversionException
    {
        $translator = $this->translator ??= new DiagnosticTranslator;

        return $this->failure = new ConversionException(
            $translator->errorCodeFor($exception),
            $translator->diagnosticFor($exception, strlen($this->serializedData)),
        );
    }
}
