<?php

namespace App\Services\Scanner;

use App\Services\Scanner\Rules\ContainerRules;

/**
 * The scanner's position inside one payload, plus the byte-level reads every
 * grammar rule needs.
 *
 * - The digit readers live here rather than on a rule family because they
 *   decide nothing about the grammar: they consume bytes and report what they
 *   saw. Every family needs them, and a family owning them would either
 *   duplicate them or force a shared base class.
 * - The properties are public and there are no accessors on purpose: the
 *   per-byte loops hoist them into locals and write back once, and a getter in
 *   that position would cost a method call per byte on a 256 KB payload.
 */
class ScannerCursor
{
    /**
     * Long enough for any real length, short enough that `(int)` cannot overflow.
     */
    private const int MAX_LENGTH_DIGITS = 18;

    /**
     * The byte the next read starts at.
     */
    public int $position = 0;

    /**
     * Set when a token is accepted on shape alone -- `E:`, `C:`, `r:`, `R:` --
     * because deciding its real validity would mean resolving classes or
     * reproducing PHP's internal value numbering on untrusted input.
     */
    public bool $unverifiable = false;

    /**
     * @param  string  $data  The whole payload being scanned.
     * @param  int  $length  Its byte length, read once rather than per loop.
     */
    public function __construct(
        public readonly string $data,
        public readonly int $length,
    ) {}

    /**
     * Whether every byte has been consumed.
     */
    public function atEnd(): bool
    {
        return $this->position >= $this->length;
    }

    /**
     * The byte sitting at the cursor.
     */
    public function current(): string
    {
        return $this->data[$this->position];
    }

    /**
     * Consume a leading `-` or `+` if one is present.
     */
    public function acceptSign(): bool
    {
        if ($this->atEnd()) {
            return false;
        }

        $byte = $this->data[$this->position];

        if ($byte !== '-' && $byte !== '+') {
            return false;
        }

        $this->position++;

        return true;
    }

    /**
     * Consume a literal if it sits at the cursor, and report whether it did.
     */
    public function acceptLiteral(string $literal): bool
    {
        if (substr($this->data, $this->position, strlen($literal)) !== $literal) {
            return false;
        }

        $this->position += strlen($literal);

        return true;
    }

    /**
     * Consume a run of digits and report how many there were.
     */
    public function digitRun(): int
    {
        $data = $this->data;
        $length = $this->length;
        $position = $this->position;
        $start = $position;

        while ($position < $length && $data[$position] >= '0' && $data[$position] <= '9') {
            $position++;
        }

        $this->position = $position;

        return $position - $start;
    }

    /**
     * Read an unsigned digit run, refusing runs long enough to overflow an int.
     */
    public function unsignedDigits(): int|false
    {
        $data = $this->data;
        $length = $this->length;
        $position = $this->position;
        $start = $position;

        while ($position < $length
            && $position - $start < self::MAX_LENGTH_DIGITS
            && $data[$position] >= '0'
            && $data[$position] <= '9'
        ) {
            $position++;
        }

        $this->position = $position;

        if ($position === $start) {
            return false;
        }

        if ($position < $length && $data[$position] >= '0' && $data[$position] <= '9') {
            return false;
        }

        return (int) substr($data, $start, $position - $start);
    }

    /**
     * A throwaway copy for speculative lookahead.
     *
     * {@see ContainerRules::surplusElements()} parses past the end of an
     * array only to count how many elements really follow. That walk must not
     * move the real cursor -- the caller still has to frame the diagnostic from
     * where the surplus began -- and must not publish `unverifiable`, since a
     * reference or custom object inside the surplus says nothing about whether
     * the payload as a whole could be verified. Writes to a fork are discarded
     * by design.
     */
    public function fork(): self
    {
        $fork = new self($this->data, $this->length);
        $fork->position = $this->position;
        $fork->unverifiable = $this->unverifiable;

        return $fork;
    }
}
