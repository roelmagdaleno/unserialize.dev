<x-layouts.app>
    <article class="mt-10 max-w-4xl space-y-6 leading-7 text-zinc-700 dark:text-zinc-300">
        <h1 class="text-3xl font-semibold tracking-tight text-zinc-950 dark:text-white">WordPress serialized data: read, convert and edit it safely</h1>
        <p>WordPress stores many settings as serialized PHP: plugin options, widget settings, theme modifications and a lot of post meta. The values look like <code>a:3:{s:10:&quot;show_title&quot;;b:1;…}</code> in the database. This guide shows where they live, how to read them as a PHP array or as JSON, and how to change them without breaking them.</p>

        <section class="space-y-3" aria-labelledby="where-stored">
            <h2 id="where-stored" class="text-xl font-semibold text-zinc-950 dark:text-white">Where WordPress stores serialized data</h2>
            <p>When code passes an array to <code>update_option()</code>, <code>update_post_meta()</code>, <code>update_user_meta()</code> or <code>update_term_meta()</code>, WordPress serializes it before writing it. You find the result in these columns:</p>
            <ul class="list-disc space-y-1 pl-6">
                <li><code>wp_options.option_value</code>: plugin settings, <code>widget_*</code> options, <code>theme_mods_*</code> and transients.</li>
                <li><code>wp_postmeta.meta_value</code>, <code>wp_usermeta.meta_value</code> and <code>wp_termmeta.meta_value</code>: structured meta saved by plugins and themes.</li>
            </ul>
            <p>Scalars such as a single string or number are usually stored as plain text. Only arrays and objects are serialized.</p>
        </section>

        <section class="space-y-3" aria-labelledby="read-value">
            <h2 id="read-value" class="text-xl font-semibold text-zinc-950 dark:text-white">Read a serialized value</h2>
            <p>With WP-CLI, ask for the option as JSON. WP-CLI unserializes it for you:</p>
            <pre class="overflow-x-auto rounded-lg p-4" data-lang="bash">wp option get my_plugin_settings --format=json</pre>
            <p>With a read-only database client, select the raw value:</p>
            <pre class="overflow-x-auto rounded-lg p-4" data-lang="text">SELECT option_value FROM wp_options WHERE option_name = 'my_plugin_settings';</pre>
            <p>The query returns the serialized string. This is the example used in the rest of this guide:</p>
            <pre class="overflow-x-auto rounded-lg p-4" data-lang="text">a:3:{s:10:&quot;show_title&quot;;b:1;s:14:&quot;posts_per_page&quot;;i:10;s:8:&quot;home_url&quot;;s:19:&quot;https://example.com&quot;;}</pre>
        </section>

        <section class="space-y-3" aria-labelledby="php-array">
            <h2 id="php-array" class="text-xl font-semibold text-zinc-950 dark:text-white">Read serialized data as a PHP array</h2>
            <p>Inside WordPress, <code>get_option()</code> and <code>get_post_meta()</code> already return the unserialized array. When you only have the raw string, use <code>maybe_unserialize()</code>, which leaves non-serialized values alone. Outside WordPress, call <code>unserialize()</code> and forbid classes, so the value can never create an object:</p>
            <pre class="overflow-x-auto rounded-lg p-4" data-lang="php">$settings = unserialize($raw, ['allowed_classes' => false]);

// [
//     'show_title' => true,
//     'posts_per_page' => 10,
//     'home_url' => 'https://example.com',
// ]</pre>
            <p>Never call <code>unserialize()</code> without <code>allowed_classes</code> on a value that came from outside your own code. A serialized value can name any class, and PHP will build it.</p>
        </section>

        <section class="space-y-3" aria-labelledby="to-json">
            <h2 id="to-json" class="text-xl font-semibold text-zinc-950 dark:text-white">Convert it to JSON</h2>
            <p>To read a value quickly, paste it into the <a class="text-blue-900 underline dark:text-blue-300" href="{{ route('home') }}">PHP unserialize to JSON converter</a>. The example above becomes:</p>
            <pre class="overflow-x-auto rounded-lg p-4" data-lang="json">{
    "show_title": true,
    "posts_per_page": 10,
    "home_url": "https://example.com"
}</pre>
            <p>Redact secrets, API keys and personal data before you paste a value into any web tool. The converter does not store what you paste, but a value that holds no secrets is the safest one to share.</p>
        </section>

        <section class="space-y-3" aria-labelledby="edit-safely">
            <h2 id="edit-safely" class="text-xl font-semibold text-zinc-950 dark:text-white">Edit serialized data safely</h2>
            <p><strong>Back up the database before making changes.</strong> Then change the value through PHP, so the lengths are calculated for you:</p>
            <ul class="list-disc space-y-1 pl-6">
                <li>In code: read the array with <code>get_option()</code>, change it, and save it with <code>update_option()</code>.</li>
                <li>With WP-CLI: <code>wp option patch update my_plugin_settings posts_per_page 20</code> changes one key in place.</li>
                <li>For a URL or domain change across the database: <code>wp search-replace</code>, which unserializes each value, replaces the text and serializes it again.</li>
            </ul>
            <p>If you need a serialized string for a fixture or a migration, generate it with PHP's <code>serialize()</code> instead of writing it by hand, so every length is counted for you.</p>
        </section>

        <section class="space-y-3" aria-labelledby="why-breaks">
            <h2 id="why-breaks" class="text-xl font-semibold text-zinc-950 dark:text-white">Why a plain search and replace breaks it</h2>
            <p>Every string in a serialized value carries its length in bytes. In <code>s:19:&quot;https://example.com&quot;;</code> the 19 must match the text exactly. A SQL <code>REPLACE()</code> that turns the URL into <code>https://staging.example.com</code> changes the text to 27 bytes but leaves the 19, and PHP can no longer read the whole value. WordPress then treats the option as missing and the plugin falls back to its defaults.</p>
            <p>Read <a class="text-blue-900 underline dark:text-blue-300" href="{{ route('guides.broken-serialized-string') }}">how to fix a broken serialized string</a> to find and repair a value that is already damaged.</p>
        </section>
    </article>
</x-layouts.app>
