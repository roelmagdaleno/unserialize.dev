<?php

namespace App\Models;

use App\Enums\InputTrait;
use App\Livewire\Serialized;
use Carbon\CarbonImmutable;
use Database\Factories\ErrorReportFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

/**
 * A failed value a person chose to send so the converter can be fixed.
 *
 * - Written only by {@see Serialized::reportFailure()}, after
 *   explicit consent, and never mass assigned.
 * - Holds no IP address, user agent or session: only the value, why it failed
 *   and the person's own note.
 * - Deleted by the daily prune once `telemetry.error_report_retention_days`
 *   pass; the privacy page states the same window.
 */
class ErrorReport extends Model
{
    /** @use HasFactory<ErrorReportFactory> */
    use HasFactory;

    use MassPrunable;

    /**
     * Reports carry `reported_at` alone; there is nothing to update.
     */
    public $timestamps = false;

    /**
     * Select the reports whose retention window has passed.
     *
     * @return Builder<$this>
     */
    public function prunable(): Builder
    {
        return static::query()->where(
            'reported_at',
            '<',
            CarbonImmutable::now('UTC')->subDays((int) config('telemetry.error_report_retention_days')),
        );
    }

    /**
     * {@inheritDoc}
     */
    protected function casts(): array
    {
        return [
            'reported_at' => 'immutable_datetime',
            'input_traits' => AsEnumCollection::of(InputTrait::class),
        ];
    }
}
