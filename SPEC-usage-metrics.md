# Spec: Historical Conversion Usage Metrics

## Status

Draft for review. Implementation must not begin until this specification is approved.

## Objective

Persist a durable, privacy-preserving history of conversion usage so the application owner can answer:

- When was the application last used successfully?
- How many conversion attempts and successful conversions occurred during a date range?
- Which interface handled them: browser, API, or MCP?
- Which bounded outcome categories are increasing over time?

Nightwatch remains the short-retention diagnostic system for individual executions. A local SQLite database on the Forge-managed VPS becomes the long-retention source for aggregate usage metrics.

The feature measures completed server-side conversion executions. A successful record means Laravel produced a valid result for the caller; it does not prove that a client received, rendered, or read the response after it left the server.

## User Story

As the application owner, I want to inspect historical conversion counts and the most recent successful usage without retaining submitted serialized data or converted JSON, so I can understand whether and how the application is being used beyond Nightwatch's retention period.

## Scope

### In scope

- Record one aggregate observation for every existing `conversion.completed` outcome.
- Aggregate observations by UTC calendar day, interface, and outcome.
- Atomically increment the matching aggregate row under concurrent requests.
- Retain the latest occurrence timestamp for each aggregate row.
- Continue sending the existing structured event to Laravel logs and Nightwatch.
- Provide a read-only Artisan command for summary and latest-use queries.
- Store metrics in the application's existing SQLite database on persistent VPS storage.
- Define an off-server, SQLite-consistent backup requirement.

### Out of scope

- Saving submitted serialized values or converted output.
- Identifying, authenticating, fingerprinting, or counting unique users.
- Saving IP addresses, IP hashes, cookies, session identifiers, request IDs, user agents, referrers, URLs, or MCP client metadata.
- A public or authenticated web analytics dashboard.
- Replacing Nightwatch, Cloudflare Web Analytics, or Google Analytics.
- Moving the application database to Cloudflare D1.
- Removing the legacy `outputs` table, model, routes, or migrations as part of this feature.
- Guaranteeing that a remote client read the response.
- Historical latency percentiles. Nightwatch remains responsible for short-term trace and latency analysis.

## Tech Stack

- PHP 8.4
- Laravel 13
- SQLite through Laravel's configured database connection
- Pest 5 for automated tests
- Laravel Nightwatch 1.x for recent structured logs and request traces
- Laravel Forge for VPS management and deployment

Implementation must use the versions installed in the repository rather than relying on version assumptions.

## Domain Language

- **Conversion attempt:** An execution that reaches an already-instrumented conversion outcome, including validation and rate-limit outcomes.
- **Successful conversion:** An attempt whose outcome is `success` after the server has produced the converted value.
- **Interface:** One of `browser`, `api`, or `mcp`, as defined by `ConversionInterface`.
- **Outcome:** `success`, `rate_limited`, `validation_error`, or an existing bounded conversion error code.
- **Usage aggregate:** A daily counter for one interface and one outcome.
- **Latest successful use:** The greatest `last_occurred_at` among aggregates whose outcome is `success`.

## Functional Requirements

### Recording

1. Every call to the existing conversion telemetry boundary must continue to emit `conversion.completed` to Laravel's logger with its current bounded context.
2. The same call must increment exactly one aggregate identified by:
   - UTC date of occurrence;
   - conversion interface;
   - outcome.
3. The increment and latest timestamp update must be atomic. Concurrent requests must not lose increments or create duplicate aggregates.
4. `last_occurred_at` must be updated to the most recent occurrence timestamp for that aggregate.
5. Metrics persistence must not change the public Web, API, or MCP response contracts.
6. A metrics write failure must not turn a successful conversion into a user-facing failure. It must be reported through the normal error-reporting path without including request content.
7. Metrics persistence must be independent of Nightwatch request sampling. A sampled-out Nightwatch trace must still increment the SQLite aggregate.

### Querying

The application must provide a read-only Artisan command with these capabilities:

- Show the latest successful use overall.
- Show the latest successful use for each interface.
- Summarize counts grouped by day, interface, and outcome for a requested date range.
- Optionally restrict the summary to one interface or one outcome.
- Produce a human-readable table by default and JSON when explicitly requested.
- Return an empty, successful result when no matching metrics exist.
- Reject invalid dates, inverted date ranges, interfaces, and outcomes with a non-zero exit code and a useful message.

The command must not expose raw SQL or permit mutations.

## Data Model

Table: `conversion_metrics`

| Column | Type | Constraints | Purpose |
|---|---|---|---|
| `id` | integer | primary key | Laravel row identity |
| `date` | date | required | UTC aggregation date |
| `interface` | string | required | `browser`, `api`, or `mcp` |
| `outcome` | string | required | Bounded outcome category |
| `count` | unsigned big integer | required, default `0` | Number of occurrences |
| `last_occurred_at` | timestamp | required | Most recent occurrence in the aggregate |
| `created_at` | timestamp | required | Row creation time |
| `updated_at` | timestamp | required | Last aggregate update time |

Required database constraint:

```text
UNIQUE(date, interface, outcome)
```

The implementation must treat the database constraint, rather than an application-level pre-check, as the concurrency boundary.

No event-level table is permitted by this specification.

## Privacy and Security

The persisted field allowlist is exactly:

```text
date
interface
outcome
count
last_occurred_at
created_at
updated_at
```

No value derived from the serialized payload may be persisted. In particular, the existing input-size bucket and diagnostic category may remain in short-lived operational logs but are not part of the durable aggregate.

The reporting command is an administrative server-side command. This feature must not add a public HTTP reporting endpoint.

Database backups must be encrypted in transit and access-restricted to the application owner or server administrator.

## Reliability and Retention

- Aggregate rows have no automatic expiration.
- Metrics are best-effort operational data: conversion availability takes priority over metrics persistence when the database is unavailable.

## Commands

Commands used during implementation and verification:

```bash
php artisan make:model ConversionMetric --migration --no-interaction
php artisan make:command UsageSummaryCommand --no-interaction
php artisan migrate --no-interaction
php artisan test --compact tests/Unit/ConversionTelemetryTest.php
php artisan test --compact tests/Feature/UsageSummaryCommandTest.php
vendor/bin/pint --dirty --format agent
```

Before creating files, their exact Artisan options must be confirmed with each command's `--help` output.

The final reporting command name and arguments must be documented in its Artisan signature and tested. The intended public shape is:

```bash
php artisan usage:summary
php artisan usage:summary --from=2026-09-01 --to=2026-09-30
php artisan usage:summary --interface=api --outcome=success
php artisan usage:summary --json
```

## Project Structure

Expected locations, subject to established sibling conventions:

```text
app/Models/ConversionMetric.php
    Eloquent representation of a daily aggregate.

app/Services/ConversionTelemetry.php
    Existing boundary that emits Nightwatch-compatible logs and records aggregates.

app/Console/Commands/UsageSummaryCommand.php
    Read-only owner-facing historical report.

database/migrations/*_create_conversion_metrics_table.php
    Aggregate schema and composite uniqueness constraint.

tests/Unit/ConversionTelemetryTest.php
    Recording, privacy allowlist, failure isolation, and atomic update behavior.

tests/Feature/UsageSummaryCommandTest.php
    CLI filtering, output, validation, latest-use, and empty-state behavior.
```

No new top-level source directory is required.

## Code Style

Use explicit types, constructor property promotion, descriptive names, and curly braces. Keep the telemetry boundary small and do not pass request objects or unbounded arrays into persistence code.

Illustrative contract only:

```php
public function record(
    ConversionInterface $interface,
    string $outcome,
    int $inputBytes,
    int $startedAt,
    ?SyntaxErrorCode $diagnostic = null,
): void {
    // Preserve the existing public method shape unless an approved plan requires otherwise.
}
```

Implementation details must follow existing application conventions and Laravel 13 documentation. Inline comments are reserved for non-obvious concurrency or privacy constraints.

## Testing Strategy

Use Pest and the existing Laravel test case. Read the project's `testing-best-practices` skill before writing tests.

Required regression coverage:

1. A first observation creates one aggregate with `count = 1`.
2. A second matching observation increments the same row and advances `last_occurred_at`.
3. Different dates, interfaces, and outcomes create distinct aggregates.
4. The composite uniqueness constraint prevents duplicate aggregate keys.
5. Successful Web, API, and MCP conversions each produce the correct interface aggregate.
6. Validation, conversion failure, and rate-limit outcomes are categorized correctly.
7. Durable rows contain only the specified allowlist of fields and never submitted or converted content.
8. A metrics persistence failure does not alter the caller's successful response.
9. The summary command filters inclusive date ranges correctly.
10. The summary command reports latest successful use overall and per interface.
11. Human-readable and JSON outputs contain equivalent aggregate values.
12. Invalid filters fail clearly and an empty result succeeds without fabricated data.

Run the narrowest affected test after each change. Once focused tests pass, request that the complete suite be run with:

```bash
php artisan test --compact
```

## Observability

The existing `conversion.completed` log remains the recent diagnostic signal and must retain its current privacy properties. Historical SQLite writes must not depend on that log being ingested by Nightwatch.

Metrics-persistence failures must be observable without recursively invoking the same metrics recorder. They must include only a stable event name and bounded failure classification, never the serialized input or converted result.

No alert should fire merely because a low-traffic application has no conversions. Alerts, if later added, should target persistence failures or sustained user-facing conversion errors.

## Boundaries

### Always do

- Use UTC for storage and date-range semantics.
- Use one atomic database operation per aggregate increment.
- Keep conversion responses available when metrics persistence fails.
- Preserve existing Web, API, MCP, and structured-log contracts.
- Run focused tests and Pint for modified PHP files.

### Ask first

- Add or change dependencies.
- Replace SQLite with MySQL, PostgreSQL, D1, or another external store.
- Add a web dashboard or HTTP reporting endpoint.
- Persist another metric dimension or event-level record.
- Change backup provider, frequency, or retention below the stated minimum.
- Delete or modify legacy `outputs` schema as part of this work.

### Never do

- Persist payloads, outputs, identifiers, IP information, user agents, request headers, or arbitrary log context.
- Derive a user identity or unique-user count from network metadata.
- Expose metrics publicly.
- Couple a successful conversion response to successful telemetry persistence.
- Use Nightwatch sampling as the source of historical counts.
- Commit production secrets or backup credentials.

## Success Criteria

The feature is complete when all of the following are true:

- Every instrumented conversion outcome updates exactly one daily aggregate.
- Concurrent observations do not lose counts or create duplicate aggregate keys.
- The latest successful usage can be retrieved overall and for each interface.
- Counts can be summarized for an inclusive UTC date range and filtered by interface or outcome.
- Browser, API, and MCP results and response schemas remain unchanged.
- No durable record contains submitted data, converted output, or an identifier capable of tracking a person or client.
- Nightwatch continues to receive the existing structured event independently of durable metrics.
- A failed metrics write is observable but does not fail the conversion.
- Focused automated tests pass and modified PHP files pass Pint.
