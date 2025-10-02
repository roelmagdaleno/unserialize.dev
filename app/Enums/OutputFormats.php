<?php

namespace App\Enums;

enum OutputFormats: string
{
    case JSON = 'json';
    case ARRAY = 'array';

    /**
     * Get the syntax language.
     *
     * @since 1.0.0
     *
     * @return string The syntax language.
     */
    public function syntaxLanguage(): string
    {
        return match ($this) {
            self::JSON => 'json',
            self::ARRAY => 'php',
        };
    }

    /**
     * Get the label.
     *
     * @since 1.0.0
     *
     * @return string The label.
     */
    public function label(): string
    {
        return match ($this) {
            self::JSON => 'JSON',
            self::ARRAY => 'Array',
        };
    }

    /**
     * Get the output formats as an array.
     *
     * @since 1.0.0
     *
     * @return string[] The output formats.
     */
    public static function toArray(): array
    {
        return [
            'json' => 'JSON',
            'array' => 'Array',
        ];
    }
}
