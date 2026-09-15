<?php

namespace App\Models;

use App\Enums\ConversionInterface;
use App\Enums\UsageEventType;
use Carbon\CarbonImmutable;
use Database\Factories\UsageEventFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

/**
 * One short-lived record of an application interaction and its technical context.
 *
 * Rows are immutable and append-only until pruning, so the model keeps no
 * `created_at`/`updated_at` pair: `occurred_at` is the only time an event has.
 * Nothing here is mass assignable either. Every column is written explicitly by
 * `UsageEventRecorder`, which is what keeps the persisted field list an
 * allowlist rather than whatever a caller happened to pass.
 */
class UsageEvent extends Model
{
    /** @use HasFactory<UsageEventFactory> */
    use HasFactory;

    use MassPrunable;

    public $timestamps = false;

    /**
     * Select the events whose retention window has passed.
     *
     * The comparison runs against the indexed `occurred_at` column and the
     * delete is issued as one statement, so no expired row is ever hydrated.
     *
     * @return Builder<$this>
     */
    public function prunable(): Builder
    {
        return static::query()->where(
            'occurred_at',
            '<',
            CarbonImmutable::now('UTC')->subDays((int) config('telemetry.retention_days')),
        );
    }

    /**
     * {@inheritDoc}
     */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'immutable_datetime',
            'interface' => ConversionInterface::class,
            'event' => UsageEventType::class,
            'duration_ms' => 'decimal:3',
            'http_status' => 'integer',
        ];
    }
}
