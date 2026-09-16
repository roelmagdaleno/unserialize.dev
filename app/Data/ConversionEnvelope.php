<?php

namespace App\Data;

/**
 * The response body shared by the HTTP API and the MCP tool.
 *
 * Both surfaces publish the same two shapes, and they used to assemble them
 * separately -- down to two different techniques for omitting an absent
 * diagnostic. Building them here is what keeps the OpenAPI document and the MCP
 * output schema describing one thing rather than two that happen to agree.
 *
 * Transport stays with the caller: the controller decides the HTTP status and
 * the tool decides how to wrap the structured content.
 */
final readonly class ConversionEnvelope
{
    /**
     * @return array{data: array{value: mixed, format: string}, meta: array{retained: bool}}
     */
    public static function success(mixed $value): array
    {
        return [
            'data' => [
                'value' => $value,
                'format' => 'json',
            ],
            'meta' => ['retained' => false],
        ];
    }

    /**
     * The diagnostic goes under its own key rather than `details`, which this
     * envelope already uses for the validation field map. No submitted bytes are
     * returned: the caller holds the value it sent, so `offset` and `length`
     * locate the problem on their own.
     *
     * @param  array<string, mixed>|null  $diagnostic
     * @param  array<string, mixed>|null  $details
     * @return array{error: array<string, mixed>}
     */
    public static function error(string $code, string $message, ?array $diagnostic = null, ?array $details = null): array
    {
        $error = [
            'code' => $code,
            'message' => $message,
        ];

        if ($diagnostic !== null) {
            $error['diagnostic'] = $diagnostic;
        }

        if ($details !== null) {
            $error['details'] = $details;
        }

        return ['error' => $error];
    }
}
