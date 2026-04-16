<?php

namespace App\Services;

use App\Models\CltViolation;
use App\Models\JourneyEntry;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class CltViolationService
{
    /**
     * Check a JourneyEntry for CLT violations and log them into clt_violations table.
     */
    public function detectDailyViolations(JourneyEntry $entry): array
    {
        $violations = [];

        // 1. Art. 59: Overtime limit exceeded (>2h/day)
        if ($entry->overtime_limit_exceeded) {
            $violations[] = $this->recordViolation(
                $entry,
                'overtime_limit_exceeded',
                'medium',
                "Horas extras excedem limite de 2h/dia. Total: {$entry->overtime_hours_50}h.",
                ['overtime' => $entry->overtime_hours_50]
            );
        }

        // 2. Art. 71: Inter-shift break compliance (Intrajornada)
        if ($entry->break_compliance === 'short_break') {
            $violations[] = $this->recordViolation(
                $entry,
                'intra_shift_short',
                'high',
                'Intervalo intrajornada inferior ao previsto por lei.',
                []
            );
        } elseif ($entry->break_compliance === 'missing_break') {
            $violations[] = $this->recordViolation(
                $entry,
                'intra_shift_missing',
                'high',
                'Trabalhador não realizou intervalo intrajornada obrigatório.',
                []
            );
        }

        // 3. Art. 66: Inter-shift break (Interjornada 11h)
        if ($entry->inter_shift_hours !== null && $entry->inter_shift_hours < config('hr.clt.inter_shift_min_hours')) {
            $violations[] = $this->recordViolation(
                $entry,
                'inter_shift_short',
                'critical',
                "Intervalo entre jornadas foi menor que 11h. Total: {$entry->inter_shift_hours}h.",
                ['rest_hours' => $entry->inter_shift_hours]
            );
        }

        return array_filter($violations);
    }

    /**
     * Art. 67 CLT: Enforce DSR (Descanso Semanal Remunerado).
     * Rule: Max 6 consecutive worked days. 7th day MUST be a rest day (typically Sunday).
     */
    public function detectConsecutiveWorkDays(int $userId, int $tenantId, string $date): ?CltViolation
    {
        $endDate = Carbon::parse($date);
        $startDate = $endDate->copy()->subDays(6);

        // Count how many days were worked in the 7-day window
        // Use DATE() for SQLite compatibility (date cast stores datetime strings)
        $workedDays = JourneyEntry::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->whereRaw('DATE(date) >= ?', [$startDate->toDateString()])
            ->whereRaw('DATE(date) <= ?', [$endDate->toDateString()])
            ->whereRaw('CAST(worked_hours AS REAL) > 0')
            ->count();

        if ($workedDays >= config('hr.clt.dsr_max_consecutive_days') + 1) {
            // Check if violation already exists for this 7-day period
            $existing = CltViolation::where('user_id', $userId)
                ->where('date', $endDate->toDateString())
                ->where('violation_type', 'dsr_missing')
                ->first();

            if (! $existing) {
                return CltViolation::create([
                    'tenant_id' => $tenantId,
                    'user_id' => $userId,
                    'date' => $endDate->toDateString(),
                    'violation_type' => 'dsr_missing',
                    'severity' => 'critical',
                    'description' => 'Trabalhador operando há 7 dias consecutivos sem Descanso Semanal Remunerado (DSR).',
                    'metadata' => ['worked_days' => $workedDays],
                ]);
            }
        }

        return null;
    }

    private function recordViolation(JourneyEntry $entry, string $type, string $severity, string $description, array $metadata = []): ?CltViolation
    {
        // Prevent duplicate for same day and type
        $existing = CltViolation::where('user_id', $entry->user_id)
            ->where('date', $entry->date)
            ->where('violation_type', $type)
            ->first();

        if ($existing) {
            return $existing;
        }

        return CltViolation::create([
            'tenant_id' => $entry->tenant_id,
            'user_id' => $entry->user_id,
            'date' => $entry->date,
            'violation_type' => $type,
            'severity' => $severity,
            'description' => $description,
            'metadata' => $metadata,
        ]);
    }
}
