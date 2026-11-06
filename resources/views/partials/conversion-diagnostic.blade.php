{{-- The excerpt <pre> stays on one physical line and uses ternaries rather than @if: whitespace inside it is content. --}}
<section
    class="mt-6 rounded-lg border border-red-300 bg-red-50 p-4 sm:p-5 dark:border-red-400/40 dark:bg-red-950/40"
    role="alert"
    aria-labelledby="diagnostic-heading"
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

            @foreach ($hints as $hint)
                <p class="mt-3 text-sm text-red-900 dark:text-red-200">
                    <span class="font-semibold">Check how it was pasted:</span> {{ $hint }}
                </p>
            @endforeach

            @if ($diagnostic['code'] === \App\Enums\SyntaxErrorCode::StringLengthMismatch->value)
                <p class="mt-3 text-sm text-red-900 dark:text-red-200">
                    Lengths often break after a search and replace. Read <a class="underline" href="{{ route('guides.broken-serialized-string') }}">how to fix a broken serialized string</a>.
                </p>
            @endif

            <p class="mt-3 text-xs text-red-900/80 dark:text-red-200/80">
                Nothing was changed for you. Edit the value above and convert again.
            </p>
        </div>
    </div>
</section>
