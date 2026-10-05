# Spec: serialize-tool

> **Status: closed (2026-10-05). Do not build.**

## Decision

The `/serialize` page (JSON → PHP serialized string) is not needed. It was implemented and then removed before any commit, together with everything that existed only for it:

- the `Serializer` Livewire component, form, views and Markdown page;
- the `JsonToSerialized` service;
- the `operation` telemetry dimension (`ConversionOperation` enum, migration, `usage:summary --operation`);
- FAQ question 6, the home ↔ serialize links, and its sitemap, `llms.txt`, footer and privacy entries.

## Where the search demand goes instead

The few `serialize online` impressions (pos. 19–45) are not targeted. The guides tell readers to generate serialized strings with PHP's own `serialize()`.
