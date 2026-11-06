<section class="mt-8" aria-labelledby="conversion-result">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 id="conversion-result" class="text-lg font-semibold">JSON result</h2>
        <p class="text-sm text-zinc-600 dark:text-zinc-300">This result is not retained by Unserialize.</p>
    </div>
    @if ($lineEndingsRestored)
        <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-300" role="status">Restored the Windows line endings (CRLF) your browser removed from this value.</p>
    @endif
    <div class="relative">
        <pre tabindex="0" class="mt-2 overflow-x-auto rounded-lg p-6" data-lang="json" id="clipText">{{ $result }}</pre>
        <button class="copyBtn hidden md:block" type="button" title="Copy JSON to clipboard" aria-label="Copy JSON to clipboard" data-clipboard-target="#clipText" data-clipboard-report="{{ route('conversion.copied') }}">
            <svg class="h-5 w-5 fill-current" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M8 3a1 1 0 011-1h2a1 1 0 110 2H9a1 1 0 01-1-1z"></path><path d="M6 3a2 2 0 00-2 2v11a2 2 0 002 2h8a2 2 0 002-2V5a2 2 0 00-2-2 3 3 0 01-3 3H9a3 3 0 01-3-3z"></path></svg>
        </button>
    </div>
</section>
