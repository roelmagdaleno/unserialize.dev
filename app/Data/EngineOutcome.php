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
readonly class EngineOutcome
{
    /**
     * Diagnostics PHP raises for payloads it still decodes correctly.
     *
     * PHP 8.4 deprecates the `S` format but reads it fine, and it reports
     * integer saturation while returning PHP_INT_MAX. Neither is a failure.
     *
     * @var list<string>
     */
    private const array BENIGN_WARNINGS = [
        "'S' format is deprecated",
        'Numerical result out of range',
    ];

    /**
     * Warnings that name a failure without reporting an offset of their own.
     *
     * @var array<string, EngineFailureKind>
     */
    private const array FAILURE_WARNINGS = [
        'Maximum depth of' => EngineFailureKind::DepthExceeded,
        'has no unserializer' => EngineFailureKind::ObjectUnserializer,
    ];

    /**
     * Warnings that carry the offset they refer to in their first capture group.
     *
     * @var array<string, EngineFailureKind>
     */
    private const array OFFSET_WARNINGS = [
        '/Extra data starting at offset (\d+)/' => EngineFailureKind::ExtraData,
        '/Error at offset (\d+)/' => EngineFailureKind::SyntaxError,
    ];

    /**
     * The order in which competing classifications win.
     *
     * Depth and trailing data both come with an "Error at offset" warning that
     * would otherwise mislabel them, so they are decided before a syntax error.
     *
     * @var list<EngineFailureKind>
     */
    private const array PRECEDENCE = [
        EngineFailureKind::DepthExceeded,
        EngineFailureKind::ObjectUnserializer,
        EngineFailureKind::ExtraData,
        EngineFailureKind::SyntaxError,
        EngineFailureKind::Opaque,
    ];

    public function __construct(
        public EngineFailureKind $kind,
        public ?int $offset = null,
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
        /**
         * The first offset seen per kind, keyed by kind, so that later warnings
         * of the same kind cannot overwrite where the failure started.
         *
         * @var array<string, int|null> $offsets
         */
        $offsets = [];

        foreach ($warnings as $warning) {
            $classified = self::classifyWarning($warning);

            if ($classified === null) {
                continue;
            }

            if (! array_key_exists($classified->kind->value, $offsets)) {
                $offsets[$classified->kind->value] = $classified->offset;
            }
        }

        foreach (self::PRECEDENCE as $kind) {
            if (! array_key_exists($kind->value, $offsets)) {
                continue;
            }

            return new self($kind, self::offsetFor($kind, $offsets));
        }

        if ($value === false && $data !== 'b:0;') {
            return new self(EngineFailureKind::Opaque);
        }

        return new self(EngineFailureKind::None);
    }

    /**
     * Match one warning against the buckets, or return null when it is benign.
     */
    private static function classifyWarning(string $warning): ?self
    {
        foreach (self::BENIGN_WARNINGS as $needle) {
            if (str_contains($warning, $needle)) {
                return null;
            }
        }

        foreach (self::FAILURE_WARNINGS as $needle => $kind) {
            if (str_contains($warning, $needle)) {
                return new self($kind);
            }
        }

        foreach (self::OFFSET_WARNINGS as $pattern => $kind) {
            if (preg_match($pattern, $warning, $matches) === 1) {
                return new self($kind, (int) $matches[1]);
            }
        }

        return new self(EngineFailureKind::Opaque);
    }

    /**
     * Resolve the offset a classified kind should be reported with.
     *
     * A depth failure has no offset of its own, so it borrows the one from the
     * "Error at offset" warning PHP raises alongside it.
     *
     * @param  array<string, int|null>  $offsets
     */
    private static function offsetFor(EngineFailureKind $kind, array $offsets): ?int
    {
        if ($kind === EngineFailureKind::DepthExceeded) {
            return $offsets[EngineFailureKind::SyntaxError->value] ?? null;
        }

        return $offsets[$kind->value];
    }
}
