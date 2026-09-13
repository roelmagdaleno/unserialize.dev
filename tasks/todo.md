# Laravel Boost Compliance Tasks

## Task 1: Refresh Boost guidance for Laravel 13

**Description:** Use Boost's supported update workflow to regenerate version-aware guidelines and remove the stale Laravel 12 instructions from the repository's agent guidance.

**Acceptance criteria:**

- [x] `php artisan boost:update --no-interaction` completes successfully.
- [x] Generated guidance identifies the installed Laravel 13 major version.
- [x] The generated diff is reviewed and contains no unrelated application changes.

**Verification:**

- [x] Run `php artisan boost:update --no-interaction`.
- [x] Run the Boost application-information tool and confirm Laravel 13.x.
- [x] Review `git diff -- AGENTS.md CLAUDE.md boost.json .agents .codex` as applicable.

**Dependencies:** None

**Files likely touched:** `AGENTS.md`, `CLAUDE.md`, Boost-managed skill files

**Estimated scope:** Small

## Task 2: Make deserialization safe and correct

**Description:** Add failing service tests, then consolidate decoding so each conversion safely deserializes once, cannot instantiate PHP classes, and correctly handles falsey scalar values such as integer zero.

**Acceptance criteria:**

- [x] `unserialize()` is always called with classes disabled.
- [x] JSON and array output correctly handle `0`, `false`, `null`, strings, and arrays.
- [x] Invalid input and JSON encoding failures retain clear, non-internal error messages.
- [x] The chosen serialized-object policy is covered by a regression test.

**Verification:**

- [x] Run `php artisan test --compact tests/Unit/UnserializeTest.php`.
- [x] Confirm a serialized class with a magic method cannot execute that method.

**Dependencies:** Task 1

**Files likely touched:** `app/Services/Serialized.php`, `app/Rules/SerializedRule.php`, `tests/Unit/UnserializeTest.php`

**Estimated scope:** Medium

## Task 3: Escape stored user input on output pages

**Description:** Replace raw rendering of the original serialized payload with Blade's escaped output and add a feature test proving malicious markup is displayed as text rather than executable HTML.

**Acceptance criteria:**

- [x] The original serialized payload is rendered with Blade escaping.
- [x] A stored `<script>` payload does not appear unescaped in the response.
- [x] Syntax-highlighted output remains readable and its escaping boundary has regression coverage.

**Verification:**

- [x] Run `php artisan test --compact tests/Feature/OutputTest.php`.
- [x] Inspect the rendered response assertions for both escaped and raw payload forms.

**Dependencies:** Task 2

**Files likely touched:** `resources/views/livewire/output.blade.php`, `tests/Feature/OutputTest.php`

**Estimated scope:** Small

## Task 4: Bound and throttle public conversions

**Description:** Add a maximum serialized-input length and use Laravel's programmatic rate limiter at the Livewire action boundary. Use the approved cap and attempt limit, with a stable key derived from the anonymous client's IP address.

**Acceptance criteria:**

- [x] Input beyond the approved limit fails validation before deserialization, highlighting, or persistence.
- [x] Requests beyond the approved attempt limit receive user-visible feedback and create no `outputs` record.
- [x] Valid requests below both limits continue to save and redirect normally.

**Verification:**

- [x] Run `php artisan test --compact tests/Feature/UnserializeTest.php`.
- [x] Test the exact validation rule and rate-limit boundary, including absence of database writes on failure.
- [x] Confirm production uses a shared cache store if deployed to multiple instances.

**Dependencies:** Task 2; explicit approval of the input cap and throttling policy

**Files likely touched:** `app/Livewire/Serialized.php`, `app/Livewire/Forms/SerializedForm.php`, `tests/Feature/UnserializeTest.php`

**Estimated scope:** Medium

## Task 5: Repair test isolation and ineffective assertions

**Description:** Remove swallowed exceptions, use database refresh consistently for feature tests, replace vague assertions with named or exact assertions, and remove unused Pest scaffold helpers and explanatory comments that merely restate the code.

**Acceptance criteria:**

- [x] No test catches and ignores unexpected exceptions.
- [x] Every database-writing feature test is isolated with `LazilyRefreshDatabase`.
- [x] Redirect, validation, return-value, and database assertions state exact expected behavior.
- [x] Tests pass alone, as a full suite, and in randomized order.

**Verification:**

- [x] Run `php artisan test --compact tests/Unit/UnserializeTest.php`.
- [x] Run `php artisan test --compact tests/Feature`.
- [x] Run `php artisan test --compact --order-by=random`.

**Dependencies:** Tasks 2–4

**Files likely touched:** `tests/Pest.php`, `tests/Unit/UnserializeTest.php`, `tests/Feature/OutputTest.php`, `tests/Feature/UnserializeTest.php`, `tests/Feature/ExampleTest.php`

**Estimated scope:** Medium

## Task 6: Apply Laravel naming and PHP formatting conventions

**Description:** Rename the plural `OutputFormats` enum to singular `OutputFormat`, remove unused imports, normalize return types where Laravel contracts allow it, and let Pint correct the identified brace and spacing violations.

**Acceptance criteria:**

- [x] The enum uses singular Laravel naming and all references resolve.
- [x] Modified PHP files contain no unused imports or redundant PHPDoc types.
- [x] Pint produces no remaining changes after formatting.

**Verification:**

- [x] Run `vendor/bin/pint --dirty --format agent`.
- [x] Run `php artisan test --compact`.
- [x] Review `git diff --check`.

**Dependencies:** Task 5

**Files likely touched:** `app/Enums/OutputFormat.php`, enum consumers under `app/` and `database/factories/`, affected migrations

**Estimated scope:** Medium

## Task 7: Run the complete Boost-aligned quality gate

**Description:** Verify the final implementation against the installed Laravel 13, Livewire 3, Flux 2, Pest 5, and Pint versions using the repository's normal tooling.

**Acceptance criteria:**

- [x] All PHP tests and frontend production build pass.
- [x] Composer metadata is valid and the locked dependency graph has no known advisories.
- [x] The final diff contains only planned changes and no debugging artifacts.

**Verification:**

- [x] Run `vendor/bin/pint --dirty --format agent`.
- [x] Run `php artisan test --compact`.
- [x] Run `npm run build`.
- [x] Run `composer validate --strict --no-check-publish`.
- [x] Run `composer audit --locked --no-interaction`.
- [x] Run `git diff --check` and review `git diff --stat`.

**Dependencies:** Tasks 1–6

**Files likely touched:** None beyond fixes required by verification

**Estimated scope:** Small
