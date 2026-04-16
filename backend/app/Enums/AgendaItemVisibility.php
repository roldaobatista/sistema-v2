<?php

namespace App\Enums;

enum AgendaItemVisibility: string
{
    case PRIVADO = 'private';
    case PRIVADA = 'privada';
    case EQUIPE = 'team';
    case DEPARTAMENTO = 'department';
    case CUSTOM = 'custom';
    case EMPRESA = 'company';

    public function label(): string
    {
        return match ($this) {
            self::PRIVADO => 'Privado (só criador e responsável)',
            self::PRIVADA => 'Privada (só criador e responsável)',
            self::EQUIPE => 'Minha equipe',
            self::DEPARTAMENTO => 'Departamentos selecionados',
            self::CUSTOM => 'Pessoas específicas',
            self::EMPRESA => 'Toda a empresa',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::PRIVADO => 'lock',
            self::PRIVADA => 'lock',
            self::EQUIPE => 'users',
            self::DEPARTAMENTO => 'building',
            self::CUSTOM => 'user-check',
            self::EMPRESA => 'globe',
        };
    }
}
