<x-layouts.app>
    <article class="mt-10 max-w-3xl space-y-5 leading-7 text-zinc-700 dark:text-zinc-300">
        <h2 class="text-3xl font-semibold tracking-tight text-zinc-950 dark:text-white">Inspect WordPress options and metadata safely</h2>
        <p>WordPress stores some arrays and structured values in serialized form in options and metadata. Use this workflow for inspection; do not edit serialized lengths by hand.</p>
        <ol class="list-decimal space-y-3 pl-6">
            <li><strong>Back up the database before making changes.</strong></li>
            <li>Read a value with a tool such as <code>wp option get your_option_name --format=json</code> or a read-only database client.</li>
            <li><strong>Redact secrets</strong>, tokens, email addresses, private URLs, and other personal data before using any web tool.</li>
            <li>Paste only the serialized value into the <a class="text-blue-900 underline dark:text-blue-300" href="{{ route('home') }}">converter</a> and inspect the JSON result.</li>
            <li>If a change is necessary, use WordPress APIs or WP-CLI so PHP recalculates string lengths. Test on staging before production.</li>
        </ol>
        <h3 class="text-xl font-semibold text-zinc-950 dark:text-white">Tested example</h3>
        <pre class="rounded-lg p-4"><code>a:2:{s:10:"show_title";b:1;s:14:"posts_per_page";i:10;}</code></pre>
        <pre class="rounded-lg p-4"><code>{
    "show_title": true,
    "posts_per_page": 10
}</code></pre>
        <p>This example is covered by the application's test suite. Review the <a class="text-blue-900 underline dark:text-blue-300" href="{{ route('security') }}">security guidance</a> before handling an unfamiliar value.</p>
    </article>
</x-layouts.app>
