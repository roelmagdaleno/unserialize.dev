<?php

namespace Tests\Support;

use Random\Randomizer;

/**
 * Corrupts valid serialized payloads in the ways real ones get corrupted.
 *
 * Each mutator returns null when it does not apply to a payload, so the caller
 * can tell "nothing to mutate" apart from "mutated into something".
 */
final class SerializedMutator
{
    public function __construct(private readonly Randomizer $randomizer) {}

    /**
     * @return array<string, string>
     */
    public function mutations(string $serialized): array
    {
        $mutations = [
            'truncate' => $this->truncate($serialized),
            'flip_byte' => $this->flipByte($serialized),
            'insert_byte' => $this->insertByte($serialized),
            'delete_byte' => $this->deleteByte($serialized),
            'bump_string_length' => $this->bumpDeclaredNumber($serialized, '/s:(\d+):"/'),
            'bump_array_count' => $this->bumpDeclaredNumber($serialized, '/a:(\d+):\{/'),
            'drop_semicolon' => $this->dropByte($serialized, ';'),
            'drop_brace' => $this->dropByte($serialized, '}'),
            'append_garbage' => $serialized.'garbage',
            'swap_type_marker' => $this->swapTypeMarker($serialized),
        ];

        return array_filter($mutations, static fn (?string $mutation): bool => $mutation !== null && $mutation !== '');
    }

    /**
     * Applies several independent mutations in sequence.
     *
     * One mutation is the easy case. Real corruption arrives in batches, and a
     * repairer that clears a single fault can still walk a multi-fault payload
     * into a wrong value.
     */
    public function chain(string $serialized, int $rounds): ?string
    {
        $mutated = $serialized;

        for ($round = 0; $round < $rounds; $round++) {
            $mutations = array_values($this->mutations($mutated));

            if ($mutations === []) {
                return $round === 0 ? null : $mutated;
            }

            $mutated = $mutations[$this->randomizer->getInt(0, count($mutations) - 1)];
        }

        return $mutated === $serialized ? null : $mutated;
    }

    /**
     * Breaks several declared string lengths at once.
     *
     * This is the canonical corruption: a search-and-replace pass rewrites the
     * bytes of many strings and updates none of their declared lengths. Edits
     * are applied right to left so earlier offsets stay valid.
     */
    public function searchReplaceCorruption(string $serialized, int $count): ?string
    {
        if (preg_match_all('/s:(\d+):"/', $serialized, $matches, PREG_OFFSET_CAPTURE) === 0) {
            return null;
        }

        $targets = array_slice($this->randomizer->shuffleArray($matches[1]), 0, $count);

        usort($targets, static fn (array $a, array $b): int => $b[1] <=> $a[1]);

        $mutated = $serialized;

        foreach ($targets as [$digits, $offset]) {
            $replacement = max(0, (int) $digits + ($this->randomizer->getInt(0, 1) === 0 ? 1 : -1));
            $mutated = substr_replace($mutated, (string) $replacement, $offset, strlen($digits));
        }

        return $mutated === $serialized ? null : $mutated;
    }

    private function truncate(string $serialized): ?string
    {
        $length = strlen($serialized);

        return $length < 2 ? null : substr($serialized, 0, $this->randomizer->getInt(1, $length - 1));
    }

    private function flipByte(string $serialized): ?string
    {
        $length = strlen($serialized);

        if ($length === 0) {
            return null;
        }

        $position = $this->randomizer->getInt(0, $length - 1);
        $serialized[$position] = $this->randomizer->getBytesFromString('abz019:;"{}', 1);

        return $serialized;
    }

    private function insertByte(string $serialized): ?string
    {
        $position = $this->randomizer->getInt(0, strlen($serialized));

        return substr_replace($serialized, $this->randomizer->getBytesFromString('abz019:;"{}', 1), $position, 0);
    }

    private function deleteByte(string $serialized): ?string
    {
        $length = strlen($serialized);

        return $length === 0 ? null : substr_replace($serialized, '', $this->randomizer->getInt(0, $length - 1), 1);
    }

    /**
     * Shifts a declared length or element count by one, which is how a
     * search-and-replace pass breaks serialized data in practice.
     */
    private function bumpDeclaredNumber(string $serialized, string $pattern): ?string
    {
        if (preg_match_all($pattern, $serialized, $matches, PREG_OFFSET_CAPTURE) === 0) {
            return null;
        }

        [$digits, $offset] = $matches[1][$this->randomizer->getInt(0, count($matches[1]) - 1)];
        $replacement = max(0, (int) $digits + ($this->randomizer->getInt(0, 1) === 0 ? 1 : -1));

        return substr_replace($serialized, (string) $replacement, $offset, strlen($digits));
    }

    private function dropByte(string $serialized, string $byte): ?string
    {
        $positions = [];

        for ($index = 0, $length = strlen($serialized); $index < $length; $index++) {
            if ($serialized[$index] === $byte) {
                $positions[] = $index;
            }
        }

        return $positions === []
            ? null
            : substr_replace($serialized, '', $positions[$this->randomizer->getInt(0, count($positions) - 1)], 1);
    }

    private function swapTypeMarker(string $serialized): ?string
    {
        if (preg_match_all('/(?<=^|[;{])([NbidsaOCErR])/', $serialized, $matches, PREG_OFFSET_CAPTURE) === 0) {
            return null;
        }

        [, $offset] = $matches[1][$this->randomizer->getInt(0, count($matches[1]) - 1)];

        return substr_replace($serialized, $this->randomizer->getBytesFromString('xNbidsa', 1), $offset, 1);
    }
}
