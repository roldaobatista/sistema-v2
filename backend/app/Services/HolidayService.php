<?php

namespace App\Services;

use App\Models\BusinessHour;
use App\Models\TenantHoliday;
use Carbon\Carbon;

class HolidayService
{
    public function __construct(private readonly BrasilApiService $brasilApi) {}

    public function getHolidayDates(int $year): array
    {
        $holidays = $this->brasilApi->holidays($year);

        return collect($holidays)
            ->pluck('date')
            ->filter()
            ->map(fn (string $date) => Carbon::parse($date)->format('Y-m-d'))
            ->all();
    }

    public function isHoliday(Carbon $date, ?int $tenantId = null): bool
    {
        $holidays = $this->getHolidayDates($date->year);

        // Incluir feriados customizados do tenant
        if ($tenantId) {
            $tenantHolidays = TenantHoliday::where('tenant_id', $tenantId)
                ->whereYear('date', $date->year)
                ->pluck('date')
                ->map(fn ($d) => Carbon::parse($d)->format('Y-m-d'))
                ->all();
            $holidays = array_merge($holidays, $tenantHolidays);
        }

        return in_array($date->format('Y-m-d'), $holidays, true);
    }

    public function isBusinessDay(Carbon $date, ?int $tenantId = null): bool
    {
        if ($this->isHoliday($date, $tenantId)) {
            return false;
        }

        // Verificar horário comercial customizado do tenant
        if ($tenantId) {
            $businessHour = BusinessHour::where('tenant_id', $tenantId)
                ->where('day_of_week', $date->dayOfWeek)
                ->first();

            if ($businessHour) {
                return $businessHour->is_active;
            }
        }

        return ! $date->isWeekend();
    }

    public function addBusinessDays(Carbon $start, int $days): Carbon
    {
        $current = $start->copy();
        $added = 0;

        while ($added < $days) {
            $current->addDay();
            if ($this->isBusinessDay($current)) {
                $added++;
            }
        }

        return $current;
    }

    public function addBusinessMinutes(Carbon $start, int $minutes): Carbon
    {
        $current = $start->copy();
        $remaining = $minutes;
        $businessDayMinutes = 8 * 60; // 8-hour business day

        while ($remaining > 0) {
            if (! $this->isBusinessDay($current)) {
                $current->addDay()->startOfDay()->setHour(8);
                continue;
            }

            $minutesLeftToday = min($remaining, $businessDayMinutes);
            $remaining -= $minutesLeftToday;

            if ($remaining > 0) {
                $current->addDay()->startOfDay()->setHour(8);
            } else {
                $current->addMinutes($minutesLeftToday);
            }
        }

        return $current;
    }

    public function businessMinutesBetween(Carbon $start, Carbon $end): int
    {
        $current = $start->copy()->startOfDay();
        $endDay = $end->copy()->startOfDay();
        $businessDays = 0;

        while ($current->lte($endDay)) {
            if ($this->isBusinessDay($current)) {
                $businessDays++;
            }
            $current->addDay();
        }

        return $businessDays * 8 * 60; // 8h business day in minutes
    }
}
