<?php

namespace Tests\Unit\Services;

use App\Models\Holiday;
use App\Models\JourneyEntry;
use App\Models\JourneyRule;
use App\Models\Tenant;
use App\Models\TimeClockEntry;
use App\Models\User;
use App\Services\JourneyCalculationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class JourneyCalculationServiceTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    private JourneyRule $rule;

    private JourneyCalculationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $this->user->tenants()->attach($this->tenant->id, ['is_default' => true]);

        app()->instance('current_tenant_id', $this->tenant->id);
        $this->actingAs($this->user);

        $this->rule = JourneyRule::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_default' => true,
            'daily_hours' => 8.00,
            'weekly_hours' => 44.00,
            'overtime_weekday_pct' => 50,
            'overtime_weekend_pct' => 100,
            'overtime_holiday_pct' => 100,
            'night_shift_pct' => 20,
            'night_start' => '22:00',
            'night_end' => '05:00',
            'uses_hour_bank' => false,
        ]);

        $this->service = app(JourneyCalculationService::class);
    }

    /**
     * Helper to create a clock entry for a specific date with given hours.
     */
    private function createClockEntry(string $date, float $hours, ?string $startTime = '08:00'): TimeClockEntry
    {
        $clockIn = Carbon::parse("{$date} {$startTime}");
        $clockOut = $clockIn->copy()->addMinutes((int) ($hours * 60));

        return TimeClockEntry::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'clock_in' => $clockIn,
            'clock_out' => $clockOut,
            'type' => 'regular',
            'approval_status' => 'approved',
        ]);
    }

    public function test_calculate_day_normal_hours(): void
    {
        // A Wednesday with exactly 8 hours worked
        $date = '2026-03-18'; // Wednesday
        $this->createClockEntry($date, 8.0);

        $journeyEntry = $this->service->calculateDay($this->user->id, $date, $this->tenant->id);

        $this->assertInstanceOf(JourneyEntry::class, $journeyEntry);
        $this->assertEquals('8.00', $journeyEntry->worked_hours);
        $this->assertEquals('0.00', $journeyEntry->overtime_hours_50);
        $this->assertEquals('0.00', $journeyEntry->overtime_hours_100);
        $this->assertEquals('0.00', $journeyEntry->absence_hours);
        $this->assertEquals('calculated', $journeyEntry->status);
    }

    public function test_calculate_day_with_overtime_50(): void
    {
        // A Wednesday with 10 hours worked (2h overtime at 50%)
        $date = '2026-03-18'; // Wednesday
        $this->createClockEntry($date, 10.0);

        $journeyEntry = $this->service->calculateDay($this->user->id, $date, $this->tenant->id);

        $this->assertEquals('10.00', $journeyEntry->worked_hours);
        $this->assertEquals('2.00', $journeyEntry->overtime_hours_50);
        $this->assertEquals('0.00', $journeyEntry->overtime_hours_100);
    }

    public function test_calculate_day_weekend_overtime_100(): void
    {
        // A Sunday — all hours are 100% overtime
        $date = '2026-03-22'; // Sunday
        $this->createClockEntry($date, 6.0);

        $journeyEntry = $this->service->calculateDay($this->user->id, $date, $this->tenant->id);

        $this->assertEquals('6.00', $journeyEntry->worked_hours);
        $this->assertEquals('6.00', $journeyEntry->overtime_hours_100);
        $this->assertTrue($journeyEntry->is_dsr);
    }

    public function test_calculate_day_night_shift(): void
    {
        // Work from 22:00 to 05:00 (7 hours, all night)
        $date = '2026-03-18'; // Wednesday
        $clockIn = Carbon::parse("{$date} 22:00");
        $clockOut = Carbon::parse('2026-03-19 05:00');

        TimeClockEntry::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'clock_in' => $clockIn,
            'clock_out' => $clockOut,
            'type' => 'regular',
            'approval_status' => 'approved',
        ]);

        $journeyEntry = $this->service->calculateDay($this->user->id, $date, $this->tenant->id);

        $this->assertGreaterThan(0, (float) $journeyEntry->night_hours, 'Night hours should be calculated for work between 22:00-05:00');
    }

    public function test_calculate_day_holiday(): void
    {
        // Create a holiday
        $date = '2026-03-18'; // Wednesday
        Holiday::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Feriado teste',
            'date' => $date,
            'is_national' => false,
            'is_recurring' => false,
        ]);

        $this->createClockEntry($date, 8.0);

        $journeyEntry = $this->service->calculateDay($this->user->id, $date, $this->tenant->id);

        $this->assertTrue($journeyEntry->is_holiday);
        $this->assertEquals('8.00', $journeyEntry->overtime_hours_100, 'All hours on holiday should be 100% overtime');
    }

    public function test_calculate_month_summary(): void
    {
        // Create entries for a few weekdays in March 2026
        $weekdays = ['2026-03-16', '2026-03-17', '2026-03-18']; // Mon, Tue, Wed
        foreach ($weekdays as $date) {
            $this->createClockEntry($date, 8.0);
        }

        $entries = $this->service->calculateMonth($this->user->id, '2026-03', $this->tenant->id);

        $this->assertIsArray($entries);
        // March has 31 days, so we should get 31 entries
        $this->assertCount(31, $entries);

        // Check that worked days have hours
        $workedEntries = collect($entries)->filter(fn ($e) => (float) $e->worked_hours > 0);
        $this->assertCount(3, $workedEntries, 'Should have 3 days with worked hours');
    }

    public function test_hour_bank_balance(): void
    {
        // Enable hour bank on the rule
        $this->rule->update(['uses_hour_bank' => true]);

        // Day 1: 10h worked (2h overtime at 50%) — balance should be +2
        $date1 = '2026-03-16'; // Monday
        $this->createClockEntry($date1, 10.0);
        $entry1 = $this->service->calculateDay($this->user->id, $date1, $this->tenant->id);

        $this->assertEquals('2.00', $entry1->hour_bank_balance, 'Hour bank should accumulate overtime hours');

        // Day 2: 6h worked (2h absence) — balance should be 2 - 2 = 0
        $date2 = '2026-03-17'; // Tuesday
        $this->createClockEntry($date2, 6.0);
        $entry2 = $this->service->calculateDay($this->user->id, $date2, $this->tenant->id);

        $this->assertEquals('0.00', $entry2->hour_bank_balance, 'Hour bank should subtract absence hours');
    }
}
