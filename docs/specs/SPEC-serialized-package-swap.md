# Spec: Replace the in-app conversion engine with `roelmagdaleno/serialized`

## Objective

Delete this application's hand-written PHP-serialization engine (~3,500 lines
across `app/Services`, `app/Data`, `app/Enums`) and run every conversion through
`roelmagdaleno/serialized` v1.0.0 instead. The application keeps its job: taking
a payload, showing JSON or explaining exactly where the payload broke, on three
surfaces (browser, HTTP API v1, MCP tool).

**User:** unchanged. A developer pasting broken serialized data must still see
the same error panel — message, byte offset, highlighted span, suggestion.

**Success looks like:** the app owns no serialization grammar, every published
contract (`public/openapi.json`, the MCP output schema, the browser panel) is
byte-for-byte what it is today, and the full test suite passes.

### Assumptions (confirmed with the user)

1. The API/MCP wire contract does **not** change. Package `DiagnosticCode` (27
   cases) is mapped down to the existing `SyntaxErrorCode` (11 cases).
2. `diagnostic.length` stays in the contract, derived app-side from the
   package's `code` + `context`.
3. Diagnostic sentences are **written by this app** from `code` + `context`.
   The package's own `reason`/`fix` are never published, because they
   interpolate bytes from the submitted payload and this app promises on the API
   and in the MCP tool description that no submitted bytes are returned.
4. The uncommitted repair prototype (`app/Services/SerializedRepairer.php`,
   `app/Data/RepairResult.php`, `tests/Unit/SerializedRepairerTest.php`) is
   deleted. `docs/ideas/serialized-package.md` is kept as the record.
5. The dependency is taken from Packagist (`^1.0`), not a path repository.

## Tech Stack

- PHP 8.4, Laravel 13, Livewire 3 + Flux 2, Pest 5
- **New:** `roelmagdaleno/serialized` `^1.0` — PHP ^8.4, zero runtime
  dependencies, requires `ext-json` and `ext-mbstring`

## Commands

```
Install:  composer require roelmagdaleno/serialized:^1.0
Test:     php artisan test --compact
Narrow:   vendor/bin/pest tests/Unit/DiagnosticTranslatorTest.php
Lint:     vendor/bin/pint --dirty --format agent
Build:    npm run build
Dev:      composer run dev
Serve:    https://unserialize.test (Herd, always up)
```

## Project Structure

### Deleted (2,437 lines of engine)

```
app/Services/SerializedScanner.php
app/Services/SerializedDiagnostics.php
app/Services/Scanner/ScannerCursor.php
app/Services/Scanner/SyntaxDiagnosticFactory.php
app/Services/Scanner/TokenReader.php
app/Services/Scanner/ValueParser.php
app/Services/Scanner/Rules/{Container,Opaque,Scalar,String}Rules.php
app/Data/ScanOutcome.php
app/Data/EngineOutcome.php
app/Enums/ScanVerdict.php
app/Enums/EngineFailureKind.php
app/Enums/DiagnosticConfidence.php
app/Services/SerializedRepairer.php          (uncommitted prototype)
app/Data/RepairResult.php                    (uncommitted prototype)
```

### Rewritten

```
app/Services/Serialized.php                  → thin wrapper over a configured
                                               SerializedConverter; keeps
                                               MAX_INPUT_BYTES, MAX_DEPTH,
                                               convert(), output(), and throws
                                               ConversionException
app/Data/SyntaxDiagnostic.php                → slimmed to the five published
                                               fields; the withers, claim
                                               interval and confidence
                                               machinery go
```

### New

```
app/Services/DiagnosticTranslator.php        → the whole adapter: package
                                               exception + Diagnostic
                                               → ConversionErrorCode +
                                                 SyntaxDiagnostic
```

### Unchanged

```
app/Services/DiagnosticPresenter.php         (windowing + byte sanitizing)
app/Enums/{SyntaxErrorCode,ConversionErrorCode,ConversionOutcome}.php
app/Exceptions/ConversionException.php
app/Data/{ConversionResult,ConversionEnvelope}.php
app/Livewire/**, app/Http/**, app/Mcp/**     (consume ConversionException only)
public/openapi.json, resources/llms.txt      (no contract change)
```

## Code Style

The adapter is a pure function of the package's own stable outputs — no payload
bytes read, no grammar re-implemented:

```php
/**
 * Restates one package diagnostic in this application's published vocabulary.
 *
 * The package's own `reason` and `fix` sentences are deliberately not used: they
 * interpolate bytes taken from the submitted payload, and both the HTTP API and
 * the MCP tool promise that no submitted bytes are returned. Every sentence here
 * is assembled from fixed templates plus integers drawn from `context`.
 */
public function translate(Diagnostic $diagnostic, int $payloadBytes): SyntaxDiagnostic
{
    $offset = max(0, min($diagnostic->offset, $payloadBytes));

    return new SyntaxDiagnostic(
        $this->codeFor($diagnostic->code),
        $offset,
        $this->spanFor($diagnostic, $payloadBytes),
        $this->messageFor($diagnostic),
        $this->suggestionFor($diagnostic),
    );
}
```

Project conventions apply as written in `.ai/rules/app.md`: a docblock on every
class, method, property and constant; one present-tense sentence that never
restates the signature; no history; `{@see}` over prose.

## The two mapping tables

### 1. Package exception → `ConversionErrorCode`

| Package exception | `DiagnosticCode` | App error code | Publishes a diagnostic |
|---|---|---|---|
| `LimitExceededException` | `MaxBytesExceeded` | `input_too_large` | no |
| `LimitExceededException` | `MaxDepthExceeded` | `depth_limit_exceeded` | yes |
| `LimitExceededException` | `MaxElementsExceeded` | `invalid_input` | yes |
| `UnsafeSerializedDataException` | `DisallowedClass`, `UnloadableClass`, `UnrestorableClass` | `unsupported_object` | no |
| `UnsafeSerializedDataException` | `ContainsReference` | `invalid_input` | yes |
| `UnrepresentableValueException` | any | `encoding_failed` | no |
| `InvalidSerializedDataException` | any | `invalid_input` | yes |
| `JsonEncodingException` | — (none) | `encoding_failed` | no |

"Publishes a diagnostic: no" reproduces today's behavior exactly — the app has
never attached a diagnostic to `unsupported_object`, `input_too_large` or
`encoding_failed`.

### 2. `DiagnosticCode` → `SyntaxErrorCode` and span

| `DiagnosticCode` | `SyntaxErrorCode` | `length` |
|---|---|---|
| `EmptyPayload` | `unexpected_end` | 0 |
| `TruncatedPayload` | `unexpected_end` | 0 |
| `UnclosedStructure` | `unexpected_end` | 0 |
| `UnknownTypePrefix` | `unknown_type_marker` | 1 |
| `UnexpectedByte` | `missing_terminator` when `expected` is `;`, else `missing_delimiter` | 1 |
| `MalformedValue` | `malformed_number` | `strlen(literal)` |
| `MalformedLength` | `malformed_number` | `strlen(literal)` |
| `MalformedElementCount` | `malformed_number` | `strlen(literal)` |
| `ElementCountMismatch` | `array_count_mismatch` | structure header width |
| `ImpossibleElementCount` | `array_count_mismatch` | `strlen(declaredCount)` |
| `NonScalarKey` | `invalid_array_key` | 0 |
| `LengthMismatch` | `string_length_mismatch` | `foundByteLength` |
| `TrailingBytes` | `trailing_data` | `payloadBytes - offset` |
| `UnbalancedClose` | `unknown_syntax_error` | 1 |
| `RejectedByPhp` | `unknown_syntax_error` | 0 |
| `ValueOverrunsDeclaredLength` | `unknown_syntax_error` | 0 |
| `UnusablePropertyName` | `unknown_syntax_error` | 0 |
| `ContainsReference` | `unknown_syntax_error` | 0 |
| `MaxDepthExceeded` | `depth_limit_exceeded` | 0 |
| `MaxElementsExceeded` | `unknown_syntax_error` | 0 |
| `NonUtf8String`, `NonBackedEnum`, `NonFiniteFloat`, `MaxBytesExceeded`, `DisallowedClass`, `UnloadableClass`, `UnrestorableClass` | not reached — these codes ride error paths that publish no diagnostic | — |

Every `offset` and `offset + length` is clamped inside the payload before it
leaves the translator, so `DiagnosticPresenter` can index the payload without
bounds-checking again. The package legitimately reports `offset === strlen($payload)`
for a truncated payload, which the clamp turns into a zero-length caret at the end.

## Converter configuration

```php
Serialized::make()
    ->withMaxBytes(self::MAX_INPUT_BYTES)   // 262,144 — the published limit
    ->withMaxDepth(self::MAX_DEPTH)         // 512
    ->withJsonFlags(0);                     // package default: pretty,
                                            // unescaped slashes + unicode
```

Two notes: `MAX_INPUT_BYTES` stays a constant on `App\Services\Serialized`
because `PublishedLimitsTest`, `ConversionErrorCode::InputTooLarge`, the MCP
input schema and the telemetry size buckets all read it. The package's
`JSON_UNESCAPED_UNICODE` is new — today's output escapes non-ASCII. This is an
accepted, visible improvement, pinned by a test.

## Testing Strategy

Pest 5. Feature tests for the three surfaces, unit tests for the adapter.

### Deleted

| File | Why |
|---|---|
| `tests/Unit/SerializedScannerTest.php` | The grammar it tested is gone |
| `tests/Unit/SerializedDiagnosticsTest.php` | Reconciliation with PHP's offset is the package's job now |
| `tests/Unit/SerializedRepairerTest.php` | Prototype deleted |
| `tests/Support/SerializedEngine.php` | Only consumer of the deleted `EngineOutcome` |

### Repurposed

`tests/Unit/SerializedScannerDifferentialTest.php` becomes
`tests/Unit/DiagnosticTranslatorPropertyTest.php`. The corpus infrastructure
(`SerializedValueGenerator`, `SerializedMutator`) is kept, and the property it
proves changes from *"the scanner agrees with PHP"* — now the package's own
guarantee — to the one this app still owns:

> For every mutated payload the converter rejects, the published diagnostic's
> `offset` and `offset + length` both land inside the payload, and its `code` is
> a declared `SyntaxErrorCode` case.

### New

`tests/Unit/DiagnosticTranslatorTest.php` — one case per row of both mapping
tables, plus:

- no published `message` or `suggestion` contains a byte from the submitted
  payload outside the closed grammar alphabet (the privacy contract, asserted
  against a payload seeded with a recognizable marker)
- `offset` and `length` are clamped for a truncated payload whose package offset
  is one past the end

### Kept unchanged, must pass untouched

`tests/Unit/{UnserializeTest,DiagnosticPresenterTest,ConversionTelemetryTest}.php`,
`tests/Feature/{UnserializeTest,UnserializeApiTest,McpUnserializeTest,PublishedLimitsTest,UsageEventTest}.php`.
These are the regression net: if the swap is faithful, they do not move. Any
edit to one of them is a contract change and needs saying out loud.

## Boundaries

**Always**
- Run `vendor/bin/pint --dirty --format agent` before finishing
- Keep `public/openapi.json`, `resources/llms.txt` and the MCP output schema in
  sync with `SyntaxErrorCode` and `ConversionErrorCode`
- Write every diagnostic sentence from fixed templates plus integers

**Ask first**
- Editing any test listed under "Kept unchanged"
- Changing `SyntaxErrorCode`, `ConversionErrorCode` or `ConversionOutcome` cases
- Adding a second dependency, or switching to a path repository
- A database migration (none is expected; `usage_events.diagnostic` is
  `string(50)` and every code fits)

**Never**
- Publish the package's `reason` or `fix` verbatim on the API or MCP surfaces
- Publish `Diagnostic::$payload`, which holds the whole submitted value
- Re-implement any part of the serialization grammar in this app
- Delete a test without approval

## Success Criteria

1. `composer show roelmagdaleno/serialized` reports an installed `^1.0`.
2. `grep -r 'Scanner\|EngineOutcome\|ScanVerdict' app/` returns nothing.
3. `php artisan test --compact` is green with no edit to any file under
   "Kept unchanged, must pass untouched".
4. The only change to `public/openapi.json` is `info.version` moving from
   `1.1.0` to `1.1.1`. Both schema enums are untouched.
5. The browser panel for `a:1:{s:4:"name";s:6:"Chrom";}` still renders a
   `string_length_mismatch`, a byte offset, a highlighted span and a
   `Change ... to ...` suggestion — verified in the browser, not only in tests.
6. A 262,145-byte payload still returns HTTP 413 `input_too_large`; a 262,144-byte
   one is accepted.
7. No published diagnostic `message` or `suggestion` contains a byte taken from
   the submitted payload.

## Known behavior changes

Three, all deliberate. Each needs a line in the commit message.

1. **`R:` array references are now rejected.** `a:2:{i:0;a:0:{}i:1;R:2;}`
   converts today and will return `invalid_input` after the swap. The package
   refuses references because JSON cannot represent them, and this app already
   rejects the `r:` value form. Narrow, and the more honest answer.
2. **Non-ASCII output is no longer escaped.** `JSON_UNESCAPED_UNICODE` is a
   package default. `"café"` becomes `"café"`.
3. **Some diagnostics move between codes.** `E:` enum payloads report
   `unsupported_object` instead of `invalid_input`; trailing bytes after a
   complete value may report `unknown_type_marker` where the old scanner said
   `trailing_data`. Both remain declared cases of the published enums, so no
   client contract breaks.

## Resolved Questions

- **Dependency source: Packagist.** `composer require roelmagdaleno/serialized:^1.0`
  from the start. No `path` repository, so the adapter is written against the
  same release every other consumer gets.
- **API version: bump to `1.1.1`.** Behavior change #1 (`R:` references now
  rejected) is user-visible, so `info.version` in `public/openapi.json` moves to
  `1.1.1`. A patch bump, not a minor: no schema changes, no field added or
  removed, and every published enum keeps its exact case list.
