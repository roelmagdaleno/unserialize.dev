<?php

namespace App\Data;

use Illuminate\Support\Str;

/**
 * The home page's frequently asked questions, written once and published as
 * HTML, Markdown and `FAQPage` structured data.
 *
 * - Answers are maintainer-authored Markdown. They are the only source the
 *   page renders unescaped, so request data must never reach this class.
 * - The structured data keeps only the tags Google accepts in answer text;
 *   any other tag is unwrapped and its text kept.
 */
class FrequentlyAskedQuestions
{
    /**
     * The HTML tags an `Answer` text may carry in structured data.
     *
     * @var list<string>
     */
    private const array STRUCTURED_DATA_TAGS = ['a', 'b', 'br', 'div', 'em', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'i', 'li', 'ol', 'p', 'strong', 'ul'];

    /**
     * Every question with its answer in Markdown and rendered HTML.
     *
     * @return list<array{question: string, markdown: string, html: string}>
     */
    public function all(): array
    {
        return array_map(
            fn (array $entry): array => [...$entry, 'html' => $this->toHtml($entry['markdown'])],
            $this->entries(),
        );
    }

    /**
     * Every question keyed to its answer, reduced to the tags structured data accepts.
     *
     * @return array<string, string>
     */
    public function forStructuredData(): array
    {
        return collect($this->all())
            ->mapWithKeys(fn (array $entry): array => [
                $entry['question'] => trim(strip_tags($entry['html'], self::STRUCTURED_DATA_TAGS)),
            ])
            ->all();
    }

    /**
     * The questions and their Markdown answers, in display order.
     *
     * @return list<array{question: string, markdown: string}>
     */
    private function entries(): array
    {
        return [
            [
                'question' => 'What does unserialize mean?',
                'markdown' => 'To unserialize (also spelled unserialise, or called deserializing) means turning a stored string back into the value it came from. PHP\'s `serialize()` writes arrays, strings, numbers and booleans as a compact string such as `a:1:{s:4:"name";s:3:"Ada";}`, and `unserialize()` reads that string back into an array. This converter does the same reading, but shows the result as JSON instead of a PHP value.',
            ],
            [
                'question' => 'Is PHP unserialize() the same as json_decode()?',
                'markdown' => 'No. Each function reads a different format. `json_decode()` reads JSON, such as `{"name":"Ada"}`. `unserialize()` reads PHP\'s own serialized format, such as `a:1:{s:4:"name";s:3:"Ada";}`, which stores type markers and byte lengths. If your value starts with `a:`, `s:`, `i:` or `b:`, it is serialized PHP: paste it above to get JSON. If it starts with `{` or `[`, it is already JSON.',
            ],
            [
                'question' => 'How do I convert PHP serialized data to JSON?',
                'markdown' => 'Paste the complete serialized value into the converter at the top of this page and select **Unserialize**. The JSON result appears below it, ready to copy. In your own code, call `unserialize($value, [\'allowed_classes\' => false])` and pass the result to `json_encode()`. Developers and AI agents can also convert through the [JSON API and MCP tool]('.route('developers').').',
            ],
            [
                'question' => 'Why does my serialized string fail to unserialize?',
                'markdown' => 'The most common cause is a wrong string length. In `s:5:"hello";` the 5 is the length in bytes, not characters, so `é` counts as 2. A database search-and-replace that changes a URL without updating its length breaks the value. Truncated values and missing semicolons or braces fail too. When a conversion fails here, the error panel shows the exact byte where the problem starts.',
            ],
            [
                'question' => 'Is it safe to unserialize untrusted data?',
                'markdown' => 'Not with PHP\'s default settings. A serialized value can name a PHP class, and `unserialize()` will create that object and run its magic methods, which can lead to code execution. Always pass `[\'allowed_classes\' => false]`, or use JSON for data you exchange. This converter never creates objects: it rejects serialized objects, limits input to 262,144 bytes, and caps nesting depth.',
            ],
        ];
    }

    /**
     * Render one maintainer-authored Markdown answer as HTML.
     */
    private function toHtml(string $markdown): string
    {
        return trim(Str::markdown($markdown, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]));
    }
}
