<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Refuse MCP requests whose Host or Origin is not this application.
 *
 * This is the DNS-rebinding guard the MCP transport specification requires for
 * a locally reachable server. A request with no Origin header is allowed, since
 * a browser always sends one for a cross-origin request.
 */
class ValidateMcpRequestOrigin
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $applicationUrl = (string) config('app.url');

        if (! $this->hostMatches($request, $applicationUrl) || ! $this->originMatches($request, $applicationUrl)) {
            return response()->json([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32000,
                    'message' => 'Forbidden origin or host.',
                ],
            ], 403);
        }

        return $next($request);
    }

    /**
     * Whether the request's Host is this application's own.
     */
    private function hostMatches(Request $request, string $applicationUrl): bool
    {
        $applicationHost = parse_url($applicationUrl, PHP_URL_HOST);

        return is_string($applicationHost)
            && hash_equals(strtolower($applicationHost), strtolower($request->getHost()));
    }

    /**
     * Whether a supplied Origin is this application's own.
     */
    private function originMatches(Request $request, string $applicationUrl): bool
    {
        $origin = $request->header('Origin');

        if ($origin === null) {
            return true;
        }

        $applicationOrigin = $this->origin($applicationUrl);
        $requestOrigin = $this->origin($origin);

        return $applicationOrigin !== null
            && $requestOrigin !== null
            && hash_equals($applicationOrigin, $requestOrigin);
    }

    /**
     * Reduce a URL to `scheme://host:port`, or null when it is not a bare origin.
     *
     * The default port is filled in so `https://example.com` and
     * `https://example.com:443` compare equal.
     */
    private function origin(string $url): ?string
    {
        $parts = parse_url($url);

        if (
            ! is_array($parts)
            || ! isset($parts['scheme'], $parts['host'])
            || isset($parts['user'], $parts['pass'], $parts['query'], $parts['fragment'])
            || (isset($parts['path']) && ! in_array($parts['path'], ['', '/'], true))
        ) {
            return null;
        }

        $scheme = strtolower($parts['scheme']);
        $host = strtolower($parts['host']);
        $port = $parts['port'] ?? match ($scheme) {
            'http' => 80,
            'https' => 443,
            default => null,
        };

        if (! is_int($port)) {
            return null;
        }

        return "$scheme://$host:$port";
    }
}
