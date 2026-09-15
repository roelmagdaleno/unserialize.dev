# API and MCP developer guide

> Unserialize exposes the same stateless conversion behavior through a versioned JSON API and one read-only MCP tool. Both interfaces reject serialized objects, accept at most 262,144 input bytes, and retain neither submitted data nor converted output.

## HTTP API

Send JSON to `POST {{ url('/api/v1/unserialize') }}` with `Content-Type: application/json`. Anonymous clients may make 10 requests per minute per network address.

```bash
curl --request POST '{{ url('/api/v1/unserialize') }}' \
  --header 'Content-Type: application/json' \
  --data '{"serialized":"a:2:{s:4:\"name\";s:5:\"Codex\";s:6:\"active\";b:1;}"}'
```

```json
{
  "data": {
    "value": {"name": "Codex", "active": true},
    "format": "json"
  },
  "meta": {"retained": false}
}
```

When a value cannot be decoded, the `invalid_input` response adds an `error.diagnostic` object locating the first problem by byte offset, with a suggested correction where one can be proven. The key is omitted when the failure has no byte position, such as a serialized object. Diagnostics never contain bytes from the submitted value—use `offset` and `length` to index the input you already hold.

```json
{
  "error": {
    "code": "invalid_input",
    "message": "Invalid serialized data.",
    "diagnostic": {
      "code": "string_length_mismatch",
      "message": "The string starting at byte 6 declares 4 bytes but 5 bytes precede the closing quote.",
      "offset": 6,
      "length": 11,
      "suggestion": "Change `s:4:` to `s:5:`."
    }
  }
}
```

Validation failures use the separate `error.details` key for a field-to-messages map, so the two never share a shape. The machine-readable [OpenAPI 3.1 description]({{ route('openapi') }}) documents request, success, validation, decoding-diagnostic, unsupported-object, encoding, size, media-type, and rate-limit responses.

## Model Context Protocol

Connect a Streamable HTTP MCP client to `{{ url('/mcp/unserialize') }}`. The public endpoint requires no credentials and is separately limited to 10 requests per minute per network address.

Discover and call `convert_php_serialized_data` with one string argument named `serialized`. The tool is declared read-only, idempotent, non-destructive, and closed-world. It returns the same `data` and `meta` structure as the HTTP API, or a stable structured `error` carrying the same `error.diagnostic` object. The tool never rewrites the submitted value for you.

```json
{
  "name": "convert_php_serialized_data",
  "arguments": {"serialized": "b:0;"}
}
```

## Privacy and safe use

Requests are processed in memory. Operational telemetry records only interface, outcome, a diagnostic category, latency, and an input-size bucket—not request bodies, converted values, byte offsets, or public output URLs. Redact secrets before sending data and review the [privacy contract]({{ route('privacy') }}) and [security guidance]({{ route('home') }}#security).
