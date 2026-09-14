<?php

namespace App\Mcp\Tools;

use App\Enums\ConversionErrorCode;
use App\Enums\ConversionInterface;
use App\Exceptions\ConversionException;
use App\Services\ConversionTelemetry;
use App\Services\Serialized;
use Illuminate\Contracts\JsonSchema\JsonSchema;
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

#[Name('convert_php_serialized_data')]
#[Title('Convert PHP Serialized Data')]
#[Description('Convert one PHP serialized value to structured JSON. Input is limited to 262144 bytes, objects are rejected, and submitted data is not retained.')]
#[IsReadOnly]
#[IsIdempotent]
#[IsDestructive(false)]
#[IsOpenWorld(false)]
class ConvertSerializedDataTool extends Tool
{
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

    public function handle(Request $request, ConversionTelemetry $telemetry): ResponseFactory
    {
        $startedAt = hrtime(true);
        $arguments = $request->all();
        $inputBytes = is_string($arguments['serialized'] ?? null) ? strlen($arguments['serialized']) : 0;

        if (array_keys($arguments) !== ['serialized'] || ! is_string($arguments['serialized'])) {
            $telemetry->record(ConversionInterface::Mcp, 'validation_error', $inputBytes, $startedAt);

            return $this->error('validation_error', 'Provide exactly one string field named serialized.');
        }

        try {
            $result = (new Serialized($arguments['serialized']))->convert();
        } catch (ConversionException $exception) {
            $telemetry->record(ConversionInterface::Mcp, $exception->errorCode->value, $inputBytes, $startedAt);

            return $this->error($exception->errorCode->value, $exception->getMessage());
        }

        $telemetry->record(ConversionInterface::Mcp, 'success', $inputBytes, $startedAt);

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
            ])->withoutAdditionalProperties(),
        ];
    }

    private function error(string $code, string $message): ResponseFactory
    {
        $error = [
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ];

        return Response::make(Response::error((string) json_encode($error, JSON_UNESCAPED_SLASHES)))
            ->withStructuredContent($error);
    }
}
