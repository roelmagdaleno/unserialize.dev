<?php

namespace Database\Factories;

use App\Enums\ConversionInterface;
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
            'outcome' => ConversionMetric::OUTCOME_SUCCESS,
            'count' => 1,
            'last_occurred_at' => $occurredAt,
        ];
    }

    /**
     * Place the aggregate on one UTC day, for one interface and one outcome.
     */
    public function on(string $date, ConversionInterface $interface, string $outcome): static
    {
        return $this->state([
            'date' => $date,
            'interface' => $interface,
            'outcome' => $outcome,
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
