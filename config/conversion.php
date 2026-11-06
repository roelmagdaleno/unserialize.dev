<?php

/**
 * Limits the conversion surfaces share.
 *
 * The browser, the HTTP API and the MCP endpoint all refuse at the same rate, so
 * the number lives here rather than on any one surface.
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | How many conversion attempts one client may make per minute. Clients are
    | identified by a hash of their IP address, never by the address itself.
    |
    */

    'rate_limit' => [
        'per_minute' => (int) env('CONVERSION_RATE_LIMIT_PER_MINUTE', 10),
    ],

];
