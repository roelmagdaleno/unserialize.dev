# Plan: external-authority

Spec: [SPEC-external-authority.md](SPEC-external-authority.md)

## Approach

1. Start with what the human fully controls: the GitHub repo and the official MCP Registry. These can start today.
2. Publish content that earns links.
3. Announce each launch on X (and Reddit if wanted) once the launched page exists.

## Tasks

- [ ] **T1. Baseline**
  - Acceptance: the current referring domains and top linked pages are recorded from Search Console → Links.
  - Verify: numbers are written in the spec's Success Criteria.
  - Files: `specs/seo/SPEC-external-authority.md`.

- [ ] **T2. GitHub repository polish**
  - Acceptance: the repo has a description containing "PHP unserialize online", topics (`php`, `unserialize`, `serialization`, `wordpress`, `json`, `mcp`), the homepage field set to https://unserialize.dev and a README link near the top.
  - Verify: visit the repo page.
  - Files: `README.md` (only if the link is missing).

- [ ] **T3. Official MCP Registry listing**
  - Acceptance:
    - `server.json` declares `dev.unserialize/unserialize`, the description from `ServerCard::DESCRIPTION`, the version from `UnserializeServer::VERSION` and the remote endpoint.
    - The domain is verified by DNS TXT.
    - `mcp-publisher publish` succeeds.
  - Verify: `mcp-publisher status`; search the registry for `dev.unserialize`; log the entry.
  - Files: `server.json` (if committing it is approved).

- [ ] **T4. Curated MCP list**
  - Acceptance: a pull request to one maintained "awesome MCP servers" list, linking to `/developers`; check whether registry-fed aggregators already list the server.
  - Verify: PR merged or listing live; logged.
  - Files: none.

- [ ] **T5a. Blog readiness (roelmagdaleno.com)** — each item needs approval, because it edits a separate project
  - Acceptance:
    - The project is a git repository.
    - The duplicate post is removed (if approved).
    - A default `og:image` gives every page a `summary_large_image` card.
    - `BlogPosting` JSON-LD is emitted from `BlogPost.astro`, with the author `sameAs` GitHub and X.
    - `article:modified_time` and `twitter:site`/`twitter:creator` are set.
    - The RSS feed sets `<language>` correctly (English-only feed or per-item language).
  - Verify: `npm run build` (it runs `astro check`); the Rich Results Test on a built post page; the RSS feed validates.
  - Files: `src/components/BaseHead.astro`, `src/layouts/BlogPost.astro`, `src/pages/rss.xml.js`, `src/consts.ts`, `public/` (default social image).

- [ ] **T5b. Deploy roelmagdaleno.com and verify it**
  - Acceptance: the site is live on https://roelmagdaleno.com; the sitemap is submitted to a new Search Console property; `/rss.xml` is reachable.
  - Verify: URL Inspection shows the home page indexable.
  - Files: host configuration (depends on open question 2).

- [ ] **T5c. Security article and daily.dev distribution**
  - Acceptance:
    - The agent drafts `content/blog/why-unserialize-on-untrusted-input-is-dangerous.md` (English, about 1,200 words, `lang: en`, tags `php`, `security`, `wordpress`, a `heroImage`) with one contextual link to https://unserialize.dev/.
    - The human reviews and publishes it, and shares it in a relevant daily.dev Squad.
    - Once the blog has a few English posts, a daily.dev source request is submitted for its RSS feed.
  - Verify: published URL and Squad post logged; source request status logged.
  - Files: `content/blog/*.md` in the blog project.

- [ ] **T6. Launch posts on X (and optionally Reddit)** (one per launch: each guide, the MCP listing)
  - Acceptance: a short X thread with a visual for each launch; a Reddit post only where the subreddit's rules allow it.
  - Verify: URLs logged.
  - Files: none.

- [ ] **T7. WordPress broken-data article** (after `long-tail-guides` T5)
  - Acceptance: the article is published on roelmagdaleno.com, links to the broken-string guide, and is distributed like T5c.
  - Verify: URL logged.
  - Files: none.

- [ ] **T8. 90-day review**
  - Acceptance: referring domains, registry listing status and `unserialize` position compared against T1; decide whether to continue or change channels.
  - Verify: a summary row in the Log.
  - Files: `specs/seo/SPEC-external-authority.md`.
