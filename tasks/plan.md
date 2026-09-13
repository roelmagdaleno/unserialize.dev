# Implementation Plan: Laravel Boost Compliance Remediation

## Overview

Remediate the security, correctness, testing, version-guidance, and style findings from the Laravel Boost audit. The work is ordered so exploit prevention lands first, every behavior change is driven by a regression test, and the application remains usable after each task.

## Scope and Assumptions

- The converter remains anonymous and publicly accessible.
- Existing output URLs remain shareable without authentication.
- No new Composer or npm dependencies are required.
- No database schema change is required.
- Proposed defaults are a 256 KiB serialized-input limit and 10 conversion attempts per minute per IP address.
- Production rate-limit counters must use a shared cache store if the application runs on multiple instances.

## Architecture Decisions

- Keep conversion logic in `App\Services\Serialized`, but make one safe decoding path responsible for validation and conversion.
- Call PHP `unserialize()` with classes disabled so user input cannot instantiate application or vendor classes.
- Escape the original serialized value with normal Blade output. Keep syntax-highlighted output raw only because the highlighter produces escaped, trusted markup; cover that boundary with a regression test.
- Apply the rate limit at the Livewire action boundary because every conversion, including invalid attempts, consumes server resources.
- Use Laravel 13's `RateLimiter` API and Livewire/Pest test helpers confirmed through Boost `search-docs`.
- Use `LazilyRefreshDatabase` for database-backed feature tests, matching the installed Laravel testing guidance while avoiding unnecessary migrations for tests that do not query the database.

## Dependency Graph

```text
Boost guidance sync
        |
        v
Safe deserialization and conversion correctness
        |
        +----------> Escaped rendering
        |
        +----------> Input bounds and throttling
                            |
                            v
                 Test-suite reliability cleanup
                            |
                            v
                   Naming and style cleanup
                            |
                            v
                    Final quality checkpoint
```

## Task List

### Phase 1: Align and secure the core

- [ ] Task 1: Refresh Boost guidance for Laravel 13.
- [ ] Task 2: Make deserialization safe and correct.
- [ ] Task 3: Escape stored user input on output pages.

### Checkpoint: Security baseline

- [ ] Focused service and output-page tests pass.
- [ ] Serialized objects cannot instantiate classes.
- [ ] A script payload is absent from rendered HTML in executable form.

### Phase 2: Bound abuse and strengthen tests

- [ ] Task 4: Bound and throttle public conversions.
- [ ] Task 5: Repair test isolation and ineffective assertions.

### Checkpoint: Behavioral reliability

- [ ] Valid conversions still save and redirect to the named output route.
- [ ] Invalid, oversized, and throttled submissions do not create records.
- [ ] Feature tests pass independently and in randomized order.

### Phase 3: Conventions and finish

- [ ] Task 6: Apply Laravel naming and PHP formatting conventions.
- [ ] Task 7: Run the complete Boost-aligned quality gate.

### Checkpoint: Complete

- [ ] All acceptance criteria in `tasks/todo.md` are satisfied.
- [ ] Full test suite, formatter, build, Composer validation, and dependency audit pass.
- [ ] Final diff contains no unrelated dependency, schema, or feature changes.

## Risks and Mitigations

| Risk | Impact | Mitigation |
| --- | --- | --- |
| Disabling serialized classes changes behavior for users submitting objects | Medium | Preserve scalar and array support; document and test the intentional rejection or inert handling of objects before release. |
| An IP-only rate limit affects users behind shared networks | Medium | Start with a moderate configurable limit, use clear feedback, and review production telemetry before tightening it. |
| A local cache makes throttling ineffective across multiple instances | High | Require a shared production cache store and verify it through configuration before deployment. |
| Escaping changes the visual representation of serialized HTML strings | Low | Assert the page displays the original text while preventing browser interpretation. |
| Updating generated Boost guidance changes more files than expected | Low | Run `boost:update` separately and review its diff before application-code changes. |

## Open Questions

- Confirm the proposed 256 KiB input cap before Task 4 is implemented.
- Confirm the proposed limit of 10 conversion attempts per minute per IP before Task 4 is implemented.
- Decide whether serialized objects should be rejected with validation feedback or accepted only as inert `__PHP_Incomplete_Class` values. Rejection is recommended for this array/JSON converter.

