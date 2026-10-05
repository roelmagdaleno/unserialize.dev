# Fix a broken PHP serialized string

> The three usual reasons `unserialize()` fails on a stored value, what each one looks like, and how to repair it.

PHP's `unserialize()` gives up on the whole value when one part is wrong. PHP only warns `unserialize(): Error at offset 45 of 78 bytes`, which points at where reading stopped rather than where the value went wrong, and in WordPress the option silently falls back to its defaults.

To find the problem in your own value, paste it into the [PHP unserialize converter]({{ url('/') }}/). When a value cannot be read, it marks the exact byte where the problem starts and, when it can, suggests the correction. The messages below are the converter's real output for each example.

## 1. A string length changed after a search and replace

This is the most common cause. A database export was moved from `https://example.com` to a staging domain with a plain text replace, so the text changed but its declared length did not:

```text
a:2:{s:8:"home_url";s:19:"https://staging.example.com";s:10:"show_title";b:1;}
```

The converter reports:

> The string starting at byte 20 declares 19 bytes but 27 bytes precede the closing quote. Change `s:19:` to `s:27:`.

**Fix it:** for a single value, correct the length as suggested. For a whole database, restore the backup and run the change again with `wp search-replace 'https://example.com' 'https://staging.example.com' --dry-run`, then without `--dry-run`. WP-CLI rewrites serialized values with the right lengths.

## 2. Characters were counted instead of bytes

Lengths count bytes in the stored encoding, not characters. In UTF-8, `é` takes two bytes, so a hand-written value or a script that used `mb_strlen()` declares one byte too few:

```text
a:1:{s:4:"city";s:9:"Querétaro";}
```

The converter reports:

> The string starting at byte 16 declares 9 bytes but 10 bytes precede the closing quote. Change `s:9:` to `s:10:`.

**Fix it:** correct the length, and change the code that produced it to call `serialize()` on the array, or `strlen()` rather than `mb_strlen()` if it must build strings by hand. The same mismatch appears when a database connection converts the text to a different character set, such as `latin1` to `utf8mb4`, after it was serialized.

## 3. The value was truncated

A column that is too short, an export cut off at a size limit, or a copy and paste that missed the end leaves a value without its closing brace:

```text
a:2:{i:0;s:3:"php";i:1;s:9:"wordpress";
```

The converter reports:

> The array starting at byte 0 is missing its closing brace.

**Fix it:** restore the complete value from a backup or from the source that wrote it. If the cut happened inside a string, the end of the data is gone: adding a brace or changing a length only hides the loss. Check the column type too; a `VARCHAR(255)` column will truncate again, so `LONGTEXT` is the usual choice.

## Prevent it next time

- Never edit serialized values with SQL `REPLACE()` or a text editor's find and replace.
- Use `wp search-replace` for domain changes and `update_option()` or `wp option patch` for settings.
- Generate serialized strings for fixtures with PHP's `serialize()`, which counts bytes for you.
- Back up the database before any bulk change.

For more on where these values live and how to read them, see [WordPress serialized data]({{ route('guides.wordpress-serialized-data') }}).
