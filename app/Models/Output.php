<?php

namespace App\Models;

use App\Enums\OutputFormat;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
            'output_format' => OutputFormat::class,
        ];
    }
}
