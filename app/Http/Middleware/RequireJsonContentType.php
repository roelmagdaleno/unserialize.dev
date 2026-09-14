<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireJsonContentType
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isJson()) {
            return response()->json([
                'error' => [
                    'code' => 'unsupported_media_type',
                    'message' => 'Content-Type must be application/json.',
                ],
            ], 415);
        }

        return $next($request);
    }
}
