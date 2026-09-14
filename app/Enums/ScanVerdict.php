<?php

namespace App\Enums;

/**
 * The scanner's judgement about a serialized payload.
 */
enum ScanVerdict: string
{
    /**
     * The whole payload matched the serialization grammar.
     */
    case Valid = 'valid';

    /**
     * The payload broke the grammar at a located byte.
     */
    case Invalid = 'invalid';

    /**
     * The payload uses tokens whose validity depends on runtime state the
     * scanner deliberately does not reproduce (enums, custom serializers, and
     * back references), or it exceeded the scanner's own depth cap.
     */
    case Unverifiable = 'unverifiable';
}
