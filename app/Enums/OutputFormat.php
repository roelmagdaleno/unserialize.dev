<?php

namespace App\Enums;

/**
 * The representations a converted value can be displayed in.
 */
enum OutputFormat: string
{
    case JSON = 'json';
    case ARRAY = 'array';

    /**
     * The highlighter language this format is rendered with.
     */
    public function syntaxLanguage(): string
    {
        return match ($this) {
            self::JSON => 'json',
            self::ARRAY => 'php',
        };
    }

    /**
     * The name shown on the format toggle.
     */
    public function label(): string
    {
        return match ($this) {
            self::JSON => 'JSON',
            self::ARRAY => 'Array',
        };
    }
}
