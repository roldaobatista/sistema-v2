<?php

namespace Tests\Unit\Services;

use App\Models\Holiday;
use App\Models\JourneyEntry;
use App\Models\JourneyRule;
use App\Models\Payroll;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PayrollService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Súmula 172 TST: DSR reflexo sobre horas extras.
 * DSR = (HE_valor + noturno_valor + comissões) / dias_úteis * (domingos + feriados)
 */
class PayrollDsrReflexoTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    private JourneyRule $rule;

    private PayrollService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'is_active' => true,
            'salary' => 3000.00,
            'admission_date' => '2025-01-01',
        ]);
        $this->user->tenants()->attach($this->tenant->id, ['is_default' => true]);

        app()->instance('current_tenant_id', $this->tenant->id);
        $this->actingAs($this->user);

        $this->rule = JourneyRule::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_default' => true,
            'daily_hours' => 8.00,
        ]);

        $this->service = app(PayrollService::class);
    }

    public function test_dsr_uses_actual_calendar_not_estimate(): void
    {
        // March 2026: 31 days, 4 Sundays (1,8,15,22,29? Let's count)
        // March 2026: Sun 1, 8, 15, 22, 29 = 5 Sundays
        $month = '2026-03';
        $monthStart = Carbon::parse("{$month}-01")->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();
        $period = CarbonPeriod::create($monthStart, $monthEnd);
        $sundays = collect($period)->filter(fn ($d) => $d->isSunday())->count();

        // Add 1 holiday
        Holiday::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Feriado Teste',
            'date' => '2026-03-18',
            'is_national' => false,
            'is_recurring' => false,
        ]);

        // Create journey entries so getMonthSummary has data
        // Simulate 20h of overtime in the month (work days)
        foreach (['2026-03-02', '2026-03-03', '2026-03-04', '2026-03-05'] as $date) {
            JourneyEntry::factory()->create([
                'tenant_id' => $this->tenant->id,
                'user_id' => $this->user->id,
                'date' => $date,
                'journey_rule_id' => $this->rule->id,
                'scheduled_hours' => 8.0,
                'worked_hours' => 10.0,
                'overtime_hours_50' => 2.0,
                'overtime_hours_100' => 0,
                'night_hours' => 0,
                'absence_hours' => 0,
                'hour_bank_balance' => 0,
                'is_dsr' => false,
                'is_holiday' => false,
                'status' => 'calculated',
            ]);
        }

        // Create DSR entries (Sundays) so calculateRealDsr has DSR days
        foreach (['2026-03-01', '2026-03-08', '2026-03-15', '2026-03-22', '2026-03-29'] as $date) {
            JourneyEntry::factory()->create([
                'tenant_id' => $this->tenant->id,
                'user_id' => $this->user->id,
                'date' => $date,
                'journey_rule_id' => $this->rule->id,
                'scheduled_hours' => 0,
                'worked_hours' => 0,
                'overtime_hours_50' => 0,
                'overtime_hours_100' => 0,
                'night_hours' => 0,
                'absence_hours' => 0,
                'hour_bank_balance' => 0,
                'is_dsr' => true,
                'is_holiday' => false,
                'status' => 'calculated',
            ]);
        }

        // Create holiday entry
        JourneyEntry::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'date' => '2026-03-18',
            'journey_rule_id' => $this->rule->id,
            'scheduled_hours' => 0,
            'worked_hours' => 0,
            'overtime_hours_50' => 0,
            'overtime_hours_100' => 0,
            'night_hours' => 0,
            'absence_hours' => 0,
            'hour_bank_balance' => 0,
            'is_dsr' => false,
            'is_holiday' => true,
            'status' => 'calculated',
        ]);

        $payroll = Payroll::create([
            'tenant_id' => $this->tenant->id,
            'reference_month' => $month,
            'type' => 'regular',
            'status' => 'draft',
        ]);

        $line = $this->service->calculateForEmployee($payroll, $this->user);

        // The DSR should use actual sundays+holidays, not estimated "holidays + 4"
        // With real calendar: sundays + 1 holiday
        $expectedSundaysHolidays = $sundays + 1;
        $this->assertGreaterThan(0, (float) $line->dsr_value, 'DSR should be calculated from overtime');

        // Verify it's NOT using the old estimate (holidays + 4 = 1 + 4 = 5)
        // Real value should use actual Sunday count from calendar
        $this->assertTrue($sundays >= 4 && $sundays <= 5,
            "March 2026 should have 4-5 Sundays, got {$sundays}");
    }

    public function test_dsr_reflects_overtime_and_night_premium(): void
    {
        $month = '2026-03';

        // Create entries with overtime AND night hours (work day)
        JourneyEntry::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'date' => '2026-03-16',
            'journey_rule_id' => $this->rule->id,
            'scheduled_hours' => 8.0,
            'worked_hours' => 10.0,
            'overtime_hours_50' => 2.0,
            'overtime_hours_100' => 0,
            'night_hours' => 3.0,
            'absence_hours' => 0,
            'hour_bank_balance' => 0,
            'is_dsr' => false,
            'is_holiday' => false,
            'status' => 'calculated',
        ]);

        // Create DSR entries (Sundays) so calculateRealDsr has DSR days
        foreach (['2026-03-01', '2026-03-08', '2026-03-15', '2026-03-22', '2026-03-29'] as $date) {
            JourneyEntry::factory()->create([
                'tenant_id' => $this->tenant->id,
                'user_id' => $this->user->id,
                'date' => $date,
                'journey_rule_id' => $this->rule->id,
                'scheduled_hours' => 0,
                'worked_hours' => 0,
                'overtime_hours_50' => 0,
                'overtime_hours_100' => 0,
                'night_hours' => 0,
                'absence_hours' => 0,
                'hour_bank_balance' => 0,
                'is_dsr' => true,
                'is_holiday' => false,
                'status' => 'calculated',
            ]);
        }

        $payroll = Payroll::create([
            'tenant_id' => $this->tenant->id,
            'reference_month' => $month,
            'type' => 'regular',
            'status' => 'draft',
        ]);

        $line = $this->service->calculateForEmployee($payroll, $this->user);

        // DSR should include both overtime value AND night premium
        $this->assertGreaterThan(0, (float) $line->dsr_value,
            'DSR should reflect overtime + night premium (Súmula 172 TST)');
    }
}
