# Spec: faq-vocabulary

## Objective

Answer the questions behind the zero-click query clusters on the home page, so the page is relevant for them and eligible for richer results:

- Spelling variants: `unserialise`, `unserialised`, `unserialized`, `unserilize` (≈110 impr., pos. 8–15)
- Synonyms: `deserialize php online`, `deserialization online` (≈12 impr., pos. 9–46)
- JSON confusion: `unserialize json`, `json unserialize online`, `php serialize to json` (≈67 impr., pos. 7–10)

The user is a developer who has a serialized string, or a JSON string they think is serialized, and needs to know which tool or function applies.

## Decisions (2026-10-05)

- Each question is a `<details>` element; the answer is trusted HTML.
- Every question and answer is also published in `FAQPage` JSON-LD.

## Assumptions

1. The FAQ lives on the home page as a new `<section>` after "Security and privacy".
2. Questions are real user intents, written once in plain English. Variant spellings appear naturally (for example "Unserialize (also spelled unserialise) means…"), never as a keyword list.
3. Google shows FAQ rich results only for a limited set of sites. The markup still helps machine understanding and AI answers, but no rich result is promised.
4. One source of truth: the questions and answers are PHP constants in one class. They feed the Blade `<details>` list, the Markdown representation and the JSON-LD, so the three cannot drift.
5. "Trusted HTML" means HTML written by the maintainer in the source code, never derived from a request. It is rendered with `{!! !!}`, and the class docblock states that constraint.
6. Content inside a closed `<details>` element is in the HTML and is indexed. It is not "hidden content" because the same text is visible on expand and matches the JSON-LD.

## Requirements

FAQ entries (final wording at implementation):

1. What does unserialize mean? (covers "unserialise", "deserialize")
2. Is PHP `unserialize()` the same as `json_decode()`? (covers "unserialize json")
3. How do I convert PHP serialized data to JSON? (covers "php serialized to json")
4. Why does my serialized string fail to unserialize? (string byte lengths, UTF-8, truncated values; refers to the diagnostic panel)
5. Is it safe to unserialize untrusted data? (objects rejected, `allowed_classes => false`)

### Markup

- The section has `<h2 id="faq-heading">Frequently asked questions</h2>`.
- Each entry is `<details>` with `<summary>` holding the question text. No heading element is placed inside `<summary>`, because that breaks heading semantics in some screen readers.
- All entries are closed by default. The `<summary>` has a visible focus style and a disclosure marker that works in light and dark mode.
- Each answer is 40–80 words, starts with a direct one-sentence answer, and may use `<p>`, `<code>`, `<a>`, `<strong>`, `<ul>` and `<li>`.

### JSON-LD

- One `FAQPage` node is built in `Serialized::mount()` with `laravel/head`'s `Schema` builder, next to the `WebApplication` node.
- `mainEntity` is a list of `Question` nodes, each with `name` (plain-text question) and `acceptedAnswer.text`.
- `acceptedAnswer.text` is the same answer HTML reduced to the tags Google accepts in answer text (`<a>`, `<p>`, `<strong>`, `<b>`, `<em>`, `<i>`, `<br>`, `<ul>`, `<ol>`, `<li>`, `<h1>`–`<h6>`, `<div>`). Unsupported tags such as `<code>` are unwrapped, not removed with their text. Reducing the tags uses `strip_tags()` with an allowlist.

### Markdown

`markdown/home.blade.php` renders each entry as `### Question` followed by the answer converted to Markdown. Keep the answer HTML simple enough that a small, tested converter handles it (`<code>` → backticks, `<a>` → `[text](url)`), or store a Markdown variant next to the HTML. Decide in T2.

## Tech Stack

Laravel 13.34, Livewire 3.8, `laravel/head` 0.2.2 (`Schema`), Tailwind 4, Pest 5.3.

## Commands

```
Test:   php artisan test --compact tests/Feature/ContentPagesTest.php tests/Feature/MetadataTest.php tests/Feature/MarkdownNegotiationTest.php
Format: vendor/bin/pint --dirty --format agent
Build:  npm run build
```

## Project Structure

- `app/Data/FrequentlyAskedQuestions.php` (via `php artisan make:class`): the questions and answers
- `app/Livewire/Serialized.php`: passes the questions to the view and publishes the JSON-LD
- `resources/views/livewire/serialized.blade.php`: the FAQ section
- `resources/views/markdown/home.blade.php`: the Markdown FAQ

## Code Style

```blade
<section id="faq" class="scroll-mt-6" aria-labelledby="faq-heading">
    <h2 id="faq-heading" class="text-2xl font-semibold tracking-tight text-zinc-950 dark:text-white">Frequently asked questions</h2>
    <div class="mt-4 divide-y divide-zinc-200 dark:divide-zinc-800">
        @foreach ($questions as $question)
            <details class="group py-4">
                <summary class="cursor-pointer font-semibold text-zinc-950 dark:text-white">{{ $question['question'] }}</summary>
                {{-- Trusted, maintainer-authored HTML from FrequentlyAskedQuestions. --}}
                <div class="mt-3 space-y-3 leading-7">{!! $question['answer'] !!}</div>
            </details>
        @endforeach
    </div>
</section>
```

## Testing Strategy

- `ContentPagesTest`: the FAQ heading and every question are on `/`; the number of `<details>` elements equals the number of questions; each answer's HTML is present.
- `MetadataTest`: there is one `"@type":"FAQPage"` node whose `mainEntity` count equals the question count; `acceptedAnswer.text` contains no `<code>` tag but keeps its text.
- `MarkdownNegotiationTest` or `ContentPagesTest`: the Markdown home contains the FAQ heading and every question.
- Any serialized example in an answer is added to the "published examples" dataset.

## Boundaries

- Always: the visible text, the Markdown and the JSON-LD come from one source; the answer HTML is only maintainer-authored.
- Ask first: adding FAQ to other pages; any answer that makes a claim about other tools.
- Never: render request data with `{!! !!}`; publish JSON-LD questions that are not on the page, or the reverse.

## Success Criteria

- Tests pass. The Google Rich Results Test validates `FAQPage` on the live URL with no errors.
- 28 days after deploy: the variant cluster (`unserialise`, `unserialised`, `unserialize json`, `deserialize php online`) gets ≥ 1 click, and its average position improves by ≥ 2.

## Open Questions

None. The answer-HTML format and the `<details>` markup are resolved.

## Implementation notes (2026-10-05)

- The single source holds each answer as maintainer-authored **Markdown** (`App\Data\FrequentlyAskedQuestions`). The HTML is rendered from it with `Str::markdown()` (`html_input: strip`, unsafe links off), the Markdown page uses it as is, and the JSON-LD uses the HTML reduced to Google's tag allowlist. This removes the need for a hand-written HTML→Markdown converter.
- The JSON-LD uses `laravel/head`'s built-in `Schema::faq()` (`FAQPage`).
- Question 6 (JSON back to PHP serialized data) was dropped with `serialize-tool`; the FAQ has five questions.
- `UnserializeTest` "shows a window around the problem" measured the whole component HTML against 20,000 bytes; the FAQ pushed the page to 24,873. It now measures only the diagnostic panel against the same limit, which still fails if the 40,000-byte value is ever rendered in full.
- Pending: the Rich Results Test on the live URL (plan T5).
