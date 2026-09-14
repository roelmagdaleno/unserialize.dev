<?php

namespace App\Data;

use App\Enums\EngineFailureKind;

/**
 * PHP's verdict on a payload, classified from the diagnostics it raised.
 *
 * `unserialize()` reports several unrelated conditions through the same
 * "Error at offset N" warning, and it raises benign diagnostics for payloads it
 * decodes perfectly well. Classifying the warnings first is what keeps a
 * deprecation notice from being reported to the user as a syntax error.
 */
class EngineOutcome
{
    public function __construct(
        public readonly EngineFailureKind $kind,
        public readonly ?int $offset = null,
    ) {}

    public function failed(): bool
    {
        return $this->kind !== EngineFailureKind::None;
    }

    /**
     * Classify one `unserialize()` call from its diagnostics and return value.
     *
     * @param  array<int, string>  $warnings  Every diagnostic message raised during the call, in order.
     */
    public static function fromWarnings(array $warnings, mixed $value, string $data): self
    {
        $depthExceeded = false;
        $objectUnserializer = false;
        $unexplainedFailure = false;
        $extraDataOffset = null;
        $syntaxOffset = null;

        foreach ($warnings as $warning) {
            /**
             * PHP 8.4 deprecates the `S` format but still decodes it correctly,
             * and it reports integer saturation while returning PHP_INT_MAX.
             * Neither is a failure.
             */
            if (str_contains($warning, "'S' format is deprecated")) {
                continue;
            }

            if (str_contains($warning, 'Numerical result out of range')) {
                continue;
            }

            if (str_contains($warning, 'Maximum depth of')) {
                $depthExceeded = true;

                continue;
            }

            if (str_contains($warning, 'has no unserializer')) {
                $objectUnserializer = true;

                continue;
            }

            if (preg_match('/Extra data starting at offset (\d+)/', $warning, $matches) === 1) {
                $extraDataOffset ??= (int) $matches[1];

                continue;
            }

            if (preg_match('/Error at offset (\d+)/', $warning, $matches) === 1) {
                $syntaxOffset ??= (int) $matches[1];

                continue;
            }

            $unexplainedFailure = true;
        }

        /**
         * Depth and trailing data both come with an "Error at offset" warning
         * that would otherwise mislabel them, so they are decided first.
         */
        if ($depthExceeded) {
            return new self(EngineFailureKind::DepthExceeded, $syntaxOffset);
        }

        if ($objectUnserializer) {
            return new self(EngineFailureKind::ObjectUnserializer);
        }

        if ($extraDataOffset !== null) {
            return new self(EngineFailureKind::ExtraData, $extraDataOffset);
        }

        if ($syntaxOffset !== null) {
            return new self(EngineFailureKind::SyntaxError, $syntaxOffset);
        }

        if ($unexplainedFailure) {
            return new self(EngineFailureKind::Opaque);
        }

        if ($value === false && $data !== 'b:0;') {
            return new self(EngineFailureKind::Opaque);
        }

        return new self(EngineFailureKind::None);
    }
}
