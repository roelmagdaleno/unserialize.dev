<?php

namespace App\Services\Scanner;

use App\Data\SyntaxDiagnostic;
use App\Services\Scanner\Rules\ContainerRules;
use App\Services\Scanner\Rules\OpaqueRules;
use App\Services\Scanner\Rules\ScalarRules;
use App\Services\Scanner\Rules\StringRules;

/**
 * The type-marker dispatch table, and the only place the families meet.
 *
 * Splitting the grammar by family leaves one job that belongs to no family:
 * reading the marker byte and choosing who handles it. That is this class.
 *
 * The families are built here rather than injected because
 * {@see ContainerRules} has to recurse back through {@see self::value()}, and
 * constructing them in this order is what resolves that cycle once, visibly,
 * instead of leaving every rule to receive a parser it might not need.
 */
class ValueParser
{
    /**
     * Above `unserialize()`'s own limit on purpose: depth is diagnosed from
     * PHP's warning, never by this scanner guessing at the boundary.
     */
    public const int MAX_DEPTH = 1024;

    private readonly ScalarRules $scalars;

    private readonly StringRules $strings;

    private readonly ContainerRules $containers;

    private readonly OpaqueRules $opaque;

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
