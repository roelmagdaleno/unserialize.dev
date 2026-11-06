# Análisis mensual de unserialize.dev — octubre 2026

Fecha de corte: 2026-10-27. Fuentes: Cloudflare (GraphQL Analytics), Google Search Console (dashboard en Chrome) y la base de producción (TablePro, conexión `unserialize.dev`, id `479EF265-FF4A-4189-B463-B60591016A95`, BD `forge`, solo lectura).

Versión con gráficas y recomendaciones: `2026-10-analisis-mensual.html` (publicado como artefacto).

## 1. Cloudflare

Zona `unserialize.dev`, id `a8be8fe3ce748809391a2e24c4c2e683`, plan Free. Cuenta `3d4b046467c36048c42ce186f2542113`. Datos de `httpRequests1dGroups`.

| | Ago | Sep | Oct (1–27) |
|---|---|---|---|
| Requests | 117,647 | 99,069 | 86,979 |
| Páginas vistas | 3,133 (101/día) | 4,230 (141/día) | 4,047 (150/día) |
| Únicos por día (promedio) | 119 | 150 | 176 |
| Amenazas | 9,603 | 1,609 | 592 |

Códigos de respuesta (requests):

| Status | Ago | Sep | Oct |
|---|---|---|---|
| 404 | 53,599 | 44,732 | 37,616 |
| 301 | 37,841 | 20,990 | 22,006 |
| 403 | 20,350 | 23,033 | 14,136 |
| 200 | 4,579 | 8,064 | 8,649 |
| 405 | 1,033 | 1,421 | 1,063 |
| 500 | 42 | 47 | **340** |
| 525 | — | — | **2,348** |

- Más del 80 % del tráfico es de bots (404/403/301).
- Octubre: 2,348 errores 525 (falla SSL entre Cloudflare y el origen) y 340 errores 500. Sin investigar todavía; revisar en Nightwatch.
- Picos: amenazas el 2 y 9 de agosto (~4,300 cada uno); 334 únicos el 4 de octubre; 435 páginas vistas el 12 de octubre.
- Países principales en octubre (requests): NL, US, AU, KR, IN, FR, SG, HK, BE, JP.
- Navegadores (páginas vistas, octubre): Chrome 1,979; Unknown 726; ChromeDerivative 443; MobileSafari 366; Firefox 197.

## 2. Google Search Console

Propiedad `sc-domain:unserialize.dev`.

| | Ago | Sep | Oct (1–27) |
|---|---|---|---|
| Clics | 2 | 11 | 17 |
| Impresiones | 662 | 1,170 | 1,500 |
| CTR | 0.3 % | 0.9 % | 1.1 % |
| Posición media | 14.8 | 9.3 | 8.8 |

Últimos 3 meses: 30 clics, 3,400 impresiones, CTR 0.9 %, posición 10.5.

Consultas principales (3 meses, clics / impresiones): unserialize 4/732; unserialize online 2/811; unserialize to json online 2/25; php unserialize online 1/257; unserialize json 1/33; unserializer 1/15; php serialize to json online 1/12; unserialize php online 0/65; online unserialize 0/61.

Octubre: "unserialize online" 2/496, "php unserialize online" 0/164, "unserialize php online" 0/50.

Páginas (3 meses): `/` 27/3,282; `/guides/fix-broken-serialized-string` 3/65; `/guides/wordpress-serialized-data` 0/59; `/privacy` 0/4.

Conclusión: ya está en primera página pero abajo; el siguiente paso de SEO es el CTR (título y descripción).

## 3. Base de datos (producción)

Tablas: `usage_events` (eventos, desde 2026-09-15), `conversion_metrics` (agregados diarios por interfaz y resultado), `outputs` (tabla heredada, último registro en septiembre 2026). No se guarda el input del usuario.

Conversiones por interfaz y resultado (`conversion_metrics`):

| | Sep | Oct (1–27) |
|---|---|---|
| Navegador: intentos | 137 | 95 |
| Navegador: éxito | 99 (72 %) | 52 (55 %) |
| Navegador: invalid_input | 34 | 42 |
| Navegador: unsupported_object | 3 | 0 |
| Navegador: input_too_large | 1 | 1 |
| MCP: éxito | 5 | 0 |
| MCP: invalid_input | 1 | 5 |
| MCP: rate_limited | 0 | 4 |
| MCP: sesiones iniciadas | 47 | 99 |
| MCP: tools listadas | — | 91 |
| Resultado copiado (navegador) | 10 | 2 |

Diagnósticos de `invalid_input` en navegador (Sep + Oct = 76):

| Diagnóstico | Total |
|---|---|
| string_length_mismatch | 46 (43 desde Windows) |
| unknown_type_marker | 12 (todos desde el 21-oct) |
| missing_delimiter | 10 |
| malformed_number | 3 |
| invalid_array_key | 2 |
| trailing_data | 2 |
| unexpected_end | 1 |

- Los fallos se concentran en pocas personas que reintentan: un usuario de Windows/Chrome hizo ~20 intentos el 16–17 de septiembre y otro 16 el 10 de octubre; dos usuarios de Mac hicieron ~9 intentos cada uno el 21 y 22 de octubre.
- Los 3 `unsupported_object` de septiembre se resolvieron con el cambio del 5 de octubre (objetos como datos).
- MCP: el límite de uso es por IP (`ConversionRateLimiter::key()` hace hash de `$request->ip()`). Los 4 `rate_limited` llegaron en pares en el mismo segundo, de `claude-code` y `Claude-User`. Hipótesis sin confirmar: `Claude-User` sale de IPs compartidas.

## 3b. Laravel Nightwatch

App `Unserialize` (`a0037617-e21c-488b-94aa-3323fc956434`), entorno Production (`a0037617-e46a-416a-a5bb-9f696147ef98`, us-east-2). El MCP solo expone issues; no hay issues de rutas, jobs ni comandos lentos.

- #7 `CannotUpdateLockedPropertyException` (abierto): 8 veces en 7 días, último 24-oct con `diagnostic`, respuesta 419, IP de GCP en NL. Bot que altera snapshots de Livewire. Resuelto el 14-sep y reabierto el 16-sep.
- #11 `MethodNotFoundException: _xNonExist` (abierto): escáner, 14–15 oct, responde 500.
- 9 resueltos: timeout de 30 s (ago), payload corrupto de Livewire (jul), bots con API de Livewire 2 (jul), Vite manifest en deploys (oct 2025).

Cloudflare por día: los 2,348 errores 525 y 338 de los 340 errores 500 de octubre ocurrieron el 15-oct (los otros 2 el 14-oct). No hubo deploy ese día (último commit 7-oct). Nightwatch no registra nada que explique los 338 errores 500; probablemente Nginx/PHP-FPM durante la falla SSL (sin confirmar). Además, 61 errores 522 dispersos en el mes. El desglose por hora quedó pendiente por el rate limit de la API GraphQL.

## 4. Causa de los fallos de conversión

1. **`string_length_mismatch` (60 %): bug de la app.** El `<textarea>` del navegador normaliza `\r\n` a `\n`, así que cada salto de línea de Windows dentro de un string pierde un byte y `s:N:` deja de coincidir. Verificado en unserialize.dev: un texto de 26 bytes con `\r\n` queda en 25. Reproducido con `App\Services\Serialized`: el original convierte y la versión con `\n` falla con "declares 18 bytes but 17 bytes precede the closing quote".
2. **Errores al pegar**, reproducidos con el parser:
   - Espacio inicial, salto de línea final, comillas alrededor (SQL), JSON, base64, `print_r` o dos valores juntos dan `unknown_type_marker`.
   - Comillas escapadas `\"` (dumps, logs) dan `missing_delimiter`.
   - Datos truncados dan `unexpected_end`.
   - Cambiar un valor sin ajustar la longitud (search-replace de WordPress) da `string_length_mismatch`.
3. El resto son errores reales del usuario.

## 5. Correcciones propuestas (no implementadas)

1. Si falla con `string_length_mismatch` y el input tiene `\n`, reintentar con `\r\n` y mostrar un aviso si funciona.
2. Quitar espacios y saltos de línea exteriores antes de convertir.
3. Mensajes específicos para comillas alrededor, `\"`, JSON y base64.
4. Telemetría sin contenido: banderas como "empieza con espacio", "tiene `\"`" o "recuperado con CRLF".
5. MCP: revisar el límite por IP para `Claude-User`.
