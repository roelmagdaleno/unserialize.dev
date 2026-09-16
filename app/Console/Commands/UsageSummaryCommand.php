<?php

namespace App\Console\Commands;

use App\Enums\ConversionInterface;
use App\Enums\ConversionOutcome;
use App\Models\ConversionMetric;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

#[Signature('usage:summary
    {--from= : Inclusive UTC start date, as Y-m-d. Defaults to the first recorded day}
    {--to= : Inclusive UTC end date, as Y-m-d. Defaults to the last recorded day}
    {--interface= : Limit the summary to browser, api, or mcp}
    {--outcome= : Limit the summary to one outcome category}
    {--json : Print the summary as JSON instead of a table}')]
#[Description('Report historical conversion counts and the latest successful use')]
/**
 * Reports the daily conversion aggregates, and when each surface last worked.
 */
class UsageSummaryCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $validator = Validator::make($this->options(), [
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'interface' => ['nullable', Rule::enum(ConversionInterface::class)],
            'outcome' => ['nullable', Rule::in(ConversionMetric::outcomes())],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->components->error($message);
            }

            return self::FAILURE;
        }

        $metrics = $this->aggregates();
        $latestSuccessfulUse = $this->latestSuccessfulUseByInterface();

        if ($this->option('json')) {
            $this->line((string) json_encode([
                'latest_successful_use' => [
                    'overall' => $latestSuccessfulUse->max(),
                    'by_interface' => $latestSuccessfulUse->all(),
                ],
                'metrics' => $metrics->all(),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->components->twoColumnDetail(
            'Latest successful use',
            $latestSuccessfulUse->max() ?? 'never',
        );

        foreach ($latestSuccessfulUse as $interface => $lastOccurredAt) {
            $this->components->twoColumnDetail("  {$interface}", $lastOccurredAt ?? 'never');
        }

        if ($metrics->isEmpty()) {
            $this->components->info('No conversion metrics match the given filters.');

            return self::SUCCESS;
        }

        $this->table(
            ['Date (UTC)', 'Interface', 'Outcome', 'Count', 'Last Occurred At (UTC)'],
            $metrics->map(static fn (array $metric): array => array_values($metric))->all(),
        );

        return self::SUCCESS;
    }

    /**
     * Read the filtered daily aggregates.
     *
     * @return Collection<int, array{date: string, interface: string, outcome: string, count: int, last_occurred_at: string}>
     */
    private function aggregates(): Collection
    {
        return ConversionMetric::query()
            ->when($this->option('from'), fn (Builder $query, string $from) => $query->where('date', '>=', $from))
            ->when($this->option('to'), fn (Builder $query, string $to) => $query->where('date', '<=', $to))
            ->when($this->option('interface'), fn (Builder $query, string $interface) => $query->where('interface', $interface))
            ->when($this->option('outcome'), fn (Builder $query, string $outcome) => $query->where('outcome', $outcome))
            ->orderBy('date')
            ->orderBy('interface')
            ->orderBy('outcome')
            ->get()
            ->map(static fn (ConversionMetric $metric): array => [
                'date' => $metric->date,
                'interface' => $metric->interface->value,
                'outcome' => $metric->outcome,
                'count' => $metric->count,
                'last_occurred_at' => $metric->last_occurred_at->toDateTimeString(),
            ]);
    }

    /**
     * Read the most recent successful conversion for every interface.
     *
     * Latest use answers "is this application still working for anyone", so it
     * deliberately ignores the report's filters and reads the whole history.
     *
     * @return Collection<string, string|null>
     */
    private function latestSuccessfulUseByInterface(): Collection
    {
        $latest = ConversionMetric::query()
            ->where('outcome', ConversionOutcome::Success->value)
            ->groupBy('interface')
            ->select('interface', DB::raw('max(last_occurred_at) as last_occurred_at'))
            ->get()
            ->mapWithKeys(static fn (ConversionMetric $metric): array => [
                $metric->interface->value => $metric->last_occurred_at->toDateTimeString(),
            ]);

        return (new Collection(ConversionInterface::cases()))
            ->mapWithKeys(static fn (ConversionInterface $interface): array => [
                $interface->value => $latest->get($interface->value),
            ]);
    }
}
