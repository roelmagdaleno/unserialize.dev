<div>
<section class="mt-8">
    <form wire:submit="unserialize">
        <flux:field>
            <flux:label>Serialized Data</flux:label>

            <flux:textarea
                wire:model="form.serializedData"
                rows="auto"
                class="font-mono text-sm md:text-base"
                autofocus="autofocus"
            />

            <flux:error name="form.serializedData" />

            <flux:description>
                Your input is processed in memory, not stored or logged. Max 262,144 bytes. <a class="underline hover:text-zinc-700 dark:hover:text-zinc-200" href="{{ route('privacy') }}">Privacy details</a>.
            </flux:description>
        </flux:field>

        <div class="mt-6 flex justify-end">
            <flux:button type="submit" class="w-full md:w-auto" variant="primary">Unserialize</flux:button>
        </div>
    </form>
    @if ($diagnostic !== null)
        {{-- The excerpt <pre> stays on one physical line and uses ternaries rather than @if: whitespace inside it is content, and Livewire wraps every Blade conditional in HTML marker comments that would land inside the excerpt text. --}}
        <section
            class="mt-6 rounded-lg border border-red-300 bg-red-50 p-4 sm:p-5 dark:border-red-400/40 dark:bg-red-950/40"
            role="alert"
            aria-labelledby="diagnostic-heading"
            wire:key="diagnostic-{{ $diagnostic['code'] }}-{{ $diagnostic['offset'] }}"
        >
            <div class="flex items-start gap-3">
                <svg class="mt-0.5 h-5 w-5 shrink-0 text-red-700 dark:text-red-300" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" /></svg>

                <div class="min-w-0 flex-1">
                    <h2 id="diagnostic-heading" class="text-base font-semibold text-red-900 dark:text-red-200">
                        Error: {{ $diagnostic['message'] }}
                    </h2>

                    <p class="mt-1 text-sm text-red-900/90 dark:text-red-200/90">
                        Byte {{ number_format($diagnostic['offset']) }}{{ $diagnostic['line'] === null ? '' : ', line '.number_format($diagnostic['line']).', column '.number_format($diagnostic['column']) }}{{ $diagnostic['length'] > 0 ? ', '.number_format($diagnostic['length']).($diagnostic['length'] === 1 ? ' byte' : ' bytes').' marked below' : '' }}.
                    </p>

                    <figure class="mt-3">
                        <figcaption class="sr-only">An excerpt of the submitted value around byte {{ $diagnostic['offset'] }}. The invalid part is marked, and bytes that cannot be shown as text appear as backslash-x escapes.</figcaption>
                        <pre tabindex="0" data-highlight="off" class="diagnostic-excerpt rounded-md p-4"><span class="diagnostic-ellipsis" aria-hidden="true">{{ $diagnostic['excerpt']['truncatedStart'] ? '…' : '' }}</span>{{ $diagnostic['excerpt']['before'] }}<mark class="diagnostic-span{{ $diagnostic['excerpt']['caret'] ? ' diagnostic-span--caret' : '' }}"><span class="sr-only">start of invalid part </span>{{ $diagnostic['excerpt']['span'] }}<span class="sr-only"> end of invalid part</span></mark>{{ $diagnostic['excerpt']['after'] }}<span class="diagnostic-ellipsis" aria-hidden="true">{{ $diagnostic['excerpt']['truncatedEnd'] ? '…' : '' }}</span></pre>
                    </figure>

                    @if ($diagnostic['suggestion'] !== null)
                        <p class="mt-3 text-sm text-red-900 dark:text-red-200">
                            <span class="font-semibold">Suggestion:</span> {{ $diagnostic['suggestion'] }}
                        </p>
                    @endif

                    <p class="mt-3 text-xs text-red-900/80 dark:text-red-200/80">
                        Nothing was changed for you. Edit the value above and convert again.
                    </p>
                </div>
            </div>
        </section>
    @endif

    @if ($result !== null)
        <section class="mt-8" aria-labelledby="conversion-result">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 id="conversion-result" class="text-lg font-semibold">JSON result</h2>
                <p class="text-sm text-zinc-600 dark:text-zinc-300">This result is not retained by Unserialize.</p>
            </div>
            <div class="relative">
                <pre tabindex="0" class="mt-2 overflow-x-auto rounded-lg p-6" data-lang="json" id="clipText">{{ $result }}</pre>
                <button class="copyBtn hidden md:block" type="button" title="Copy JSON to clipboard" aria-label="Copy JSON to clipboard" data-clipboard-target="#clipText" data-clipboard-event="result-copied">
                    <svg class="h-5 w-5 fill-current" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M8 3a1 1 0 011-1h2a1 1 0 110 2H9a1 1 0 01-1-1z"></path><path d="M6 3a2 2 0 00-2 2v11a2 2 0 002 2h8a2 2 0 002-2V5a2 2 0 00-2-2 3 3 0 01-3 3H9a3 3 0 01-3-3z"></path></svg>
                </button>
            </div>
        </section>
    @endif
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
                        <tr><td class="p-3">Object</td><td class="p-3"><code>O:8:&quot;stdClass&quot;:1:{s:4:&quot;name&quot;;s:3:&quot;Ada&quot;;}</code></td><td class="p-3">Rejected</td></tr>
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

            <p class="mt-6 leading-7">Sequential integer keys become JSON arrays; associative keys become JSON objects. Null, booleans, integers, floats, strings, arrays, and nested combinations are supported. A reference (<code>R:</code>) becomes a copy of the value it points to. Serialized objects (<code>O:</code>) are rejected, because unserializing them can run code; see <a class="text-blue-900 underline dark:text-blue-300" href="#security">Security and privacy</a>.</p>
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
            <p class="mt-3 leading-7">Treat serialized data as untrusted input. This converter calls PHP with <code>allowed_classes</code> set to <code>false</code>, rejects decoded objects, limits input to 262,144 bytes, and caps decoding depth.</p>
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
                    <details class="group py-4" wire:key="faq-{{ $loop->index }}">
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
</div>
