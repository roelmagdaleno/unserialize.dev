<?php

namespace App\Data;

use App\Enums\DiagnosticConfidence;
use App\Enums\SyntaxErrorCode;
use App\Services\SerializedDiagnostics;

/**
 * One located problem inside a serialized payload.
 *
 * `message` and `suggestion` are assembled from fixed templates plus integers
 * and never interpolate a byte taken from the submitted value. That rule is
 * what lets both strings travel out through the HTTP API and the MCP tool
 * without contradicting the promise that submitted data is not returned.
 */
class SyntaxDiagnostic
{
    /**
     * @param  int  $offset  Byte offset of the highlighted region.
     * @param  int  $length  Byte length of the highlighted region, possibly zero.
     * @param  int  $contextStart  First byte of the element being parsed when the problem was found.
     * @param  int|null  $expectedTerminatorOffset  Byte where a declared length said a terminator should sit.
     * @param  array{offset: int, length: int, replacement: string}|null  $fix  Candidate byte edit, verified before `suggestion` is published.
     */
    public function __construct(
        public readonly SyntaxErrorCode $code,
        public readonly int $offset,
        public readonly int $length,
        public readonly string $message,
        public readonly ?string $suggestion = null,
        public readonly int $contextStart = 0,
        public readonly ?int $expectedTerminatorOffset = null,
        public readonly ?array $fix = null,
        public readonly ?int $engineOffset = null,
        public readonly DiagnosticConfidence $confidence = DiagnosticConfidence::Exact,
    ) {}

    /**
     * Widest byte range this diagnostic claims responsibility for.
     *
     * PHP's own offset must fall inside this interval for the scanner's
     * explanation to be trusted over PHP's location.
     *
     * @return array{0: int, 1: int}
     */
    public function claimInterval(): array
    {
        return [
            $this->contextStart,
            max($this->offset + $this->length, $this->expectedTerminatorOffset ?? 0),
        ];
    }

    /**
     * Copy this diagnostic with PHP's offset and the resulting confidence attached.
     */
    public function corroboratedBy(?int $engineOffset, DiagnosticConfidence $confidence): self
    {
        return new self(
            $this->code,
            $this->offset,
            $this->length,
            $this->message,
            $this->suggestion,
            $this->contextStart,
            $this->expectedTerminatorOffset,
            $this->fix,
            $engineOffset,
            $confidence,
        );
    }

    /**
     * Copy this diagnostic with its claim widened to start at `$contextStart`.
     *
     * The highlighted span is untouched. Only the corroboration window grows,
     * because PHP rewinds to the start of the element it was reading before it
     * reports where it stopped, while the scanner names the innermost token
     * that actually broke.
     */
    public function widenedTo(int $contextStart): self
    {
        if ($contextStart >= $this->contextStart) {
            return $this;
        }

        return new self(
            $this->code,
            $this->offset,
            $this->length,
            $this->message,
            $this->suggestion,
            $contextStart,
            $this->expectedTerminatorOffset,
            $this->fix,
            $this->engineOffset,
            $this->confidence,
        );
    }

    /**
     * Copy this diagnostic with its region forced inside a payload of `$length` bytes.
     *
     * Every diagnostic leaving {@see SerializedDiagnostics} passes
     * through here, so a rendering surface can index the payload with `offset`
     * and `length` without bounds-checking them again.
     */
    public function clampedTo(int $length): self
    {
        $offset = max(0, min($this->offset, $length));
        $span = max(0, min($this->length, $length - $offset));

        if ($offset === $this->offset && $span === $this->length) {
            return $this;
        }

        return new self(
            $this->code,
            $offset,
            $span,
            $this->message,
            $this->suggestion,
            min($this->contextStart, $length),
            $this->expectedTerminatorOffset,
            $this->fix,
            $this->engineOffset,
            $this->confidence,
        );
    }

    /**
     * Copy this diagnostic with the candidate fix and its suggestion removed.
     */
    public function withoutSuggestion(): self
    {
        return new self(
            $this->code,
            $this->offset,
            $this->length,
            $this->message,
            null,
            $this->contextStart,
            $this->expectedTerminatorOffset,
            null,
            $this->engineOffset,
            $this->confidence,
        );
    }

    /**
     * The public wire shape shared by the HTTP API and the MCP tool.
     *
     * No excerpt is included: the caller already holds the bytes it sent, and
     * `offset` plus `length` locate the problem unambiguously.
     *
     * @return array{code: string, message: string, offset: int, length: int, suggestion?: string}
     */
    public function toArray(): array
    {
        $payload = [
            'code' => $this->code->value,
            'message' => $this->message,
            'offset' => $this->offset,
            'length' => $this->length,
        ];

        if ($this->suggestion !== null) {
            $payload['suggestion'] = $this->suggestion;
        }

        return $payload;
    }
}
