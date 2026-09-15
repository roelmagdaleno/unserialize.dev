<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\AcceptHeader;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serve a Markdown representation of a page to clients that ask for one.
 *
 * HTML stays the default: only an Accept header that names `text/markdown`
 * explicitly, and at no lower quality than `text/html`, switches the
 * representation. Browsers send `text/html` plus a wildcard catch-all, never
 * the Markdown type, so they are unaffected.
 */
class NegotiateMarkdownRepresentation
{
    private const MEDIA_TYPE = 'text/markdown';

    /**
     * Bytes per token used to estimate the `X-Markdown-Tokens` count.
     */
    private const BYTES_PER_TOKEN = 4;

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     * @param  string  $view  The Blade view holding the Markdown representation.
     */
    public function handle(Request $request, Closure $next, string $view): Response
    {
        $response = $this->wantsMarkdown($request)
            ? $this->markdownResponse($view)
            : $next($request);

        $response->setVary(array_unique([...$response->getVary(), 'Accept']));

        return $response;
    }

    /**
     * Determine whether the client asked for Markdown over HTML.
     */
    private function wantsMarkdown(Request $request): bool
    {
        $accept = AcceptHeader::fromString($request->headers->get('Accept', ''));

        if (! $accept->has(self::MEDIA_TYPE)) {
            return false;
        }

        $markdownQuality = $accept->get(self::MEDIA_TYPE)->getQuality();

        if ($markdownQuality <= 0) {
            return false;
        }

        $htmlQuality = $accept->has('text/html')
            ? $accept->get('text/html')->getQuality()
            : 0.0;

        return $markdownQuality >= $htmlQuality;
    }

    /**
     * Render the Markdown representation with its negotiated headers.
     */
    private function markdownResponse(string $view): Response
    {
        $markdown = rtrim(view($view)->render())."\n";

        return response($markdown, 200, [
            'Content-Type' => self::MEDIA_TYPE.'; charset=UTF-8',
            'X-Markdown-Tokens' => (string) (int) ceil(strlen($markdown) / self::BYTES_PER_TOKEN),
        ]);
    }
}
