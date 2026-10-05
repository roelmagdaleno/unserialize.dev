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
 * share, and reading every object as data rather than restoring its class.
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
     * Every object is read as data, and no class is allowed:
     *
     * - An object of any class comes back as its properties. The class is never loaded
     *   or instantiated, so nothing from a payload runs.
     * - Custom-serialized objects and enums are refused before PHP reads the payload.
     *   Never allow a class here: allowing one runs its magic methods on payload data.
     */
    private static function converter(): SerializedConverter
    {
        return Package::make()
            ->withMaxBytes(self::MAX_INPUT_BYTES)
            ->withMaxDepth(self::MAX_DEPTH)
            ->objectsAsData();
    }

    /**
     * Encode a decoded value with the package's own JSON flags.
     *
     * The value is encoded here rather than by a second call to the package, which
     * would re-read and re-validate the whole payload to reach a value already in
     * hand. Nothing is lost by doing so: with objects read as data, the package hands
     * back every object already normalized to a `stdClass` of its properties, which is
     * exactly what it would encode. `ConversionEncodingTest` pins the two outputs
     * against each other, so a normalization that ever does start to matter fails
     * there rather than silently changing what the site returns.
     *
     * A value that contains itself never reaches this point: the package refuses it
     * while reading objects as data, naming the reference that closes the loop.
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
