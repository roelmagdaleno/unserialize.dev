# Unserialize

[Unserialize](https://unserialize.dev) converts PHP serialized values into readable JSON. It provides a Livewire browser interface, a versioned JSON API, and a read-only MCP tool backed by the same conversion service.

## Behavior and privacy

Supported values include `null`, booleans, integers, floats, strings, indexed arrays, associative arrays, and nested combinations of those values. Serialized objects are rejected. Inputs are capped at 262,144 bytes and decoding depth is capped at 512.

New conversions are processed in memory and create no `outputs` database record. Submitted values and converted content are excluded from application telemetry. Existing legacy `/o/{uuid}` records remain readable for compatibility and are returned with `noindex` directives. See the public [privacy contract](https://unserialize.dev/privacy), [security guide](https://unserialize.dev/security), and the implementation in [`app/Services/Serialized.php`](app/Services/Serialized.php).

Do not treat decoded content as trusted data. Redact credentials, personal data, and private URLs before submitting a value.

## Local setup

Requirements are PHP 8.4, Composer, Node.js, and SQLite or another Laravel-supported database.

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --no-interaction
npm install
npm run build
```

Set `APP_URL` to the local URL used by your environment. Laravel Herd users can visit `https://unserialize.test`; no development server command is needed.

## HTTP API

Send JSON to `POST https://unserialize.dev/api/v1/unserialize`. The public endpoint requires no authentication and allows 10 requests per minute per network address.

```bash
curl --request POST 'https://unserialize.dev/api/v1/unserialize' \
  --header 'Content-Type: application/json' \
  --data '{"serialized":"a:2:{s:4:\"name\";s:5:\"Codex\";s:6:\"active\";b:1;}"}'
```

The response contains the native JSON value in `data.value` and `meta.retained: false`. See the [developer guide](https://unserialize.dev/developers) and [OpenAPI 3.1 contract](https://unserialize.dev/openapi.json) for every response and stable error code.

## MCP

Connect a Streamable HTTP MCP client to `https://unserialize.dev/mcp/unserialize`, then discover and call `convert_php_serialized_data`. Its only argument is the `serialized` string. The anonymous endpoint has its own 10-request-per-minute limit, and the tool is read-only and idempotent.

## Verification

```bash
vendor/bin/pint --format agent
php artisan test --compact
npm run build
composer validate --strict --no-check-publish
composer audit --locked --no-interaction
```

Focused behavior is covered by the [service tests](tests/Unit/UnserializeTest.php), [browser tests](tests/Feature/UnserializeTest.php), [API tests](tests/Feature/UnserializeApiTest.php), and [MCP tests](tests/Feature/McpUnserializeTest.php).

## Operational signals

Every conversion emits a `conversion.completed` event with only `interface`, `outcome`, `duration_ms`, and `input_size_bucket`. Nightwatch request-body capture is forcibly disabled. Build dashboards grouped by interface and outcome, graph p95 duration, and monitor the `over-256KiB` and `rate_limited` series for abuse. Alert on sustained availability failures, elevated non-user error rates, or material p95 latency regressions against the established production baseline. Nightwatch supplies the request trace identifier without adding submitted data to the event.

## Security and license

Report vulnerabilities privately to [roelmagdaleno@gmail.com](mailto:roelmagdaleno@gmail.com). Source and issue tracking live at [github.com/roelmagdaleno/unserialize](https://github.com/roelmagdaleno/unserialize).

Unserialize is open-source software licensed under the [MIT license](LICENSE).
