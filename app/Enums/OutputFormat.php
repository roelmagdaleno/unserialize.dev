<?php

namespace App\Enums;

enum OutputFormat: string
{
    case JSON = 'json';
    case ARRAY = 'array';

    public function syntaxLanguage(): string
    {
        return match ($this) {
            self::JSON => 'json',
            self::ARRAY => 'php',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::JSON => 'JSON',
            self::ARRAY => 'Array',
        };
    }

    /** @return array<string, string> */
    public static function toArray(): array
    {
        return [
            self::JSON->value => self::JSON->label(),
            self::ARRAY->value => self::ARRAY->label(),
        ];
    }
}
