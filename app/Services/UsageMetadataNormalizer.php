<?php

namespace App\Services;

/**
 * Sanitizes the untrusted metadata usage telemetry is allowed to keep.
 *
 * User-Agent, request URL, and referrer are the only values in this capability
 * that arrive verbatim from a caller, so this is the one place they are touched
 * before persistence. Every method is pure: nothing here logs, throws, or
 * echoes the value it was given, because a normalizer that reported its input
 * would defeat the reason it exists.
 *
 * A value that cannot be represented safely is discarded rather than repaired.
 * A truncated URL or a mangled header is worse than a null: it still carries
 * whatever it carried, and it now also misrepresents what was observed.
 */
class UsageMetadataNormalizer
{
    /**
     * What a sensitive query value is replaced with.
     */
    private const string REDACTED = '[redacted]';

    /**
     * The `mcp_protocol_version` column's own width. A protocol version is a
     * short date-shaped token, so anything longer is not one.
     */
    private const int MAX_PROTOCOL_VERSION_BYTES = 20;

    /**
     * Normalize a User-Agent header.
     */
    public function userAgent(?string $value): ?string
    {
        return $this->sanitize($value, (int) config('telemetry.max_user_agent_bytes'));
    }

    /**
     * Normalize an MCP protocol version negotiated by a client.
     *
     * The value is client supplied, so it is accepted only when it still looks
     * like a version token after sanitization. An implausible or over-length
     * value is dropped rather than stored.
     */
    public function protocolVersion(?string $value): ?string
    {
        $version = $this->sanitize($value, self::MAX_PROTOCOL_VERSION_BYTES);

        if ($version === null || preg_match('/^[A-Za-z0-9._-]+$/', $version) !== 1) {
            return null;
        }

        return $version;
    }

    /**
     * Normalize a request URL or a referrer.
     *
     * Credentials embedded in the authority are removed outright, and the value
     * of every configured sensitive query key is replaced. The rest of the
     * string is preserved byte for byte: unknown query values are kept, which
     * is why this metadata may not be described as anonymous.
     */
    public function url(?string $value): ?string
    {
        $url = $this->sanitize($value, (int) config('telemetry.max_url_bytes'));

        if ($url === null) {
            return null;
        }

        $url = $this->withoutCredentials($url);
        $url = $this->withRedactedQuery($url);

        return strlen($url) > (int) config('telemetry.max_url_bytes') ? null : $url;
    }

    /**
     * Remove control and formatting characters, reject anything unrepresentable.
     *
     * Invalid UTF-8 is rejected rather than scrubbed byte by byte, because the
     * surviving bytes of a broken sequence are not the value that was sent.
     */
    private function sanitize(?string $value, int $maxBytes): ?string
    {
        if ($value === null || ! mb_check_encoding($value, 'UTF-8')) {
            return null;
        }

        $sanitized = preg_replace('/[\p{Cc}\p{Cf}\p{Zl}\p{Zp}]/u', '', $value);

        if ($sanitized === null) {
            return null;
        }

        $sanitized = trim($sanitized);

        if ($sanitized === '' || strlen($sanitized) > $maxBytes) {
            return null;
        }

        return $sanitized;
    }

    /**
     * Drop a `user:password@host` authority component.
     */
    private function withoutCredentials(string $url): string
    {
        return (string) preg_replace('~^([a-z][a-z0-9+.\-]*://)[^/?#@]*@~i', '$1', $url);
    }

    /**
     * Replace the value of every configured sensitive query key.
     *
     * The string is edited in place rather than rebuilt from `parse_url()` so a
     * non-sensitive value keeps its exact original encoding and no part of the
     * URL is silently rewritten.
     */
    private function withRedactedQuery(string $url): string
    {
        $queryStart = strpos($url, '?');

        if ($queryStart === false) {
            return $url;
        }

        $fragmentStart = strpos($url, '#', $queryStart);
        $query = $fragmentStart === false
            ? substr($url, $queryStart + 1)
            : substr($url, $queryStart + 1, $fragmentStart - $queryStart - 1);

        $sensitiveKeys = array_map(
            static fn (string $key): string => mb_strtolower($key),
            (array) config('telemetry.sensitive_query_keys'),
        );

        $pairs = array_map(function (string $pair) use ($sensitiveKeys): string {
            $separator = strpos($pair, '=');

            if ($separator === false) {
                return $pair;
            }

            $key = substr($pair, 0, $separator);

            return in_array(mb_strtolower(urldecode($key)), $sensitiveKeys, true)
                ? $key.'='.self::REDACTED
                : $pair;
        }, explode('&', $query));

        return substr($url, 0, $queryStart + 1)
            .implode('&', $pairs)
            .($fragmentStart === false ? '' : substr($url, $fragmentStart));
    }
}
