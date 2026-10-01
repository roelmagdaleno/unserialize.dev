<?php

namespace App\Mcp\Methods;

use App\Data\UsageContext;
use App\Enums\ConversionInterface;
use App\Enums\UsageEventType;
use App\Mcp\Servers\UnserializeServer;
use App\Mcp\Tools\ConvertSerializedDataTool;
use App\Services\UsageEventRecorder;
use Illuminate\Http\Request;
use Laravel\Mcp\Server\Methods\ListTools;
use Laravel\Mcp\Server\ServerContext;
use Laravel\Mcp\Transport\JsonRpcRequest;
use Laravel\Mcp\Transport\JsonRpcResponse;

/**
 * Answers `tools/list` and records that a client asked for the tool listing.
 *
 * - Laravel MCP dispatches no event for `tools/list`, so this handler replaces
 *   the package's own in {@see UnserializeServer::boot()}.
 * - The cursor and page size are paging details, not usage, and are not recorded.
 */
class RecordingListTools extends ListTools
{
    /**
     * Persists the usage event, and supplies the HTTP request it describes.
     */
    public function __construct(
        private readonly UsageEventRecorder $recorder,
        private readonly Request $httpRequest,
    ) {}

    /**
     * List the tools, then record the listing as a usage event.
     */
    public function handle(JsonRpcRequest $request, ServerContext $context): JsonRpcResponse
    {
        $response = parent::handle($request, $context);

        $this->recorder->record(
            ConversionInterface::Mcp,
            UsageEventType::McpToolsListed,
            UsageContext::fromRequest(
                $this->httpRequest,
                mcpTransport: ConvertSerializedDataTool::TRANSPORT,
            ),
        );

        return $response;
    }
}
