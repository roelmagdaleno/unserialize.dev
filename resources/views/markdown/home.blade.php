# Unserialize — PHP Unserialize to JSON Converter

> Convert PHP serialized data into clean, readable JSON. Conversions are processed in memory: the submitted value and the JSON result are not stored or logged.

- Converter: {{ url('/') }}/
- Privacy and retention: {{ route('privacy') }}
- API and MCP developer guide: {{ route('developers') }}
- OpenAPI 3.1 description: {{ route('openapi') }}

## How to convert PHP serialized data

Paste a value produced by PHP's `serialize()` function into the converter, select **Unserialize**, then review or copy the readable JSON result.

1. Copy the complete serialized value, including its type markers, lengths, and delimiters.
2. Paste it into the editor and click on **Unserialize**.
3. Use the JSON result to inspect the structure without editing the original value by hand.

Agents can convert without the browser form by posting to the JSON API or calling the MCP tool. See {{ route('developers') }}.

## Why I built Unserialize

I kept running into serialized data while working with WordPress. The online tools I used could decode it, but they did not turn the result into clean JSON, so the data was still harder to read than it needed to be.

I built Unserialize with modern web technology to make that everyday task simpler for me—and for other developers who run into the same problem. It turns PHP serialized values into a familiar, readable format without storing the submitted input or result.

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

### Tested example

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

Sequential integer keys become JSON arrays; associative keys become JSON objects. Null, booleans, integers, floats, strings, arrays, and nested combinations are supported. Serialized objects are rejected.

## Working with WordPress serialized data

WordPress stores some arrays and structured settings as serialized PHP in options and metadata. Unserialize makes those values easier to inspect as JSON, whether they come from WP-CLI, a database export, or a read-only database client.

**Back up the database before making changes.** Redact secrets and personal data before pasting a value into any web tool. If you need to update it, use WordPress APIs or WP-CLI so PHP recalculates string lengths; never edit those lengths manually.

## Security and privacy

Treat serialized data as untrusted input. This converter calls PHP with `allowed_classes` set to `false`, rejects decoded objects, limits input to 262,144 bytes, and caps decoding depth.

Conversions are processed in memory and the submitted value and JSON result are not stored or logged. Technical usage metadata such as your User-Agent and the request URL is kept locally for a limited period. Even so, remove passwords, tokens, email addresses, private URLs, and other sensitive information before submitting data. Read the [privacy and retention details]({{ route('privacy') }}) for the complete policy.
