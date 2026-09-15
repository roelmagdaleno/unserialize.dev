<?php

use App\Data\UsageContext;
use App\Enums\ConversionInterface;
use App\Enums\SyntaxErrorCode;
use App\Enums\UsageEventType;
use App\Livewire\Serialized;
use App\Mcp\Servers\UnserializeServer;
use App\Mcp\Tools\ConvertSerializedDataTool;
use App\Models\Output;
use App\Models\UsageEvent;
use App\Services\ConversionTelemetry;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Laravel\Mcp\Events\SessionInitialized;
use Livewire\Livewire;

it('records one event and one aggregate for a single conversion', function () {
    $this->travelTo('2026-09-15 10:30:00');

    app(ConversionTelemetry::class)->record(ConversionInterface::Api, 'success', 2048, hrtime(true));

    $this->assertDatabaseCount('usage_events', 1);
    $this->assertDatabaseCount('conversion_metrics', 1);
    $this->assertDatabaseHas('usage_events', [
        'occurred_at' => '2026-09-15 10:30:00',
        'interface' => 'api',
        'event' => 'conversion_completed',
        'outcome' => 'success',
        'input_size_bucket' => '1-16KiB',
        'diagnostic' => null,
    ]);
});

it('stores only the allowlisted columns', function () {
    app(ConversionTelemetry::class)->record(
        ConversionInterface::Browser,
        'invalid_input',
        2048,
        hrtime(true),
        SyntaxErrorCode::StringLengthMismatch,
        new UsageContext(userAgent: 'curl/8.7.1'),
    );

    expect(array_keys(UsageEvent::query()->sole()->getAttributes()))->toEqualCanonicalizing([
        'id',
        'occurred_at',
        'interface',
        'event',
        'outcome',
        'duration_ms',
        'input_size_bucket',
        'diagnostic',
        'result_type',
        'api_version',
        'http_status',
        'mcp_tool',
        'mcp_transport',
        'mcp_protocol_version',
        'user_agent',
        'referrer_url',
        'request_url',
    ]);
});

it('records the diagnostic category without anything measured from the payload', function () {
    app(ConversionTelemetry::class)->record(
        ConversionInterface::Browser,
        'invalid_input',
        2048,
        hrtime(true),
        SyntaxErrorCode::StringLengthMismatch,
    );

    $event = UsageEvent::query()->sole();

    expect($event->diagnostic)->toBe('string_length_mismatch')
        ->and($event->getAttributes())->not->toHaveKeys(['offset', 'length', 'suggestion', 'excerpt']);
});

it('normalizes the untrusted metadata it is given before storing it', function () {
    app(ConversionTelemetry::class)->record(
        ConversionInterface::Api,
        'success',
        2048,
        hrtime(true),
        null,
        new UsageContext(
            userAgent: "curl/8.7.1\r\nX-Injected: yes",
            requestUrl: 'https://alice:s3cret@unserialize.dev/api/v1/unserialize?token=abc123&page=2',
            referrerUrl: 'https://unserialize.dev/developers',
        ),
    );

    $event = UsageEvent::query()->sole();

    expect($event->user_agent)->toBe('curl/8.7.1X-Injected: yes')
        ->and($event->request_url)->toBe('https://unserialize.dev/api/v1/unserialize?token=[redacted]&page=2')
        ->and($event->referrer_url)->toBe('https://unserialize.dev/developers');
});

it('stores null metadata when the caller supplies no context', function () {
    app(ConversionTelemetry::class)->record(ConversionInterface::Mcp, 'success', 2048, hrtime(true));

    $event = UsageEvent::query()->sole();

    expect($event->user_agent)->toBeNull()
        ->and($event->request_url)->toBeNull()
        ->and($event->referrer_url)->toBeNull()
        ->and($event->api_version)->toBeNull()
        ->and($event->http_status)->toBeNull()
        ->and($event->mcp_tool)->toBeNull();
});

/**
 * The two stores answer different questions over different lifetimes, so
 * neither may take the other down with it.
 */
it('still counts the aggregate when the event write fails', function () {
    Log::spy();
    Schema::drop('usage_events');

    app(ConversionTelemetry::class)->record(ConversionInterface::Mcp, 'success', 2048, hrtime(true));

    $this->assertDatabaseCount('conversion_metrics', 1);
    Log::shouldHaveReceived('warning')->once()->withArgs(function (string $message, array $context): bool {
        expect($message)->toBe('usage.event_write_failed')
            ->and($context)->toBe([
                'interface' => 'mcp',
                'event' => 'conversion_completed',
                'outcome' => 'success',
                'exception' => QueryException::class,
            ]);

        return true;
    });
});

it('keeps the recorded event when the aggregate write fails', function () {
    Log::spy();
    Schema::drop('conversion_metrics');

    app(ConversionTelemetry::class)->record(ConversionInterface::Browser, 'success', 2048, hrtime(true));

    $this->assertDatabaseCount('usage_events', 1);
    Log::shouldHaveReceived('warning')->once()->withArgs(
        fn (string $message): bool => $message === 'conversion.metrics_write_failed',
    );
});

it('records a matching event for each instrumented api status', function (array $payload, array $headers, int $status, string $outcome) {
    $this->postJson('/api/v1/unserialize', $payload, $headers)->assertStatus($status);

    $event = UsageEvent::query()->sole();

    expect($event->interface)->toBe(ConversionInterface::Api)
        ->and($event->event)->toBe(UsageEventType::ConversionCompleted)
        ->and($event->outcome)->toBe($outcome)
        ->and($event->api_version)->toBe('v1')
        ->and($event->http_status)->toBe($status);
})->with([
    'success' => [['serialized' => 'i:1;'], [], 200, 'success'],
    'input too large' => [['serialized' => 'a:1:{i:0;s:1:"a";}'.str_repeat('x', 262_144)], [], 413, 'input_too_large'],
    'unsupported media type' => [['serialized' => 'i:1;'], ['Content-Type' => 'text/plain'], 415, 'unsupported_media_type'],
    'validation error' => [[], [], 422, 'validation_error'],
    'conversion error' => [['serialized' => 'invalid'], [], 422, 'invalid_input'],
]);

it('records 429 from the api rate limiter', function () {
    foreach (range(1, 10) as $ignored) {
        $this->postJson('/api/v1/unserialize', ['serialized' => 'i:1;'])->assertOk();
    }

    $this->postJson('/api/v1/unserialize', ['serialized' => 'i:1;'])->assertStatus(429);

    $event = UsageEvent::query()->where('outcome', 'rate_limited')->sole();

    expect($event->api_version)->toBe('v1')
        ->and($event->http_status)->toBe(429);
});

it('derives the api version from the route rather than a client header', function () {
    $this->postJson('/api/v1/unserialize', ['serialized' => 'i:1;'], [
        'X-Api-Version' => 'v99',
        'Accept-Version' => 'v99',
    ])->assertOk();

    expect(UsageEvent::query()->sole()->api_version)->toBe('v1');
});

it('records the root result type of a successful api conversion', function (string $serialized, string $resultType) {
    $this->postJson('/api/v1/unserialize', ['serialized' => $serialized])->assertOk();

    expect(UsageEvent::query()->sole()->result_type)->toBe($resultType);
})->with([
    'list' => ['a:1:{i:0;i:1;}', 'array'],
    'object-like array' => ['a:1:{s:4:"name";s:3:"Roe";}', 'object_array'],
    'string' => ['s:3:"abc";', 'string'],
    'integer' => ['i:42;', 'integer'],
    'float' => ['d:1.5;', 'float'],
    'boolean' => ['b:1;', 'boolean'],
    'null' => ['N;', 'null'],
]);

it('leaves the result type null on a failed api conversion', function () {
    $this->postJson('/api/v1/unserialize', ['serialized' => 'invalid'])->assertStatus(422);

    expect(UsageEvent::query()->sole()->result_type)->toBeNull();
});

it('captures the api user agent, request url, and referrer when they are sent', function () {
    $this->postJson('https://unserialize.dev/api/v1/unserialize', ['serialized' => 'i:1;'], [
        'User-Agent' => 'curl/8.7.1',
        'Referer' => 'https://unserialize.dev/developers',
    ])->assertOk();

    $event = UsageEvent::query()->sole();

    expect($event->user_agent)->toBe('curl/8.7.1')
        ->and($event->request_url)->toBe('https://unserialize.dev/api/v1/unserialize')
        ->and($event->referrer_url)->toBe('https://unserialize.dev/developers');
});

it('stores a null referrer when none is sent', function () {
    $this->postJson('/api/v1/unserialize', ['serialized' => 'i:1;'])->assertOk();

    expect(UsageEvent::query()->sole()->referrer_url)->toBeNull();
});

/**
 * Query parameters are not part of this endpoint's contract, so the request is
 * rejected. The event is still written, and the URL it holds is what must never
 * carry the credentials or the token verbatim.
 */
it('redacts credentials and sensitive query values from an api request url', function () {
    $this->postJson('https://alice:s3cret@unserialize.dev/api/v1/unserialize?token=abc123&page=2', [
        'serialized' => 'i:1;',
    ])->assertStatus(422);

    expect(UsageEvent::query()->sole()->request_url)
        ->toBe('https://unserialize.dev/api/v1/unserialize?page=2&token=[redacted]');
});

it('does not change the api response contract', function () {
    $this->postJson('/api/v1/unserialize', ['serialized' => 'i:1;'])
        ->assertOk()
        ->assertExactJson([
            'data' => ['value' => 1, 'format' => 'json'],
            'meta' => ['retained' => false],
        ]);
});

it('never stores the submitted value or the converted result', function () {
    $this->postJson('/api/v1/unserialize', ['serialized' => 's:6:"secret";'])->assertOk();

    foreach (UsageEvent::query()->sole()->getAttributes() as $value) {
        expect((string) $value)->not->toContain('secret');
    }
});

it('records the tool name and http transport on an mcp tool call', function (array $arguments, string $outcome) {
    UnserializeServer::tool(ConvertSerializedDataTool::class, $arguments);

    $event = UsageEvent::query()->sole();

    expect($event->interface)->toBe(ConversionInterface::Mcp)
        ->and($event->event)->toBe(UsageEventType::ConversionCompleted)
        ->and($event->outcome)->toBe($outcome)
        ->and($event->mcp_tool)->toBe('convert_php_serialized_data')
        ->and($event->mcp_transport)->toBe('http')
        ->and($event->mcp_protocol_version)->toBeNull();
})->with([
    'success' => [['serialized' => 'a:1:{i:0;i:1;}'], 'success'],
    'validation error' => [[], 'validation_error'],
    'conversion error' => [['serialized' => 'not serialized'], 'invalid_input'],
]);

it('records the negotiated protocol version when an mcp session initializes', function () {
    event(new SessionInitialized(
        sessionId: 'a5a2ca4a-e0f1-4f66-8e0c-7a9f6f0f8a11',
        clientInfo: ['name' => 'Claude Code', 'version' => '2.4.1'],
        protocolVersion: '2025-06-18',
        clientCapabilities: ['roots' => ['listChanged' => true]],
    ));

    $event = UsageEvent::query()->sole();

    expect($event->event)->toBe(UsageEventType::McpSessionInitialized)
        ->and($event->interface)->toBe(ConversionInterface::Mcp)
        ->and($event->mcp_protocol_version)->toBe('2025-06-18');
    $this->assertDatabaseCount('conversion_metrics', 0);
});

it('stores nothing that identifies the initializing mcp client', function () {
    event(new SessionInitialized(
        sessionId: 'a5a2ca4a-e0f1-4f66-8e0c-7a9f6f0f8a11',
        clientInfo: ['name' => 'Claude Code', 'version' => '2.4.1'],
        protocolVersion: '2025-06-18',
        clientCapabilities: ['roots' => ['listChanged' => true]],
    ));

    foreach (UsageEvent::query()->sole()->getAttributes() as $value) {
        expect((string) $value)->not->toContain('a5a2ca4a')
            ->not->toContain('Claude Code')
            ->not->toContain('2.4.1')
            ->not->toContain('roots');
    }
});

it('rejects an implausible mcp protocol version instead of storing it', function (?string $protocolVersion) {
    event(new SessionInitialized(
        sessionId: 'a5a2ca4a-e0f1-4f66-8e0c-7a9f6f0f8a11',
        clientInfo: null,
        protocolVersion: $protocolVersion,
        clientCapabilities: null,
    ));

    expect(UsageEvent::query()->sole()->mcp_protocol_version)->toBeNull();
})->with([
    'over length' => [str_repeat('9', 21)],
    'not a version token' => ['2025-06-18 <script>'],
    'absent' => [null],
]);

it('records 429 from the mcp rate limiter', function () {
    foreach (range(1, 10) as $ignored) {
        $this->postJson(route('mcp.unserialize'), ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping'], [
            'Accept' => 'application/json, text/event-stream',
        ]);
    }

    $this->postJson(route('mcp.unserialize'), ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping'], [
        'Accept' => 'application/json, text/event-stream',
    ])->assertStatus(429);

    $event = UsageEvent::query()->where('outcome', 'rate_limited')->sole();

    expect($event->interface)->toBe(ConversionInterface::Mcp)
        ->and($event->mcp_tool)->toBe('convert_php_serialized_data')
        ->and($event->mcp_transport)->toBe('http')
        ->and($event->http_status)->toBe(429);
});

/**
 * A browser conversion is an XHR, so the URL the server observes is the
 * Livewire update endpoint rather than the page the person was looking at. The
 * page itself arrives separately as the referrer; nothing is read back out of
 * the request body to make the URL look like a page view.
 */
it('records a browser conversion with its livewire transport metadata', function () {
    Livewire::test(Serialized::class)
        ->set('form.serializedData', 'a:1:{s:4:"name";s:6:"Chrome";}')
        ->call('unserialize')
        ->assertHasNoErrors();

    $event = UsageEvent::query()->sole();

    expect($event->interface)->toBe(ConversionInterface::Browser)
        ->and($event->event)->toBe(UsageEventType::ConversionCompleted)
        ->and($event->outcome)->toBe('success')
        ->and($event->result_type)->toBe('object_array')
        ->and($event->http_status)->toBe(200)
        ->and($event->request_url)->toEndWith('/livewire/update')
        ->and($event->api_version)->toBeNull();
});

it('reads the browser referrer from the request header rather than the payload', function () {
    $request = Request::create('https://unserialize.dev/livewire/update', 'POST', server: [
        'HTTP_USER_AGENT' => 'HeadlessChrome/141.0 Playwright/1.49',
        'HTTP_REFERER' => 'https://unserialize.dev/',
    ]);

    $context = UsageContext::fromRequest($request);

    expect($context->userAgent)->toBe('HeadlessChrome/141.0 Playwright/1.49')
        ->and($context->referrerUrl)->toBe('https://unserialize.dev/')
        ->and($context->requestUrl)->toBe('https://unserialize.dev/livewire/update');
});

/**
 * Livewire answers a functional failure inside a successful response, so the
 * two columns must disagree here rather than collapse into one.
 */
it('records a browser conversion error as its outcome over a successful transport', function () {
    Livewire::test(Serialized::class)
        ->set('form.serializedData', 'not serialized')
        ->call('unserialize')
        ->assertHasErrors('form.serializedData');

    $event = UsageEvent::query()->sole();

    expect($event->outcome)->toBe('invalid_input')
        ->and($event->http_status)->toBe(200)
        ->and($event->result_type)->toBeNull();
});

it('records a copy success without counting a conversion', function () {
    $component = Livewire::test(Serialized::class)
        ->set('form.serializedData', 'i:1;')
        ->call('unserialize');

    $component->call('resultCopied');

    $event = UsageEvent::query()->where('event', 'result_copied')->sole();

    expect($event->interface)->toBe(ConversionInterface::Browser)
        ->and($event->outcome)->toBeNull()
        ->and($event->duration_ms)->toBeNull()
        ->and($event->diagnostic)->toBeNull();
    $this->assertDatabaseCount('conversion_metrics', 1);
});

it('records nothing when a copy is reported with no result on the page', function () {
    Livewire::test(Serialized::class)->call('resultCopied');

    $this->assertDatabaseCount('usage_events', 0);
});

it('never stores the browser input or result when a copy is reported', function () {
    $component = Livewire::test(Serialized::class)
        ->set('form.serializedData', 's:6:"secret";')
        ->call('unserialize');

    $component->call('resultCopied');

    foreach (UsageEvent::query()->get() as $event) {
        foreach ($event->getAttributes() as $value) {
            expect((string) $value)->not->toContain('secret');
        }
    }
});

/**
 * An automated client is recorded, not filtered out. The test client sends
 * `Symfony`, and that is exactly what a report must be able to see and exclude
 * later rather than have silently dropped at collection time.
 */
it('stores the user agent an automated browser client sends', function () {
    Livewire::test(Serialized::class)
        ->set('form.serializedData', 'i:1;')
        ->call('unserialize');

    expect(UsageEvent::query()->sole()->user_agent)->toBe('Symfony');
});

it('copies no legacy output content or uuid beyond the requested url', function () {
    $output = Output::factory()->create();

    $this->get(route('outputs', $output))->assertOk();

    $this->assertDatabaseCount('usage_events', 0);
});
