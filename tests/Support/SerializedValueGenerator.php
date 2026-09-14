<?php

namespace Tests\Support;

use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;

/**
 * Deterministic generator of PHP values for differential testing.
 *
 * Seeded through an explicit engine rather than the global generator so a
 * failing seed reproduces exactly, no matter what else the suite has run.
 */
final class SerializedValueGenerator
{
    private readonly Randomizer $randomizer;

    public function __construct(private readonly int $seed)
    {
        $this->randomizer = new Randomizer(new Xoshiro256StarStar($seed));
    }

    public function randomizer(): Randomizer
    {
        return $this->randomizer;
    }

    /**
     * @return iterable<int, mixed>
     */
    public function corpus(int $count, int $maxDepth = 4): iterable
    {
        for ($index = 0; $index < $count; $index++) {
            yield $this->value($maxDepth);
        }
    }

    public function value(int $maxDepth = 4): mixed
    {
        if ($maxDepth <= 0 || $this->randomizer->getInt(0, 100) < 45) {
            return $this->leaf();
        }

        return match ($this->randomizer->getInt(0, 3)) {
            0 => $this->listValue($maxDepth),
            1 => $this->mapValue($maxDepth),
            2 => $this->mixedKeyValue($maxDepth),
            default => $this->referencingValue($maxDepth),
        };
    }

    private function leaf(): mixed
    {
        return match ($this->randomizer->getInt(0, 12)) {
            0 => null,
            1 => true,
            2 => false,
            3 => 0,
            4 => -1,
            5 => PHP_INT_MAX,
            6 => PHP_INT_MIN,
            7 => $this->randomizer->getInt(-1_000_000, 1_000_000),
            8 => 0.1,
            9 => INF,
            10 => NAN,
            11 => 1.0e100,
            default => $this->string(),
        };
    }

    /**
     * Strings deliberately include the bytes a quote-scanning parser would trip
     * on, because the scanner must walk declared lengths instead.
     */
    private function string(): string
    {
        return match ($this->randomizer->getInt(0, 5)) {
            0 => '',
            1 => 'name',
            2 => 'café 😀 ünïcode',
            3 => 'quote" semi; brace} backslash\\',
            4 => "null\0byte\tand\nnewline",
            default => $this->randomizer->getBytesFromString(
                'abcdefghijklmnopqrstuvwxyz0123456789 ";{}\\:',
                $this->randomizer->getInt(1, 24),
            ),
        };
    }

    /**
     * @return array<int, mixed>
     */
    private function listValue(int $maxDepth): array
    {
        $items = [];

        for ($index = 0, $count = $this->randomizer->getInt(0, 4); $index < $count; $index++) {
            $items[] = $this->value($maxDepth - 1);
        }

        return $items;
    }

    /**
     * @return array<string, mixed>
     */
    private function mapValue(int $maxDepth): array
    {
        $items = [];

        for ($index = 0, $count = $this->randomizer->getInt(0, 4); $index < $count; $index++) {
            $items[$this->string().$index] = $this->value($maxDepth - 1);
        }

        return $items;
    }

    /**
     * @return array<array-key, mixed>
     */
    private function mixedKeyValue(int $maxDepth): array
    {
        return [
            0 => $this->value($maxDepth - 1),
            'key' => $this->value($maxDepth - 1),
            7 => $this->value($maxDepth - 1),
            '' => $this->value($maxDepth - 1),
        ];
    }

    /**
     * Produces a payload containing an `R:` back reference.
     *
     * @return array<int, mixed>
     */
    private function referencingValue(int $maxDepth): array
    {
        $items = [$this->value($maxDepth - 1)];
        $items[1] = &$items[0];
        $items[2] = $this->value($maxDepth - 1);

        return $items;
    }
}
