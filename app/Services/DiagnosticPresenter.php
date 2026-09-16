<?php

namespace App\Services;

use App\Data\SyntaxDiagnostic;

/**
 * Turns a diagnostic into the bounded, display-safe shape the browser renders.
 *
 * Both jobs belong here rather than in a view:
 *
 * - Windowing. The input may be 262,144 bytes, so only a slice around the
 *   problem reaches the page and the panel stays a fixed size.
 * - Byte sanitizing. Handing raw payload bytes to Blade would run them through
 *   `e()`, whose `ENT_SUBSTITUTE` flag replaces every invalid sequence with
 *   U+FFFD and shifts the highlighted span away from the byte offset the panel
 *   claims. Escaping here keeps the frame aligned with the number beside it.
 */
class DiagnosticPresenter
{
    /**
     * Bytes of context shown on each side of the highlighted span.
     */
    public const int WINDOW_BYTES = 200;

    /**
     * Longest span rendered in full before its middle is elided.
     */
    public const int MAX_SPAN_BYTES = 400;

    /**
     * Render one diagnostic for the browser panel.
     *
     * @return array{
     *     code: string,
     *     message: string,
     *     offset: int,
     *     length: int,
     *     suggestion: string|null,
     *     line: int|null,
     *     column: int|null,
     *     excerpt: array{
     *         before: string,
     *         span: string,
     *         after: string,
     *         truncatedStart: bool,
     *         truncatedEnd: bool,
     *         spanTruncated: bool,
     *         caret: bool
     *     }
     * }
     */
    public function present(SyntaxDiagnostic $diagnostic, string $data): array
    {
        [$line, $column] = $this->position($diagnostic->offset, $data);

        return [
            'code' => $diagnostic->code->value,
            'message' => $diagnostic->message,
            'offset' => $diagnostic->offset,
            'length' => $diagnostic->length,
            'suggestion' => $diagnostic->suggestion,
            'line' => $line,
            'column' => $column,
            'excerpt' => $this->excerpt($diagnostic, $data),
        ];
    }

    /**
     * Cut a bounded, sanitized window around the highlighted span.
     *
     * @return array{
     *     before: string,
     *     span: string,
     *     after: string,
     *     truncatedStart: bool,
     *     truncatedEnd: bool,
     *     spanTruncated: bool,
     *     caret: bool
     * }
     */
    private function excerpt(SyntaxDiagnostic $diagnostic, string $data): array
    {
        $length = strlen($data);
        $spanEnd = $diagnostic->offset + $diagnostic->length;

        $windowStart = max(0, $diagnostic->offset - self::WINDOW_BYTES);
        $windowEnd = min($length, $spanEnd + self::WINDOW_BYTES);

        $span = substr($data, $diagnostic->offset, $diagnostic->length);
        $spanTruncated = strlen($span) > self::MAX_SPAN_BYTES;

        if ($spanTruncated) {
            $half = intdiv(self::MAX_SPAN_BYTES, 2);
            $span = substr($span, 0, $half)."\u{2026}".substr($span, -$half);
        }

        /**
         * A zero-length span marks a position rather than a range, so it is
         * given one blank byte to paint. An empty mark would be invisible.
         */
        $caret = $diagnostic->length === 0;

        return [
            'before' => $this->sanitize(substr($data, $windowStart, $diagnostic->offset - $windowStart)),
            'span' => $caret ? ' ' : $this->sanitize($span),
            'after' => $this->sanitize(substr($data, $spanEnd, $windowEnd - $spanEnd)),
            'truncatedStart' => $windowStart > 0,
            'truncatedEnd' => $windowEnd < $length,
            'spanTruncated' => $spanTruncated,
            'caret' => $caret,
        ];
    }

    /**
     * Rewrite a byte run as guaranteed-valid UTF-8.
     *
     * Newlines and tabs stay literal so the excerpt keeps the line structure the
     * reported line and column refer to. Everything unprintable or not valid
     * UTF-8 becomes a `\xNN` escape: fixed width, searchable, and the notation
     * a PHP developer already reads in `var_dump` output. A character straddling
     * the span boundary is escaped on both sides, which is honest -- the offset
     * really does fall mid-character there.
     */
    private function sanitize(string $chunk): string
    {
        $safe = '';
        $position = 0;
        $length = strlen($chunk);

        while ($position < $length) {
            $byte = $chunk[$position];
            $ordinal = ord($byte);

            if ($byte === "\n" || $byte === "\t") {
                $safe .= $byte;
                $position++;

                continue;
            }

            if ($ordinal < 0x20 || $ordinal === 0x7F) {
                $safe .= sprintf('\x%02X', $ordinal);
                $position++;

                continue;
            }

            if ($ordinal < 0x80) {
                $safe .= $byte;
                $position++;

                continue;
            }

            $width = match (true) {
                $ordinal >= 0xF0 => 4,
                $ordinal >= 0xE0 => 3,
                $ordinal >= 0xC0 => 2,
                default => 0,
            };

            $candidate = $width > 0 ? substr($chunk, $position, $width) : '';

            if ($width > 0 && strlen($candidate) === $width && preg_match('//u', $candidate) === 1) {
                $safe .= $candidate;
                $position += $width;

                continue;
            }

            $safe .= sprintf('\x%02X', $ordinal);
            $position++;
        }

        return $safe;
    }

    /**
     * Locate a byte offset as a line and column, both counted in bytes.
     *
     * Single-line payloads report neither, because "line 1" adds nothing to an
     * offset the panel already shows.
     *
     * @return array{0: int|null, 1: int|null}
     */
    private function position(int $offset, string $data): array
    {
        if (! str_contains($data, "\n")) {
            return [null, null];
        }

        $prefix = substr($data, 0, $offset);
        $lastNewline = strrpos($prefix, "\n");

        return [
            substr_count($prefix, "\n") + 1,
            $lastNewline === false ? $offset + 1 : $offset - $lastNewline,
        ];
    }
}
