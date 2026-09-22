# Tasks: Replace the conversion engine with `roelmagdaleno/serialized`

Spec: `docs/specs/SPEC-serialized-package-swap.md` · Plan: `tasks/plan.md`

---

## Phase 1: Foundation

## Task 1: Install `roelmagdaleno/serialized` from Packagist — DONE

**Description:** Add the package as a runtime dependency. No application code
changes; this task only proves the dependency resolves and autoloads on PHP 8.4
alongside Laravel 13.

**Acceptance criteria:**
- [x] `composer require roelmagdaleno/serialized:^1.0` resolves with no conflict
- [x] `composer.json` lists it under `require`, sorted (the project sets `sort-packages`)
- [x] `Serialized\Serialized::toJson('i:1;')` returns `1` from a one-off check

**Verification:**
- [x] `composer show roelmagdaleno/serialized` reports an installed 1.0.0
- [x] `php artisan test --compact` still green — 597 passed (needs `DB_HOST=127.0.0.1`; `.env` names `lerd-mysql`, which does not resolve here)

**Dependencies:** None

**Files likely touched:**
- `composer.json`
- `composer.lock`

**Estimated scope:** XS

---

## Task 2: Pin today's diagnostic contract with a golden test — DONE (blocker found)

**Description:** Before anything is swapped, capture what the current engine
publishes for a fixed corpus of broken payloads — one per `SyntaxErrorCode` case
plus the three refusals that carry no diagnostic. This test runs against the old
engine now and must pass unchanged against the new one, which is what turns
"the mapping looks right" into "the mapping is proven".

**Acceptance criteria:**
- [x] A dataset covers every `SyntaxErrorCode` case reachable from a real payload,
      plus `unsupported_object`, `input_too_large` and `encoding_failed`
- [x] Each row asserts the published `code`, `offset`, `length`, `message` and
      `suggestion` exactly
- [x] Deltas marked with DELTA comments — five found, not three

**Verification:**
- [x] `vendor/bin/pest tests/Feature/DiagnosticContractTest.php` green — 20 passed

**Dependencies:** None

**Files likely touched:**
- `tests/Feature/DiagnosticContractTest.php` (new)

**Estimated scope:** S

---

### Checkpoint: Foundation
- [ ] `php artisan test --compact` fully green
- [ ] The package is installed and the app still runs on its own engine
- [ ] The golden test describes today's behavior, not tomorrow's

---

## BLOCKED: waiting on `roelmagdaleno/serialized` 1.0.1

Task 2 found two payloads PHP decodes that the package refused, both breaking
`tests/Unit/UnserializeTest.php` ("converts values PHP decodes despite raising a
diagnostic"), which is on the protected list:

| Payload | PHP | App today | Package 1.0.0 |
|---|---|---|---|
| `S:5:"\68ello";` | `'hello'` + deprecation | converts | `unknown_type_prefix` |
| `i:99999999999999999999999;` | `PHP_INT_MAX` + range warning | converts | `rejected_by_php` |

Fixed upstream: https://github.com/roelmagdaleno/serialized/pull/2
(branch `feat/escaped-strings-and-benign-warnings`, `composer check` green,
449 tests). Awaiting review, merge and a `v1.0.1` tag.

**To resume:** `composer update roelmagdaleno/serialized`, confirm
`vendor/bin/pest tests/Feature/DiagnosticContractTest.php` still passes its two
"converts values PHP decodes" rows, then start Task 3.

Also on resume: bump the constraint in `composer.json` from `^1.0` to `^1.0.1`,
since the swap depends on the fix.

---

## Phase 2: The swap

## Task 3: Build `DiagnosticTranslator` — DONE

**Description:** Write the adapter that turns a package exception plus its
`Diagnostic` into this app's `ConversionErrorCode` and `SyntaxDiagnostic`. Both
mapping tables from the spec live here. The old engine stays running; nothing
calls this yet.

**Acceptance criteria:**
- [x] `errorCodeFor(SerializedException)` implements spec table 1, including the
      three `LimitExceededException` sub-cases keyed on `DiagnosticCode`
- [x] `translate(Diagnostic, int $payloadBytes)` implements spec table 2 for
      every one of the 27 codes, with no `default` arm that could swallow a new
      package case silently
- [x] `offset` and `offset + length` are always inside the payload
- [x] Every `message` and `suggestion` is built from fixed templates plus
      integers — no value from `context` that can hold a payload byte
      (`prefix` in the `Change s:4 to s:5` suggestion is the one allowed
      exception; its alphabet is closed by the grammar and it is already
      published today)

**Verification:**
- [x] `vendor/bin/pest tests/Unit/DiagnosticTranslatorTest.php` green
- [x] One test per row of both mapping tables
- [x] A truncated payload whose package offset is one past the end clamps to a
      zero-length caret at the last byte
- [x] A payload seeded with a recognizable marker never sees that marker in the
      published message or suggestion

**Dependencies:** Task 1

**Files likely touched:**
- `app/Services/DiagnosticTranslator.php` (new)
- `tests/Unit/DiagnosticTranslatorTest.php` (new)

**Estimated scope:** M

---

## Task 4: Flip `App\Services\Serialized` onto the package — DONE

**Description:** Rewrite the service's internals to run a configured
`SerializedConverter` and route every thrown `SerializedException` through
`DiagnosticTranslator`. Its public shape does not change, so no consumer is
edited. This is where the three known behavior deltas land.

**Acceptance criteria:**
- [x] The converter is configured `->withMaxBytes(MAX_INPUT_BYTES)->withMaxDepth(MAX_DEPTH)`
- [x] `convert()` still returns `ConversionResult { value, json }` and `output()`
      still returns the JSON string
- [x] Every failure still throws `ConversionException` carrying the same
      `ConversionErrorCode` and an optional `SyntaxDiagnostic`
- [x] `MAX_INPUT_BYTES` stays a constant on this class — `PublishedLimitsTest`,
      `ConversionErrorCode::InputTooLarge`, the MCP input schema and the
      telemetry size buckets all read it
- [x] The old scanner classes are now referenced by nothing but their own tests

**Verification:**
- [x] `php artisan test --compact` green **without editing any file listed in the
      spec's "Kept unchanged, must pass untouched"** — if one fails, stop and
      report rather than adjusting it
- [x] `tests/Feature/DiagnosticContractTest.php` passes, or differs only in the
      rows Task 2 marked as known deltas
- [x] A new test pins `JSON_UNESCAPED_UNICODE`: `s:5:"café";` converts to
      `"café"`, not `"café"`

**Dependencies:** Task 3

**Files likely touched:**
- `app/Services/Serialized.php`
- `tests/Feature/DiagnosticContractTest.php` (delta rows only)
- `tests/Unit/UnserializeTest.php` (unicode pin only — flag if more is needed)

**Estimated scope:** M

---

### Checkpoint: The swap
- [x] `php artisan test --compact` fully green — 709 passed
- [x] Every surface runs on the package; the old engine is dead code
- [x] No protected test was edited — `git status` on all eight is clean
- [x] **Reviewed and approved**

---

## Phase 3: Removal and publication

## RESOLVED: `roelmagdaleno/serialized` 1.1.0 shipped

Tasks 5 and 6 are complete. The property test in Task 6 found that `serialize()`
emits `R:` back-references for any value holding a PHP reference, and the package
refused all of them — payloads the site converts today.

Fixed upstream: https://github.com/roelmagdaleno/serialized/pull/3
(branch `feat/resolve-back-references`, `composer check` green, 459 tests).
References now resolve; only a value containing itself is refused.

Merged and tagged. The app is on Packagist `^1.1` and the suite is green.

---

## Task 5: Delete the orphaned engine and the repair prototype — DONE

**Description:** Remove the 14 now-unreferenced files. Pure deletion — no edits,
so a green suite afterwards proves nothing still depended on them.

**Acceptance criteria:**
- [x] Deleted: `SerializedScanner`, `SerializedDiagnostics`, `Scanner/` (6 files),
      `Data/ScanOutcome`, `Data/EngineOutcome`, `Enums/ScanVerdict`,
      `Enums/EngineFailureKind`, `Enums/DiagnosticConfidence`
- [x] Deleted: `app/Services/SerializedRepairer.php`, `app/Data/RepairResult.php`,
      `tests/Unit/SerializedRepairerTest.php`
- [x] `docs/ideas/serialized-package.md` is kept
- [x] `grep -rn 'Scanner\|EngineOutcome\|ScanVerdict\|DiagnosticConfidence' app/`
      returns nothing

**Verification:**
- [x] `php artisan test --compact` green apart from the engine tests Task 6 removes
- [x] `composer dump-autoload` produces no warning

**Dependencies:** Task 4

**Files likely touched:**
- 14 deletions, 0 edits

**Estimated scope:** M (mechanical)

---

## Task 6: Slim `SyntaxDiagnostic` and settle the engine tests — DONE

**Description:** Reduce the DTO to the five fields the app actually publishes,
now that nothing constructs the others. Delete the tests whose subject is gone
and repurpose the differential test onto the property the app still owns.

**Acceptance criteria:**
- [x] `SyntaxDiagnostic` keeps `code`, `offset`, `length`, `message`,
      `suggestion` and `toArray()`; the withers, `claimInterval()`,
      `contextStart`, `expectedTerminatorOffset`, `fix`, `engineOffset` and
      `confidence` are gone
- [x] Deleted: `tests/Unit/SerializedScannerTest.php`,
      `tests/Unit/SerializedDiagnosticsTest.php`,
      `tests/Support/SerializedEngine.php`
- [x] `tests/Unit/SerializedScannerDifferentialTest.php` becomes
      `tests/Unit/DiagnosticTranslatorPropertyTest.php`, keeping
      `SerializedValueGenerator` and `SerializedMutator`, and asserting: for
      every rejected mutation, `offset` and `offset + length` land inside the
      payload and `code` is a declared `SyntaxErrorCode` case

**Verification:**
- [x] `php artisan test --compact` fully green
- [x] `vendor/bin/pest tests/Unit/DiagnosticTranslatorPropertyTest.php` green
      across the full seeded corpus

**Dependencies:** Task 5

**Files likely touched:**
- `app/Data/SyntaxDiagnostic.php`
- `tests/Unit/DiagnosticTranslatorPropertyTest.php` (renamed)
- 3 deletions

**Estimated scope:** M

---

## Task 7: Publish the contract change and verify in the browser — DONE

**Description:** Bump the API version, confirm every published artifact still
tells the truth, and look at the actual error panel — the spec's success
criterion is a rendered page, not a passing assertion.

**Acceptance criteria:**
- [x] `public/openapi.json` `info.version` is `1.1.1`; both schema enums are
      untouched and the diff shows nothing else
- [x] `resources/llms.txt` and the Blade/markdown pages still state the real
      262,144-byte limit
- [x] The MCP output schema still enumerates the same codes it does today

**Verification:**
- [x] `php artisan test --compact` fully green, `PublishedLimitsTest` included
- [x] `vendor/bin/pint --dirty --format agent` clean
- [x] Manual: at `https://unserialize.test`, submitting
      `a:1:{s:4:"name";s:6:"Chrom";}` renders a `string_length_mismatch` panel
      with a byte offset, a highlighted span and a `Change ... to ...` suggestion
- [x] Manual: a valid payload still converts and the copy button still works

**Dependencies:** Task 6

**Files likely touched:**
- `public/openapi.json`

**Estimated scope:** S

---

### Checkpoint: Complete
- [x] Every spec success criterion met
- [ ] Behavior deltas named in the commit message — pending the commit
- [x] `git diff --stat` shows a net deletion of roughly 2,400 lines
- [x] Ready for review
