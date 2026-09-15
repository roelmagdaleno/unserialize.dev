<?php

use App\Http\Middleware\ValidateMcpRequestOrigin;
use App\Mcp\ServerCard;
use App\Mcp\Servers\UnserializeServer;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;

Mcp::web('/mcp/unserialize', UnserializeServer::class)
    ->middleware([ValidateMcpRequestOrigin::class, 'throttle:unserialize-mcp'])
    ->name('mcp.unserialize');

/**
 * Serve the MCP Server Card so an agent can discover the server without
 * opening a connection to it.
 *
 * The card is published at two locations. SEP-2127 reserves
 * `<streamable-http-url>/server-card` as the recommended spelling, while
 * `/.well-known/mcp/server-card.json` is the path agent crawlers probe for
 * site-wide discovery. Both return the same document.
 *
 * @see https://github.com/modelcontextprotocol/modelcontextprotocol/pull/2127
 */
$serverCard = function (ServerCard $card) {
    return response()->json($card->toArray(), 200, [
        'Content-Type' => ServerCard::MEDIA_TYPE,
        'Cache-Control' => 'public, max-age=3600',

        /**
         * Cards carry only public, read-only metadata, so browser-based
         * clients are allowed to read them from any origin.
         */
        'Access-Control-Allow-Origin' => '*',
        'Access-Control-Allow-Methods' => 'GET',
        'Access-Control-Allow-Headers' => 'Content-Type, If-None-Match',
        'Access-Control-Expose-Headers' => 'ETag',
    ], JSON_UNESCAPED_SLASHES);
};

Route::get('/mcp/unserialize/server-card', $serverCard)->name('mcp.server-card');

Route::get('/.well-known/mcp/server-card.json', $serverCard)->name('mcp.server-card.well-known');
