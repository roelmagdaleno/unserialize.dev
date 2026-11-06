# Auditoría de rendimiento con Lighthouse — unserialize.dev

Fecha: 2026-11-05. Lighthouse 13.6.0, home en móvil salvo donde se indica. Rama `feat/october-recommendations` (`a0bb83a`), con `npm run build` recién hecho. No se cambió código de la app.

Reportes completos de Lighthouse: [local](2026-11-lighthouse/local-home-mobile.html) y [producción](2026-11-lighthouse/prod-home-mobile.html).

## Conclusión

**Las fuentes de Bunny no son lo que baja el score.** Quitarlas por completo deja el score igual. **Lo que lo baja es el resaltado de sintaxis (Shiki)**: quitarlo sube el score de ~81 a ~95 en las condiciones más parecidas a tu PageSpeed.

Vale la pena dejar de usar Bunny por otras razones (hallazgo 2), pero no esperes que eso mueva el 82.

## Cuánto aporta cada causa

Mediana de 3 corridas contra producción. Lighthouse bloqueó cada recurso en el navegador, así que cada fila es el **máximo** alcanzable, no el resultado de un fix ya hecho.

| Escenario | Scores | FCP | LCP | TBT | Peso |
|---|---|---:|---:|---:|---:|
| Base | 88 / 90 / 91 | 1.9 s | 2.7 s | 253 ms | 395 KB |
| Sin fuentes | 90 / 90 / 92 | 1.9 s | 2.7 s | 249 ms | 312 KB |
| Sin Shiki | 97 / 97 / 96 | 1.9 s | 2.4 s | 26 ms | 244 KB |
| Sin fuentes ni Shiki | 98 / 97 / 97 | 1.9 s | 2.2 s | 0 ms | 160 KB |
| **CPU 8x, base** | 80 / 81 / 82 | 1.9 s | 2.7 s | 550 ms | 396 KB |
| CPU 8x, sin fuentes | 81 / 79 / 81 | 1.6 s | 2.7 s | 612 ms | 312 KB |
| CPU 8x, sin Shiki | 95 / 96 / 95 | 1.9 s | 2.4 s | 141 ms | 244 KB |

- **Las filas CPU 8x son las más parecidas a tu 82.** Es una inferencia: la API de PageSpeed respondió 429 (cuota agotada), así que no pude ver el TBT de tu corrida ni datos de campo (CrUX). Tu Mac es más rápida que los servidores de PageSpeed, por eso la base da 88-91 sin ese ajuste.
- **Se repitió con throttling real (`--throttling-method=devtools`), sin simulación:** base 89/88/89, sin fuentes 88/89/88, sin Shiki 95/95/96. La conclusión no cambia.

## Hallazgos

### 1. Shiki carga y ejecuta todo al entrar, para código que no se ve (prioridad alta)

- **Qué pasa:** la home descarga `highlight` (71 KB comprimido) y el grammar de PHP (83 KB comprimido, 454 KB sin comprimir). Es más de un tercio del peso total de la página (395 KB).
- **Efecto:** una tarea larga de ~300 ms en `highlight-*.js`, casi todo el TBT. También compite por ancho de banda con el CSS y retrasa el LCP unos 300 ms.
- **Por qué es evitable:** en la home, ese trabajo es para bloques que están en el `<article>`, debajo del pliegue, incluido un snippet PHP de 3 líneas en [serialized.blade.php:108](../../resources/views/livewire/serialized.blade.php:108). El grammar pesa tanto porque `php` arrastra `html`, `xml`, `sql`, `javascript`, `json` y `css`.
- **También afecta a otras páginas:** `/guides/wordpress-serialized-data` (score local 69) tiene dos tareas largas (264 y 114 ms) y `/developers` una de 90 ms.

**Solución recomendada:** que [highlight.js](../../resources/js/highlight.js) sea un entry mínimo sin imports estáticos de Shiki. Debe usar `import()` solo cuando un `pre[data-lang]` entre en pantalla, con `IntersectionObserver` y un `rootMargin` de unos 200 px.

- Debe seguir funcionando el hook `morphed` de Livewire, para que el resultado de una conversión se resalte.
- Copiar sigue funcionando en un bloque aún sin resaltar: `clipboard.js` usa `textContent` si no hay `data-code`.
- El bloque se verá en texto plano un instante si el usuario hace scroll rápido, y la tarea larga pasa al primer scroll. Conviene medir INP después del cambio.

**Alternativa:** prerenderizar los snippets estáticos con tokens en el Blade y cargar Shiki solo cuando exista `$result`. Elimina el JS por completo en la home, pero añade un paso de build y más mantenimiento.

**Impacto esperado:** hasta ~95 en condiciones tipo PageSpeed. Es la cifra de "sin Shiki", y es el techo.

### 2. Fuentes: dos terceros, no uno (prioridad media, no por score)

- **Bunny solo entrega el CSS.** Los `woff2` vienen de `cdn.jsdelivr.net`: las 66 URLs de fuentes de ese CSS apuntan ahí. Hay dos orígenes externos, y solo tienes `preconnect` al primero ([AppServiceProvider.php:124](../../app/Providers/AppServiceProvider.php:124)).
- **Por qué casi no afecta al score:** el CSS de Bunny termina antes que `app.css` (1.5 s contra 2.3 s con throttling real), así que no decide el FCP. Los `woff2` empiezan después del FCP y usan `display=swap`. El CLS atribuido a las fuentes es de 0.0004.
- **Por qué sí conviene cambiarlo:**
  - Es un `<link>` que bloquea el render y depende de un tercero. Si ese servicio se pone lento, tu página se pinta tarde.
  - Quita una conexión extra (DNS, TLS) y ~15 KB de `@font-face` sin comprimir que casi no se usan.
  - Cada visita envía la IP del usuario a Bunny y a jsDelivr, y la [página de privacidad](../../resources/views/privacy.blade.php) no menciona a ninguno. Vale la pena revisarlo, dado que el sitio promete no guardar nada.
- **Ganancia medible:** unos 160 ms de LCP una vez resuelto Shiki (2.38 s → 2.22 s) como máximo.

**Solución recomendada: alojar las fuentes en la app.**

1. Copiar los `woff2` a `resources/fonts/`. Así no se añade ninguna dependencia; `@fontsource/*` también sirve, pero requiere tu aprobación.
2. Declarar `@font-face` en [app.css](../../resources/css/app.css) con rutas relativas. Vite les pone hash y salen con el caché de un año que ya tiene `/build`.
3. Quitar el `<link>` de [app.blade.php:9](../../resources/views/components/layouts/app.blade.php:9) y el `preconnect`.
4. Añadir `preload` solo para Geist Mono 400 (9.9 KB): el elemento LCP es el texto del `textarea`, que usa esa fuente.

```css
@font-face {
    font-family: 'Inter';
    font-style: normal;
    font-weight: 100 900;
    font-display: swap;
    src: url('../fonts/inter-latin-wght-normal.woff2') format('woff2');
}

@font-face {
    font-family: 'Geist Mono';
    font-style: normal;
    font-weight: 400;
    font-display: swap;
    src: url('../fonts/geist-mono-latin-400-normal.woff2') format('woff2');
}
```

| Fuente | Hoy | Propuesta |
|---|---|---|
| Inter | 400 + 500 + 600 estáticas: 72.4 KB | Variable: 48.3 KB |
| Geist Mono | 400 estática: 9.9 KB | Igual. La variable pesa 23.1 KB |

**Tres cosas a decidir o revisar al implementarlo:**

- **Peso del logo:** hoy no cargas Inter 700, así que el `font-bold` del logo usa la de 600. Con la variable será un 700 real y se verá un poco más grueso. Si no lo quieres, cámbialo a `font-semibold`.
- **Otros alfabetos:** Bunny da cirílico, griego, etc. bajo demanda. Con solo el subset latin, esos caracteres usan la fuente del sistema. No es grave, pero la gente puede pegar datos en cualquier idioma. `latin-ext` se puede añadir con `unicode-range`.
- **Geist Mono 500:** se declara hoy pero no se descargó en la home. Solo hay un `font-mono` en las vistas, así que parece prescindible. Confírmalo visualmente.

### 3. Livewire y Flux: el piso del FCP (prioridad baja)

- **Qué pasa:** son 93 KB comprimidos de scripts síncronos en el `<body>`. Lighthouse estima 75 KB sin usar y los marca como bloqueantes. Junto con `app.css` fijan el FCP de ~1.9 s, que no bajó en ningún escenario.
- **Límite:** el selector de tema usa Flux y Alpine en todas las páginas, y Alpine viene con Livewire. No se pueden quitar sin más.
- **Opción:** empaquetar Livewire manualmente con `@livewireScriptConfig` para que cargue como módulo diferido. No lo probé; hazlo solo si tras los hallazgos 1 y 2 quieres seguir bajando el FCP.

### 4. Otros puntos menores (prioridad baja)

- **HTML sin caché en el edge:** la home responde `cf-cache-status: DYNAMIC` y `set-cookie` de sesión, con 260-410 ms hasta el documento. Cachearla requiere resolver el token CSRF que va en la página y el de Livewire, así que no compensa por ahora.
- **`app.css` de 398 KB:** suena mal, pero son 39 KB comprimidos y Lighthouse estima solo 9% sin usar. Flux escanea sus propios stubs (`@source "../stubs"`). **No lo priorizaría.**
- **`resources/js/app.js` vacío:** genera un bundle de 0 bytes, y sigue como input en [vite.config.js](../../vite.config.js). No se carga en el layout. Es limpieza, no rendimiento.
- **`public/favicon.ico` pesa 0 bytes:** no afecta al score, pero el navegador lo pide en cada carga y no muestra ningún ícono.
- **Cloudflare Insights:** 10 KB con caché de solo un día. Lighthouse lo marca, pero el ahorro es mínimo. Se quita desde el dashboard de Cloudflare si no lo usas.

## El score local no sirve para esto

La misma home da **52-56 en local y 88-91 en producción**. Casi toda la diferencia es del entorno, no de tu código:

| | Local (`unserialize.test`) | Producción |
|---|---|---|
| Compresión | Ninguna | Brotli / gzip |
| Protocolo | HTTP/1.1 | HTTP/2 y HTTP/3 |
| Caché de `/build` | Ninguno | 1 año |
| Livewire | `livewire.js`, 392 KB | `livewire.min.js`, 58 KB comprimido |
| Peso total | 1,762 KB | 395 KB |

Por eso, sin fuentes el local apenas cambia (57/57/57) y sus recomendaciones de ahorro están infladas (por ejemplo, "5,260 ms" de render-blocking).

El CLS de 0.117 que aparece en local tampoco es un hallazgo real: da 0 en producción y también en local con throttling real.

**Para comparar antes y después de un fix:** usa una rama desplegada o la misma configuración en ambas corridas, y mira TBT y LCP en lugar del score absoluto.

## Orden recomendado

1. **Cargar Shiki de forma diferida.** Es el fix que mueve el score: de ~81 a un máximo de ~95.
2. **Alojar las fuentes en la app.** Sube poco el score, pero elimina dos terceros y aclara el tema de privacidad.
3. **Volver a correr PageSpeed** y decidir si Livewire/Flux todavía merece trabajo.

## Resultado tras implementar 1 y 2 (solo en local)

Implementados el 2026-11-05, sin desplegar. Home en móvil, mismo entorno local antes y después, 3 corridas:

| | Antes | Después |
|---|---:|---:|
| Score | 52-56 | 61 |
| TBT | 262-339 ms | 0 ms |
| LCP | 8.1-11.4 s | 7.1 s |
| Peso | 1,762 KB | 1,047 KB |
| Orígenes | 3 | 1 |

El CLS simulado sigue en local (0.117 → 0.128) y da 0 con throttling real. Hay que confirmarlo en producción. En pantallas donde el snippet PHP queda a menos de 200 px de la vista, Shiki sí carga al entrar; el TBT en desktop sigue en 26 ms.

Decisiones tomadas: Inter variable limitada a 400-600 para que el logo se vea igual, `latin` + `latin-ext` bajo demanda, y sin Geist Mono 500. Cirílico, griego y vietnamita usan la fuente del sistema.

## Cómo repetir las mediciones

Lighthouse quedó instalado global en `~/.local/share/lerd/node-global` (no toca `package.json`).

```bash
lighthouse https://unserialize.dev/ --only-categories=performance --view
```

Para medir con CPU como PageSpeed, añade `--throttling.cpuSlowdownMultiplier=8`. Para probar sin un recurso, añade `--blocked-url-patterns='*fonts.bunny.net*'`. En local (`unserialize.test`) añade también `--chrome-flags="--ignore-certificate-errors"`.

## Límites de este análisis

- Son mediciones de laboratorio con 3 corridas por escenario. Las diferencias de 1-2 puntos son ruido.
- Bloquear un recurso no equivale al fix real: las fuentes alojadas en la app se siguen descargando (ahora 82 KB, ~58 KB con Inter variable) y Shiki se seguirá ejecutando cuando el usuario haga scroll.
- Sin datos de campo de CrUX no sé cómo lo viven los usuarios reales. Las cifras no incluyen INP.
