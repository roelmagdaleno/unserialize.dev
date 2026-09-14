<?php

use App\Http\Middleware\ValidateMcpRequestOrigin;
use App\Mcp\Servers\UnserializeServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::web('/mcp/unserialize', UnserializeServer::class)
    ->middleware([ValidateMcpRequestOrigin::class, 'throttle:unserialize-mcp'])
    ->name('mcp.unserialize');
