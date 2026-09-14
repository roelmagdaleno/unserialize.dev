<div>
<section class="mt-8">
    <form wire:submit="unserialize">
        <flux:textarea
            label="Serialized Data"
            wire:model="form.serializedData"
            rows="auto"
            class="font-mono text-sm md:text-base"
            autofocus="autofocus"
        />

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

    <p class="mt-4 text-sm leading-6 text-zinc-600 dark:text-zinc-300">
        Your input is sent to this server for conversion, processed in memory, and not stored or logged.
        The maximum input size is 262,144 bytes. <a class="font-medium text-blue-800 underline dark:text-blue-300" href="{{ route('privacy') }}">Read the privacy details</a>.
    </p>

    @if ($result !== null)
        <section class="mt-8" aria-labelledby="conversion-result">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 id="conversion-result" class="text-lg font-semibold">JSON result</h2>
                <p class="text-sm text-zinc-600 dark:text-zinc-300">This result is not retained by Unserialize.</p>
            </div>
            <div class="relative">
                <pre class="mt-2 rounded-lg p-6" data-lang="json" id="clipText">{{ $result }}</pre>
                <button class="copyBtn hidden md:block" type="button" title="Copy JSON to clipboard" aria-label="Copy JSON to clipboard" data-clipboard-target="#clipText">
                    <svg class="h-5 w-5 fill-current" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M8 3a1 1 0 011-1h2a1 1 0 110 2H9a1 1 0 01-1-1z"></path><path d="M6 3a2 2 0 00-2 2v11a2 2 0 002 2h8a2 2 0 002-2V5a2 2 0 00-2-2 3 3 0 01-3 3H9a3 3 0 01-3-3z"></path></svg>
                </button>
            </div>
        </section>
    @endif
</section>

<article class="mt-14 max-w-4xl border-t border-zinc-200 pt-10 text-zinc-700 dark:border-zinc-700 dark:text-zinc-300">
    <div class="space-y-12">
        <section aria-labelledby="how-to-convert">
            <h2 id="how-to-convert" class="text-2xl font-semibold tracking-tight text-zinc-950 dark:text-white">How to convert PHP serialized data</h2>
            <p class="mt-3 leading-7">Paste a value produced by PHP's <code>serialize()</code> function into the converter above, select <strong>Unserialize</strong>, then review or copy the readable JSON result.</p>
            <ol class="mt-4 list-decimal space-y-2 pl-6 leading-7">
                <li>Copy the complete serialized value, including its type markers, lengths, and delimiters.</li>
                <li>Paste it into the editor and click on <strong>Unserialize</strong>.</li>
                <li>Use the JSON result to inspect the structure without editing the original value by hand.</li>
            </ol>
        </section>

        <section aria-labelledby="why-unserialize">
            <h2 id="why-unserialize" class="text-2xl font-semibold tracking-tight text-zinc-950 dark:text-white">Why I built Unserialize</h2>
            <p class="mt-3 leading-7">I kept running into serialized data while working with WordPress. The online tools I used could decode it, but they did not turn the result into clean JSON, so the data was still harder to read than it needed to be.</p>
            <p class="mt-3 leading-7">I built Unserialize with modern web technology to make that everyday task simpler for me—and for other developers who run into the same problem. It turns PHP serialized values into a familiar, readable format without storing the submitted input or result.</p>
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
                    </tbody>
                </table>
            </div>

            <h3 class="mt-8 text-lg font-semibold text-zinc-950 dark:text-white">Tested example</h3>
            <div class="mt-4 grid gap-4 md:grid-cols-2">
                <div>
                    <h4 class="font-semibold text-zinc-950 dark:text-white">Serialized PHP</h4>
                    <pre class="mt-2 rounded-lg p-4"><code>a:2:{s:4:"name";s:6:"Chrome";s:6:"active";b:1;}</code></pre>
                </div>
                <div>
                    <h4 class="font-semibold text-zinc-950 dark:text-white">JSON</h4>
                    <pre class="mt-2 rounded-lg p-4"><code>{
    "name": "Chrome",
    "active": true
}</code></pre>
                </div>
            </div>

            <p class="mt-6 leading-7">Sequential integer keys become JSON arrays; associative keys become JSON objects. Null, booleans, integers, floats, strings, arrays, and nested combinations are supported. Serialized objects are rejected.</p>
        </section>

        <section id="wordpress" class="scroll-mt-6" aria-labelledby="wordpress-data">
            <h2 id="wordpress-data" class="text-2xl font-semibold tracking-tight text-zinc-950 dark:text-white">Working with WordPress serialized data</h2>
            <p class="mt-3 leading-7">WordPress stores some arrays and structured settings as serialized PHP in options and metadata. Unserialize makes those values easier to inspect as JSON, whether they come from WP-CLI, a database export, or a read-only database client.</p>
            <p class="mt-3 leading-7"><strong>Back up the database before making changes.</strong> Redact secrets and personal data before pasting a value into any web tool. If you need to update it, use WordPress APIs or WP-CLI so PHP recalculates string lengths; never edit those lengths manually.</p>
        </section>

        <section id="security" class="scroll-mt-6" aria-labelledby="security-and-privacy">
            <h2 id="security-and-privacy" class="text-2xl font-semibold tracking-tight text-zinc-950 dark:text-white">Security and privacy</h2>
            <p class="mt-3 leading-7">Treat serialized data as untrusted input. This converter calls PHP with <code>allowed_classes</code> set to <code>false</code>, rejects decoded objects, limits input to 262,144 bytes, and caps decoding depth.</p>
            <p class="mt-3 leading-7">Conversions are processed in memory and the submitted value and JSON result are not stored or logged. Even so, remove passwords, tokens, email addresses, private URLs, and other sensitive information before submitting data. Read the <a class="text-blue-900 underline dark:text-blue-300" href="{{ route('privacy') }}">privacy and retention details</a> for the complete policy.</p>
        </section>
    </div>
</article>
</div>
