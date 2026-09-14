<?php

use App\Mcp\Servers\UnserializeServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::web('/mcp/unserialize', UnserializeServer::class)
    ->middleware('throttle:unserialize-mcp')
    ->name('mcp.unserialize');
