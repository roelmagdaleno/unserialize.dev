# Plan: title-online

Spec: [SPEC-title-online.md](SPEC-title-online.md) · Deploy date: _(fill in)_

## Approach

One vertical slice. It is copy and metadata only, with no new behavior. The only technical risk is how the `withHead` macro passes `exact` to `Title::make()`.

## Risks

| Risk | Mitigation |
| --- | --- |
| `withHead(title:)` does not accept `exact` | Fall back to `Head::title(..., exact: true)` in `Serialized::mount()` |
| Google rewrites the title anyway | Keep the visible tagline consistent with the title; Google prefers titles that match on-page text |
| Ranking for "unserialize" drops | Title still starts with "Unserialize"; check the position at 14 and 28 days |

## Tasks

- [x] **T1. Confirm the exact-title API**
  - Acceptance: the way to render the home title without the suffix is known (macro arg or `Head::title`).
  - Verify: `search-docs` (`laravel/head`) "title exact suffix" and read `vendor/laravel/head/src` route macro.
  - Files: none.

- [x] **T2. Update tests first (red)**
  - Acceptance: the `home` row in `MetadataTest` expects `Unserialize Online – PHP Unserialize to JSON Converter` and the new description; `MarkdownNegotiationTest` expects the new Markdown heading; one test asserts the new tagline on every public page.
  - Verify: `php artisan test --compact tests/Feature/MetadataTest.php tests/Feature/MarkdownNegotiationTest.php` fails on the new expectations.
  - Files: `tests/Feature/MetadataTest.php`, `tests/Feature/MarkdownNegotiationTest.php`, `tests/Feature/ContentPagesTest.php`.

- [x] **T3. Change title, description, tagline, Markdown heading (green)**
  - Acceptance: all T2 tests pass; no other test changes.
  - Verify: the same test command passes, then `vendor/bin/pint --dirty --format agent`.
  - Files: `routes/web.php`, `app/Livewire/Serialized.php`, `resources/views/components/layouts/app.blade.php`, `resources/views/markdown/home.blade.php`.

- [x] **T4. Check the rendered head**
  - Acceptance: `<title>` and `og:title` read `Unserialize Online – PHP Unserialize to JSON Converter` (54 chars, no ` | Unserialize` suffix); description and tagline are correct in the browser.
  - Verify: load `/` locally and read `<head>`; ask the user to run the full suite `php artisan test --compact`.
  - Files: none.

- [ ] **T5. After deploy: request indexing and record the date**
  - Acceptance: URL Inspection → Request indexing done for `/`; deploy date filled in above.
  - Verify: Search Console shows the new title within ~1–2 weeks.
  - Files: this plan.

## Checkpoint

After T4: human reviews the rendered title and tagline before deploy.
