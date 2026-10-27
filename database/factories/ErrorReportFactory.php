<?php

namespace Database\Factories;

use App\Enums\ConversionOutcome;
use App\Enums\SyntaxErrorCode;
use App\Models\ErrorReport;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ErrorReport>
 */
class ErrorReportFactory extends Factory
{
    protected $model = ErrorReport::class;

    /**
     * A string length failure, the most common report.
     */
    public function definition(): array
    {
        return [
            'reported_at' => now()->utc(),
            'outcome' => ConversionOutcome::InvalidInput->value,
            'diagnostic' => SyntaxErrorCode::StringLengthMismatch->value,
            'input_traits' => null,
            'input' => 'a:1:{s:4:"names";N;}',
            'note' => null,
        ];
    }
}
