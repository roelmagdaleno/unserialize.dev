<x-layouts.app>
    <article class="mt-10 max-w-4xl space-y-6 leading-7 text-zinc-700 dark:text-zinc-300">
        <h2 class="text-3xl font-semibold tracking-tight text-zinc-950 dark:text-white">API and MCP developer guide</h2>
        <p>Unserialize exposes the same stateless conversion behavior through a versioned JSON API and one read-only MCP tool. Both interfaces reject serialized objects, accept at most 262,144 input bytes, and retain neither submitted data nor converted output.</p>

        <section class="space-y-3" aria-labelledby="http-api">
            <h3 id="http-api" class="text-xl font-semibold text-zinc-950 dark:text-white">HTTP API</h3>
            <p>Send JSON to <code>POST {{ url('/api/v1/unserialize') }}</code> with <code>Content-Type: application/json</code>. Anonymous clients may make 10 requests per minute per network address.</p>
            <pre class="overflow-x-auto rounded-lg bg-zinc-950 p-4 text-sm text-zinc-100"><code>curl --request POST '{{ url('/api/v1/unserialize') }}' \
  --header 'Content-Type: application/json' \
  --data '{"serialized":"a:2:{s:4:\"name\";s:5:\"Codex\";s:6:\"active\";b:1;}"}'</code></pre>
            <pre class="overflow-x-auto rounded-lg bg-zinc-950 p-4 text-sm text-zinc-100"><code>{
  "data": {
    "value": {"name": "Codex", "active": true},
    "format": "json"
  },
  "meta": {"retained": false}
}</code></pre>
            <p>The machine-readable <a class="text-blue-900 underline dark:text-blue-300" href="{{ route('openapi') }}">OpenAPI 3.1 description</a> documents request, success, validation, unsupported-object, encoding, size, media-type, and rate-limit responses.</p>
        </section>

        <section class="space-y-3" aria-labelledby="mcp">
            <h3 id="mcp" class="text-xl font-semibold text-zinc-950 dark:text-white">Model Context Protocol</h3>
            <p>Connect a Streamable HTTP MCP client to <code>{{ url('/mcp/unserialize') }}</code>. The public endpoint requires no credentials and is separately limited to 10 requests per minute per network address.</p>
            <p>Discover and call <code>convert_php_serialized_data</code> with one string argument named <code>serialized</code>. The tool is declared read-only, idempotent, non-destructive, and closed-world. It returns the same <code>data</code> and <code>meta</code> structure as the HTTP API, or a stable structured <code>error</code>.</p>
            <pre class="overflow-x-auto rounded-lg bg-zinc-950 p-4 text-sm text-zinc-100"><code>{
  "name": "convert_php_serialized_data",
  "arguments": {"serialized": "b:0;"}
}</code></pre>
        </section>

        <section class="space-y-3" aria-labelledby="privacy-security">
            <h3 id="privacy-security" class="text-xl font-semibold text-zinc-950 dark:text-white">Privacy and safe use</h3>
            <p>Requests are processed in memory. Operational telemetry records only interface, outcome, latency, and an input-size bucket—not request bodies, converted values, or public output URLs. Redact secrets before sending data and review the <a class="text-blue-900 underline dark:text-blue-300" href="{{ route('privacy') }}">privacy contract</a> and <a class="text-blue-900 underline dark:text-blue-300" href="{{ route('security') }}">security guidance</a>.</p>
        </section>
    </article>
</x-layouts.app>
