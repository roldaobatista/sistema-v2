<?php

namespace Tests\Unit\Models;

use App\Models\Department;
use App\Models\Holiday;
use App\Models\JourneyRule;
use App\Models\Position;
use App\Models\Skill;
use App\Models\Tenant;
use App\Models\TimeClockEntry;
use App\Models\User;
use App\Models\WorkSchedule;
use Carbon\Carbon;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class HrModelsTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

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
    }

    // ── Department ──

    public function test_department_belongs_to_tenant(): void
    {
        $dept = Department::create(['tenant_id' => $this->tenant->id, 'name' => 'Engineering']);
        $this->assertEquals($this->tenant->id, $dept->tenant_id);
    }

    public function test_department_has_fillable_name(): void
    {
        $dept = new Department;
        $this->assertContains('name', $dept->getFillable());
    }

    // ── Position ──

    public function test_position_belongs_to_tenant(): void
    {
        $dept = Department::create(['tenant_id' => $this->tenant->id, 'name' => 'Metrologia']);
        $pos = Position::create(['tenant_id' => $this->tenant->id, 'name' => 'Técnico Metrologia', 'department_id' => $dept->id]);
        $this->assertEquals($this->tenant->id, $pos->tenant_id);
    }

    public function test_position_belongs_to_department(): void
    {
        $dept = Department::create(['tenant_id' => $this->tenant->id, 'name' => 'Técnico']);
        $pos = Position::create(['tenant_id' => $this->tenant->id, 'name' => 'Técnico Jr', 'department_id' => $dept->id]);

        $this->assertInstanceOf(Department::class, $pos->department);
    }

    // ── Holiday ──

    public function test_holiday_belongs_to_tenant(): void
    {
        $holiday = Holiday::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Natal',
            'date' => '2026-12-25',
        ]);

        $this->assertEquals($this->tenant->id, $holiday->tenant_id);
    }

    public function test_holiday_stores_date_correctly(): void
    {
        $holiday = Holiday::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Ano Novo',
            'date' => '2026-01-01',
        ]);

        $holiday->refresh();
        $this->assertEquals('2026-01-01', $holiday->date);
    }

    // ── TimeClockEntry ──

    public function test_time_clock_entry_belongs_to_user(): void
    {
        $entry = TimeClockEntry::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'clock_in' => now(),
            'type' => 'regular',
        ]);

        $this->assertInstanceOf(User::class, $entry->user);
    }

    public function test_time_clock_entry_datetime_cast(): void
    {
        $entry = TimeClockEntry::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'clock_in' => '2026-03-15 08:00:00',
            'type' => 'regular',
        ]);

        $entry->refresh();
        $this->assertInstanceOf(Carbon::class, $entry->clock_in);
    }

    // ── JourneyRule ──

    public function test_journey_rule_belongs_to_tenant(): void
    {
        $rule = JourneyRule::create([
            'tenant_id' => $this->tenant->id,
            'name' => '44h semanal',
            'weekly_hours' => 44,
            'daily_hours' => 8.8,
        ]);

        $this->assertEquals($this->tenant->id, $rule->tenant_id);
    }

    // ── Skill ──

    public function test_skill_belongs_to_tenant(): void
    {
        $skill = Skill::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Calibração Balança',
        ]);

        $this->assertEquals($this->tenant->id, $skill->tenant_id);
    }

    // ── WorkSchedule ──

    public function test_work_schedule_belongs_to_tenant(): void
    {
        $ws = WorkSchedule::create([
            'tenant_id' => $this->tenant->id,
            'technician_id' => $this->user->id,
            'date' => '2026-03-17',
            'shift_type' => 'regular',
            'start_time' => '08:00',
            'end_time' => '17:00',
        ]);

        $this->assertEquals($this->tenant->id, $ws->tenant_id);
    }
}
