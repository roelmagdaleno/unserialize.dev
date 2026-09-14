<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\ConvertSerializedDataTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Unserialize Server')]
#[Version('1.0.0')]
#[Instructions('Convert untrusted PHP serialized values to structured JSON. Inputs are limited to 262144 bytes, objects are rejected, and conversions are not retained.')]
class UnserializeServer extends Server
{
    protected array $tools = [
        ConvertSerializedDataTool::class,
    ];
}
