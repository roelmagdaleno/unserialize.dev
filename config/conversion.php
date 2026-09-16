<?php

/**
 * Limits the conversion surfaces share.
 *
 * The browser, the HTTP API and the MCP endpoint all refuse at the same rate.
 * That number used to be written three times -- twice as `Limit::perMinute(10)`
 * and once as a private constant on the Livewire component -- so raising it on
 * one surface silently left the other two behind.
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
