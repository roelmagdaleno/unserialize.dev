<?php

namespace App\Data;

use App\Enums\SyntaxErrorCode;
use App\Services\DiagnosticTranslator;

/**
 * One located problem inside a serialized payload.
 *
 * `message` and `suggestion` are assembled from fixed templates plus integers and
 * never interpolate a byte taken from the submitted value. That rule is what lets
 * both strings travel out through the HTTP API and the MCP tool without
 * contradicting the promise that submitted data is not returned; it is enforced in
 * {@see DiagnosticTranslator}, which builds every instance of this class.
 *
 * `offset` and `length` are guaranteed to lie inside the payload they describe, so
 * a rendering surface can index it with them without bounds-checking again.
 */
readonly class SyntaxDiagnostic
{
    /**
     * @param  int  $offset  Byte offset of the highlighted region.
     * @param  int  $length  Byte length of the highlighted region, possibly zero.
     */
    public function __construct(
        public SyntaxErrorCode $code,
        public int $offset,
        public int $length,
        public string $message,
        public ?string $suggestion = null,
    ) {}

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
