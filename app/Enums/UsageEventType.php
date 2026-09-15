<?php

namespace App\Enums;

/**
 * The closed set of usage events this application records.
 *
 * Event names are application-owned. A client may never name an event, and a
 * case is added only when the specification adds one.
 */
enum UsageEventType: string
{
    case ConversionCompleted = 'conversion_completed';
    case McpSessionInitialized = 'mcp_session_initialized';
    case ResultCopied = 'result_copied';
}
