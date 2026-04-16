<?php

namespace Tests\Feature\Api\V1;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Tenant;
use App\Models\TimeClockEntry;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AutoClockFromOSTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    private User $technician;

    protected function setUp(): void
    {
        parent::setUp();
        Gate::before(fn () => true);
        $this->withoutMiddleware([
            EnsureTenantScope::class,
            CheckPermission::class,
        ]);
        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $this->technician = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        app()->instance('current_tenant_id', $this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    public function test_auto_clock_in_created_when_os_starts_service(): void
    {
        $workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'assigned_to' => $this->technician->id,
            'status' => WorkOrder::STATUS_AT_CLIENT,
            'created_by' => $this->user->id,
        ]);

        // Transition to in_service triggers auto clock-in via observer
        $workOrder->status = WorkOrder::STATUS_IN_SERVICE;
        $workOrder->save();

        $clockEntry = TimeClockEntry::where('user_id', $this->technician->id)
            ->where('work_order_id', $workOrder->id)
            ->where('clock_method', 'auto_os')
            ->first();

        $this->assertNotNull($clockEntry, 'TimeClockEntry should be created with clock_method=auto_os');
        $this->assertEquals('auto_os', $clockEntry->clock_method);
        $this->assertNotNull($clockEntry->clock_in);
        $this->assertNull($clockEntry->clock_out);
    }

    public function test_auto_clock_out_set_when_os_completed(): void
    {
        $workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'assigned_to' => $this->technician->id,
            'status' => WorkOrder::STATUS_AT_CLIENT,
            'created_by' => $this->user->id,
        ]);

        // First go to in_service (creates clock-in)
        $workOrder->status = WorkOrder::STATUS_IN_SERVICE;
        $workOrder->save();

        // Then transition through awaiting_return to completed (creates clock-out)
        $workOrder->status = WorkOrder::STATUS_AWAITING_RETURN;
        $workOrder->save();

        $workOrder->status = WorkOrder::STATUS_COMPLETED;
        $workOrder->save();

        $clockEntry = TimeClockEntry::where('user_id', $this->technician->id)
            ->where('work_order_id', $workOrder->id)
            ->where('clock_method', 'auto_os')
            ->first();

        $this->assertNotNull($clockEntry, 'TimeClockEntry should exist');
        $this->assertNotNull($clockEntry->clock_out, 'clock_out should be set after OS completed');
    }
}
