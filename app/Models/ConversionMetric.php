<?php

namespace App\Models;

use App\Enums\ConversionErrorCode;
use App\Enums\ConversionInterface;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * A daily count of conversions for one interface and one outcome.
 *
 * `date` is a UTC calendar day held as a `Y-m-d` string rather than a date
 * cast. A date cast would write `Y-m-d 00:00:00` back to the column, and the
 * aggregate key and the reporting range both compare this value as text.
 */
class ConversionMetric extends Model
{
    use HasFactory;

    public const OUTCOME_SUCCESS = 'success';

    protected $fillable = [
        'date',
        'interface',
        'outcome',
        'count',
        'last_occurred_at',
    ];

    /**
     * Count one conversion against its daily aggregate.
     */
    public static function recordOccurrence(
        ConversionInterface $interface,
        string $outcome,
        CarbonImmutable $occurredAt,
    ): void {
        $occurredAt = $occurredAt->utc();

        static::query()->upsert([[
            'date' => $occurredAt->toDateString(),
            'interface' => $interface->value,
            'outcome' => $outcome,
            'count' => 1,
            'last_occurred_at' => $occurredAt,
        ]], ['date', 'interface', 'outcome'], [
            'count' => DB::raw('`count` + 1'),
            'last_occurred_at' => DB::raw('greatest(`last_occurred_at`, values(`last_occurred_at`))'),
        ]);
    }

    /**
     * List every outcome category an aggregate may hold.
     *
     * @return list<string>
     */
    public static function outcomes(): array
    {
        return [
            self::OUTCOME_SUCCESS,
            'rate_limited',
            'unsupported_media_type',
            'validation_error',
            ...array_map(
                static fn (ConversionErrorCode $code): string => $code->value,
                ConversionErrorCode::cases(),
            ),
        ];
    }

    /**
     * {@inheritDoc}
     */
    protected function casts(): array
    {
        return [
            'count' => 'integer',
            'interface' => ConversionInterface::class,
            'last_occurred_at' => 'immutable_datetime',
        ];
    }
}
