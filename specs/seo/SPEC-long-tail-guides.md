# Spec: long-tail-guides

## Objective

Give Google more than one relevant URL for this site. Today only `/` gets impressions. On 2026-09-14 the guides were folded into the home page (commit `0171d7e`), and their redirects were later removed (`8bccb28`), so `/guides/wordpress` now returns 404. Consolidation helped the head term. This module adds back only guides with **distinct search intent**, each built around something the site uniquely does well.

Target intents:

| Guide | Intent / queries | Unique angle |
| --- | --- | --- |
| `/guides/wordpress-serialized-data` | `wordpress serialized data to array`, WordPress options/meta, `wp option get --format=json` | Real WP-CLI and SQL examples, plus safe editing |
| `/guides/fix-broken-serialized-string` | "error at offset", "unserialize(): Error at offset X of Y bytes", string length mismatch after search-replace | The converter's byte-level diagnostic panel |

## Decisions (2026-10-05)

- Both topics are approved.
- The home WordPress section is shortened to a summary that links to the guide.
- The array output stays closed (see [SPEC-array-output.md](SPEC-array-output.md)). The WordPress guide covers `unserialize array` and `wordpress serialized data to array` with content.

## Assumptions

1. Two guides only in v1. More only if these earn impressions within 60 days.
2. The guides are static Blade views (`Route::view`) with the shared layout. They are not Livewire components.
3. Each guide is 600–1,200 words with original, tested examples, and links to the converter at the point of need ("paste it into the converter to see the exact byte").
4. The home page keeps its current sections, and the WordPress section becomes a 2–3 sentence summary that links to the full guide. The safety warning ("Back up the database…") stays on the home page. This avoids two pages competing for the same intent.
5. The URL prefix is `/guides/…`. The old `/guides/wordpress` 301-redirects to the new WordPress guide to recover any residual links.

## Requirements

- Each guide follows the cross-cutting page contract in `CAPABILITY-MAP.md`.
- The WordPress guide includes a section "Read serialized data as a PHP array": `unserialize()` with `['allowed_classes' => false]`, WordPress's `maybe_unserialize()` and `wp option get <name> --format=json`, with a tested example and its PHP array result. The converter UI does not change.
- Each guide has a unique title under 60 chars, for example:
  - "WordPress Serialized Data: Read, Convert and Edit Safely"
  - "Fix a Broken PHP Serialized String (Error at Offset)"
- `Article` (or `TechArticle`) JSON-LD with the `#author` reference, `datePublished` and `dateModified`.
- Every code example that is serialized PHP is covered by the "published examples" test dataset.
- The broken-string guide shows real diagnostic output produced by the app (the message, offset and suggestion from `DiagnosticTranslator`), generated from tested fixtures and not hand-typed.
- The layout footer gets a "Guides" group; the home page links to each guide from the relevant section.

## Tech Stack

Laravel 13.34 Blade views, `laravel/head` 0.2.2, Tailwind 4, Shiki 4, Pest 5.3.

## Commands

```
Test:   php artisan test --compact tests/Feature/ContentPagesTest.php tests/Feature/MetadataTest.php tests/Feature/SitemapTest.php tests/Feature/MarkdownNegotiationTest.php
Format: vendor/bin/pint --dirty --format agent
Build:  npm run build
```

## Project Structure

- `resources/views/guides/wordpress-serialized-data.blade.php`, `resources/views/guides/fix-broken-serialized-string.blade.php`
- `resources/views/markdown/guides/*.blade.php`
- `routes/web.php` (two routes and one `permanentRedirect`), sitemap, `llms.txt`, layout footer
- `tests/Feature/ContentPagesTest.php` (+ the dataset rows)

## Code Style

Reuse the typographic classes of the home `<article>` (headings, `leading-7`, code blocks with `data-lang`). If the same classes repeat across three or more pages, extract a Blade component (`x-prose-section`) in a separate task.

## Testing Strategy

- `ContentPagesTest`: each guide renders its key headings and links to the converter; examples round-trip through `App\Services\Serialized`; the broken-string guide's diagnostic samples match `DiagnosticTranslator` output for the same fixture.
- A redirect test: `/guides/wordpress` returns 301 to the new URL.
- Dataset rows in `MetadataTest`, `SitemapTest` and `MarkdownNegotiationTest`.

## Boundaries

- Always: original examples that tests verify; one clear intent per URL.
- Ask first: adding more than two guides; reintroducing `/security` or `/guides/php-serialization`; changing home section order.
- Never: copy content from php.net or other tools; publish untested serialized examples; let a guide target "unserialize online" (that belongs to the home page).

## Success Criteria

- Both guides are indexed within 3 weeks of deploy.
- 60 days: each guide has ≥ 50 impressions, and `wordpress serialized data to array` position is ≤ 15.
- The home page's `unserialize` position does not regress by more than 1.

## Open Questions

None. Topics and home shortening are approved.

## Implementation notes (2026-10-05)

- Both guides use `TechArticle` JSON-LD, built in `routes/web.php` and passed through `withHead(schema:)`. `og:type` is `article`.
- Route names: `guides.wordpress-serialized-data` and `guides.broken-serialized-string`. `/guides/wordpress` returns a 301 to the WordPress guide.
- The broken-string examples and their quoted diagnostics are checked in `ContentPagesTest` against the real `DiagnosticPresenter` output.
- **Finding (not fixed):** for a value cut inside a string (for example `a:2:{i:0;s:3:"php";i:1;s:9:"wordpr`), the diagnostic suggests "Change `s:9:` to `s:6:`", which is misleading because the string has no closing quote. The guide uses a truncation that ends on a complete element instead. This belongs to the diagnostics in `roelmagdaleno/serialized` and `DiagnosticTranslator`.
- T7 (shared prose component) was not done; it stays optional.
- Pending: resubmit the sitemap and request indexing for both guides after deploy (plan T6).
- The guides originally linked to `/serialize`; after it was removed they point readers to PHP's own `serialize()` instead.
