<?php

namespace App\Services;

use App\Enums\OutputFormats;
use Brick\VarExporter\VarExporter;
use Exception;

class Serialized
{
    /**
     * Serialized constructor.
     *
     * @since 1.0.0
     *
     * @param  string  $serializedData  The serialized data.
     * @param  string  $outputFormat  The output format (`json` by default).
     */
    public function __construct(
        public string $serializedData,
        public string $outputFormat = OutputFormats::JSON->value,
    ) {}

    /**
     * Output the serialized data.
     *
     * @since 1.0.0
     *
     * @return string The output.
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
     * @return string The JSON string.
     *
     * @throws Exception If the serialized data is invalid.
     */
    public function toJson(): string
    {
        if (! $this->isValid()) {
            throw new Exception('Invalid serialized data.');
        }

        $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES;
        $json = json_encode(@unserialize($this->serializedData), $flags);

        if ($json === false) {
            throw new Exception('Failed to encode the serialized data to JSON.');
        }

        return $json;
    }

    /**
     * Transform the serialized data to an array.
     *
     * @since 1.0.0
     *
     * @return string The array string.
     *
     * @throws Exception If the serialized data is invalid.
     */
    public function toArray(): string
    {
        $unserialized = $this->toJson();

        return $unserialized ? VarExporter::export(json_decode($unserialized, true)) : false;
    }

    /**
     * Check if the serialized data is valid.
     *
     * @since 1.0.0
     *
     * @return bool True if the serialized data is valid, false otherwise.
     */
    public function isValid(): bool
    {
        // Check for empty input
        if (empty($this->serializedData) || ! is_string($this->serializedData)) {
            return false;
        }

        // Check if string is a valid serialized "false" value
        if ($this->serializedData === 'b:0;') {
            return true;
        }

        // Special case: serialize(null) returns "N;"
        if ($this->serializedData === 'N;') {
            return true;
        }

        if (strlen($this->serializedData) < 4) {
            return false;
        }

        if ($this->serializedData[1] !== ':') {
            return false;
        }

        // Attempt to unserialize
        $data = @unserialize($this->serializedData);

        return $data !== false;
    }
}
