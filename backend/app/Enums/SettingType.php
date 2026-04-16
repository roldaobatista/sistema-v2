<?php

namespace App\Enums;

enum SettingType: string
{
    case STRING = 'string';
    case BOOLEAN = 'boolean';
    case INTEGER = 'integer';
    case JSON = 'json';

    public function label(): string
    {
        return match ($this) {
            self::STRING => 'Texto',
            self::BOOLEAN => 'Booleano',
            self::INTEGER => 'Inteiro',
            self::JSON => 'JSON',
        };
    }
}
