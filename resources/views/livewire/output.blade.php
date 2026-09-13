<section class="mt-8">
    <div class="flex justify-between items-center">
        <p class="block text-sm/6 font-medium text-gray-900 dark:text-gray-300">
            Output: {{ $output->output_format->label() }}
        </p>

        <!-- Timestamp -->
        <p class="block text sm/6 font-medium text-gray-900 dark:text-gray-300">
            {{ $output->created_at }}
        </p>
    </div>

    <div class="relative">
        <pre class="mt-2 p-6 rounded-lg" data-lang="json" id="clipText">{!! $output->syntax_highlighted !!}</pre>
        <button class="md:block hidden copyBtn" title="Copy to Clipboard" data-clipboard-target="#clipText">
            <svg class="fill-current h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M8 3a1 1 0 011-1h2a1 1 0 110 2H9a1 1 0 01-1-1z"></path><path d="M6 3a2 2 0 00-2 2v11a2 2 0 002 2h8a2 2 0 002-2V5a2 2 0 00-2-2 3 3 0 01-3 3H9a3 3 0 01-3-3z"></path></svg>
        </button>
    </div>

    <div class="mt-6">
        <details>
            <summary class="text-sm/6 font-medium text-gray-900 dark:text-gray-300 cursor-pointer">
                Serialized Data
            </summary>
            <div class="relative">
                <pre class="mt-2 p-6 rounded-lg" data-lang="text" id="clipSerializedText">{{ $output->serialized }}</pre>
                <button class="md:block hidden copyBtn" title="Copy to Clipboard" data-clipboard-target="#clipSerializedText">
                    <svg class="fill-current h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M8 3a1 1 0 011-1h2a1 1 0 110 2H9a1 1 0 01-1-1z"></path><path d="M6 3a2 2 0 00-2 2v11a2 2 0 002 2h8a2 2 0 002-2V5a2 2 0 00-2-2 3 3 0 01-3 3H9a3 3 0 01-3-3z"></path></svg>
                </button>
            </div>
        </details>
    </div>
</section>
