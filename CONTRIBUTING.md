# Contributing

Thanks for taking the time. Unserialize is a small, deliberately narrow tool: it converts PHP serialized data into JSON over the web, an HTTP API, and MCP, and it stores nothing. Changes that keep it small are the easiest to merge.

## Before you start

Open an issue first for anything beyond a bug fix or a typo. It saves you from building something that does not fit the scope, and it gives us a place to agree on the approach.

Do not add or upgrade dependencies as part of an unrelated change. Propose it in an issue instead.

## Local setup

Follow [Local setup](README.md#local-setup) in the README. You need PHP 8.4, Composer, Node.js, and MySQL 8 or later. The test suite expects a separate `unserialize_testing` database on the same server.

## Conventions

The project records its conventions in files that both people and coding agents read:

- `CLAUDE.md` and `AGENTS.md` — framework and tooling guidance for the whole repository.
- `.ai/rules/` — path-scoped rules. `.ai/rules/index.md` maps file globs to rule files. Read every rule whose globs cover the files you are touching before editing `app/**`.

Code style is enforced by Laravel Pint. Run it rather than formatting by hand.

## Tests

Add or update tests for behavior changes. Pure copy, styling, and layout changes do not need them.

Tests live in `tests/Unit` and `tests/Feature` and run on Pest. Create new ones with `php artisan make:test --pest SomeFeatureTest`.

## Before you push

Run the full [Verification](README.md#verification) block:

```bash
vendor/bin/pint --format agent
php artisan test --compact
npm run build
composer validate --strict --no-check-publish
composer audit --locked --no-interaction
```

CI runs the same checks on every pull request.

## Pull requests

- One focused change per pull request. Split unrelated work.
- Explain what changes and why in the description. Link the issue it closes.
- Keep the diff free of formatting churn in files you did not otherwise touch.

## Security

Do not open a public issue for a vulnerability. Report it privately to [roelmagdaleno@gmail.com](mailto:roelmagdaleno@gmail.com), or use GitHub's private vulnerability reporting on this repository.
