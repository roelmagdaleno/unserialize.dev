<x-layouts.app>
    <article class="mt-10 max-w-4xl space-y-6 leading-7 text-zinc-700 dark:text-zinc-300">
        <h2 class="text-3xl font-semibold tracking-tight text-zinc-950 dark:text-white">Fix a broken PHP serialized string</h2>
        <p>PHP's <code>unserialize()</code> gives up on the whole value when one part is wrong. PHP only warns <code>unserialize(): Error at offset 45 of 78 bytes</code>, which points at where reading stopped rather than where the value went wrong, and in WordPress the option silently falls back to its defaults. This guide shows the three usual causes, what each one looks like, and how to repair it.</p>
        <p>To find the problem in your own value, paste it into the <a class="text-blue-900 underline dark:text-blue-300" href="{{ route('home') }}">PHP unserialize converter</a>. When a value cannot be read, it marks the exact byte where the problem starts and, when it can, suggests the correction. The messages below are the converter's real output for each example.</p>

        <section class="space-y-3" aria-labelledby="wrong-length">
            <h3 id="wrong-length" class="text-xl font-semibold text-zinc-950 dark:text-white">1. A string length changed after a search and replace</h3>
            <p>This is the most common cause. A database export was moved from <code>https://example.com</code> to a staging domain with a plain text replace, so the text changed but its declared length did not:</p>
            <pre class="overflow-x-auto rounded-lg p-4" data-lang="text">a:2:{s:8:&quot;home_url&quot;;s:19:&quot;https://staging.example.com&quot;;s:10:&quot;show_title&quot;;b:1;}</pre>
            <p>The converter reports:</p>
            <blockquote class="border-l-4 border-zinc-300 pl-4 dark:border-zinc-600">The string starting at byte 20 declares 19 bytes but 27 bytes precede the closing quote. Change <code>s:19:</code> to <code>s:27:</code>.</blockquote>
            <p><strong>Fix it:</strong> for a single value, correct the length as suggested. For a whole database, restore the backup and run the change again with <code>wp search-replace 'https://example.com' 'https://staging.example.com' --dry-run</code>, then without <code>--dry-run</code>. WP-CLI rewrites serialized values with the right lengths.</p>
        </section>

        <section class="space-y-3" aria-labelledby="multibyte">
            <h3 id="multibyte" class="text-xl font-semibold text-zinc-950 dark:text-white">2. Characters were counted instead of bytes</h3>
            <p>Lengths count bytes in the stored encoding, not characters. In UTF-8, <code>é</code> takes two bytes, so a hand-written value or a script that used <code>mb_strlen()</code> declares one byte too few:</p>
            <pre class="overflow-x-auto rounded-lg p-4" data-lang="text">a:1:{s:4:&quot;city&quot;;s:9:&quot;Querétaro&quot;;}</pre>
            <p>The converter reports:</p>
            <blockquote class="border-l-4 border-zinc-300 pl-4 dark:border-zinc-600">The string starting at byte 16 declares 9 bytes but 10 bytes precede the closing quote. Change <code>s:9:</code> to <code>s:10:</code>.</blockquote>
            <p><strong>Fix it:</strong> correct the length, and change the code that produced it to call <code>serialize()</code> on the array, or <code>strlen()</code> rather than <code>mb_strlen()</code> if it must build strings by hand. The same mismatch appears when a database connection converts the text to a different character set, such as <code>latin1</code> to <code>utf8mb4</code>, after it was serialized.</p>
        </section>

        <section class="space-y-3" aria-labelledby="truncated">
            <h3 id="truncated" class="text-xl font-semibold text-zinc-950 dark:text-white">3. The value was truncated</h3>
            <p>A column that is too short, an export cut off at a size limit, or a copy and paste that missed the end leaves a value without its closing brace:</p>
            <pre class="overflow-x-auto rounded-lg p-4" data-lang="text">a:2:{i:0;s:3:&quot;php&quot;;i:1;s:9:&quot;wordpress&quot;;</pre>
            <p>The converter reports:</p>
            <blockquote class="border-l-4 border-zinc-300 pl-4 dark:border-zinc-600">The array starting at byte 0 is missing its closing brace.</blockquote>
            <p><strong>Fix it:</strong> restore the complete value from a backup or from the source that wrote it. If the cut happened inside a string, the end of the data is gone: adding a brace or changing a length only hides the loss. Check the column type too; a <code>VARCHAR(255)</code> column will truncate again, so <code>LONGTEXT</code> is the usual choice.</p>
        </section>

        <section class="space-y-3" aria-labelledby="prevent">
            <h3 id="prevent" class="text-xl font-semibold text-zinc-950 dark:text-white">Prevent it next time</h3>
            <ul class="list-disc space-y-1 pl-6">
                <li>Never edit serialized values with SQL <code>REPLACE()</code> or a text editor's find and replace.</li>
                <li>Use <code>wp search-replace</code> for domain changes and <code>update_option()</code> or <code>wp option patch</code> for settings.</li>
                <li>Generate serialized strings for fixtures with PHP's <code>serialize()</code>, which counts bytes for you.</li>
                <li>Back up the database before any bulk change.</li>
            </ul>
            <p>For more on where these values live and how to read them, see <a class="text-blue-900 underline dark:text-blue-300" href="{{ route('guides.wordpress-serialized-data') }}">WordPress serialized data</a>.</p>
        </section>
    </article>
</x-layouts.app>
