<?php

namespace App\Enums;

use Illuminate\Support\Str;

enum EquipmentStatus: string
{
    case ACTIVE = 'active';
    case IN_CALIBRATION = 'in_calibration';
    case IN_MAINTENANCE = 'in_maintenance';
    case OUT_OF_SERVICE = 'out_of_service';
    case DISCARDED = 'discarded';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Ativo',
            self::IN_CALIBRATION => 'Em Calibração',
            self::IN_MAINTENANCE => 'Em Manutenção',
            self::OUT_OF_SERVICE => 'Fora de Uso',
            self::DISCARDED => 'Descartado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::ACTIVE => 'success',
            self::IN_CALIBRATION => 'info',
            self::IN_MAINTENANCE => 'warning',
            self::OUT_OF_SERVICE => 'amber',
            self::DISCARDED => 'danger',
        };
    }

    public function isOperational(): bool
    {
        return $this === self::ACTIVE;
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = Str::of($value)
            ->trim()
            ->lower()
            ->ascii()
            ->replace([' ', '-'], '_')
            ->value();

        if ($normalized === '') {
            return null;
        }

        $legacyMap = [
            'ativo' => self::ACTIVE->value,
            'em_calibracao' => self::IN_CALIBRATION->value,
            'em_manutencao' => self::IN_MAINTENANCE->value,
            'fora_de_uso' => self::OUT_OF_SERVICE->value,
            'descartado' => self::DISCARDED->value,
            'active' => self::ACTIVE->value,
            'in_calibration' => self::IN_CALIBRATION->value,
            'in_maintenance' => self::IN_MAINTENANCE->value,
            'out_of_service' => self::OUT_OF_SERVICE->value,
            'discarded' => self::DISCARDED->value,
        ];

        return $legacyMap[$normalized] ?? null;
    }
}
