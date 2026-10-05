# Plan: long-tail-guides

Spec: [SPEC-long-tail-guides.md](SPEC-long-tail-guides.md) · Deploy date: _(fill in)_

## Approach

Ship one guide end to end, with the full contract and tests, before starting the second. The first guide sets the template; the second reuses it.

## Dependency graph

```
Guide 1 (WordPress): copy ─> route+head+Markdown+sitemap+llms+footer ─> examples tests ─> home link
Guide 2 (broken string): copy ─> diagnostic fixtures ─> same contract ─> home link
(optional) extract x-prose-section once 3+ pages share markup
```

## Risks

| Risk | Mitigation |
| --- | --- |
| Guides cannibalise the home page | Distinct intents; the home WordPress section is shortened and links out |
| Thin content | 600–1,200 words, original tested examples, human copy review |
| Diagnostic samples drift from the real output | Generate them from fixtures and assert in tests |

## Tasks

- [ ] **T1. Outline and copy for the WordPress guide** (copy written; awaiting human review)
  - Acceptance: the human approves an outline covering where WP stores serialized data, reading it with WP-CLI and SQL, reading it as a PHP array (`unserialize()`, `maybe_unserialize()`), converting it to JSON, editing it safely and why search-replace breaks lengths.
  - Verify: human sign-off.
  - Files: none.

- [x] **T2. WordPress guide page with the SEO contract**
  - Acceptance: the route, exact title, description, canonical, `Article` JSON-LD, Markdown version, sitemap, `llms.txt` and footer are done; `/guides/wordpress` returns 301 to it.
  - Verify: `php artisan test --compact tests/Feature/ContentPagesTest.php tests/Feature/MetadataTest.php tests/Feature/SitemapTest.php tests/Feature/MarkdownNegotiationTest.php`.
  - Files: `routes/web.php`, `resources/views/guides/wordpress-serialized-data.blade.php`, `resources/views/markdown/guides/wordpress-serialized-data.blade.php`, `resources/views/sitemap.blade.php`, `resources/llms.txt` (+ tests).

- [x] **T3. Tested examples and home cross-link**
  - Acceptance: every serialized example (including the PHP array section) is in the round-trip dataset; the home WordPress section is a 2–3 sentence summary that keeps the backup warning and links to the guide; the `ContentPagesTest` home assertions are updated to match.
  - Verify: `php artisan test --compact tests/Feature/ContentPagesTest.php`.
  - Files: `tests/Feature/ContentPagesTest.php`, `resources/views/livewire/serialized.blade.php`, `resources/views/markdown/home.blade.php`, `resources/views/components/layouts/app.blade.php`.

- [ ] **Checkpoint A**: the human reviews guide 1 locally; adjust the template before guide 2.

- [ ] **T4. Outline, copy and fixtures for the broken-string guide** (fixtures tested; copy awaiting human review)
  - Acceptance: 3–4 realistic broken values (length mismatch after a domain change, a truncated value, a multibyte character counted as one byte) with the diagnostic output captured from the app.
  - Verify: the human approves the copy; a test asserts that each fixture produces the shown message and offset.
  - Files: `tests/Feature/ContentPagesTest.php` (fixtures dataset).

- [x] **T5. Broken-string guide page with the SEO contract**
  - Acceptance: same as T2, for `/guides/fix-broken-serialized-string`; links to the converter with the example prefilled only if that already exists (otherwise plain link).
  - Verify: the same test command as T2.
  - Files: `routes/web.php`, `resources/views/guides/fix-broken-serialized-string.blade.php`, `resources/views/markdown/guides/fix-broken-serialized-string.blade.php`, sitemap, `llms.txt`.

- [ ] **T6. Final checks and indexing**
  - Acceptance: Pint is clean; the user runs the full suite; after deploy, the sitemap is resubmitted and indexing is requested for both URLs; the deploy date is recorded.
  - Verify: `vendor/bin/pint --dirty --format agent`; `php artisan test --compact`; Search Console.
  - Files: this plan.

- [ ] **T7. (Optional) Extract a shared prose component**
  - Acceptance: 3+ pages use `x-prose-section`; the rendered HTML is unchanged.
  - Verify: the content tests still pass.
  - Files: `resources/views/components/prose-section.blade.php` + the pages that use it.
