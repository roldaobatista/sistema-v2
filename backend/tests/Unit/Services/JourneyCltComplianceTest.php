<?php

namespace Tests\Unit\Services;

use App\Models\Holiday;
use App\Models\JourneyRule;
use App\Models\Tenant;
use App\Models\TimeClockEntry;
use App\Models\User;
use App\Services\JourneyCalculationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * CLT Legal Compliance Tests for JourneyCalculationService.
 *
 * B1: Hora Noturna Reduzida (Art. 73 §1 CLT) — 52min30s = 1h
 * B2: Tolerância 5/10min (Art. 58 §1 CLT)
 * B3: Limite 2h extras/dia (Art. 59 caput CLT)
 * B4: Intervalo Intrajornada (Art. 71 CLT)
 * B5: Intervalo Interjornada (Art. 66 CLT)
 */
class JourneyCltComplianceTest extends TestCase
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

    private function createClockEntryRaw(string $clockIn, string $clockOut, array $extra = []): TimeClockEntry
    {
        $tz = 'America/Sao_Paulo';

        return TimeClockEntry::factory()->create(array_merge([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'clock_in' => Carbon::parse($clockIn, $tz)->utc(),
            'clock_out' => Carbon::parse($clockOut, $tz)->utc(),
            'type' => 'regular',
            'approval_status' => 'approved',
        ], $extra));
    }

    // ═══════════════════════════════════════════════════════════════
    // B1: HORA NOTURNA REDUZIDA (Art. 73 §1 CLT)
    // 52min30s reais = 1 hora paga
    // ═══════════════════════════════════════════════════════════════

    public function test_b1_night_full_shift_22_to_05_gives_8_paid_hours(): void
    {
        // 22:00-05:00 = 420 real minutes = 420/52.5 = 8.0 paid night hours
        $date = '2026-03-18'; // Wednesday
        $this->createClockEntryRaw("{$date} 22:00", '2026-03-19 05:00');

        $entry = $this->service->calculateDay($this->user->id, $date, $this->tenant->id);

        $this->assertEquals(8.0, round((float) $entry->night_hours, 1),
            'Art. 73 §1: 420 real night minutes / 52.5 = 8.0 paid hours');
    }

    public function test_b1_night_one_hour_gives_1_14_paid_hours(): void
    {
        // 22:00-23:00 = 60 real minutes = 60/52.5 = 1.14 paid night hours
        $date = '2026-03-18';
        $this->createClockEntryRaw("{$date} 22:00", "{$date} 23:00");

        $entry = $this->service->calculateDay($this->user->id, $date, $this->tenant->id);

        $this->assertEqualsWithDelta(1.14, (float) $entry->night_hours, 0.02,
            'Art. 73 §1: 60 real night minutes / 52.5 ≈ 1.14 paid hours');
    }

    public function test_b1_partial_night_20_to_02_gives_correct_hours(): void
    {
        // 20:00-02:00 = 6h total, 4h noturnas (22:00-02:00 = 240min)
        // Night paid hours = 240/52.5 = 4.57
        $date = '2026-03-18';
        $this->createClockEntryRaw("{$date} 20:00", '2026-03-19 02:00');

        $entry = $this->service->calculateDay($this->user->id, $date, $this->tenant->id);

        $this->assertEqualsWithDelta(4.57, (float) $entry->night_hours, 0.02,
            'Art. 73 §1: 240 real night minutes / 52.5 ≈ 4.57 paid hours');
    }

    public function test_b1_daytime_shift_has_zero_night_hours(): void
    {
        $date = '2026-03-18';
        $this->createClockEntryRaw("{$date} 06:00", "{$date} 14:00");

        $entry = $this->service->calculateDay($this->user->id, $date, $this->tenant->id);

        $this->assertEquals(0, (float) $entry->night_hours,
            'Daytime shift should have zero night hours');
    }

    public function test_b1_night_bonus_increases_worked_hours(): void
    {
        // 22:00-05:00 = 420 real minutes, but with night bonus:
        // bonus = 420 * (60/52.5 - 1) = 420 * 0.142857 ≈ 60 bonus minutes
        // Total worked = 420 + 60 = 480 minutes = 8.0 hours
        $date = '2026-03-18';
        $this->createClockEntryRaw("{$date} 22:00", '2026-03-19 05:00');

        $entry = $this->service->calculateDay($this->user->id, $date, $this->tenant->id);

        $this->assertEquals(8.0, round((float) $entry->worked_hours, 1),
            'Art. 73 §1: Night bonus minutes must be added to worked hours (420 real + 60 bonus = 480min = 8h)');
    }

    // ═══════════════════════════════════════════════════════════════
    // B2: TOLERÂNCIA 5/10min (Art. 58 §1 CLT)
    // Variação ≤5min por marcação, total ≤10min/dia = não conta
    // ═══════════════════════════════════════════════════════════════

    public function test_b2_within_tolerance_rounds_to_scheduled(): void
    {
        // Worker arrives 5min early, leaves 4min late = 489min worked vs 480min scheduled
        // Total variation = 9min, each ≤5min → tolerance applied, count as 480min (8h)
        $date = '2026-03-18'; // Wednesday
        $clockIn = Carbon::parse("{$date} 07:55");
        $clockOut = $clockIn->copy()->addMinutes(489); // 07:55 + 489min = 16:04

        TimeClockEntry::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'clock_in' => $clockIn,
            'clock_out' => $clockOut,
            'type' => 'regular',
            'approval_status' => 'approved',
        ]);

        $entry = $this->service->calculateDay($this->user->id, $date, $this->tenant->id);

        $this->assertTrue((bool) $entry->tolerance_applied,
            'Art. 58 §1: Tolerance should be applied when variation ≤ 10min/day');
        $this->assertEquals('8.00', $entry->worked_hours,
            'Art. 58 §1: Worked hours should round to scheduled (8h) when tolerance applies');
        $this->assertEquals('0.00', $entry->overtime_hours_50,
            'Art. 58 §1: No overtime when tolerance is applied');
    }

    public function test_b2_12min_variation_exceeds_daily_tolerance(): void
    {
        // Worker works 492min (12min extra) vs 480min scheduled → > 10min, no tolerance
        $date = '2026-03-18';
        $clockIn = Carbon::parse("{$date} 07:54");
        $clockOut = $clockIn->copy()->addMinutes(492); // 492min worked, scheduled 480, diff=12

        TimeClockEntry::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'clock_in' => $clockIn,
            'clock_out' => $clockOut,
            'type' => 'regular',
            'approval_status' => 'approved',
        ]);

        $entry = $this->service->calculateDay($this->user->id, $date, $this->tenant->id);

        $this->assertFalse((bool) $entry->tolerance_applied,
            'Art. 58 §1: Tolerance should NOT apply when total variation > 10min/day');
    }

    public function test_b2_11min_total_exceeds_daily_tolerance(): void
    {
        // Two entries: each 5min30s variation (within per-entry) but total = 11min > 10min
        $date = '2026-03-18';
        // Entry 1: 245min (4h05min) vs 240min scheduled half = +5min
        // Entry 2: 246min vs 240min = +6min → individual > 5min, no tolerance
        // Better: just total variation 11min with individual ≤ 5min... hard with single entry.
        // Single entry: 491min worked vs 480min scheduled = 11min variation > 10min total
        $clockIn = Carbon::parse("{$date} 07:55");
        $clockOut = $clockIn->copy()->addMinutes(491);

        TimeClockEntry::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'clock_in' => $clockIn,
            'clock_out' => $clockOut,
            'type' => 'regular',
            'approval_status' => 'approved',
        ]);

        $entry = $this->service->calculateDay($this->user->id, $date, $this->tenant->id);

        $this->assertFalse((bool) $entry->tolerance_applied,
            'Art. 58 §1: Tolerance should NOT apply when total variation > 10min/day');
    }

    public function test_b2_late_arrival_within_tolerance_no_absence(): void
    {
        // Worker arrives 4min late, leaves on time = 476min worked vs 480min
        // Variation = 4min (< 5min per entry, < 10min total) → tolerance
        $date = '2026-03-18';
        $clockIn = Carbon::parse("{$date} 08:04");
        $clockOut = $clockIn->copy()->addMinutes(476);

        TimeClockEntry::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'clock_in' => $clockIn,
            'clock_out' => $clockOut,
            'type' => 'regular',
            'approval_status' => 'approved',
        ]);

        $entry = $this->service->calculateDay($this->user->id, $date, $this->tenant->id);

        $this->assertTrue((bool) $entry->tolerance_applied);
        $this->assertEquals('8.00', $entry->worked_hours);
        $this->assertEquals('0.00', $entry->absence_hours,
            'Art. 58 §1: No absence when tolerance is applied');
    }

    // ═══════════════════════════════════════════════════════════════
    // B3: LIMITE 2h EXTRAS/DIA (Art. 59 caput CLT)
    // ═══════════════════════════════════════════════════════════════

    public function test_b3_overtime_within_limit_not_exceeded(): void
    {
        // 10h on weekday = 2h overtime (exactly at limit)
        $date = '2026-03-18'; // Wednesday
        $this->createClockEntryRaw("{$date} 08:00", "{$date} 18:00"); // 10h

        $entry = $this->service->calculateDay($this->user->id, $date, $this->tenant->id);

        $this->assertEquals('2.00', $entry->overtime_hours_50);
        $this->assertFalse((bool) $entry->overtime_limit_exceeded,
            'Art. 59: 2h overtime should NOT flag limit exceeded');
    }

    public function test_b3_overtime_exceeds_limit_flags_warning(): void
    {
        // 11h on weekday = 3h overtime (exceeds 2h limit)
        $date = '2026-03-18'; // Wednesday
        $this->createClockEntryRaw("{$date} 08:00", "{$date} 19:00"); // 11h

        Log::shouldReceive('warning')->once();

        $entry = $this->service->calculateDay($this->user->id, $date, $this->tenant->id);

        $this->assertEquals('3.00', $entry->overtime_hours_50);
        $this->assertTrue((bool) $entry->overtime_limit_exceeded,
            'Art. 59: 3h overtime should flag limit exceeded');
    }

    public function test_b3_holiday_overtime_limit_does_not_apply(): void
    {
        // 10h on holiday = 100% overtime, no limit applies
        $date = '2026-03-18';
        Holiday::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Feriado Teste',
            'date' => $date,
            'is_national' => false,
            'is_recurring' => false,
        ]);

        $this->createClockEntryRaw("{$date} 08:00", "{$date} 18:00"); // 10h

        $entry = $this->service->calculateDay($this->user->id, $date, $this->tenant->id);

        $this->assertEquals('10.00', $entry->overtime_hours_100);
        $this->assertFalse((bool) $entry->overtime_limit_exceeded,
            'Art. 59: Overtime limit does not apply to holidays/DSR');
    }

    // ═══════════════════════════════════════════════════════════════
    // B4: INTERVALO INTRAJORNADA (Art. 71 CLT)
    // >6h → min 1h break | 4-6h → min 15min break | <4h → nenhum
    // ═══════════════════════════════════════════════════════════════

    public function test_b4_shift_over_6h_with_1h_break_is_compliant(): void
    {
        $date = '2026-03-18';
        $this->createClockEntryRaw("{$date} 08:00", "{$date} 17:00", [
            'break_start' => Carbon::parse("{$date} 12:00"),
            'break_end' => Carbon::parse("{$date} 13:00"),
        ]);

        $entry = $this->service->calculateDay($this->user->id, $date, $this->tenant->id);

        $this->assertEquals('compliant', $entry->break_compliance,
            'Art. 71: 1h break on >6h shift is compliant');
    }

    public function test_b4_shift_over_6h_with_30min_break_is_short(): void
    {
        $date = '2026-03-18';
        $this->createClockEntryRaw("{$date} 08:00", "{$date} 17:00", [
            'break_start' => Carbon::parse("{$date} 12:00"),
            'break_end' => Carbon::parse("{$date} 12:30"),
        ]);

        $entry = $this->service->calculateDay($this->user->id, $date, $this->tenant->id);

        $this->assertEquals('short_break', $entry->break_compliance,
            'Art. 71: 30min break on >6h shift is short_break');
    }

    public function test_b4_shift_over_6h_no_break_is_missing(): void
    {
        $date = '2026-03-18';
        $this->createClockEntryRaw("{$date} 08:00", "{$date} 17:00");
        // No break_start/break_end

        $entry = $this->service->calculateDay($this->user->id, $date, $this->tenant->id);

        $this->assertEquals('missing_break', $entry->break_compliance,
            'Art. 71: No break on >6h shift is missing_break');
    }

    public function test_b4_shift_4_to_6h_with_15min_break_is_compliant(): void
    {
        $date = '2026-03-18';
        $this->createClockEntryRaw("{$date} 08:00", "{$date} 13:00", [
            'break_start' => Carbon::parse("{$date} 10:00"),
            'break_end' => Carbon::parse("{$date} 10:15"),
        ]);

        $entry = $this->service->calculateDay($this->user->id, $date, $this->tenant->id);

        $this->assertEquals('compliant', $entry->break_compliance,
            'Art. 71: 15min break on 4-6h shift is compliant');
    }

    public function test_b4_shift_4_to_6h_with_10min_break_is_short(): void
    {
        $date = '2026-03-18';
        $this->createClockEntryRaw("{$date} 08:00", "{$date} 13:00", [
            'break_start' => Carbon::parse("{$date} 10:00"),
            'break_end' => Carbon::parse("{$date} 10:10"),
        ]);

        $entry = $this->service->calculateDay($this->user->id, $date, $this->tenant->id);

        $this->assertEquals('short_break', $entry->break_compliance,
            'Art. 71: 10min break on 4-6h shift is short_break');
    }

    public function test_b4_shift_under_4h_no_break_required(): void
    {
        $date = '2026-03-18';
        $this->createClockEntryRaw("{$date} 08:00", "{$date} 11:00"); // 3h, no break

        $entry = $this->service->calculateDay($this->user->id, $date, $this->tenant->id);

        $this->assertEquals('compliant', $entry->break_compliance,
            'Art. 71: No break required for shifts < 4h');
    }

    // ═══════════════════════════════════════════════════════════════
    // B5: INTERVALO INTERJORNADA (Art. 66 CLT)
    // Mínimo 11h de descanso entre jornadas
    // ═══════════════════════════════════════════════════════════════

    public function test_b5_inter_shift_14h_rest_is_compliant(): void
    {
        // Day 1: 08:00-18:00, Day 2: 08:00-18:00 → 14h rest → compliant
        $date1 = '2026-03-17'; // Tuesday
        $date2 = '2026-03-18'; // Wednesday

        $this->createClockEntryRaw("{$date1} 08:00", "{$date1} 18:00");
        $this->service->calculateDay($this->user->id, $date1, $this->tenant->id);

        $this->createClockEntryRaw("{$date2} 08:00", "{$date2} 18:00");
        $entry2 = $this->service->calculateDay($this->user->id, $date2, $this->tenant->id);

        $this->assertEqualsWithDelta(14.0, (float) $entry2->inter_shift_hours, 0.1,
            'Art. 66: Should calculate 14h rest between shifts');
    }

    public function test_b5_inter_shift_7h_rest_is_violation(): void
    {
        // Day 1: out 23:00, Day 2: in 06:00 → 7h rest → violation
        $date1 = '2026-03-17';
        $date2 = '2026-03-18';

        $this->createClockEntryRaw("{$date1} 14:00", "{$date1} 23:00");
        $this->service->calculateDay($this->user->id, $date1, $this->tenant->id);

        $this->createClockEntryRaw("{$date2} 06:00", "{$date2} 14:00");

        Log::shouldReceive('warning')->once();

        $entry2 = $this->service->calculateDay($this->user->id, $date2, $this->tenant->id);

        $this->assertEqualsWithDelta(7.0, (float) $entry2->inter_shift_hours, 0.1,
            'Art. 66: Should calculate 7h rest (violation < 11h)');
    }

    public function test_b5_first_day_no_previous_entry_is_null(): void
    {
        // No previous entry → inter_shift_hours should be null
        $date = '2026-03-18';
        $this->createClockEntryRaw("{$date} 08:00", "{$date} 17:00");

        $entry = $this->service->calculateDay($this->user->id, $date, $this->tenant->id);

        $this->assertNull($entry->inter_shift_hours,
            'Art. 66: First day should have null inter_shift_hours');
    }
}
