<?php

namespace Tests\Feature\Api\V1;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\AccountReceivable;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OperationalDashboardControllerTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

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
        $this->user->tenants()->attach($this->tenant->id, ['is_default' => true]);

        $this->setTenantContext($this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    public function test_stats_returns_structure(): void
    {
        $response = $this->getJson('/api/v1/operational-dashboard/stats');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'work_orders' => ['open', 'in_progress', 'in_displacement'],
                    'equipment' => ['overdue', 'due_soon'],
                    'financial' => ['due_today_count', 'due_today_amount'],
                    'last_updated',
                ],
            ]);
    }

    public function test_active_displacements_returns_list(): void
    {
        $response = $this->getJson('/api/v1/operational-dashboard/active-displacements');

        $response->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_stats_count_only_current_tenant_records(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'status' => WorkOrder::STATUS_OPEN,
        ]);

        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'current_tenant_id' => $otherTenant->id,
        ]);
        $otherCustomer = Customer::factory()->create(['tenant_id' => $otherTenant->id]);
        WorkOrder::factory()->create([
            'tenant_id' => $otherTenant->id,
            'customer_id' => $otherCustomer->id,
            'created_by' => $otherUser->id,
            'status' => WorkOrder::STATUS_OPEN,
        ]);

        $response = $this->getJson('/api/v1/operational-dashboard/stats');

        $response->assertOk()
            ->assertJsonPath('data.work_orders.open', 1);
    }

    public function test_active_displacements_count_only_current_tenant_records(): void
    {
        $customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Cliente atual',
        ]);
        WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'assigned_to' => $this->user->id,
            'status' => WorkOrder::STATUS_IN_PROGRESS,
            'displacement_started_at' => now()->subMinutes(30),
            'displacement_arrived_at' => null,
        ]);

        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'current_tenant_id' => $otherTenant->id,
        ]);
        $otherCustomer = Customer::factory()->create(['tenant_id' => $otherTenant->id]);
        WorkOrder::factory()->create([
            'tenant_id' => $otherTenant->id,
            'customer_id' => $otherCustomer->id,
            'created_by' => $otherUser->id,
            'assigned_to' => $otherUser->id,
            'status' => WorkOrder::STATUS_IN_PROGRESS,
            'displacement_started_at' => now()->subMinutes(30),
            'displacement_arrived_at' => null,
        ]);

        $response = $this->getJson('/api/v1/operational-dashboard/active-displacements');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.customer', 'Cliente atual');
    }

    public function test_stats_include_equipment_and_financial_metrics(): void
    {
        Equipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
            'next_calibration_at' => now()->subDay(),
        ]);

        AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'due_date' => now()->toDateString(),
            'status' => AccountReceivable::STATUS_PENDING,
            'amount' => 200,
            'amount_paid' => 50,
        ]);

        $response = $this->getJson('/api/v1/operational-dashboard/stats');

        $response->assertOk()
            ->assertJsonPath('data.equipment.overdue', 1)
            ->assertJsonPath('data.financial.due_today_count', 1)
            ->assertJsonPath('data.financial.due_today_amount', 150);
    }

    public function test_stats_count_due_soon_equipment(): void
    {
        Equipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
            'next_calibration_at' => now()->addDays(3),
        ]);

        $response = $this->getJson('/api/v1/operational-dashboard/stats');

        $response->assertOk()
            ->assertJsonPath('data.equipment.due_soon', 1);
    }

    public function test_active_displacements_excludes_arrived_work_orders(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'assigned_to' => $this->user->id,
            'status' => WorkOrder::STATUS_IN_PROGRESS,
            'displacement_started_at' => now()->subHour(),
            'displacement_arrived_at' => now()->subMinutes(30),
        ]);

        $response = $this->getJson('/api/v1/operational-dashboard/active-displacements');

        $response->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_stats_count_in_progress_and_displacement_work_orders(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'assigned_to' => $this->user->id,
            'status' => WorkOrder::STATUS_IN_PROGRESS,
            'displacement_started_at' => now()->subHour(),
            'displacement_arrived_at' => null,
        ]);

        $response = $this->getJson('/api/v1/operational-dashboard/stats');

        $response->assertOk()
            ->assertJsonPath('data.work_orders.in_progress', 1)
            ->assertJsonPath('data.work_orders.in_displacement', 1);
    }
}
