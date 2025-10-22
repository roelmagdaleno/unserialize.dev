<?php

namespace App\Models;

use App\Enums\OutputFormats;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Tempest\Highlight\Highlighter;

class Output extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'serialized',
        'unserialized',
        'output_format',
    ];

    /**
     * {@inheritDoc}
     */
    protected function casts(): array
    {
        return [
            'output_format' => OutputFormats::class,
        ];
    }

    /**
     * Get the syntax highlighted attribute.
     *
     * @since 1.0.0
     *
     * @return Attribute The syntax highlighted attribute.
     */
    protected function syntaxHighlighted(): Attribute {
        return Attribute::make(
            get: fn () => (new Highlighter)->parse($this->unserialized, $this->output_format->syntaxLanguage()),
        );
    }
}
