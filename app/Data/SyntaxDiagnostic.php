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
readonly class SyntaxDiagnostic
{
    /**
     * @param  int  $offset  Byte offset of the highlighted region.
     * @param  int  $length  Byte length of the highlighted region, possibly zero.
     * @param  int  $contextStart  First byte of the element being parsed when the problem was found.
     * @param  int|null  $expectedTerminatorOffset  Byte where a declared length said a terminator should sit.
     * @param  array{offset: int, length: int, replacement: string}|null  $fix  Candidate byte edit, verified before `suggestion` is published.
     */
    public function __construct(
        public SyntaxErrorCode $code,
        public int $offset,
        public int $length,
        public string $message,
        public ?string $suggestion = null,
        public int $contextStart = 0,
        public ?int $expectedTerminatorOffset = null,
        public ?array $fix = null,
        public ?int $engineOffset = null,
        public DiagnosticConfidence $confidence = DiagnosticConfidence::Exact,
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
        return $this->with(engineOffset: $engineOffset, confidence: $confidence);
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

        return $this->with(contextStart: $contextStart);
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

        return $this->with(
            offset: $offset,
            length: $span,
            contextStart: min($this->contextStart, $length),
        );
    }

    /**
     * Copy this diagnostic with the candidate fix and its suggestion removed.
     */
    public function withoutSuggestion(): self
    {
        return $this->with(suggested: false);
    }

    /**
     * Copy this diagnostic with selected fields replaced.
     *
     * Every wither above routes through here, so the constructor's argument
     * list is written out once rather than restated in each of them.
     *
     * `engineOffset` defaults to `false` rather than null because null is a
     * value {@see self::corroboratedBy()} legitimately sets, so it cannot double
     * as "leave this alone". The suggestion and the fix that backs it travel
     * together: one is never dropped without the other.
     */
    private function with(
        ?int $offset = null,
        ?int $length = null,
        ?int $contextStart = null,
        int|false|null $engineOffset = false,
        ?DiagnosticConfidence $confidence = null,
        bool $suggested = true,
    ): self {
        return new self(
            $this->code,
            $offset ?? $this->offset,
            $length ?? $this->length,
            $this->message,
            $suggested ? $this->suggestion : null,
            $contextStart ?? $this->contextStart,
            $this->expectedTerminatorOffset,
            $suggested ? $this->fix : null,
            $engineOffset === false ? $this->engineOffset : $engineOffset,
            $confidence ?? $this->confidence,
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
