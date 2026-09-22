# serialized — diagnóstico y reparación de datos serializados de PHP

## Problem Statement
¿Cómo podríamos darle a un developer que encuentra datos serializados de PHP
corruptos una respuesta precisa de qué byte está mal, por qué, y una reparación
verificada — sin que tenga que confiar en `unserialize()` ni en un snippet de
StackOverflow?

## Recommended Direction
Extraer el motor de unserialize.dev a `roelmagdaleno/serialized`, un paquete
PHP 8.1+ con **cero dependencias**, y dejar este repositorio como el sitio
Laravel que lo consume. La extracción es casi mecánica: la capa de dominio
(~3,300 líneas en `Services/`, `Data/`, `Enums/`) no importa ni una sola clase
de Illuminate. El costo real del downgrade a 8.1 son 11 `readonly class` y 13
constantes tipadas.

El paquete se posiciona como **inspector y explicador**, con la reparación como
diferenciador. Ese orden lo fijó la auditoría de Packagist (ver más abajo): la
demanda viva está en *mirar* datos serializados, no en repararlos vía Composer.
La conversión a JSON ya se escribe en una línea con la librería estándar; lo que
no existe es un parser completo de la gramática que localice el fallo por offset
y ofrezca una edición de bytes verificada. El README abre con "inspecciona y
explica", y la reparación es la segunda frase.

La superficie pública se limita a dos tipos — una fachada y `Diagnostic` — con
todo el scanner marcado `@internal`. Eso es lo que hace que el compromiso de
semver estricto cueste casi nada, y es la condición que vuelve compatibles las
dos metas declaradas (codebase limpia + credibilidad) con ese compromiso.

## Key Assumptions to Validate
- [x] ~~Existe demanda real en PHP para reparar serialized roto~~ — **VALIDADO
      2026-09-17, resultado negativo para la reparación y positivo para la
      inspección.** Ver "Evidencia de mercado".
- [x] ~~Aplicar N reparaciones en cadena converge a un payload correcto~~ —
      **VALIDADO 2026-09-17, con una condición que cambia el contrato público.**
      Ver "Qué promete `repair()`".
- [ ] Una API de 2 tipos públicos cubre los tres casos de uso declarados
      (reuso propio, WordPress legacy, migraciones de datos) — escribir los tres
      snippets de README antes de congelar la fachada.
- [ ] PHP 8.1 alcanza a la audiencia de WordPress — verificar la distribución de
      versiones de PHP en wordpress.org/about/stats.

## Evidencia de mercado (auditoría Packagist, 2026-09-17)

**La reparación como paquete de Composer ya se intentó y murió dos veces:**

| Paquete | Total | Mensual | Última release |
| --- | --- | --- | --- |
| `b-poignant/serialize-data-fixer` | 30 | 0 | 2019 |
| `jedi58/reserializer` | 65 | 0 | 2019 |

**La inspección tiene ~13,000 instalaciones/mes y los incumbentes están parados:**

| Paquete | Total | Mensual | Última release | ★ |
| --- | --- | --- | --- | --- |
| `qafoo/ser-pretty` | 229k | 7,809 | 2022 (1 release) | 40 |
| `academe/serializeparser` | 119k | 5,278 | **2017**, 7 issues abiertos | 4 |
| `lyte/serial` (unserializer seguro) | 30k | 448 | 2026-03, activo | 1 |

**Por qué murió la reparación: canal, no demanda.** WordPress ya la resolvió fuera
de Composer — FG Fix Serialized Strings, Better Search Replace, `wp search-replace`
e interconnectit Search-Replace-DB. El dev de WordPress con `wp_options` roto
instala un plugin o corre WP-CLI; no ejecuta `composer require`. La audiencia
existe y es inalcanzable desde Packagist.

**Consecuencias para este plan:**
- El titular del README es inspección, no reparación.
- La reparación sigue en el MVP como diferenciador (ninguno de los tres vivos la
  tiene) pero no financia la adopción por sí sola.
- La Dirección B sube de prioridad: `academe/serializeparser` deja 5,278
  instalaciones mensuales sin mantenedor y `lyte/serial` no domina el nicho.
- Alcanzar WordPress requiere un canal propio (plugin o comando WP-CLI), que es
  una decisión posterior al MVP.

## Qué promete `repair()` (validado 2026-09-17)

Prototipo en `app/Services/SerializedRepairer.php` y pruebas en
`tests/Unit/SerializedRepairerTest.php`, sobre el corpus generado existente.

**Resultados medidos:**

| Clase de corrupción | Payloads | Reparados | Round-trip correcto |
| --- | --- | --- | --- |
| Search-and-replace (longitudes obsoletas) | 462 | 109 (24%) | **109/109 = 100%** |
| Mutaciones de byte encadenadas | 999 | 4 (0.4%) | 1/4 = 25% |

Los tres contraejemplos no son fallos del bucle: en los tres, la mutación dañó el
**contenido** además de la metadata (un byte volteado dentro de un string, un
marcador de tipo `i`→`d`, un byte insertado en el contenido). El reparador no
puede distinguir "la longitud miente" de "el contenido está dañado" — las dos se
presentan idénticas — y siempre asume que el contenido es la verdad. Esa
suposición es exactamente correcta para search-and-replace y no lo es para
corrupción aleatoria.

**El invariante que sí es universal**, medido sobre 4,737 ediciones en 3,387
reparaciones, con cero violaciones:

> Toda edición reescribe un número decimal declarado por otro número decimal.
> `repair()` nunca inventa, altera ni descarta un byte de contenido.

**Contrato público que se deriva de esto:**

- `repair()` promete que el payload parseará y que ningún byte de contenido fue
  tocado. **No** promete devolver el valor original.
- El round-trip al valor original se garantiza solo cuando la corrupción se
  limitó a números declarados — que es la clase que el paquete ataca.
- La lista de ediciones en `RepairResult` es el mecanismo de auditoría: el
  invariante la vuelve verificable por el usuario, no una promesa de confianza.
- El reparador es deliberadamente conservador: abandona 76% de las corrupciones
  de search-and-replace en lugar de adivinar.

**Riesgo residual:** un payload cuyo contenido fue dañado *y* cuyas longitudes
quedaron obsoletas se repara "con éxito" y devuelve el contenido dañado. El
paquete no puede detectarlo, así que la documentación debe decirlo.

## MVP Scope

**Dentro:**
- Fachada de 6 métodos: `inspect()`, `isValid()`, `explain()`, `repair()`,
  `toArray()`, `toJson()` — `inspect()` es el verbo de portada y cubre el caso
  que hoy atienden `ser-pretty` y `serializeparser`
- `Diagnostic` como único DTO público (code, offset, length, message, suggestion, fix)
- Reparador multi-error: bucle escanear → aplicar `fix` → re-escanear, con
  presupuesto máximo de ediciones y verificación por paso
- `RepairResult` que devuelve el payload reparado **y la lista de ediciones
  aplicadas** — nunca repara en sitio ni en silencio
- El invariante de preservación de contenido como test de propiedad publicado, y
  como la primera línea de la documentación de `repair()`
- PHP ^8.1, cero dependencias de runtime, matriz de CI 8.1→8.5
- Suite diferencial portada como garantía de corrección publicada en el README
- README con benchmark explícito contra `ser-pretty` y `serializeparser`: mismo
  caso de uso, más un offset de error cuando el payload está roto

**Fuera:**
- Decodificador propio (Dirección B) — v2 declarada, no anunciada, pero con
  prioridad elevada tras la auditoría
- Plugin de WordPress y comando WP-CLI — el canal correcto para esa audiencia,
  y una decisión de producto separada
- ServiceProvider / facade de Laravel
- Binario CLI
- Soporte de objetos serializados
- PHP 7.4

## Not Doing (and Why)
- **Publicar el scanner interno (`TokenReader`, `ScannerCursor`, `ValueParser`,
  `Rules/*`) como API** — cada tipo expuesto es un contrato que semver obliga a
  honrar; son ~13 tipos de impuesto permanente para cero beneficio al usuario.
- **Un segundo paquete `unserialize-laravel`** — el sitio es el único consumidor
  Laravel conocido, y un ServiceProvider para instanciar una clase sin
  dependencias resuelve un problema que nadie tiene.
- **Bajar a PHP 7.4** — cuesta enums, readonly y tipos de retorno; se paga solo
  si la validación de versiones de WordPress demuestra que 8.1 deja fuera a la
  mayoría.
- **Reparación automática silenciosa (`repairInPlace()`)** — el modo de fallo que
  puede hundir la reputación del paquete es devolver datos plausibles pero
  distintos; toda reparación es explícita y auditable.
- **Vender el paquete como "reparador de serialized"** — dos paquetes con esa
  portada tienen 0 instalaciones mensuales; la reparación se gana el espacio
  como diferenciador, no como titular.
- **Perseguir la audiencia de WordPress desde Packagist** — ya la atienden
  plugins y WP-CLI; entrar ahí exige un canal propio, no un `composer require`.
- **Mover la telemetría, el rate limiter y el MCP tool** — son propiedades del
  sitio, no del dominio, y `UsageContext` es la única clase de esa capa que
  toca Illuminate.

## Open Questions
- **Nombre del paquete y colisión de namespace.** Este repo ya ocupa
  `roelmagdaleno/unserialize` en su `composer.json`. ¿El paquete toma
  `roelmagdaleno/serialized` y el sitio conserva su nombre, o se renombra el sitio?
- ¿`repair()` se detiene ante la primera reparación no verificable, o continúa y
  reporta las regiones que no pudo tocar?
- ¿El sitio consume el paquete vía Packagist desde el día uno, o por `path`
  repository mientras la API se estabiliza?
- ¿Repo nuevo o monorepo con split de solo-lectura?
- ¿Se contacta a los mantenedores de `academe/serializeparser` (abandonado desde
  2017, 5,278 instalaciones/mes) para ofrecer una ruta de migración, o se compite
  de frente?
- ¿La Dirección B se adelanta a v1.1 en lugar de v2, dado que `lyte/serial` no
  domina el nicho?
