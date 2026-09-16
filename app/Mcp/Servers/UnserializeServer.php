<?php

namespace App\Mcp\Servers;

use App\Mcp\ServerCard;
use App\Mcp\Tools\ConvertSerializedDataTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Tool;

#[Name(UnserializeServer::NAME)]
#[Version(UnserializeServer::VERSION)]
#[Instructions('Convert untrusted PHP serialized values to structured JSON. Inputs are limited to 262144 bytes, objects are rejected, and conversions are not retained.')]
/**
 * The MCP server exposing this application's single conversion tool.
 *
 * The primitive lists are public constants so {@see ServerCard} can
 * describe the same surface the live handshake reports.
 */
class UnserializeServer extends Server
{
    /**
     * The display name reported as `serverInfo.name` during initialization.
     *
     * The Server Card repeats this value, so both the static card and the live
     * handshake are driven by one declaration and cannot drift apart.
     */
    public const string NAME = 'Unserialize Server';

    /**
     * The version reported as `serverInfo.version` during initialization.
     */
    public const string VERSION = '1.0.0';

    /**
     * The tools this server exposes.
     *
     * @var list<class-string<Tool>>
     */
    public const array TOOLS = [
        ConvertSerializedDataTool::class,
    ];

    /**
     * The resources this server exposes. This server exposes none.
     *
     * @var list<class-string<Server\Resource>>
     */
    public const array RESOURCES = [];

    /**
     * The prompts this server exposes. This server exposes none.
     *
     * @var list<class-string<Prompt>>
     */
    public const array PROMPTS = [];

    /**
     * @var list<class-string<Tool>>
     */
    protected array $tools = self::TOOLS;

    /**
     * @var list<class-string<Server\Resource>>
     */
    protected array $resources = self::RESOURCES;

    /**
     * @var list<class-string<Prompt>>
     */
    protected array $prompts = self::PROMPTS;
}
