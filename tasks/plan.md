# Implementation Plan: reporte de salud del código + refactor de mantenibilidad

## Contexto

`app/Services/SerializedScanner.php` (902 líneas) disparó la pregunta, pero el escaneo completo encontró que el scanner **no** es el peor problema de mantenibilidad. Es un parser recursivo descendente cohesivo, muy bien documentado, y blindado por ~269 casos de test (incluida una suite diferencial de 25 semillas y un test de rendimiento). Su problema es de *forma*, no de arquitectura: cada método arrastra `string $data, int $length, int &$position` y, en los compuestos, `int $depth, bool &$unverifiable`; y ~22 construcciones inline de `SyntaxDiagnostic` mezclan copy en inglés con la lógica de gramática.

Los problemas que sí producen bugs de sincronización están repartidos entre las tres superficies (Browser / HTTP API / MCP):

- Bloque rate-limit + telemetría **triplicado**: `AppServiceProvider.php:39-63`, `:65-93`, `Livewire/Serialized.php:117-146`.
- `outcome` como **string suelto** en 5 archivos (`'success'`, `'rate_limited'`, `'validation_error'`), mientras `ConversionMetric::OUTCOME_SUCCESS` (`app/Models/ConversionMetric.php:23`) existe sin usarse.
- `262144` escrito a mano en 12+ lugares; solo `ConvertSerializedDataTool.php:142` usa la constante.
- Sobres de respuesta duplicados byte a byte: `UnserializeController.php:55-87` vs `ConvertSerializedDataTool.php:108-130`.
- Dos topes de profundidad divergentes: `Serialized::MAX_DEPTH = 512` vs `SerializedScanner::MAX_DEPTH = 1024`.
- `SyntaxDiagnostic` con 4 witters que relistan los 10 argumentos del constructor cada uno.
- `App\Rules\SerializedRule` sin ninguna referencia en el repo → `Serialized::isValid()` es código muerto.

Resultado esperado: un `REFACTOR-REPORT.md` en el root que permita decidir, y un refactor por etapas donde cada tarea deja el sistema funcionando y sin cambiar un solo byte de comportamiento observable.

## Architecture Decisions

**1. El scanner se queda como UNA sola clase de gramática.** Se descartó dividir en clases por tipo (`StringParser`, `ArrayParser`, …): los primitivos compartidos (`delimiter`, `terminator`, `unsignedDigits`, `quotedPayload`) los consumen 6 de 11 producciones, la recursión mutua `arrayValue → value → arrayValue` obligaría a un dispatcher con indirección en ruta caliente, y re-cortar cada frontera de método es exactamente donde vive el comportamiento de offsets exactos. Queda en ~480 líneas conservando su docblock de clase, que declara garantías transversales (containment, validación solo-de-forma de `E:`/`C:`/`r:`/`R:`, la salvedad de la búsqueda de `";`).

**2. Dos extracciones, en fases independientes y commiteables por separado.** El copy sale primero (≈90% de la ganancia de legibilidad, ≈10% del riesgo) y no queda rehén de la mitad riesgosa.

**3. `ScannerCursor` es mutable y con `fork()` explícito.** Hoy `surplusElements()` (línea 558) recibe `int $position` y `bool $unverifiable` **por valor** y avanza un local: es un lookahead especulativo cuyo avance y cuyas escrituras se descartan a propósito. Un cursor compartido haría desaparecer ese descarte en silencio. `fork()` vuelve explícita la semántica de hoy.

**4. La factory recibe solo `int` y escalares, nunca el cursor.** Pasar el cursor permitiría leer una posición ya avanzada — así se rompe un test de offset exacto.

**5. `$depth` NO entra al cursor.** Es per-recursión, no estado compartido; dejarlo como parámetro evita desenrollarlo en cada return.

**6. Carpeta `app/Services/Scanner/`** (aprobada). `app/Data/` es uniformemente `readonly` y el cursor es mutable, así que no pertenece ahí.

## Task List

> Las tareas se materializan en `tasks/todo.md` y este documento en `tasks/plan.md` como primer paso (Tarea 0). No existe `tasks/` ni ningún plan previo — verificado.

### Phase 0: Entregables y red de seguridad

---

#### Tarea 0: Materializar `tasks/plan.md` y `tasks/todo.md`

**Description:** Crear `tasks/` y volcar este plan y su checklist, para que el estado sobreviva a los límites de sesión.

**Acceptance criteria:**
- [ ] `tasks/plan.md` contiene este documento
- [ ] `tasks/todo.md` contiene las tareas 1-18 y los checkpoints como checklist

**Verification:** `ls tasks/` muestra ambos archivos.
**Dependencies:** None · **Scope:** XS

---

#### Tarea 1: Escribir `REFACTOR-REPORT.md`

**Description:** El entregable que pediste. Tabla por archivo más las secciones que permiten decidir qué no tocar.

**Acceptance criteria:**
- [ ] Tabla con columnas `Archivo · Líneas · Por qué debe mejorar · Cómo mejorarlo · Arquitectura propuesta · Riesgo · Esfuerzo · Cobertura actual`, con fila para: `SerializedScanner`, `Serialized`, `SerializedDiagnostics`, `DiagnosticPresenter`, `ConversionTelemetry`, `UsageMetadataNormalizer`, `SyntaxDiagnostic`, `UsageContext`, `AppServiceProvider`, `Livewire/Serialized`, `UnserializeController`, `ConvertSerializedDataTool`, `SerializedRule`, `ConversionErrorCode`
- [ ] Sección **"Qué NO tocar y por qué"**: los docblocks narrativos del scanner; la política de reconciliación de `SerializedDiagnostics`; y las inconsistencias deliberadas de `contextStart` (`arrayKey()` pasa `$contextStart` pero `integer()`/`string()` lo ignoran; `customObject` usa `length: 1` fijo) — sostienen la propiedad de *containment* que verifica la suite diferencial
- [ ] Sección **"Contratos publicados"**: los valores de `SyntaxErrorCode` viven también en `public/openapi.json:125` y en el schema MCP; los strings exactos de mensaje/sugerencia están copiados en `public/openapi.json:183` y en `resources/views/developers.blade.php`

**Verification:** Lectura humana. Ningún test.
**Dependencies:** None · **Scope:** S (1 archivo)

---

#### Tarea 2: Línea base de caracterización ("golden")

**Description:** Los tests commiteados afirman ~41 strings exactos, pero la suite diferencial afirma *propiedades*, no copy: un argumento de `sprintf` intercambiado en una rama poco visitada pasaría ambas. Esta línea base cierra ese hueco. **Es la red de seguridad de todo el refactor — va primero, sobre HEAD limpio.**

Script PHP desechable en el scratchpad que recorra `Tests\Support\SerializedValueGenerator(seed)->corpus(30)` × `SerializedMutator->mutations()` para las semillas 1-25 e imprima, por mutación, `md5(serialize([$outcome->verdict, $outcome->consumedBytes, $d?->code, $d?->offset, $d?->length, $d?->message, $d?->suggestion, $d?->contextStart, $d?->expectedTerminatorOffset, $d?->fix]))`. `Tests\Support\*` está bajo PSR-4 en `autoload-dev`, así que un script plano lo carga.

**Acceptance criteria:**
- [ ] `golden.txt` generado en el scratchpad, con una línea por mutación
- [ ] Re-ejecutarlo sobre HEAD sin cambios produce un `diff` vacío (determinismo confirmado)
- [ ] Tiempo base del test de rendimiento registrado en el mismo directorio

**Verification:** `diff golden.txt <(php scratchpad/golden.php)` → vacío, dos veces seguidas.
**Dependencies:** None · **Scope:** S (1 archivo, desechable)

---

### ✅ Checkpoint A — Red de seguridad lista
- [ ] `vendor/bin/pest` completo en verde sobre HEAD (219 declaraciones)
- [ ] `golden.txt` reproducible
- [ ] Reporte revisado por humano **antes** de tocar código

---

### Phase 1: Scanner — sacar el copy de la gramática (riesgo bajo)

#### Tarea 3: Crear `SyntaxDiagnosticFactory` (sin usar)

**Description:** `app/Services/Scanner/SyntaxDiagnosticFactory.php`, `readonly class` sin estado, ~22 métodos nombrados por la *condición*: `emptyValue`, `depthLimitExceeded`, `unknownTypeMarker`, `trailingData`, `nonBinaryBoolean`, `integerWithoutDigits`, `floatWithoutDigits`, `floatExponentWithoutDigits`, `nonNumericStringLength`, `malformedEscape`, `unterminatedString`, `stringLengthMismatch`, `nonNumericElementCount`, `arrayShorterThanDeclared`, `arrayLongerThanDeclared`, `invalidArrayKey`, `nonNumericPropertyCount`, `nonNumericPayloadLength`, `referenceWithoutTarget`, `referenceToNothing`, `missingByte`, `unexpectedEnd`. Uno por cada construcción inline en las líneas 54, 75, 96, 121, 154, 192, 245, 270, 318, 353, 419, 451, 481, 503, 591, 645, 682, 739, 803, 822, 851, 868.

**Acceptance criteria:**
- [ ] Los `sprintf` se copian **literales**, sin reflow ni reordenar argumentos
- [ ] `suggestion` y `fix` se construyen en el **mismo** método (la suite diferencial exige que toda sugerencia publicada traiga un `fix` que demostrablemente cambie la queja)
- [ ] El docblock de clase prohíbe explícitamente recibir el cursor
- [ ] Constructor del scanner con default para no romper `new SerializedScanner` (`Serialized.php:39` + 3 archivos de test): `private readonly SyntaxDiagnosticFactory $diagnostics = new SyntaxDiagnosticFactory`

**Verification:** `vendor/bin/pest tests/Unit/SerializedScannerTest.php tests/Unit/SerializedScannerDifferentialTest.php tests/Unit/SerializedDiagnosticsTest.php` + `vendor/bin/pint --dirty --format agent`. Verde trivial; confirma autoload y estilo.
**Dependencies:** Tarea 2 · **Scope:** S (2 archivos)

---

#### Tarea 4: Migrar call sites — escalares

**Description:** `scan()`, `value()` y los escalares: líneas 54, 75, 96, 121, 154, 192, 245, 270.

**Acceptance criteria:**
- [ ] Ninguna cadena de mensaje queda en esos métodos
- [ ] Docblocks narrativos intactos

**Verification:** los 3 archivos de test + `diff` de golden vacío.
**Dependencies:** Tarea 3 · **Scope:** S

---

#### Tarea 5: Migrar call sites — strings

**Description:** Líneas 318, 353, 419, 451. `lengthMismatch()` **se queda** como método del scanner (la búsqueda `strpos($data, '";', …)` es lógica de gramática, no copy) y delega sus dos formas de mensaje a `unterminatedString()` / `stringLengthMismatch()`.

**Acceptance criteria:**
- [ ] `lengthMismatch()` conserva su docblock narrativo y su lógica de búsqueda
- [ ] La sugerencia ``Change `s:4:` to `s:5:`.`` sigue idéntica

**Verification:** los 3 archivos de test + `diff` de golden vacío.
**Dependencies:** Tarea 4 · **Scope:** S

---

#### Tarea 6: Migrar call sites — arrays, objetos, referencias, helpers

**Description:** Líneas 481, 503, 591, 645, 682, 739, 803, 822, 851, 868.

**Acceptance criteria:**
- [ ] `grep -c 'new SyntaxDiagnostic' app/Services/SerializedScanner.php` → `0`
- [ ] La factory produce los 9 `SyntaxErrorCode` que exige el piso de cobertura diferencial (verificar por grep, no por confianza: un método escrito pero nunca cableado deja una ruta muerta que el test puede no detectar)

**Verification:** los 3 archivos de test + `diff` de golden vacío.
**Dependencies:** Tarea 5 · **Scope:** M (2 archivos, muchos puntos)

---

### ✅ Checkpoint B — Copy centralizado (punto de commit fuerte)
- [ ] `vendor/bin/pest` completo verde
- [ ] `diff` de golden vacío
- [ ] `vendor/bin/pint --test`
- [ ] **Commit.** Si la Fase 2 sale mal, esta ganancia ya está asegurada.

---

### Phase 2: Scanner — cursor explícito (riesgo medio)

#### Tarea 7: Crear `ScannerCursor` (sin usar)

**Description:** `app/Services/Scanner/ScannerCursor.php`, mutable, propiedades públicas (sin getters, para que los bucles por byte puedan hoistear a locales):

```php
class ScannerCursor
{
    public int $position = 0;
    public bool $unverifiable = false;

    public function __construct(public readonly string $data, public readonly int $length) {}

    public function atEnd(): bool;      // position >= length
    public function current(): string;  // data[position]
    public function fork(): self;       // copia de data/length/position/unverifiable
}
```

**Acceptance criteria:**
- [ ] El docblock de `fork()` dice que las escrituras a un fork se descartan a propósito y nombra `surplusElements()` como la razón

**Verification:** los 3 archivos de test + Pint.
**Dependencies:** Checkpoint B · **Scope:** XS

---

#### Tarea 8: Cablear cursor — primitivos y escalares

**Description:** Hojas primero: `unsignedDigits`, `delimiter`, `terminator`, `unexpectedEnd`; luego `nullValue`, `boolean`, `integer`, `double`.

```php
// antes
private function boolean(string $data, int $length, int &$position): ?SyntaxDiagnostic
// después
private function boolean(ScannerCursor $cursor): ?SyntaxDiagnostic
```

**Acceptance criteria:**
- [ ] Se escribe `$cursor->position` **antes** de construir cualquier diagnóstico, y nunca se hoistea un local a través de una construcción. Varios diagnósticos leen la posición *después* de avanzar parcialmente: `integer` usa `max(1, $position - $digitsStart)`; `reference` usa `$position - $start` tras consumir el terminador

**Verification:** los 3 archivos de test + `diff` de golden vacío.
**Dependencies:** Tarea 7 · **Scope:** M

---

#### Tarea 9: Cablear cursor — strings

**Description:** `string`, `quotedPayload`, `lengthMismatch`.

```php
// antes
private function quotedPayload(string $data, int $length, int &$position, int $tokenStart, bool $escaped, ?string $fixPrefix): ?SyntaxDiagnostic
// después
private function quotedPayload(ScannerCursor $cursor, int $tokenStart, bool $escaped, ?string $fixPrefix): ?SyntaxDiagnostic
```

**Acceptance criteria:**
- [ ] **Colisión de nombre resuelta**: la rama escapada ya tiene un local llamado `$cursor` (línea 341) → renombrar a `$scan`. Silencioso, solo lo ejercitan payloads `S:`
- [ ] Un caso `S:` con escapes pasa explícitamente

**Verification:** los 3 archivos de test + `diff` de golden vacío.
**Dependencies:** Tarea 8 · **Scope:** S

---

#### Tarea 10: Cablear cursor — compuestos

**Description:** `arrayKey`, `objectValue`, `customObject`, `enumValue`, `reference`; luego `arrayValue`, `value()` y `scan()` (el cursor se construye una sola vez, en `scan()`).

**Acceptance criteria:**
- [ ] `surplusElements` sigue **sin tocar** hasta la Tarea 11
- [ ] Las inconsistencias preservadas siguen intactas: `customObject` reporta el error de longitud en `$position` con `length: 1` fijo mientras sus hermanos usan `max(1, $position - $start)`. **No "arreglarlas" en este refactor** — sostienen la propiedad de containment

**Verification:** los 3 archivos de test + `diff` de golden vacío.
**Dependencies:** Tarea 9 · **Scope:** M

---

#### Tarea 11: `surplusElements` con `fork()` — el riesgo principal

**Description:** Va al final, sola, porque es el mayor peligro de corrección del refactor completo.

```php
// antes (9 params)
private function surplusElements(string $data, int $length, int $position, int $start, int $countStart, int $countEnd, int $declared, int $depth, bool $unverifiable): SyntaxDiagnostic
// después (6 params, descarte explícito)
private function surplusElements(ScannerCursor $cursor, int $start, int $countStart, int $countEnd, int $declared, int $depth): SyntaxDiagnostic
```

**Acceptance criteria:**
- [ ] El lookahead usa `$cursor->fork()`, nunca el cursor vivo. Con el cursor vivo, la posición principal avanzaría a través del excedente, el `length` del diagnóstico (`max(1, min($cursor,$length) - $position)`) cambiaría, y el llamador de `arrayValue` reanudaría en otro lado
- [ ] La fuga de `unverifiable` queda prevenida por el fork. Hoy se descarta por valor; con cursor compartido, un token `R:`/`C:`/`E:` dentro del excedente lo activaría globalmente. Es inocuo hoy (esa ruta siempre retorna diagnóstico, así que `scan()` nunca lee el flag) pero es un cambio semántico real: preservarlo, no razonarlo
- [ ] Spot-check manual: `a:1:{i:0;i:1;i:2;i:3;}` sigue sugiriendo ``Change `a:1:` to `a:2:`.``

**Verification:** los 3 archivos de test + `diff` de golden vacío + el spot-check.
**Dependencies:** Tarea 10 · **Scope:** S

---

#### Tarea 12: Hoisting en bucles calientes y verificación de rendimiento

**Description:** Hoistear `$data`/`$length`/`$position` a locales en los cuatro bucles por byte (`integer`, mantisa y exponente de `double`, decodificación escapada de `quotedPayload`) y en `unsignedDigits`, escribiendo de vuelta a `$cursor->position` una sola vez por bucle.

**Acceptance criteria:**
- [ ] Ninguna llamada a `current()` queda dentro de un bucle por byte
- [ ] `fork()` no se alcanza en ruta caliente
- [ ] El tiempo del test de rendimiento se **compara contra la línea base** de la Tarea 2, no solo contra el límite de 2.0 s

**Verification:** `vendor/bin/pest --filter="maximum-size payload"` tres veces + `diff` de golden vacío.
**Dependencies:** Tarea 11 · **Scope:** S

---

### ✅ Checkpoint C — Scanner terminado
- [ ] `vendor/bin/pest` completo verde
- [ ] `diff` de golden vacío
- [ ] Rendimiento sin regresión medible
- [ ] Ningún docblock perdido: `git diff <base> -- app/Services/SerializedScanner.php | grep '^-\s*\*'` muestra solo líneas que se movieron literales a la factory
- [ ] Script `golden.php` borrado antes de commitear

---

### Phase 3: Duplicación transversal

#### Tarea 13: Tipar el vocabulario de outcome

**Description:** Nuevo `App\Enums\ConversionOutcome` (`Success`, `RateLimited`, `ValidationError`, más un `from(ConversionErrorCode)`). `ConversionTelemetry::record()` lo recibe tipado.

**Acceptance criteria:**
- [ ] Los 5 call sites actualizados: `AppServiceProvider.php:45,70`, `Livewire/Serialized.php:133,155,164,175`, `UnserializeController.php:42,69`, `ConvertSerializedDataTool.php:84,101,109,117`
- [ ] `ConversionMetric::OUTCOME_SUCCESS` retirado o apuntando al enum
- [ ] Los valores persistidos en BD no cambian

**Verification:** `vendor/bin/pest tests/Unit/ConversionTelemetryTest.php tests/Feature/UsageEventTest.php tests/Feature/UsageSummaryCommandTest.php`
**Dependencies:** Checkpoint C · **Scope:** M (5 archivos)

---

#### Tarea 14: Deduplicar rate limiting

**Description:** Extraer la derivación de clave `hash('sha256', $request->ip())` y el bloque telemetría+429 a un solo lugar, consumido por los tres call sites.

**Acceptance criteria:**
- [ ] `Limit::perMinute(10)` (hoy hardcodeado dos veces) y `Livewire\Serialized::MAX_ATTEMPTS` viven en `config/`
- [ ] Los mensajes 429 siguen siendo distintos por superficie (API vs MCP vs browser) — es diferencia real, no duplicación
- [ ] `app(ConversionTelemetry::class)` dentro de los closures se resuelve igual o mejor

**Verification:** `vendor/bin/pest tests/Feature/UnserializeApiTest.php tests/Feature/McpUnserializeTest.php tests/Feature/UnserializeTest.php`
**Dependencies:** Tarea 13 · **Scope:** M (4 archivos)

---

#### Tarea 15: Unificar sobres de respuesta

**Description:** Un solo constructor de sobres para éxito (`data.value`/`data.format`/`meta.retained`) y error (`error.code`/`error.message`/`error.diagnostic?`), consumido por `UnserializeController` y `ConvertSerializedDataTool`.

**Acceptance criteria:**
- [ ] El mapeo a HTTP status (`InputTooLarge` → 413, resto → 422) **se queda en el controller**; MCP no tiene equivalente y eso no cambia
- [ ] La omisión condicional de `diagnostic` se comporta igual en ambas superficies (hoy una usa `if`, la otra `array_filter`)
- [ ] El JSON de salida es idéntico byte a byte

**Verification:** `vendor/bin/pest tests/Feature/UnserializeApiTest.php tests/Feature/McpUnserializeTest.php`
**Dependencies:** Tarea 14 · **Scope:** M (3 archivos)

---

#### Tarea 16: Una sola fuente para `MAX_INPUT_BYTES`

**Description:** Derivar de `Serialized::MAX_INPUT_BYTES` en `ConversionErrorCode::message()` y en el `#[Description]` del MCP.

**Acceptance criteria:**
- [ ] `public/openapi.json` y las vistas Blade no se pueden derivar en runtime → **añadir un test** que compare el número publicado contra la constante, para que el desajuste falle en CI en vez de en silencio
- [ ] El texto visible al usuario no cambia (`262,144` con coma en la prosa del enum)

**Verification:** `vendor/bin/pest tests/Feature/ApiCatalogTest.php` + el test nuevo.
**Dependencies:** Tarea 15 · **Scope:** S

---

#### Tarea 17: Colapsar los witters de `SyntaxDiagnostic`

**Description:** Reemplazar los 4 bloques de 10 argumentos (`corroboratedBy`, `widenedTo`, `clampedTo`, `withoutSuggestion`) por un helper privado `with(...)` con argumentos nombrados.

**Acceptance criteria:**
- [ ] Añadir una propiedad toca un solo lugar
- [ ] Los cortocircuitos actuales se preservan (`widenedTo` retorna `$this` si no ensancha; `clampedTo` retorna `$this` si nada cambia) — evitan asignaciones en ruta caliente
- [ ] `claimInterval()` y `toArray()` intactos: son la bisagra con `SerializedDiagnostics` y el contrato de cable publicado

**Verification:** los 3 archivos de test del scanner + `diff` de golden vacío.
**Dependencies:** Tarea 16 · **Scope:** S

---

#### Tarea 18: Limpieza de código muerto y deuda menor

**Description:** Acotada a lo verificado.

**Acceptance criteria:**
- [ ] `App\Rules\SerializedRule` borrado (cero referencias en `app/`, `tests/`, `routes/`, `resources/`) y con él la razón de existir de `Serialized::isValid()`
- [ ] `output()`/`toJson()` colapsados (son byte a byte idénticos)
- [ ] `@throws Exception` obsoletos corregidos a `ConversionException` (`Serialized.php:47`, `:78`)
- [ ] El fallo se memoiza en `decode()`: hoy `$hasDecoded` solo se marca en éxito, así que un payload inválido re-ejecuta `unserialize()` + escaneo completo en cada llamada
- [ ] `Serialized::MAX_DEPTH = 512` vs `SerializedScanner::MAX_DEPTH = 1024`: alinear **o** documentar por qué difieren. Decisión a tomar con evidencia, no por simetría — el scanner declara a propósito estar por encima del límite de PHP

**Verification:** `vendor/bin/pest` completo.
**Dependencies:** Tarea 17 · **Scope:** S

---

### ✅ Checkpoint D — Completo
- [ ] `vendor/bin/pest` completo verde (219 declaraciones)
- [ ] `vendor/bin/pint --test`
- [ ] `diff` de golden vacío
- [ ] `REFACTOR-REPORT.md` actualizado marcando lo hecho
- [ ] Revisión humana

---

## Risks and Mitigations

| Riesgo | Impacto | Mitigación |
|---|---|---|
| Aliasing del lookahead: `surplusElements` recibe el cursor vivo en vez de un fork | **Alto** | `fork()` explícito; Tarea 11 aislada al final; hash golden lo detecta |
| Fuga de `unverifiable` desde el lookahead al cursor compartido | Medio | `fork()`; documentado como cambio semántico real, no como detalle inocuo |
| Deriva de copy: un `sprintf` reflowed o con argumentos intercambiados | **Alto** (silencioso) | Copiado literal + hash golden sobre las 25 semillas |
| Orden de snapshot de posición roto en diagnósticos que leen tras avanzar | Alto | Regla explícita en Tarea 8; golden lo detecta |
| Colisión del local `$cursor` en la rama escapada (línea 341) | Medio | Renombrado a `$scan` en Tarea 9; solo lo cubren payloads `S:` |
| Un método de la factory recibe el cursor y lee un offset ya avanzado | Medio | Prohibido en el docblock de clase y en revisión |
| Regresión de rendimiento por `current()` dentro de bucle por byte | Bajo | Tarea 12 compara contra tiempo base, no contra el límite |
| Se "arreglan" inconsistencias deliberadas (`contextStart`, `length: 1`) | Alto | Listadas explícitamente en el reporte y en la Tarea 10 como intocables |
| Romper `new SerializedScanner` en 4 archivos a la vez | Bajo | Constructor con valor por defecto |
| `public/openapi.json` se desincroniza de los mensajes del scanner | Medio | Ningún test compara hoy; Tarea 16 añade la guarda para el byte limit |

## Open Questions

- **`MAX_DEPTH` 512 vs 1024** (Tarea 18): el docblock del scanner dice que está por encima del límite de PHP *a propósito*, para que la profundidad se diagnostique desde el warning de PHP y no adivinando. Si eso sigue siendo cierto, la respuesta correcta es documentar, no alinear. A decidir con evidencia durante la Tarea 18.
- **Nombre de la factory**: `SyntaxDiagnosticFactory` evita colisión con el `SerializedDiagnostics` existente (que es el reconciliador de offsets de PHP, algo distinto). `ScannerMessages` es la alternativa si prefieres.

## Archivos críticos

- `app/Services/SerializedScanner.php` — el refactor principal (902 → ~480 líneas)
- `app/Services/Scanner/SyntaxDiagnosticFactory.php`, `app/Services/Scanner/ScannerCursor.php` — nuevos
- `app/Services/SerializedDiagnostics.php` — único consumidor del scanner (`:55`, `:129`)
- `app/Data/SyntaxDiagnostic.php` — `claimInterval()` es la bisagra scanner↔reconciliador
- `app/Providers/AppServiceProvider.php`, `app/Livewire/Serialized.php`, `app/Http/Controllers/Api/V1/UnserializeController.php`, `app/Mcp/Tools/ConvertSerializedDataTool.php` — duplicación transversal
- `tests/Unit/SerializedScannerDifferentialTest.php` — la red de seguridad real
