<?php

namespace App\Services\Scanner;

use App\Data\SyntaxDiagnostic;
use App\Services\Scanner\Rules\ContainerRules;
use App\Services\Scanner\Rules\OpaqueRules;
use App\Services\Scanner\Rules\ScalarRules;
use App\Services\Scanner\Rules\StringRules;

/**
 * The type-marker dispatch table: read the marker byte and choose the family
 * that handles it.
 *
 * The families are built here rather than injected because
 * {@see ContainerRules} has to recurse back through {@see self::value()}, and
 * constructing them in this order resolves that cycle once, visibly, instead of
 * leaving every rule to receive a parser it might not need.
 */
class ValueParser
{
    /**
     * Above `unserialize()`'s own limit on purpose: depth is diagnosed from
     * PHP's warning, never by this scanner guessing at the boundary.
     */
    public const int MAX_DEPTH = 1024;

    /**
     * The values with no length and no body: `N`, `b`, `i`, `d`.
     */
    private readonly ScalarRules $scalars;

    /**
     * Everything written as `<length>:"<contents>"`.
     */
    private readonly StringRules $strings;

    /**
     * The values that declare a count and hold other values: `a` and `O`.
     */
    private readonly ContainerRules $containers;

    /**
     * The tokens validated for shape only: `C`, `E`, `r` and `R`.
     */
    private readonly OpaqueRules $opaque;

    /**
     * @param  TokenReader  $reader  Shared by every family it builds.
     */
    public function __construct(private readonly TokenReader $reader = new TokenReader)
    {
        $this->scalars = new ScalarRules($reader);
        $this->strings = new StringRules($reader);
        $this->opaque = new OpaqueRules($reader, $this->strings);
        $this->containers = new ContainerRules($reader, $this->scalars, $this->strings, $this);
    }

    /**
     * Consume one serialized value starting at the cursor.
     */
    public function value(ScannerCursor $cursor, int $depth): ?SyntaxDiagnostic
    {
        if ($depth > self::MAX_DEPTH) {
            $cursor->unverifiable = true;

            return $this->reader->diagnostics->depthLimitExceeded($cursor->position);
        }

        $position = $cursor->position;

        if ($position >= $cursor->length) {
            return $this->reader->diagnostics->valueEndedEarly($cursor->length, $position);
        }

        return match ($cursor->data[$position]) {
            'N' => $this->scalars->nullValue($cursor),
            'b' => $this->scalars->boolean($cursor),
            'i' => $this->scalars->integer($cursor),
            'd' => $this->scalars->double($cursor),
            's' => $this->strings->string($cursor, false),
            'S' => $this->strings->string($cursor, true),
            'a' => $this->containers->arrayValue($cursor, $depth),
            'O' => $this->containers->objectValue($cursor, $depth),
            'C' => $this->opaque->customObject($cursor),
            'E' => $this->opaque->enumValue($cursor),
            'r', 'R' => $this->opaque->reference($cursor),
            default => $this->reader->diagnostics->unknownTypeMarker($cursor->position),
        };
    }
}
