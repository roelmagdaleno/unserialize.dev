<x-layouts.app>
    <article class="mt-10 max-w-3xl space-y-5 leading-7 text-zinc-700 dark:text-zinc-300">
        <h2 class="text-3xl font-semibold tracking-tight text-zinc-950 dark:text-white">Treat serialized data as untrusted input</h2>
        <p>PHP warns against passing untrusted data to <a class="text-blue-900 underline dark:text-blue-300" href="https://www.php.net/manual/en/function.unserialize.php"><code>unserialize()</code></a>. Unserialize is an inspection aid, not a signal that submitted data is trustworthy or safe to write back into an application.</p>
        <h3 class="text-xl font-semibold text-zinc-950 dark:text-white">Safeguards used here</h3>
        <ul class="list-disc space-y-2 pl-6">
            <li><code>allowed_classes</code> is set to <code>false</code>, and decoded objects are rejected.</li>
            <li>Input is limited to 262,144 bytes and decoding depth is capped.</li>
            <li>New conversions are processed in memory and are not stored or logged.</li>
            <li>Malformed input and JSON encoding failures return stable, non-internal messages.</li>
        </ul>
        <h3 class="text-xl font-semibold text-zinc-950 dark:text-white">Before you paste</h3>
        <p>Redact passwords, tokens, personal information, and private URLs. If a value came from WordPress, work from a backup and verify the meaning of every field before changing production data.</p>
        <h3 class="text-xl font-semibold text-zinc-950 dark:text-white">Authorship and reporting</h3>
        <p>Maintained by <a class="text-blue-900 underline dark:text-blue-300" href="https://github.com/roelmagdaleno">Roel Magdaleno</a>. Source and tests are public on <a class="text-blue-900 underline dark:text-blue-300" href="https://github.com/roelmagdaleno/unserialize">GitHub</a>. Last reviewed September 13, 2026.</p>
        <p><a class="font-semibold text-blue-900 underline dark:text-blue-300" href="mailto:roelmagdaleno@gmail.com">Report a security issue</a> privately by email.</p>
    </article>
</x-layouts.app>
