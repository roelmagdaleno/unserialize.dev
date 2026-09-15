<x-layouts.app>
    <article class="mt-10 max-w-3xl space-y-5 leading-7 text-zinc-700 dark:text-zinc-300">
        <h2 class="text-2xl font-semibold tracking-tight text-zinc-950 dark:text-white">Privacy and retention</h2>
        <p>Last reviewed September 15, 2026.</p>
        <p>New conversions are processed in memory. Your serialized input is transmitted to the Unserialize server, converted, returned to your browser, and not stored in the outputs database.</p>
        <p>Request bodies are not recorded in application logs or Nightwatch. Operational telemetry uses only the interface, outcome category, a diagnostic category, latency, and an input-size bucket—not submitted serialized values, byte offsets, or converted content.</p>
        <h3 class="text-xl font-semibold tracking-tight text-zinc-950 dark:text-white">Technical usage metadata</h3>
        <p>Separately from conversion content, Unserialize records technical metadata about how it is used: the browser or client User-Agent string, the request URL, and the referring URL, along with the interface, outcome, and timing described above. This is not anonymous data. A User-Agent, a URL, and a timestamp can describe a particular visit, and a URL you arrive from may itself carry information about you.</p>
        <p>This metadata is stored only in Unserialize's own database. It is never sent to Google Analytics or any other third-party analytics provider, it is never exposed through a public endpoint, and it is not used to build a profile, a fingerprint, or a unique-visitor count. Credentials embedded in a URL are removed and the values of sensitive query keys such as <code>token</code>, <code>api_key</code>, and <code>password</code> are replaced before anything is written.</p>
        <p>Usage metadata is deleted automatically after {{ config('telemetry.retention_days') }} days by a scheduled daily purge. The database is not backed up, so {{ config('telemetry.retention_days') }} days is the complete retention period and there is no copy that outlives it. Long-lived conversion counts are kept, but they hold only a date, an interface, an outcome, and a total.</p>
        <h3 class="text-xl font-semibold tracking-tight text-zinc-950 dark:text-white">Limits and unsupported data</h3>
        <p>Inputs are limited to 262,144 bytes. PHP objects are rejected and classes are disabled during decoding. Invalid data and values that cannot be represented as JSON return an error.</p>
        <h3 class="text-xl font-semibold tracking-tight text-zinc-950 dark:text-white">Legacy output links</h3>
        <p>Legacy output links created by an earlier version may remain available so existing links continue to work. They are excluded from search indexing and are not linked from public navigation or discovery files. Contact <a href="mailto:roelmagdaleno@gmail.com">roelmagdaleno@gmail.com</a> about a legacy value or privacy concern.</p>
    </article>
</x-layouts.app>
