<?php

namespace App\Services\Journey;

use App\Models\JourneyRule;
use App\Models\User;

class JourneyPolicyResolver
{
    public function resolve(User $user): JourneyRule
    {
        $tenantId = $user->current_tenant_id;

        // 1. Buscar regra default ativa do tenant
        $default = JourneyRule::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->where('is_default', true)
            ->where(function ($q) {
                $q->where('is_active', true)->orWhereNull('is_active');
            })
            ->first();

        if ($default) {
            return $default;
        }

        // 2. Criar regra padrão CLT caso nenhuma exista
        return $this->createDefaultRule($tenantId);
    }

    private function createDefaultRule(int $tenantId): JourneyRule
    {
        return JourneyRule::withoutGlobalScope('tenant')->create([
            'tenant_id' => $tenantId,
            'name' => 'CLT Padrão',
            // Campos legados
            'daily_hours' => 8.00,
            'weekly_hours' => 44.00,
            'overtime_weekday_pct' => 50,
            'overtime_weekend_pct' => 100,
            'overtime_holiday_pct' => 100,
            'night_shift_pct' => 20,
            'night_start' => '22:00',
            'night_end' => '05:00',
            'uses_hour_bank' => false,
            'hour_bank_expiry_months' => 6,
            'is_default' => true,
            // Campos Motor Operacional
            'regime_type' => 'clt_mensal',
            'daily_hours_limit' => 480,
            'weekly_hours_limit' => 2640,
            'break_minutes' => 60,
            'displacement_counts_as_work' => false,
            'wait_time_counts_as_work' => true,
            'travel_meal_counts_as_break' => true,
            'auto_suggest_clock_on_displacement' => true,
            'pre_assigned_break' => false,
            'overnight_min_hours' => 11,
            'oncall_multiplier_percent' => 33,
            'saturday_is_overtime' => false,
            'sunday_is_overtime' => true,
            'is_active' => true,
            // Campos Banco de Horas
            'compensation_period_days' => 30,
            'overtime_50_multiplier' => 1.50,
            'overtime_100_multiplier' => 2.00,
            'requires_two_level_approval' => true,
        ]);
    }
}
