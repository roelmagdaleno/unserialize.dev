# WordPress serialized data: read, convert and edit it safely

> Where WordPress stores serialized PHP, how to read it as a PHP array or as JSON, and how to change it without breaking it.

WordPress stores many settings as serialized PHP: plugin options, widget settings, theme modifications and a lot of post meta. The values look like `a:3:{s:10:"show_title";b:1;…}` in the database.

## Where WordPress stores serialized data

When code passes an array to `update_option()`, `update_post_meta()`, `update_user_meta()` or `update_term_meta()`, WordPress serializes it before writing it. You find the result in these columns:

- `wp_options.option_value`: plugin settings, `widget_*` options, `theme_mods_*` and transients.
- `wp_postmeta.meta_value`, `wp_usermeta.meta_value` and `wp_termmeta.meta_value`: structured meta saved by plugins and themes.

Scalars such as a single string or number are usually stored as plain text. Only arrays and objects are serialized.

## Read a serialized value

With WP-CLI, ask for the option as JSON. WP-CLI unserializes it for you:

```bash
wp option get my_plugin_settings --format=json
```

With a read-only database client, select the raw value:

```sql
SELECT option_value FROM wp_options WHERE option_name = 'my_plugin_settings';
```

The query returns the serialized string. This is the example used in the rest of this guide:

```text
a:3:{s:10:"show_title";b:1;s:14:"posts_per_page";i:10;s:8:"home_url";s:19:"https://example.com";}
```

## Read serialized data as a PHP array

Inside WordPress, `get_option()` and `get_post_meta()` already return the unserialized array. When you only have the raw string, use `maybe_unserialize()`, which leaves non-serialized values alone. Outside WordPress, call `unserialize()` and forbid classes, so the value can never create an object:

```php
$settings = unserialize($raw, ['allowed_classes' => false]);

// [
//     'show_title' => true,
//     'posts_per_page' => 10,
//     'home_url' => 'https://example.com',
// ]
```

Never call `unserialize()` without `allowed_classes` on a value that came from outside your own code. A serialized value can name any class, and PHP will build it.

## Convert it to JSON

To read a value quickly, paste it into the [PHP unserialize to JSON converter]({{ url('/') }}/). The example above becomes:

```json
{
    "show_title": true,
    "posts_per_page": 10,
    "home_url": "https://example.com"
}
```

Redact secrets, API keys and personal data before you paste a value into any web tool. The converter does not store what you paste, but a value that holds no secrets is the safest one to share.

## Edit serialized data safely

**Back up the database before making changes.** Then change the value through PHP, so the lengths are calculated for you:

- In code: read the array with `get_option()`, change it, and save it with `update_option()`.
- With WP-CLI: `wp option patch update my_plugin_settings posts_per_page 20` changes one key in place.
- For a URL or domain change across the database: `wp search-replace`, which unserializes each value, replaces the text and serializes it again.

If you need a serialized string for a fixture or a migration, generate it with PHP's `serialize()` instead of writing it by hand, so every length is counted for you.

## Why a plain search and replace breaks it

Every string in a serialized value carries its length in bytes. In `s:19:"https://example.com";` the 19 must match the text exactly. A SQL `REPLACE()` that turns the URL into `https://staging.example.com` changes the text to 27 bytes but leaves the 19, and PHP can no longer read the whole value. WordPress then treats the option as missing and the plugin falls back to its defaults.

Read [how to fix a broken serialized string]({{ route('guides.broken-serialized-string') }}) to find and repair a value that is already damaged.
