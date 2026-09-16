<?php

namespace App\Services\Scanner;

/**
 * The scanner's position inside one payload.
 *
 * Every grammar rule used to thread `string $data, int $length, int &$position`
 * by hand, which put a by-reference parameter in front of the reader on every
 * signature and made it impossible to tell at a glance which rules advance the
 * cursor and which only look. One mutable object carries the same state without
 * the ceremony.
 *
 * The properties are public and there are no accessors on purpose: the per-byte
 * loops hoist them into locals and write back once, and a getter in that
 * position would cost a method call per byte on a 256 KB payload.
 */
class ScannerCursor
{
    public int $position = 0;

    /**
     * Set when a token is accepted on shape alone -- `E:`, `C:`, `r:`, `R:` --
     * because deciding its real validity would mean resolving classes or
     * reproducing PHP's internal value numbering on untrusted input.
     */
    public bool $unverifiable = false;

    public function __construct(
        public readonly string $data,
        public readonly int $length,
    ) {}

    public function atEnd(): bool
    {
        return $this->position >= $this->length;
    }

    public function current(): string
    {
        return $this->data[$this->position];
    }

    /**
     * A throwaway copy for speculative lookahead.
     *
     * {@see SerializedScanner::surplusElements()} parses past the end of an
     * array only to count how many elements really follow. That walk must not
     * move the real cursor -- the caller still has to frame the diagnostic from
     * where the surplus began -- and must not publish `unverifiable`, since a
     * reference or custom object inside the surplus says nothing about whether
     * the payload as a whole could be verified. Writes to a fork are discarded
     * by design; that discard used to be implicit in passing `$position` and
     * `$unverifiable` by value, and this method is what keeps it visible.
     */
    public function fork(): self
    {
        $fork = new self($this->data, $this->length);
        $fork->position = $this->position;
        $fork->unverifiable = $this->unverifiable;

        return $fork;
    }
}
