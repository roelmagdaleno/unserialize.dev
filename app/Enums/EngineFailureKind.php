<?php

namespace App\Enums;

/**
 * How PHP's own `unserialize()` reacted to a payload.
 *
 * PHP reports several non-syntactic conditions through the same
 * "Error at offset N" warning, so the kind must be decided before any offset
 * is trusted as a syntax location.
 */
enum EngineFailureKind: string
{
    /**
     * `unserialize()` succeeded.
     */
    case None = 'none';

    /**
     * The payload nests deeper than the configured maximum.
     */
    case DepthExceeded = 'depth_exceeded';

    /**
     * A complete value was followed by bytes that are not part of it.
     */
    case ExtraData = 'extra_data';

    /**
     * A class-backed payload could not be restored by its own unserializer.
     */
    case ObjectUnserializer = 'object_unserializer';

    /**
     * `unserialize()` failed without saying where.
     */
    case Opaque = 'opaque';

    /**
     * The payload broke the grammar at the reported offset.
     */
    case SyntaxError = 'syntax_error';
}
