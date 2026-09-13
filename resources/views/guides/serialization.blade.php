<x-layouts.app>
    <article class="mt-10 max-w-4xl text-zinc-700 dark:text-zinc-300">
        <h2 class="text-3xl font-semibold tracking-tight text-zinc-950 dark:text-white">PHP serialization format and JSON mapping</h2>
        <p class="mt-4 leading-7">PHP's <a class="text-blue-900 underline dark:text-blue-300" href="https://www.php.net/manual/en/function.serialize.php">serialize function</a> encodes a value with a type marker and length or count where needed. Unserialize decodes that grammar with classes disabled and converts the resulting value to JSON.</p>

        <div class="mt-8 overflow-x-auto">
            <table class="w-full border-collapse text-left text-sm">
                <thead><tr class="border-b border-zinc-300 dark:border-zinc-700"><th class="p-3">PHP value</th><th class="p-3">Serialized</th><th class="p-3">JSON</th></tr></thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                    <tr><td class="p-3">null</td><td class="p-3"><code>N;</code></td><td class="p-3"><code>null</code></td></tr>
                    <tr><td class="p-3">boolean</td><td class="p-3"><code>b:1;</code></td><td class="p-3"><code>true</code></td></tr>
                    <tr><td class="p-3">integer</td><td class="p-3"><code>i:42;</code></td><td class="p-3"><code>42</code></td></tr>
                    <tr><td class="p-3">float</td><td class="p-3"><code>d:3.5;</code></td><td class="p-3"><code>3.5</code></td></tr>
                    <tr><td class="p-3">string</td><td class="p-3"><code>s:5:"hello";</code></td><td class="p-3"><code>"hello"</code></td></tr>
                    <tr><td class="p-3">indexed array</td><td class="p-3"><code>a:2:{i:0;s:3:"red";i:1;s:4:"blue";}</code></td><td class="p-3"><code>["red", "blue"]</code></td></tr>
                    <tr><td class="p-3">associative array</td><td class="p-3"><code>a:1:{s:4:"name";s:3:"Ada";}</code></td><td class="p-3"><code>{"name": "Ada"}</code></td></tr>
                </tbody>
            </table>
        </div>

        <h3 class="mt-10 text-xl font-semibold text-zinc-950 dark:text-white">Arrays and nested values</h3>
        <p class="mt-3 leading-7">Indexed arrays become JSON arrays when their integer keys are sequential from zero. Associative arrays become JSON objects. Nested arrays apply the same mapping at each level.</p>

        <h3 class="mt-10 text-xl font-semibold text-zinc-950 dark:text-white">Unsupported objects and edge cases</h3>
        <p class="mt-3 leading-7">Serialized objects are not supported. PHP classes remain disabled, and any decoded object—including an incomplete class—is rejected. Recursive references are traversed without an infinite loop. Values such as <code>NAN</code> that JSON cannot represent return an encoding error.</p>

        <p class="mt-8 leading-7">Try an example in the <a class="text-blue-900 underline dark:text-blue-300" href="{{ route('home') }}">converter</a>, read the <a class="text-blue-900 underline dark:text-blue-300" href="{{ route('security') }}">security and privacy guidance</a>, or inspect the <a class="text-blue-900 underline dark:text-blue-300" href="https://github.com/roelmagdaleno/unserialize">source and tests</a>.</p>
    </article>
</x-layouts.app>
