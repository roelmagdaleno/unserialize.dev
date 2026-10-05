# Spec: array-output

> **Status: closed (2026-10-05). Do not build.**

## Decision

The PHP array output is not reintroduced in any form, including a tab after conversion.

## Reason

The stored outputs showed that nobody used the array format; every conversion used JSON. The toggle was removed on 2026-09-13 (commit `37005b2`). `tests/Feature/UnserializeTest.php` keeps asserting that it is absent.

## Where the search demand goes instead

`unserialize array` and `wordpress serialized data to array` are covered as content: the WordPress guide in [SPEC-long-tail-guides.md](SPEC-long-tail-guides.md) has a section that shows how to read serialized data as a PHP array with `unserialize()`, `maybe_unserialize()` and WP-CLI. The converter UI does not change.
