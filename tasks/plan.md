# Implementation Plan: Replace the conversion engine with `roelmagdaleno/serialized`

Spec: `docs/specs/SPEC-serialized-package-swap.md` (approved)
Tasks: `tasks/todo.md`

## Overview

Swap this application's hand-written PHP-serialization engine for the
`roelmagdaleno/serialized` package, keeping all three surfaces (browser panel,
HTTP API v1, MCP tool) behaviorally identical. The work is a single capability,
so there is no capability map: one spec, one plan, seven tasks.

## Architecture Decisions

- **The adapter is the whole design.** `DiagnosticTranslator` is the only new
  class. It converts a package exception plus its `Diagnostic` into the
  `ConversionErrorCode` + `SyntaxDiagnostic` pair the app already publishes.
  Every mapping decision from the spec lives in that one file, so the contract
  has exactly one place to drift from.

- **`App\Services\Serialized` survives as the chokepoint.** Every surface already
  goes through it and catches `ConversionException`. Keeping its public shape
  (`convert()`, `output()`, `MAX_INPUT_BYTES`, `MAX_DEPTH`) means Livewire, the
  API controller and the MCP tool need no edit at all — which is also what makes
  their tests a real regression net rather than tests we quietly rewrote.

- **Build the translator before flipping the engine, delete after.** The new
  adapter and the old scanner coexist for two tasks. That keeps the suite green
  at every step and makes the flip itself a small, reviewable diff instead of a
  rewrite tangled up with 2,437 lines of deletion.

- **`SyntaxDiagnostic` is slimmed last.** The old `SyntaxDiagnosticFactory`
  constructs it with fields the package has no equivalent for (`contextStart`,
  `fix`, `engineOffset`, `confidence`). Slimming it before the old engine is gone
  would break the old engine; slimming it after is a mechanical edit.

- **A golden contract test is written first, against the current engine.** Task 2
  pins today's published diagnostic for a fixed corpus of broken payloads. It is
  how the translator is proven faithful in Task 4 rather than merely plausible.

## Dependency Graph

```
T1  composer require roelmagdaleno/serialized
      │
T2  golden contract test (pins TODAY's output, old engine still running)
      │
T3  DiagnosticTranslator + unit tests   ← the adapter, built alongside the old engine
      │
T4  flip App\Services\Serialized to the package   ← behavior deltas surface HERE
      │
      ├── T5  delete the orphaned engine + repair prototype
      │         │
      │    T6  slim SyntaxDiagnostic + delete/repurpose the engine tests
      │
      └── T7  published artifacts (openapi 1.1.1) + browser verification
```

Bottom-up: nothing is deleted until the thing that replaced it is proven.

## Task List

### Phase 1: Foundation (green at every step, no behavior change)
- [ ] Task 1: Install `roelmagdaleno/serialized` from Packagist
- [ ] Task 2: Pin today's diagnostic contract with a golden test

### Checkpoint: Foundation

### Phase 2: The swap (behavior changes land here)
- [ ] Task 3: Build `DiagnosticTranslator`
- [ ] Task 4: Flip `App\Services\Serialized` onto the package

### Checkpoint: The swap

### Phase 3: Removal and publication
- [ ] Task 5: Delete the orphaned engine and the repair prototype
- [ ] Task 6: Slim `SyntaxDiagnostic` and settle the engine tests
- [ ] Task 7: Publish the contract change and verify in the browser

### Checkpoint: Complete

## Risks and Mitigations

| Risk | Impact | Mitigation |
|---|---|---|
| A package diagnostic maps to a span that falls outside the payload, and `DiagnosticPresenter` indexes past the end | High — a 500 on the error path, which is the path users hit when something is already wrong | Clamp inside the translator (spec'd); Task 3 unit-tests the one-past-the-end truncated case; Task 6's property test runs the clamp across the mutated corpus |
| The three behavior deltas are wider than the probe showed, and a kept test fails | High — signals the mapping is wrong, not the test | Task 2's golden test makes the diff explicit before the flip; any kept test that fails in Task 4 stops the work for review rather than being edited |
| A published message leaks payload bytes through `context` (`prefix`, `literal`, `className`) | High — breaks a stated privacy promise on the API and MCP tool | Translator writes every sentence from fixed templates plus integers; Task 3 asserts it against a payload seeded with a marker |
| `JSON_UNESCAPED_UNICODE` changes output for existing users | Low — visible, but an improvement | Pinned by a test in Task 4 and named in the commit message |
| `MaxElementsExceeded` is unreachable today but becomes reachable if the byte limit ever rises | Low | Mapped defensively in the translator rather than left to `match` exhaustion |
| Deleting `tests/Support/SerializedEngine.php` loses the differential guarantee | Medium | The guarantee moves to the package's own suite; the corpus infrastructure is kept and repurposed in Task 6 to prove the property the app still owns |

## Definition of Done (every task clears this)

- `vendor/bin/pint --dirty --format agent` is clean
- The narrowest relevant test command passes
- No file listed in the spec's "Kept unchanged, must pass untouched" was edited
- Docblocks follow `.ai/rules/app.md`: one present-tense sentence, no history,
  no restating the signature

## Open Questions

None. Both spec questions were resolved: Packagist from the start, and
`public/openapi.json` bumps to `1.1.1`.
