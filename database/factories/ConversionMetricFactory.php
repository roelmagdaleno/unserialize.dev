<?php

namespace Database\Factories;

use App\Enums\ConversionInterface;
use App\Enums\ConversionOutcome;
use App\Models\ConversionMetric;
use Illuminate\Database\Eloquent\Factories\Factory;

class ConversionMetricFactory extends Factory
{
    protected $model = ConversionMetric::class;

    public function definition(): array
    {
        $occurredAt = now()->utc();

        return [
            'date' => $occurredAt->toDateString(),
            'interface' => ConversionInterface::Browser,
            'outcome' => ConversionOutcome::Success->value,
            'count' => 1,
            'last_occurred_at' => $occurredAt,
        ];
    }

    /**
     * Place the aggregate on one UTC day, for one interface and one outcome.
     */
    public function on(string $date, ConversionInterface $interface, ConversionOutcome $outcome): static
    {
        return $this->state([
            'date' => $date,
            'interface' => $interface,
            'outcome' => $outcome->value,
            'last_occurred_at' => $date.' 12:00:00',
        ]);
    }

    /**
     * Set how many occurrences the aggregate holds.
     */
    public function counted(int $count): static
    {
        return $this->state(['count' => $count]);
    }
}
