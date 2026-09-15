<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Advertise the machine-readable resources of this site in a `Link` header.
 *
 * Agents that only issue a HEAD or GET against the homepage learn where the
 * API catalog, the OpenAPI description, the developer guide, and the llms.txt
 * summary live without parsing any HTML.
 *
 * @see https://www.rfc-editor.org/rfc/rfc8288
 * @see https://www.rfc-editor.org/rfc/rfc9727#section-3
 */
class AddDiscoveryLinkHeaders
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('Link', implode(', ', $this->links()));

        return $response;
    }

    /**
     * Build the `Link` field values pointing at each machine-readable resource.
     *
     * @return list<string>
     */
    private function links(): array
    {
        return [
            $this->link(route('api-catalog', absolute: false), 'api-catalog', 'application/linkset+json'),
            $this->link(route('openapi', absolute: false), 'service-desc', 'application/openapi+json'),
            $this->link(route('developers', absolute: false), 'service-doc', 'text/html'),
            $this->link(route('llms', absolute: false), 'describedby', 'text/plain'),
        ];
    }

    /**
     * Format a single RFC 8288 link field value.
     */
    private function link(string $target, string $relation, string $type): string
    {
        return sprintf('<%s>; rel="%s"; type="%s"', $target, $relation, $type);
    }
}
