<x-layouts.app>
<section class="mt-8">
    <form action="{{ route('conversion.convert') }}" method="post" data-converter onsubmit="event.preventDefault()">
        <div class="min-w-0">
            <label for="serialized-data" class="mb-3 inline-flex items-center text-sm font-medium text-zinc-800 dark:text-white">Serialized Data</label>

            <textarea
                id="serialized-data"
                name="serializedData"
                class="block w-full resize-y rounded-lg border border-zinc-200 border-b-zinc-300/80 bg-white p-3 font-mono text-sm text-zinc-700 shadow-xs field-sizing-content placeholder-zinc-400 data-invalid:border-red-500 data-invalid:shadow-none md:text-base dark:border-white/10 dark:bg-white/10 dark:text-zinc-300 dark:placeholder-zinc-400 dark:data-invalid:border-red-500"
                aria-describedby="serialized-data-description"
                autofocus
            >{{ $serializedData }}</textarea>

            <div id="serialized-data-error" role="alert" aria-live="polite" aria-atomic="true" class="mt-3 hidden text-sm font-medium text-red-500 dark:text-red-400">
                <svg class="inline size-5 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495ZM10 5a.75.75 0 0 1 .75.75v3.5a.75.75 0 0 1-1.5 0v-3.5A.75.75 0 0 1 10 5Zm0 9a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd"/></svg>
                <span data-error-message></span>
            </div>

            <p id="serialized-data-description" class="mt-3 text-sm text-zinc-500 dark:text-white/60">
                Your input is processed in memory, not stored or logged. Max 262,144 bytes. <a class="underline hover:text-zinc-700 dark:hover:text-zinc-200" href="{{ route('privacy') }}">Privacy details</a>.
            </p>
        </div>

        <div class="mt-6 flex justify-end">
            <button type="submit" class="relative inline-flex h-10 items-center justify-center gap-2 rounded-lg text-sm font-medium whitespace-nowrap disabled:pointer-events-none disabled:cursor-default disabled:opacity-50 disabled:shadow-none w-full border border-black/10 bg-zinc-800 ps-4 pe-4 text-white shadow-[inset_0px_1px_--theme(--color-white/.2)] *:transition-opacity hover:bg-zinc-800/90 md:w-auto dark:border-0 dark:bg-white dark:text-zinc-800 dark:hover:bg-white/90 [&[disabled]>[data-loading-indicator]]:opacity-100 [&[disabled]>:not([data-loading-indicator])]:opacity-0">
                <span class="absolute inset-0 flex items-center justify-center opacity-0" data-loading-indicator>
                    <svg class="size-4 shrink-0 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                </span>
                <span>Unserialize</span>
            </button>
        </div>
    </form>

    <div id="conversion-diagnostic"></div>
    <div id="conversion-result"></div>
</section>

<article class="mt-14 max-w-4xl border-t border-zinc-200 pt-10 text-zinc-700 dark:border-zinc-700 dark:text-zinc-300">
    <div class="space-y-12">
        <section aria-labelledby="how-to-convert">
            <h2 id="how-to-convert" class="text-2xl font-semibold tracking-tight text-zinc-950 dark:text-white">How to convert PHP serialized data to JSON</h2>
            <p class="mt-3 leading-7">Paste a serialized string produced by PHP's <code>serialize()</code> function into the converter above, click <strong>Unserialize</strong>, then review or copy the readable JSON result. You see the decoded structure without running PHP yourself.</p>
            <ol class="mt-4 list-decimal space-y-2 pl-6 leading-7">
                <li>Copy the complete serialized value, including its type markers, lengths, and delimiters.</li>
                <li>Paste it into the editor and click <strong>Unserialize</strong>.</li>
                <li>Use the JSON result to inspect the structure without editing the original value by hand.</li>
            </ol>

            <h3 class="mt-8 text-lg font-semibold text-zinc-950 dark:text-white">Convert it in your own PHP code</h3>
            <p class="mt-3 leading-7">Call <code>unserialize()</code> with <code>allowed_classes</code> set to <code>false</code>, then pass the result to <code>json_encode()</code>. <code>unserialize()</code> returns <code>false</code> when the value is invalid.</p>
            <pre class="mt-4 overflow-x-auto rounded-lg p-4" data-lang="php">$value = unserialize($serialized, ['allowed_classes' => false]);

echo json_encode($value, JSON_PRETTY_PRINT);</pre>
            <p class="mt-4 leading-7">Need the PHP array instead of JSON? Pass the unserialized value to <code>print_r()</code> or <code>var_export()</code> in place of <code>json_encode()</code>. The WordPress guide explains how to <a class="text-blue-900 underline dark:text-blue-300" href="{{ route('guides.wordpress-serialized-data') }}#php-array">read serialized data as a PHP array</a>.</p>
            <p class="mt-4 leading-7">Developers and AI agents can also convert without the browser form through the <a class="text-blue-900 underline dark:text-blue-300" href="{{ route('developers') }}">JSON API and MCP tool</a>.</p>
        </section>

        <section id="format" class="scroll-mt-6" aria-labelledby="serialization-format">
            <h2 id="serialization-format" class="text-2xl font-semibold tracking-tight text-zinc-950 dark:text-white">PHP serialization format</h2>
            <p class="mt-3 leading-7">PHP's <a class="text-blue-900 underline dark:text-blue-300" href="https://www.php.net/manual/en/function.serialize.php"><code>serialize()</code> function</a> represents a value using compact type markers plus length or item-count information. For example, <code>s:5:&quot;hello&quot;;</code> is a five-byte string and <code>i:42;</code> is an integer.</p>

            <div class="mt-6 overflow-x-auto">
                <table class="w-full border-collapse text-left text-sm">
                    <caption class="sr-only">How supported PHP values map from serialized data to JSON</caption>
                    <thead>
                        <tr class="border-b border-zinc-300 dark:border-zinc-700">
                            <th scope="col" class="p-3">PHP value</th>
                            <th scope="col" class="p-3">Serialized</th>
                            <th scope="col" class="p-3">JSON</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                        <tr><td class="p-3">Null</td><td class="p-3"><code>N;</code></td><td class="p-3"><code>null</code></td></tr>
                        <tr><td class="p-3">Boolean</td><td class="p-3"><code>b:1;</code></td><td class="p-3"><code>true</code></td></tr>
                        <tr><td class="p-3">Integer</td><td class="p-3"><code>i:42;</code></td><td class="p-3"><code>42</code></td></tr>
                        <tr><td class="p-3">Float</td><td class="p-3"><code>d:3.5;</code></td><td class="p-3"><code>3.5</code></td></tr>
                        <tr><td class="p-3">String</td><td class="p-3"><code>s:5:&quot;hello&quot;;</code></td><td class="p-3"><code>&quot;hello&quot;</code></td></tr>
                        <tr><td class="p-3">Indexed array</td><td class="p-3"><code>a:2:{i:0;s:3:&quot;red&quot;;i:1;s:4:&quot;blue&quot;;}</code></td><td class="p-3"><code>[&quot;red&quot;, &quot;blue&quot;]</code></td></tr>
                        <tr><td class="p-3">Associative array</td><td class="p-3"><code>a:1:{s:4:&quot;name&quot;;s:3:&quot;Ada&quot;;}</code></td><td class="p-3"><code>{&quot;name&quot;: &quot;Ada&quot;}</code></td></tr>
                        <tr><td class="p-3">Reference</td><td class="p-3"><code>a:2:{i:0;s:1:&quot;x&quot;;i:1;R:2;}</code></td><td class="p-3"><code>[&quot;x&quot;, &quot;x&quot;]</code></td></tr>
                        <tr><td class="p-3">Object</td><td class="p-3"><code>O:4:&quot;User&quot;:1:{s:4:&quot;name&quot;;s:3:&quot;Ada&quot;;}</code></td><td class="p-3"><code>{&quot;name&quot;: &quot;Ada&quot;}</code></td></tr>
                    </tbody>
                </table>
            </div>

            <h3 class="mt-8 text-lg font-semibold text-zinc-950 dark:text-white">Example: serialized PHP array to JSON</h3>
            <div class="mt-4 grid gap-4 md:grid-cols-2">
                <div>
                    <h4 class="font-semibold text-zinc-950 dark:text-white">Serialized PHP</h4>
                    <pre class="mt-2 overflow-x-auto rounded-lg p-4" data-lang="text">a:2:{s:4:"name";s:6:"Chrome";s:6:"active";b:1;}</pre>
                </div>
                <div>
                    <h4 class="font-semibold text-zinc-950 dark:text-white">JSON</h4>
                    <pre class="mt-2 overflow-x-auto rounded-lg p-4" data-lang="json">{
    "name": "Chrome",
    "active": true
}</pre>
                </div>
            </div>

            <p class="mt-6 leading-7">Sequential integer keys become JSON arrays; associative keys become JSON objects. Null, booleans, integers, floats, strings, arrays, and nested combinations are supported. A reference (<code>R:</code>) becomes a copy of the value it points to. An object (<code>O:</code>) becomes a JSON object of its properties, private and protected ones included. Its class is never loaded, because unserializing a real object can run code; see <a class="text-blue-900 underline dark:text-blue-300" href="#security">Security and privacy</a>.</p>
        </section>

        <section id="wordpress" class="scroll-mt-6" aria-labelledby="wordpress-data">
            <h2 id="wordpress-data" class="text-2xl font-semibold tracking-tight text-zinc-950 dark:text-white">Working with WordPress serialized data</h2>
            <p class="mt-3 leading-7">WordPress serializes every array it saves. You find these values in <code>wp_options</code> (plugin settings, <code>widget_*</code> options and <code>theme_mods_*</code>) and in the <code>meta_value</code> column of <code>wp_postmeta</code>, <code>wp_usermeta</code> and <code>wp_termmeta</code>. WordPress reads them back with <code>maybe_unserialize()</code>, which leaves values that are not serialized alone.</p>
            <p class="mt-3 leading-7">Paste a value from WP-CLI or a read-only database client above to inspect it as JSON. Never change a serialized value with a plain SQL search and replace: each string stores its length in bytes, so new text with the old length breaks the value. Use <code>wp search-replace</code>, which re-serializes each value for you.</p>
            <p class="mt-3 leading-7">Read the <a class="text-blue-900 underline dark:text-blue-300" href="{{ route('guides.wordpress-serialized-data') }}">WordPress serialized data guide</a> to read it as a PHP array and edit it safely, or <a class="text-blue-900 underline dark:text-blue-300" href="{{ route('guides.broken-serialized-string') }}">fix a broken serialized string</a>.</p>
            <p class="mt-3 leading-7"><strong>Back up the database before making changes.</strong> Redact secrets and personal data before pasting a value into any web tool.</p>
        </section>

        <section id="security" class="scroll-mt-6" aria-labelledby="security-and-privacy">
            <h2 id="security-and-privacy" class="text-2xl font-semibold tracking-tight text-zinc-950 dark:text-white">Security and privacy</h2>
            <p class="mt-3 leading-7">Treat serialized data as untrusted input. This converter calls PHP with <code>allowed_classes</code> set to <code>false</code>, reads every object as plain data without loading its class, rejects custom-serialized objects and enums, limits input to 262,144 bytes, and caps decoding depth.</p>
            <p class="mt-3 leading-7">The submitted value and JSON result are processed in memory and never stored or logged. Still, remove passwords, tokens and personal data before submitting. Read the <a class="text-blue-900 underline dark:text-blue-300" href="{{ route('privacy') }}">privacy and retention details</a> for the technical metadata that is kept.</p>
        </section>

        <section aria-labelledby="why-unserialize">
            <h2 id="why-unserialize" class="text-2xl font-semibold tracking-tight text-zinc-950 dark:text-white">Why I built Unserialize</h2>
            <p class="mt-3 leading-7">I kept running into serialized data while working with WordPress. The online tools I used could decode it, but they did not turn the result into clean JSON, so the data was still harder to read than it needed to be.</p>
            <p class="mt-3 leading-7">I built Unserialize with modern web technology to make that everyday task simpler for me—and for other developers who run into the same problem. It turns PHP serialized values into a familiar, readable format without storing the submitted input or result.</p>
            <p class="mt-3 leading-7">— Roel Magdaleno Ramón (<a class="text-blue-900 underline dark:text-blue-300" href="https://github.com/roelmagdaleno" rel="author">GitHub</a>, <a class="text-blue-900 underline dark:text-blue-300" href="https://x.com/Roel7nxju0">X</a>)</p>
        </section>

        <section id="faq" class="scroll-mt-6" aria-labelledby="faq-heading">
            <h2 id="faq-heading" class="text-2xl font-semibold tracking-tight text-zinc-950 dark:text-white">Frequently asked questions</h2>
            <div class="mt-4 divide-y divide-zinc-200 border-y border-zinc-200 dark:divide-zinc-800 dark:border-zinc-800">
                @foreach ($questions as $question)
                    <details class="group py-4">
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-4 rounded font-semibold text-zinc-950 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-blue-700 dark:text-white dark:focus-visible:outline-blue-300 [&::-webkit-details-marker]:hidden">
                            {{ $question['question'] }}
                            <svg class="h-5 w-5 shrink-0 text-zinc-500 transition-transform group-open:rotate-180 dark:text-zinc-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" /></svg>
                        </summary>
                        {{-- Trusted, maintainer-authored HTML rendered from FrequentlyAskedQuestions; no request data reaches it. --}}
                        <div class="faq-answer mt-3 space-y-3 leading-7">{!! $question['html'] !!}</div>
                    </details>
                @endforeach
            </div>
        </section>
    </div>
</article>
</x-layouts.app>
