# TODO: reporte de salud + refactor de mantenibilidad

Plan completo con criterios de aceptación y verificación: [tasks/plan.md](plan.md)

## Phase 0: Entregables y red de seguridad

- [x] **Tarea 0** — Materializar `tasks/plan.md` y `tasks/todo.md` · XS · deps: ninguna
- [ ] **Tarea 1** — Escribir `REFACTOR-REPORT.md` (tabla por archivo + "qué NO tocar" + contratos publicados) · S · deps: ninguna
- [ ] **Tarea 2** — Línea base de caracterización "golden" (25 semillas × corpus × mutaciones) · S · deps: ninguna

### ✅ Checkpoint A — Red de seguridad lista
- [ ] `vendor/bin/pest` completo verde sobre HEAD (219 declaraciones)
- [ ] `golden.txt` reproducible (diff vacío dos veces)
- [ ] Reporte revisado por humano **antes** de tocar código

## Phase 1: Scanner — sacar el copy de la gramática (riesgo bajo)

- [ ] **Tarea 3** — Crear `app/Services/Scanner/SyntaxDiagnosticFactory.php` (sin usar) · S · deps: T2
- [ ] **Tarea 4** — Migrar call sites: escalares (líneas 54, 75, 96, 121, 154, 192, 245, 270) · S · deps: T3
- [ ] **Tarea 5** — Migrar call sites: strings (318, 353, 419, 451) · S · deps: T4
- [ ] **Tarea 6** — Migrar call sites: arrays/objetos/refs/helpers (481, 503, 591, 645, 682, 739, 803, 822, 851, 868) · M · deps: T5

### ✅ Checkpoint B — Copy centralizado (punto de commit fuerte)
- [ ] `grep -c 'new SyntaxDiagnostic' app/Services/SerializedScanner.php` → 0
- [ ] `vendor/bin/pest` completo verde
- [ ] `diff` de golden vacío
- [ ] `vendor/bin/pint --test`
- [ ] **Commit.** Si la Fase 2 sale mal, esta ganancia ya está asegurada.

## Phase 2: Scanner — cursor explícito (riesgo medio)

- [ ] **Tarea 7** — Crear `app/Services/Scanner/ScannerCursor.php` (sin usar) · XS · deps: Checkpoint B
- [ ] **Tarea 8** — Cablear cursor: primitivos y escalares · M · deps: T7
- [ ] **Tarea 9** — Cablear cursor: strings (¡renombrar local `$cursor` → `$scan` en línea 341!) · S · deps: T8
- [ ] **Tarea 10** — Cablear cursor: compuestos · M · deps: T9
- [ ] **Tarea 11** — `surplusElements` con `fork()` — **el riesgo principal** · S · deps: T10
- [ ] **Tarea 12** — Hoisting en bucles calientes + verificación de rendimiento · S · deps: T11

### ✅ Checkpoint C — Scanner terminado
- [ ] `vendor/bin/pest` completo verde
- [ ] `diff` de golden vacío
- [ ] Rendimiento sin regresión medible (comparar contra base, no contra el límite de 2.0 s)
- [ ] Ningún docblock perdido
- [ ] Script `golden.php` borrado antes de commitear

## Phase 3: Duplicación transversal

- [ ] **Tarea 13** — Tipar el vocabulario de outcome (`App\Enums\ConversionOutcome`) · M · deps: Checkpoint C
- [ ] **Tarea 14** — Deduplicar rate limiting (triplicado hoy) · M · deps: T13
- [ ] **Tarea 15** — Unificar sobres de respuesta API/MCP · M · deps: T14
- [ ] **Tarea 16** — Una sola fuente para `MAX_INPUT_BYTES` + test guarda · S · deps: T15
- [ ] **Tarea 17** — Colapsar los 4 witters de `SyntaxDiagnostic` · S · deps: T16
- [ ] **Tarea 18** — Limpieza de código muerto y deuda menor · S · deps: T17

### ✅ Checkpoint D — Completo
- [ ] `vendor/bin/pest` completo verde (219 declaraciones)
- [ ] `vendor/bin/pint --test`
- [ ] `diff` de golden vacío
- [ ] `REFACTOR-REPORT.md` actualizado marcando lo hecho
- [ ] Revisión humana
