# Spec: title-online

## Objective

Make the home page an explicit match for "unserialize online" and its variants, which hold 416+ impressions at position ~12 with zero clicks, without losing the current match for "unserialize" (position 8.86).

The user is a PHP or WordPress developer who searches for an online tool and scans the result titles. Success means the title and the first visible copy say "unserialize online" in plain words.

## Assumptions

1. The copy stays in English; the audience is international.
2. The brand stays "Unserialize"; the large visual `<h1>` keeps the brand name.
3. The title renders exactly (no ` | Unserialize` suffix) on the home page only, through `laravel/head`'s `exact` option, so it stays under ~60 characters.

## Requirements

- `<title>` (approved 2026-10-05): `Unserialize Online – PHP Unserialize to JSON Converter` (54 chars, en dash U+2013, no suffix).
- `og:title` matches the `<title>`.
- Meta description (`Serialized::META_DESCRIPTION`) keeps its privacy claim and also contains "online" and "JSON". It stays under 160 chars.
- The tagline under the `<h1>` (layout, every page; decided 2026-10-05) reads: "Unserialize PHP data online and convert it to clean, readable JSON."
- `WebApplication` JSON-LD `description` keeps using `META_DESCRIPTION` (no drift).
- The Markdown representation's top heading (`markdown/home.blade.php`) matches the new title.
- `resources/llms.txt` title line is unchanged (it describes the site, not the page).

## Tech Stack

Laravel 13.34, Livewire 3.8, `laravel/head` 0.2.2, Pest 5.3, PHP 8.4.

## Commands

```
Test:   php artisan test --compact tests/Feature/MetadataTest.php tests/Feature/MarkdownNegotiationTest.php tests/Feature/ContentPagesTest.php
Format: vendor/bin/pint --dirty --format agent
Build:  npm run build
```

## Project Structure

- `routes/web.php`: home `withHead()` call
- `app/Livewire/Serialized.php`: `META_DESCRIPTION`, `SOCIAL_DESCRIPTION`
- `resources/views/components/layouts/app.blade.php`: tagline
- `resources/views/markdown/home.blade.php`: Markdown heading
- `tests/Feature/MetadataTest.php`, `tests/Feature/MarkdownNegotiationTest.php`

## Code Style

Match the existing route metadata block:

```php
->withHead(
    title: ['value' => 'Unserialize Online – PHP Unserialize to JSON Converter', 'exact' => true],
    description: Serialized::META_DESCRIPTION,
    ...
);
```

Confirm the array form of `title` that the `withHead` route macro accepts before writing it. If it does not accept `exact`, set the title in `Serialized::mount()` with `Head::title(..., exact: true)` instead.

## Testing Strategy

Update the `home` row of the `MetadataTest` dataset (title, description) and the `home` row of `MarkdownNegotiationTest`. Add one assertion (dataset over `/`, `/privacy` and `/developers`) that every page shows the new tagline. Do not add new test files.

## Boundaries

- Always: keep canonical, `og:url` and robots unchanged; run the three test files above.
- Ask first: changing the visual `<h1>` text, the brand name, or `SOCIAL_DESCRIPTION`.
- Never: keyword-stuff (no more than one "online" in the title and one in the description).

## Success Criteria

- Tests pass with the new title (assert the en dash HTML-escaped as `e()` renders it), description and tagline.
- The live `<title>` on https://unserialize.dev/ is the approved string and is under 60 chars.
- 28 days after deploy: `unserialize online` average position ≤ 9 and ≥ 1 click; `unserialize` position no worse than 9.5.

## Open Questions

1. ~~Title string~~ Resolved: `Unserialize Online – PHP Unserialize to JSON Converter`.
2. ~~Tagline scope~~ Resolved: every page.

## Implementation notes (2026-10-05)

- Implemented. The meta description already contained "online" and "JSON", so it is unchanged.
- `ExampleTest` also asserted the old title and was updated.
- Pending: request indexing for `/` after deploy (plan T5).
