# Unserialize Online – PHP Unserialize to JSON Converter

> Convert PHP serialized data into clean, readable JSON. Conversions are processed in memory: the submitted value and the JSON result are not stored or logged.

- Converter: {{ url('/') }}/
- Privacy and retention: {{ route('privacy') }}
- API and MCP developer guide: {{ route('developers') }}
- OpenAPI 3.1 description: {{ route('openapi') }}

## How to convert PHP serialized data to JSON

Paste a serialized string produced by PHP's `serialize()` function into the converter, click **Unserialize**, then review or copy the readable JSON result. You see the decoded structure without running PHP yourself.

1. Copy the complete serialized value, including its type markers, lengths, and delimiters.
2. Paste it into the editor and click **Unserialize**.
3. Use the JSON result to inspect the structure without editing the original value by hand.

### Convert it in your own PHP code

Call `unserialize()` with `allowed_classes` set to `false`, then pass the result to `json_encode()`. `unserialize()` returns `false` when the value is invalid.

```php
$value = unserialize($serialized, ['allowed_classes' => false]);

echo json_encode($value, JSON_PRETTY_PRINT);
```

Need the PHP array instead of JSON? Pass the unserialized value to `print_r()` or `var_export()` in place of `json_encode()`. The WordPress guide explains how to [read serialized data as a PHP array]({{ route('guides.wordpress-serialized-data') }}#php-array).

Developers and AI agents can also convert without the browser form by posting to the JSON API or calling the MCP tool. See {{ route('developers') }}.

## PHP serialization format

PHP's [`serialize()` function](https://www.php.net/manual/en/function.serialize.php) represents a value using compact type markers plus length or item-count information. For example, `s:5:"hello";` is a five-byte string and `i:42;` is an integer.

| PHP value | Serialized | JSON |
| --- | --- | --- |
| Null | `N;` | `null` |
| Boolean | `b:1;` | `true` |
| Integer | `i:42;` | `42` |
| Float | `d:3.5;` | `3.5` |
| String | `s:5:"hello";` | `"hello"` |
| Indexed array | `a:2:{i:0;s:3:"red";i:1;s:4:"blue";}` | `["red", "blue"]` |
| Associative array | `a:1:{s:4:"name";s:3:"Ada";}` | `{"name": "Ada"}` |
| Reference | `a:2:{i:0;s:1:"x";i:1;R:2;}` | `["x", "x"]` |
| Object | `O:4:"User":1:{s:4:"name";s:3:"Ada";}` | `{"name": "Ada"}` |

### Example: serialized PHP array to JSON

Serialized PHP:

```text
a:2:{s:4:"name";s:6:"Chrome";s:6:"active";b:1;}
```

JSON:

```json
{
    "name": "Chrome",
    "active": true
}
```

Sequential integer keys become JSON arrays; associative keys become JSON objects. Null, booleans, integers, floats, strings, arrays, and nested combinations are supported. A reference (`R:`) becomes a copy of the value it points to. An object (`O:`) becomes a JSON object of its properties, private and protected ones included. Its class is never loaded, because unserializing a real object can run code; see Security and privacy below.

## Working with WordPress serialized data

WordPress serializes every array it saves. You find these values in `wp_options` (plugin settings, `widget_*` options and `theme_mods_*`) and in the `meta_value` column of `wp_postmeta`, `wp_usermeta` and `wp_termmeta`. WordPress reads them back with `maybe_unserialize()`, which leaves values that are not serialized alone.

Paste a value from WP-CLI or a read-only database client into the converter to inspect it as JSON. Never change a serialized value with a plain SQL search and replace: each string stores its length in bytes, so new text with the old length breaks the value. Use `wp search-replace`, which re-serializes each value for you.

Read the [WordPress serialized data guide]({{ route('guides.wordpress-serialized-data') }}) to read it as a PHP array and edit it safely, or [fix a broken serialized string]({{ route('guides.broken-serialized-string') }}).

**Back up the database before making changes.** Redact secrets and personal data before pasting a value into any web tool.

## Security and privacy

Treat serialized data as untrusted input. This converter calls PHP with `allowed_classes` set to `false`, reads every object as plain data without loading its class, rejects custom-serialized objects and enums, limits input to 262,144 bytes, and caps decoding depth.

The submitted value and JSON result are processed in memory and never stored or logged. Still, remove passwords, tokens and personal data before submitting. Read the [privacy and retention details]({{ route('privacy') }}) for the technical metadata that is kept.

## Why I built Unserialize

I kept running into serialized data while working with WordPress. The online tools I used could decode it, but they did not turn the result into clean JSON, so the data was still harder to read than it needed to be.

I built Unserialize with modern web technology to make that everyday task simpler for me—and for other developers who run into the same problem. It turns PHP serialized values into a familiar, readable format without storing the submitted input or result.

— Roel Magdaleno Ramón ([GitHub](https://github.com/roelmagdaleno), [X](https://x.com/Roel7nxju0))

## Frequently asked questions

@foreach (app(\App\Data\FrequentlyAskedQuestions::class)->all() as $question)
### {!! $question['question'] !!}

{!! $question['markdown'] !!}

@endforeach
Unserialize is open source. Read the code, report an issue, or send a patch at [github.com/roelmagdaleno/unserialize](https://github.com/roelmagdaleno/unserialize.dev).
