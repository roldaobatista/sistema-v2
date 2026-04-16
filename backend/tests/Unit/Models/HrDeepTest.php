<?php

namespace Tests\Unit\Models;

use App\Models\Department;
use App\Models\LeaveRequest;
use App\Models\Position;
use App\Models\Tenant;
use App\Models\TimeClockEntry;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class HrDeepTest extends TestCase
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

    public function test_department_creation(): void
    {
        $d = Department::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->assertNotNull($d);
    }

    public function test_department_has_positions(): void
    {
        $d = Department::factory()->create(['tenant_id' => $this->tenant->id]);
        Position::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'department_id' => $d->id,
        ]);
        $this->assertGreaterThanOrEqual(3, $d->positions()->count());
    }

    public function test_department_destroy_removes_record(): void
    {
        $d = Department::factory()->create(['tenant_id' => $this->tenant->id]);
        $d->delete();
        $this->assertNull(Department::query()->find($d->id));
    }

    public function test_department_hierarchy(): void
    {
        $parent = Department::factory()->create(['tenant_id' => $this->tenant->id]);
        $child = Department::factory()->create([
            'tenant_id' => $this->tenant->id,
            'parent_id' => $parent->id,
        ]);
        $this->assertEquals($parent->id, $child->parent_id);
    }

    // ── Position ──

    public function test_position_belongs_to_department(): void
    {
        $d = Department::factory()->create(['tenant_id' => $this->tenant->id]);
        $p = Position::factory()->create([
            'tenant_id' => $this->tenant->id,
            'department_id' => $d->id,
        ]);
        $this->assertInstanceOf(Department::class, $p->department);
    }

    public function test_position_level_defaults_to_pleno(): void
    {
        $department = Department::factory()->create(['tenant_id' => $this->tenant->id]);

        $p = Position::factory()->create([
            'tenant_id' => $this->tenant->id,
            'department_id' => $department->id,
        ]);
        $this->assertEquals('pleno', $p->fresh()->level);
    }

    // ── TimeClockEntry ──

    public function test_time_clock_check_in(): void
    {
        $entry = TimeClockEntry::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'type' => 'check_in',
        ]);
        $this->assertEquals('check_in', $entry->type);
    }

    public function test_time_clock_check_out(): void
    {
        $entry = TimeClockEntry::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'type' => 'check_out',
        ]);
        $this->assertEquals('check_out', $entry->type);
    }

    public function test_time_clock_belongs_to_user(): void
    {
        $entry = TimeClockEntry::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
        ]);
        $this->assertInstanceOf(User::class, $entry->user);
    }

    public function test_time_clock_clock_in_cast(): void
    {
        $entry = TimeClockEntry::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
        ]);
        $entry->refresh();
        $this->assertInstanceOf(Carbon::class, $entry->clock_in);
    }

    // ── LeaveRequest ──

    public function test_leave_request_creation(): void
    {
        $lr = LeaveRequest::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'type' => 'vacation',
            'status' => 'pending',
        ]);
        $this->assertEquals('vacation', $lr->type);
    }

    public function test_leave_request_approval(): void
    {
        $lr = LeaveRequest::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'status' => 'pending',
        ]);
        $lr->update([
            'status' => 'approved',
            'approved_by' => $this->user->id,
            'approved_at' => now(),
        ]);
        $this->assertEquals('approved', $lr->fresh()->status);
    }

    public function test_leave_request_rejection(): void
    {
        $lr = LeaveRequest::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'status' => 'pending',
        ]);
        $lr->update(['status' => 'rejected', 'rejection_reason' => 'Período indisponível']);
        $this->assertEquals('rejected', $lr->fresh()->status);
    }

    public function test_leave_request_date_range(): void
    {
        $lr = LeaveRequest::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'start_date' => now(),
            'end_date' => now()->addDays(5),
        ]);
        $this->assertTrue($lr->start_date->isBefore($lr->end_date));
    }

    public function test_leave_request_types(): void
    {
        foreach (['vacation', 'sick', 'personal', 'maternity'] as $type) {
            $lr = LeaveRequest::factory()->create([
                'tenant_id' => $this->tenant->id,
                'user_id' => $this->user->id,
                'type' => $type,
            ]);
            $this->assertEquals($type, $lr->type);
        }
    }
}
