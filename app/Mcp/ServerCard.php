<?php

namespace App\Mcp;

use App\Mcp\Servers\UnserializeServer;
use Laravel\Mcp\Enums\ProtocolVersion;
use Laravel\Mcp\Server;

/**
 * Build the static MCP Server Card describing this application's MCP server.
 *
 * The card lets an agent learn where the server lives, which transport it
 * speaks, and which protocol versions it accepts without first opening a
 * connection. Every value is derived from the declarations on
 * {@see UnserializeServer}, so the card cannot contradict the `serverInfo` and
 * capabilities reported during the live initialization handshake.
 *
 * @see https://github.com/modelcontextprotocol/modelcontextprotocol/pull/2127
 */
class ServerCard
{
    /**
     * The versioned schema this document conforms to. Required by the
     * extension, and pinned to the `v1` family.
     */
    public const string SCHEMA = 'https://static.modelcontextprotocol.io/schemas/v1/server-card.schema.json';

    /**
     * The media type a Server Card is served and requested with.
     */
    public const string MEDIA_TYPE = 'application/mcp-server-card+json';

    /**
     * The server's reverse-DNS identifier: exactly one slash separating the
     * namespace from the server name.
     */
    public const string IDENTIFIER = 'dev.unserialize/unserialize';

    /**
     * A human-readable summary of what the server does. The schema caps this
     * at 100 characters, so it stays shorter than the server instructions.
     */
    public const string DESCRIPTION = 'Convert untrusted PHP serialized values to structured JSON.';

    /**
     * Build the Server Card document.
     *
     * `serverInfo`, `capabilities`, and `transport` are additive compatibility
     * fields. SEP-2127 omits them deliberately because a static document cannot
     * describe a surface that varies per user or session; this server is
     * public, unauthenticated, and exposes the same single tool to every
     * caller, so the values cannot go stale. Clients that follow the final
     * schema read `remotes` and ignore them.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            '$schema' => self::SCHEMA,
            'name' => self::IDENTIFIER,
            'title' => UnserializeServer::NAME,
            'description' => self::DESCRIPTION,
            'version' => UnserializeServer::VERSION,
            'websiteUrl' => route('developers'),
            'repository' => [
                'source' => 'github',
                'url' => 'https://github.com/roelmagdaleno/unserialize',
            ],
            'remotes' => [$this->remote()],
            'serverInfo' => [
                'name' => UnserializeServer::NAME,
                'version' => UnserializeServer::VERSION,
            ],
            'transport' => [
                'type' => 'streamable-http',
                'endpoint' => route('mcp.unserialize'),
            ],
            'capabilities' => $this->capabilities(),
        ];
    }

    /**
     * Describe the one HTTP endpoint this server is reachable on.
     *
     * The advertised protocol versions are the ones the server actually
     * accepts during initialization, so a client can pick a compatible version
     * before connecting rather than being rejected after.
     *
     * @return array<string, mixed>
     */
    private function remote(): array
    {
        return [
            'type' => 'streamable-http',
            'url' => route('mcp.unserialize'),
            'supportedProtocolVersions' => ProtocolVersion::supported(),
        ];
    }

    /**
     * Report only the primitive kinds this server actually registers.
     *
     * The framework's default capability set advertises tools, resources, and
     * prompts alike. Deriving from the declared primitives instead keeps the
     * card from claiming a resources or prompts surface that does not exist.
     *
     * @return array<string, array<string, bool>>
     */
    private function capabilities(): array
    {
        $declared = [
            Server::CAPABILITY_TOOLS => UnserializeServer::TOOLS,
            Server::CAPABILITY_RESOURCES => UnserializeServer::RESOURCES,
            Server::CAPABILITY_PROMPTS => UnserializeServer::PROMPTS,
        ];

        $capabilities = [];

        foreach ($declared as $capability => $primitives) {
            if ($primitives !== []) {
                $capabilities[$capability] = ['listChanged' => false];
            }
        }

        return $capabilities;
    }
}
