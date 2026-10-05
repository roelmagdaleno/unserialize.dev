# Spec: external-authority

## Objective

Raise the site's authority and discovery so that it moves from positions 8–12 to the top 5 for "unserialize". On-page work (the other modules) makes the page relevant. Older competing tools win on links and mentions. This module is listings and posts. **It changes no application code**, except one optional DNS record outside the repo.

The audience is PHP, Laravel and WordPress developers, and AI agents that discover tools through MCP registries and `llms.txt`.

## Decisions (2026-10-05)

- Channels: **X**, **daily.dev** and possibly **Reddit**. No Stack Overflow, Hacker News or Product Hunt.
- The MCP server is not yet in any registry. It goes to the official MCP Registry first.
- Articles are published on the author's own blog, **https://roelmagdaleno.com** (Astro 7, local at `/Users/roelmagdaleno/Code/Roel/roelmagdaleno.com`, not yet deployed). A link from the author's own domain is a real, followed link, and the blog's RSS feed is how daily.dev picks up posts once the blog is accepted as a source.

## Assumptions

1. Only genuine, relevant placements. No paid links, link exchanges or mass directory submissions.
2. Links from X, Reddit and daily.dev are `nofollow`/`ugc`, so they pass little ranking value directly. Their value is traffic, discovery and earning editorial links from people who see the post. The direct-link channels are the GitHub repo, the MCP registries and curated lists.
3. daily.dev does not accept arbitrary link posts in the main feed. Content reaches it through Squads (public communities where members post links) or through a blog that daily.dev indexes as a source.
4. Every placement links to the most relevant URL (`/`, a guide or `/developers`).
5. The author posts from their own accounts. The agent drafts text; the human publishes.

## MCP registry recommendation

1. **Official MCP Registry** (`registry.modelcontextprotocol.io`). This is the canonical directory. Other directories and clients read from it, so one listing propagates.
   - Name: `dev.unserialize/unserialize`. It already matches `App\Mcp\ServerCard::IDENTIFIER`, so the registry entry and the server card agree.
   - Namespace ownership: verify the `unserialize.dev` domain with a DNS TXT record (no code change). The alternative `io.github.roelmagdaleno/unserialize` needs only a GitHub login, but it would not match the server card identifier.
   - Publish with the `mcp-publisher` CLI (`init`, `login`, `validate`, `publish`, `status`) using a `server.json` that declares the remote streamable-HTTP endpoint (`https://unserialize.dev/mcp/unserialize`).
2. **Curated lists**: a pull request to a well-maintained "awesome MCP servers" list on GitHub. The link is public and points at `/developers`.
3. **Aggregators** that index the official registry (for example PulseMCP or Glama): check whether the listing appears automatically before submitting manually.

## Requirements

| # | Channel | Target URL | Asset |
| --- | --- | --- | --- |
| 1 | GitHub repo `roelmagdaleno/unserialize.dev`: description, topics, homepage field and a README link | `/` | — |
| 2 | Official MCP Registry, then one curated list | `/developers` | `server.json`, DNS TXT record |
| 3 | Article on roelmagdaleno.com (English): "Why `unserialize()` on untrusted input is dangerous, and how this converter avoids it", also shared to a PHP or web-dev daily.dev Squad | `/` | Draft by agent in `content/blog/` |
| 4 | Article: "Fixing broken serialized data after a WordPress domain change" (same distribution) | broken-string guide | Needs `long-tail-guides` |
| 5 | X: a short thread per launch (each guide, the MCP listing) with a screenshot or GIF of the tool | the launched URL | Drafts by agent |
| 6 | (Maybe) Reddit: one helpful post in r/PHP or r/Wordpress per launch that follows each subreddit's self-promotion rules, plus answers in threads where the tool solves the question | the relevant URL | Drafts by agent |

## Blog readiness (roelmagdaleno.com)

What the blog already has (checked 2026-10-05): `site` set to `https://roelmagdaleno.com`, canonical URL, Open Graph and Twitter tags, `@astrojs/sitemap`, an RSS feed at `/rss.xml` with `@astrojs/rss`, a typed `blog` collection (`title`, `description`, `pubDate`, `updatedDate`, `lang`, `tags`, `draft`, `heroImage`) and `npm run new`.

Gaps to close before the first unserialize article goes live:

| Gap | Why it matters |
| --- | --- |
| The project is not a git repository | No history or rollback, and most hosts deploy from a repo |
| Not deployed; no host or DNS configured | Nothing can be linked or indexed yet |
| No default `og:image`; posts without `heroImage` get a `summary` card | X posts are the main distribution channel and need a large image card |
| No `BlogPosting` JSON-LD (headline, dates, author with `sameAs` GitHub/X, `image`) | Helps search engines and AI attribute the article to the author |
| No `article:modified_time` from `updatedDate` | Freshness signal |
| No `twitter:site`/`twitter:creator` (`@roelmagdaleno`) | Card attribution on X |
| RSS `<language>` is hard-coded `en-us`, but posts can be `es` | Mixed-language feed; daily.dev favours English sources |
| `content/blog/por-que-deje-wordpress-para-mi-blog 1.md` looks like an accidental duplicate | It builds as a second post with the same content |
| The blog needs a few published English posts before requesting a daily.dev source | daily.dev reviews source requests; check its current criteria when requesting |
| No Search Console property for roelmagdaleno.com | Needed to verify indexing of the articles |

The articles are written in English, to match unserialize.dev's audience and daily.dev.

## Commands

```
brew install mcp-publisher
mcp-publisher init        # generates server.json; edit the name, description and remote URL
mcp-publisher login dns --domain unserialize.dev ...   # confirm the exact flags with `mcp-publisher login --help`
mcp-publisher validate
mcp-publisher publish
mcp-publisher status
```

## Project Structure

- `server.json`: publish metadata for the registry. Whether it lives in the repo root is an **Ask first** item. Recommended: commit it, so the version stays in sync with `UnserializeServer::VERSION`.
- The tracker is the "Log" table in this file.

## Testing Strategy

No code. Each placement is verified by visiting it and checking that the link resolves. The registry listing is verified with `mcp-publisher status` and a registry API search for `dev.unserialize`.

## Boundaries

- Always: disclose authorship; answer the actual question first; follow each subreddit's rules.
- Ask first: every public post (the human publishes); committing `server.json`; any DNS change; any edit in the roelmagdaleno.com project (it is a separate project, outside this repo).
- Never: buy links, post identical text across communities, or publish a registry entry whose name differs from the server card identifier.

## Success Criteria

- 30 days: the server is listed in the official MCP Registry under `dev.unserialize/unserialize` and in one curated list.
- 90 days: ≥ 3 placements live from channels 3–6, and the referring domains in Search Console → Links grow against the T1 baseline.
- 90 days: `unserialize` average position ≤ 6.

## Open Questions

1. ~~Blog~~ Resolved: roelmagdaleno.com (Astro).
2. Where will roelmagdaleno.com be hosted (Cloudflare Pages, Netlify, Vercel, other)?
3. Delete the duplicate `por-que-deje-wordpress-para-mi-blog 1.md`?
4. Commit `server.json` to the repo (recommended) or keep it outside?

## Log

| Date | Channel | URL placed | Status |
| --- | --- | --- | --- |
| | | | |

## Implementation notes (2026-10-05)

- The two articles are written as drafts (`draft: true`, no `heroImage`) in the blog's `content/blog/`: `why-unserialize-on-untrusted-input-is-dangerous.md` (~1,230 words) and `fixing-broken-serialized-data-after-a-wordpress-domain-change.md` (~1,000 words). No technical file of the blog was changed.
- The second article quotes diagnostics returned by the unserialize MCP tool and links to `/guides/fix-broken-serialized-string`, which now exists.
- Still open: committing `server.json`, the registry listing, the GitHub repo settings and the posts on X, daily.dev and Reddit. These are the author's actions.
