<?php

namespace Database\Factories;

use App\Enums\ConversionInterface;
use App\Enums\ConversionOutcome;
use App\Enums\SyntaxErrorCode;
use App\Enums\UsageEventType;
use App\Mcp\Tools\ConvertSerializedDataTool;
use App\Models\UsageEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UsageEvent>
 */
class UsageEventFactory extends Factory
{
    protected $model = UsageEvent::class;

    /**
     * Every generated value stays inside the bounded vocabulary the column
     * allows, so a factory row can never widen what the table is allowed to
     * hold beyond what the application itself writes.
     */
    public function definition(): array
    {
        return [
            'occurred_at' => now()->utc(),
            'interface' => ConversionInterface::Browser,
            'event' => UsageEventType::ConversionCompleted,
            'outcome' => ConversionOutcome::Success->value,
            'duration_ms' => fake()->randomFloat(3, 0, 500),
            'input_size_bucket' => fake()->randomElement(['0-1KiB', '1-16KiB', '16-64KiB']),
            'diagnostic' => null,
            'result_type' => 'array',
            'api_version' => null,
            'http_status' => 200,
            'mcp_tool' => null,
            'mcp_transport' => null,
            'mcp_protocol_version' => null,
            'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)',
            'referrer_url' => null,
            'request_url' => 'https://unserialize.test/livewire/update',
        ];
    }

    /**
     * A conversion that arrived over the browser's Livewire transport.
     */
    public function browser(): static
    {
        return $this->state([
            'interface' => ConversionInterface::Browser,
            'api_version' => null,
            'request_url' => 'https://unserialize.test/livewire/update',
            'referrer_url' => 'https://unserialize.test/',
        ]);
    }

    /**
     * A conversion that arrived over the versioned JSON API.
     */
    public function api(): static
    {
        return $this->state([
            'interface' => ConversionInterface::Api,
            'api_version' => 'v1',
            'request_url' => 'https://unserialize.test/api/v1/unserialize',
            'referrer_url' => null,
            'user_agent' => 'curl/8.7.1',
        ]);
    }

    /**
     * A conversion that arrived through the MCP tool over HTTP.
     */
    public function mcp(): static
    {
        return $this->state([
            'interface' => ConversionInterface::Mcp,
            'http_status' => 200,
            'mcp_tool' => ConvertSerializedDataTool::NAME,
            'mcp_transport' => 'http',
            'request_url' => 'https://unserialize.test/mcp/unserialize',
            'referrer_url' => null,
            'user_agent' => 'mcp-client/1.0',
        ]);
    }

    /**
     * A failed conversion carrying a located syntax problem.
     */
    public function diagnosed(SyntaxErrorCode $code): static
    {
        return $this->state([
            'outcome' => 'invalid_input',
            'diagnostic' => $code->value,
            'result_type' => null,
        ]);
    }

    /**
     * Place the event at a fixed moment, for retention-boundary coverage.
     */
    public function occurredAt(string $occurredAt): static
    {
        return $this->state(['occurred_at' => $occurredAt]);
    }
}
