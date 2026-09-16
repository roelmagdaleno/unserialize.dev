<?php

namespace App\Mcp\Tools;

use App\Data\UsageContext;
use App\Enums\ConversionErrorCode;
use App\Enums\ConversionInterface;
use App\Enums\SyntaxErrorCode;
use App\Exceptions\ConversionException;
use App\Services\ConversionTelemetry;
use App\Services\Serialized;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name(ConvertSerializedDataTool::NAME)]
#[Title('Convert PHP Serialized Data')]
#[Description('Convert one PHP serialized value to structured JSON. Input is limited to 262144 bytes, objects are rejected, and submitted data is not retained. Invalid input returns error.diagnostic with a byte offset, a length, and a suggested correction; the tool never rewrites the input for you.')]
#[IsReadOnly]
#[IsIdempotent]
#[IsDestructive(false)]
#[IsOpenWorld(false)]
class ConvertSerializedDataTool extends Tool
{
    /**
     * The application-owned tool name.
     *
     * Telemetry records this constant rather than anything read back off the
     * request, so a recorded tool name is always one this application exposes.
     */
    public const string NAME = 'convert_php_serialized_data';

    /**
     * How this server is reached. The MCP server is registered as a web
     * endpoint only, so the classification is the application's, not a value
     * read back off a request.
     */
    public const string TRANSPORT = 'http';

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $tool = parent::toArray();
        $tool['inputSchema']['additionalProperties'] = false;
        $tool['outputSchema']['additionalProperties'] = false;

        return $tool;
    }

    public function handle(
        Request $request,
        HttpRequest $httpRequest,
        ConversionTelemetry $telemetry,
    ): ResponseFactory {
        $startedAt = hrtime(true);
        $arguments = $request->all();
        $inputBytes = is_string($arguments['serialized'] ?? null) ? strlen($arguments['serialized']) : 0;

        /**
         * The protocol version belongs to initialization, not to a tool call:
         * reading it here would mean carrying a session identifier forward.
         */
        $context = fn (?string $resultType = null): UsageContext => UsageContext::fromRequest(
            $httpRequest,
            resultType: $resultType,
            mcpTool: self::NAME,
            mcpTransport: self::TRANSPORT,
        );

        if (array_keys($arguments) !== ['serialized'] || ! is_string($arguments['serialized'])) {
            $telemetry->record(
                ConversionInterface::Mcp,
                'validation_error',
                $inputBytes,
                $startedAt,
                null,
                $context(),
            );

            return $this->error('validation_error', 'Provide exactly one string field named serialized.');
        }

        try {
            $result = new Serialized($arguments['serialized'])->convert();
        } catch (ConversionException $exception) {
            $telemetry->record(
                ConversionInterface::Mcp,
                $exception->errorCode->value,
                $inputBytes,
                $startedAt,
                $exception->diagnostic?->code,
                $context(),
            );

            return $this->error(
                $exception->errorCode->value,
                $exception->getMessage(),
                $exception->diagnostic?->toArray(),
            );
        }

        $telemetry->record(
            ConversionInterface::Mcp,
            'success',
            $inputBytes,
            $startedAt,
            null,
            $context(UsageContext::resultTypeFor($result->value)),
        );

        return Response::structured([
            'data' => [
                'value' => $result->value,
                'format' => 'json',
            ],
            'meta' => ['retained' => false],
        ]);
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'serialized' => $schema->string()
                ->max(Serialized::MAX_INPUT_BYTES)
                ->description('A PHP serialized value. Maximum 262144 bytes. Serialized objects are not supported.')
                ->required(),
        ];
    }

    /**
     * @return array<string, Type>
     */
    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'data' => $schema->object([
                'value' => $schema->union(['object', 'array', 'string', 'number', 'boolean', 'null'])->required(),
                'format' => $schema->string()->enum(['json'])->required(),
            ])->withoutAdditionalProperties(),
            'meta' => $schema->object([
                'retained' => $schema->boolean()->required(),
            ])->withoutAdditionalProperties(),
            'error' => $schema->object([
                'code' => $schema->string()->enum([
                    'validation_error',
                    ...array_map(
                        static fn (ConversionErrorCode $code): string => $code->value,
                        ConversionErrorCode::cases(),
                    ),
                ])->required(),
                'message' => $schema->string()->required(),
                'diagnostic' => $schema->object([
                    'code' => $schema->string()->enum(array_map(
                        static fn (SyntaxErrorCode $code): string => $code->value,
                        SyntaxErrorCode::cases(),
                    ))->required(),
                    'message' => $schema->string()->required(),
                    'offset' => $schema->integer()->required(),
                    'length' => $schema->integer()->required(),
                    'suggestion' => $schema->string(),
                ])->withoutAdditionalProperties()
                    ->description('Present only when the failure can be located. Byte offsets index the value you submitted; no submitted bytes are returned.'),
            ])->withoutAdditionalProperties(),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $diagnostic
     */
    private function error(string $code, string $message, ?array $diagnostic = null): ResponseFactory
    {
        $error = [
            'error' => array_filter([
                'code' => $code,
                'message' => $message,
                'diagnostic' => $diagnostic,
            ], static fn (mixed $value): bool => $value !== null),
        ];

        return Response::make(Response::error((string) json_encode($error, JSON_UNESCAPED_SLASHES)))
            ->withStructuredContent($error);
    }
}
