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

<article class="mt-14 max-w-4xl border-t border-zinc-200 pt-10 dark:border-zinc-700">
    <h2 class="text-2xl font-semibold tracking-tight">How to convert PHP serialized data</h2>
    <ol class="mt-4 list-decimal space-y-2 pl-6 text-zinc-700 dark:text-zinc-300">
        <li>Paste a value produced by PHP's <code>serialize()</code> function into the converter.</li>
        <li>Select <strong>Unserialize</strong>. The server validates the value with PHP classes disabled.</li>
        <li>Review or copy the JSON result. The input and result are not retained.</li>
    </ol>

    <h2 class="mt-10 text-2xl font-semibold tracking-tight">Tested example</h2>
    <div class="mt-4 grid gap-4 md:grid-cols-2">
        <div>
            <h3 class="font-semibold">Serialized PHP</h3>
            <pre class="mt-2 rounded-lg p-4"><code>a:2:{s:4:"name";s:6:"Chrome";s:6:"active";b:1;}</code></pre>
        </div>
        <div>
            <h3 class="font-semibold">JSON</h3>
            <pre class="mt-2 rounded-lg p-4"><code>{
    "name": "Chrome",
    "active": true
}</code></pre>
        </div>
    </div>

    <h2 class="mt-10 text-2xl font-semibold tracking-tight">Supported PHP values</h2>
    <p class="mt-3 leading-7 text-zinc-700 dark:text-zinc-300">Null, booleans, integers, floats, strings, indexed arrays, associative arrays, and nested combinations are supported. Objects are rejected, malformed input is refused, recursion is handled safely, and JSON encoding failures are reported without exposing internals.</p>
    <p class="mt-4 leading-7 text-zinc-700 dark:text-zinc-300">For syntax details and edge cases, read the <a class="text-blue-900 underline dark:text-blue-300" href="{{ route('guides.serialization') }}">PHP serialization format guide</a>. For untrusted values, review the <a class="text-blue-900 underline dark:text-blue-300" href="{{ route('security') }}">security guidance</a> first.</p>
</article>
</div>
