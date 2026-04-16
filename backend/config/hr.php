<?php

return [
    /*
    |--------------------------------------------------------------------------
    | CLT (Consolidação das Leis do Trabalho) Configuration
    |--------------------------------------------------------------------------
    */
    'clt' => [
        // Art. 58 §1: Tolerância por marcação (minutos)
        'tolerance_per_entry_minutes' => 5,
        // Art. 58 §1: Tolerância máxima diária (minutos)
        'tolerance_daily_max_minutes' => 10,
        // Art. 59: Limite de horas extras por dia
        'overtime_daily_limit_hours' => 2,
        // Art. 66: Intervalo interjornada mínimo (horas)
        'inter_shift_min_hours' => 11,
        // Art. 71: Intervalo intrajornada para jornada >6h (minutos)
        'intra_shift_break_6h_minutes' => 60,
        // Art. 71: Intervalo intrajornada para jornada 4-6h (minutos)
        'intra_shift_break_4h_minutes' => 15,
        // Art. 67: Máximo de dias consecutivos trabalhados antes do DSR
        'dsr_max_consecutive_days' => 6,
        // Art. 73: Início do período noturno
        'night_start' => '22:00',
        // Art. 73: Fim do período noturno
        'night_end' => '05:00',
        // Art. 73 §1: Hora noturna reduzida (52min30s = 1h)
        'night_hour_minutes' => 52.5,
    ],

    /*
    |--------------------------------------------------------------------------
    | Portaria 671/2021 Configuration
    |--------------------------------------------------------------------------
    */
    'portaria671' => [
        // Art. 96: Tempo de retenção de dados (anos)
        'retention_years' => 5,
        // Liveness: Score mínimo para aprovação automática
        'liveness_min_score' => 0.8,
        // GPS: Precisão máxima aceitável (metros)
        'gps_max_accuracy_meters' => 150,
        // GPS: Velocidade máxima aceitável (m/s)
        'gps_max_speed_ms' => 55,
        // Consistência: Velocidade máxima entre clock-in/out (km/h)
        'max_consistency_speed_kmh' => 900,
    ],
];
