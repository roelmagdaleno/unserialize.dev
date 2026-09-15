<?php

/**
 * Retention and normalization limits for local usage telemetry.
 *
 * The retention window is read by both the pruning query and the public privacy
 * copy, so neither can drift away from the other.
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Event Retention
    |--------------------------------------------------------------------------
    |
    | How many days a `usage_events` row may remain in the active database
    | before the daily prune deletes it. Conversion aggregates are durable and
    | are never touched by this window.
    |
    */

    'retention_days' => (int) env('TELEMETRY_RETENTION_DAYS', 30),

    /*
    |--------------------------------------------------------------------------
    | Normalization Limits
    |--------------------------------------------------------------------------
    |
    | Maximum byte lengths for the untrusted metadata this application stores.
    | A value longer than its cap is discarded rather than truncated, so a
    | stored value is always the complete value that was observed.
    |
    */

    'max_user_agent_bytes' => (int) env('TELEMETRY_MAX_USER_AGENT_BYTES', 512),

    'max_url_bytes' => (int) env('TELEMETRY_MAX_URL_BYTES', 2048),

    /*
    |--------------------------------------------------------------------------
    | Sensitive Query Keys
    |--------------------------------------------------------------------------
    |
    | Query-string keys whose values are replaced with `[redacted]` before a URL
    | or referrer is persisted. Matching is case-insensitive. This is defense in
    | depth, not proof that a stored URL holds no personal data.
    |
    */

    'sensitive_query_keys' => array_values(array_filter(array_map(
        trim(...),
        explode(',', (string) env(
            'TELEMETRY_SENSITIVE_QUERY_KEYS',
            'token,access_token,api_key,key,secret,password,code,authorization',
        )),
    ))),

];
