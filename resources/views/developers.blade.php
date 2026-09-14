<x-layouts.app>
    <article class="mt-10 max-w-4xl space-y-6 leading-7 text-zinc-700 dark:text-zinc-300">
        <h2 class="text-3xl font-semibold tracking-tight text-zinc-950 dark:text-white">API and MCP developer guide</h2>
        <p>Unserialize exposes the same stateless conversion behavior through a versioned JSON API and one read-only MCP tool. Both interfaces reject serialized objects, accept at most 262,144 input bytes, and retain neither submitted data nor converted output.</p>

        <section class="space-y-3" aria-labelledby="http-api">
            <h3 id="http-api" class="text-xl font-semibold text-zinc-950 dark:text-white">HTTP API</h3>
            <p>Send JSON to <code>POST {{ url('/api/v1/unserialize') }}</code> with <code>Content-Type: application/json</code>. Anonymous clients may make 10 requests per minute per network address.</p>
            <pre class="overflow-x-auto rounded-lg p-4" data-lang="bash">curl --request POST '{{ url('/api/v1/unserialize') }}' \
  --header 'Content-Type: application/json' \
  --data '{"serialized":"a:2:{s:4:\"name\";s:5:\"Codex\";s:6:\"active\";b:1;}"}'</pre>
            <pre class="overflow-x-auto rounded-lg p-4" data-lang="json">{
  "data": {
    "value": {"name": "Codex", "active": true},
    "format": "json"
  },
  "meta": {"retained": false}
}</pre>
            <p>When a value cannot be decoded, the <code>invalid_input</code> response adds an <code>error.diagnostic</code> object locating the first problem by byte offset, with a suggested correction where one can be proven. The key is omitted when the failure has no byte position, such as a serialized object. Diagnostics never contain bytes from the submitted value&mdash;use <code>offset</code> and <code>length</code> to index the input you already hold.</p>

            <pre class="overflow-x-auto rounded-lg p-4" data-lang="json">{
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
}</pre>

            <p>Validation failures use the separate <code>error.details</code> key for a field-to-messages map, so the two never share a shape. The machine-readable <a class="text-blue-900 underline dark:text-blue-300" href="{{ route('openapi') }}">OpenAPI 3.1 description</a> documents request, success, validation, decoding-diagnostic, unsupported-object, encoding, size, media-type, and rate-limit responses.</p>
        </section>

        <section class="space-y-3" aria-labelledby="mcp">
            <h3 id="mcp" class="text-xl font-semibold text-zinc-950 dark:text-white">Model Context Protocol</h3>
            <p>Connect a Streamable HTTP MCP client to <code>{{ url('/mcp/unserialize') }}</code>. The public endpoint requires no credentials and is separately limited to 10 requests per minute per network address.</p>
            <p>Discover and call <code>convert_php_serialized_data</code> with one string argument named <code>serialized</code>. The tool is declared read-only, idempotent, non-destructive, and closed-world. It returns the same <code>data</code> and <code>meta</code> structure as the HTTP API, or a stable structured <code>error</code> carrying the same <code>error.diagnostic</code> object. The tool never rewrites the submitted value for you.</p>
            <pre class="overflow-x-auto rounded-lg p-4" data-lang="json">{
  "name": "convert_php_serialized_data",
  "arguments": {"serialized": "b:0;"}
}</pre>
        </section>

        <section class="space-y-3" aria-labelledby="privacy-security">
            <h3 id="privacy-security" class="text-xl font-semibold text-zinc-950 dark:text-white">Privacy and safe use</h3>
            <p>Requests are processed in memory. Operational telemetry records only interface, outcome, a diagnostic category, latency, and an input-size bucket—not request bodies, converted values, byte offsets, or public output URLs. Redact secrets before sending data and review the <a class="text-blue-900 underline dark:text-blue-300" href="{{ route('privacy') }}">privacy contract</a> and <a class="text-blue-900 underline dark:text-blue-300" href="{{ route('home') }}#security">security guidance</a>.</p>
        </section>
    </article>
</x-layouts.app>
