# Plan: faq-vocabulary

Spec: [SPEC-faq-vocabulary.md](SPEC-faq-vocabulary.md) · Deploy date: _(fill in)_

## Approach

1. Define the FAQ data once.
2. Render it as `<details>` in Blade and as Markdown.
3. Emit `FAQPage` JSON-LD from the same data, with the answer HTML reduced to Google's tag allowlist.

Land the change after `title-online`, because both touch `Serialized.php` and the home tests.

## Dependency graph

```
FAQ data class ──┬──> <details> section ──> Markdown section
                 └──> answer-HTML reducer ──> FAQPage JSON-LD
```

## Risks

| Risk | Mitigation |
| --- | --- |
| `laravel/head` `Schema` has no `faqPage()`/`question()` helper | Check with `search-docs`; fall back to a generic node with `set('@type', 'FAQPage')` and `set('mainEntity', [...])` |
| Visible text and JSON-LD drift | One data source plus count and content assertions |
| `{!! !!}` becomes an XSS path later | Answers are class constants only; the docblock states it; no request data reaches the view |
| Thin or keyword-stuffed answers | Human reviews the copy in T1 before any code |

## Tasks

- [ ] **T1. Write the FAQ copy for review** (copy written; awaiting human review)
  - Acceptance: 5 questions with HTML answers of 40–80 words, approved by the human; the variant spellings are used naturally.
  - Verify: human sign-off.
  - Files: none (draft in chat or PR).

- [x] **T2. FAQ data source and its Markdown form**
  - Acceptance: `FrequentlyAskedQuestions` returns `list<array{question: string, answer: string, markdown: string}>` (or a tested HTML→Markdown helper; choose one), with docblocks per `.ai/rules/app.md`.
  - Verify: `ContentPagesTest` case for the questions on `/` fails (red).
  - Files: `app/Data/FrequentlyAskedQuestions.php`, `tests/Feature/ContentPagesTest.php`.

- [x] **T3. `<details>` section on the home page and in Markdown**
  - Acceptance: the section appears after "Security and privacy"; one `<details>` per question, closed by default; `<summary>` has a visible focus style; the Markdown home matches.
  - Verify: `php artisan test --compact tests/Feature/ContentPagesTest.php tests/Feature/MarkdownNegotiationTest.php`.
  - Files: `resources/views/livewire/serialized.blade.php`, `resources/views/markdown/home.blade.php`, `app/Livewire/Serialized.php`.

- [x] **T4. `FAQPage` JSON-LD with the reduced answer HTML**
  - Acceptance: one `FAQPage` node; `mainEntity` count equals the visible question count; `acceptedAnswer.text` keeps the text of `<code>` but not the tag.
  - Verify: new `MetadataTest` cases; `php artisan test --compact tests/Feature/MetadataTest.php`.
  - Files: `app/Livewire/Serialized.php`, `tests/Feature/MetadataTest.php`.

- [ ] **T5. Browser and Rich Results check**
  - Acceptance: keyboard open and close works; there are no console errors; light and dark mode are correct; after deploy, the Rich Results Test reports a valid `FAQPage`.
  - Verify: manual check; `vendor/bin/pint --dirty --format agent`; ask the user to run `php artisan test --compact`.
  - Files: none.

## Checkpoint

After T1, copy approval gates all code. After T5, record the deploy date.
