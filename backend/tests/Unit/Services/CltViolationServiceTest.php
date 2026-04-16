<?php

namespace Tests\Unit\Services;

use App\Models\CltViolation;
use App\Models\JourneyEntry;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CltViolationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class CltViolationServiceTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    private CltViolationService $service;

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

        $this->service = new CltViolationService;
    }

    public function test_detects_overtime_limit_exceeded_art59(): void
    {
        $entry = JourneyEntry::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'date' => '2026-03-18',
            'overtime_limit_exceeded' => true,
            'overtime_hours_50' => 3.0,
            'worked_hours' => 11.0,
        ]);

        $violations = $this->service->detectDailyViolations($entry);

        $this->assertNotEmpty($violations);
        $found = collect($violations)->firstWhere('violation_type', 'overtime_limit_exceeded');
        $this->assertNotNull($found);
        $this->assertEquals('medium', $found->severity);
    }

    public function test_detects_short_break_art71(): void
    {
        $entry = JourneyEntry::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'date' => '2026-03-18',
            'break_compliance' => 'short_break',
            'worked_hours' => 8.0,
        ]);

        $violations = $this->service->detectDailyViolations($entry);

        $found = collect($violations)->firstWhere('violation_type', 'intra_shift_short');
        $this->assertNotNull($found);
        $this->assertEquals('high', $found->severity);
    }

    public function test_detects_missing_break_art71(): void
    {
        $entry = JourneyEntry::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'date' => '2026-03-18',
            'break_compliance' => 'missing_break',
            'worked_hours' => 8.0,
        ]);

        $violations = $this->service->detectDailyViolations($entry);

        $found = collect($violations)->firstWhere('violation_type', 'intra_shift_missing');
        $this->assertNotNull($found);
        $this->assertEquals('high', $found->severity);
    }

    public function test_detects_inter_shift_short_art66(): void
    {
        $entry = JourneyEntry::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'date' => '2026-03-18',
            'inter_shift_hours' => 9.5,
            'worked_hours' => 8.0,
        ]);

        $violations = $this->service->detectDailyViolations($entry);

        $found = collect($violations)->firstWhere('violation_type', 'inter_shift_short');
        $this->assertNotNull($found);
        $this->assertEquals('critical', $found->severity);
    }

    public function test_inter_shift_11h_is_compliant(): void
    {
        $entry = JourneyEntry::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'date' => '2026-03-18',
            'inter_shift_hours' => 11.0,
            'worked_hours' => 8.0,
        ]);

        $violations = $this->service->detectDailyViolations($entry);

        $interShiftViolation = collect($violations)->firstWhere('violation_type', 'inter_shift_short');
        $this->assertNull($interShiftViolation);
    }

    /**
     * Art. 67 CLT: 7 dias consecutivos trabalhados devem gerar violação DSR.
     */
    public function test_detects_dsr_missing_7_consecutive_days_art67(): void
    {
        // Create 7 consecutive worked days
        for ($i = 0; $i < 7; $i++) {
            JourneyEntry::factory()->create([
                'tenant_id' => $this->tenant->id,
                'user_id' => $this->user->id,
                'date' => Carbon::parse('2026-03-12')->addDays($i)->toDateString(),
                'worked_hours' => 8.0,
            ]);
        }

        $result = $this->service->detectConsecutiveWorkDays(
            $this->user->id,
            $this->tenant->id,
            '2026-03-18'
        );

        $this->assertNotNull($result);
        $this->assertEquals('dsr_missing', $result->violation_type);
        $this->assertEquals('critical', $result->severity);
        $this->assertEquals($this->user->id, $result->user_id);
        $this->assertEquals($this->tenant->id, $result->tenant_id);
    }

    public function test_no_dsr_violation_with_6_consecutive_days(): void
    {
        for ($i = 0; $i < 6; $i++) {
            JourneyEntry::factory()->create([
                'tenant_id' => $this->tenant->id,
                'user_id' => $this->user->id,
                'date' => Carbon::parse('2026-03-13')->addDays($i)->toDateString(),
                'worked_hours' => 8.0,
            ]);
        }

        $result = $this->service->detectConsecutiveWorkDays(
            $this->user->id,
            $this->tenant->id,
            '2026-03-18'
        );

        $this->assertNull($result);
    }

    public function test_prevents_duplicate_violations(): void
    {
        $entry = JourneyEntry::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'date' => '2026-03-18',
            'overtime_limit_exceeded' => true,
            'overtime_hours_50' => 3.0,
            'worked_hours' => 11.0,
        ]);

        $violations1 = $this->service->detectDailyViolations($entry);
        $violations2 = $this->service->detectDailyViolations($entry);

        // Check using withoutGlobalScopes to avoid tenant filtering
        $total = CltViolation::withoutGlobalScopes()
            ->where('user_id', $this->user->id)
            ->where('tenant_id', $this->tenant->id)
            ->where('violation_type', 'overtime_limit_exceeded')
            ->count();

        $this->assertEquals(1, $total, 'Should not create duplicate violations');
    }

    public function test_no_violations_for_compliant_day(): void
    {
        $entry = JourneyEntry::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'date' => '2026-03-18',
            'overtime_limit_exceeded' => false,
            'break_compliance' => 'ok',
            'inter_shift_hours' => 12.0,
            'worked_hours' => 8.0,
        ]);

        $violations = $this->service->detectDailyViolations($entry);

        $this->assertEmpty($violations);
    }
}
