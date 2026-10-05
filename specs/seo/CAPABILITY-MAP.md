# Capability Map: SEO growth from Search Console data

Source data: Google Search Console export, web search, 2026-07-04 → 2026-10-03.

## Baseline (do not edit; every module measures against this)

| Metric | Value |
| --- | --- |
| Clicks / impressions (3 months) | 20 / 2,705 |
| CTR / average position | 0.74% / ~12 |
| Indexed pages with impressions | 1 (`/`; `/privacy` has 1 impression) |
| `unserialize` | 820 impr., pos. 8.86, CTR 0.61% |
| `unserialize online` | 416 impr., pos. 11.92, 0 clicks |
| `php unserialize online` | 119 impr., pos. 8.86 |
| `unserialise` + `unserialised` | 63 impr., pos. ~8.3, 0 clicks |
| `unserialize json` + `json unserialize online` | 53 impr., pos. ~8.3, 0 clicks |
| `serialize online` / `php serialize online` / `online serialize` | 14 impr., pos. 41 / 19 / 45 |
| `wordpress serialized data to array` / `unserialize array` | 4 impr., pos. 30 / 21.5 |
| Monthly position (weighted) | Jul 14.7 · Aug 14.8 · Sep 9.3 |

## Modules

| Module id | Responsibility | Depends on |
| --- | --- | --- |
| `title-online` | Home `<title>`, visible heading copy and meta description target "unserialize online" | — |
| `faq-vocabulary` | Home FAQ section, `FAQPage` JSON-LD, natural coverage of spelling and synonym variants | `title-online` (same files; lands after to avoid churn) |
| ~~`serialize-tool`~~ | **Closed 2026-10-05.** Built, then removed before commit: not needed | — |
| ~~`array-output`~~ | **Closed 2026-10-05.** Usage data showed only JSON output was used; array queries are covered by the WordPress guide in `long-tail-guides` | — |
| `long-tail-guides` | Focused, indexable guide pages for WordPress data and broken serialized strings | — |
| `external-authority` | Off-site link and listing work; no application code | `long-tail-guides` (soft: more link targets) |

Arrows point one way; no module needs another's code.

## Build order

1. `title-online` (smallest, highest leverage, fastest signal)
2. `faq-vocabulary`
3. `long-tail-guides`
4. `external-authority` (MCP registry listing can start now; posts start once 3 is live)

## Cross-cutting contract for any new indexable page

Every new public page must ship all of these in the same change, or it is not done:

- Route in `routes/web.php` with `->name()` and `->withHead(title, description, canonical, og)` built with `$pageUrl`.
- Markdown representation (`resources/views/markdown/<page>.blade.php`) wired through `NegotiateMarkdownRepresentation`.
- Entry in `resources/views/sitemap.blade.php`, `resources/llms.txt` and the layout footer.
- Rows added to the `MetadataTest`, `MarkdownNegotiationTest` and `SitemapTest` datasets.

## Measurement

Record the deploy date of each module in its plan file. Compare 28 days before against 28 days after in Search Console (Performance → Compare), filtered by the module's target queries. Treat changes under ~100 impressions as noise.
