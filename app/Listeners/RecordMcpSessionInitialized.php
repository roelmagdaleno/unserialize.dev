<?php

namespace App\Listeners;

use App\Data\UsageContext;
use App\Enums\ConversionInterface;
use App\Enums\UsageEventType;
use App\Services\UsageEventRecorder;
use Laravel\Mcp\Events\SessionInitialized;

/**
 * Records the protocol version an MCP client negotiated.
 *
 * The event Laravel MCP dispatches also carries a session ID, a client name and
 * version, and the client's capabilities. None of those are read here: the
 * session ID would correlate a client's calls, and the client strings are
 * arbitrary caller-supplied values this capability has no use for. Initialization
 * is the authoritative protocol-version event, so tool calls record none.
 */
readonly class RecordMcpSessionInitialized
{
    public function __construct(private UsageEventRecorder $recorder) {}

    public function handle(SessionInitialized $event): void
    {
        $this->recorder->record(
            ConversionInterface::Mcp,
            UsageEventType::McpSessionInitialized,
            new UsageContext(mcpProtocolVersion: $event->protocolVersion),
        );
    }
}
