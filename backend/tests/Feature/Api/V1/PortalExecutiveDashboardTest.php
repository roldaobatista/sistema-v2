<?php

namespace Tests\Feature\Api\V1;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\AccountReceivable;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PortalExecutiveDashboardTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    private Customer $customer;

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
        $this->customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        app()->instance('current_tenant_id', $this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    public function test_executive_dashboard_uses_open_invoice_balance_for_partial_and_overdue_titles(): void
    {
        AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'amount' => 1000.00,
            'amount_paid' => 400.00,
            'status' => 'partial',
            'due_date' => now()->subDays(5),
        ]);

        AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'amount' => 800.00,
            'amount_paid' => 100.00,
            'status' => 'overdue',
            'due_date' => now()->subDays(20),
        ]);

        AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'amount' => 500.00,
            'amount_paid' => 500.00,
            'status' => 'paid',
            'due_date' => now()->subDays(30),
        ]);

        $data = $this->getJson("/api/v1/portal/dashboard/{$this->customer->id}")
            ->assertOk()
            ->json('data');

        $this->assertEquals(1300.0, $data['stats']['open_invoices']);
    }

    public function test_executive_dashboard_counts_operational_work_orders_as_pending(): void
    {
        WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'status' => WorkOrder::STATUS_OPEN,
        ]);

        WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'status' => WorkOrder::STATUS_AWAITING_DISPATCH,
        ]);

        WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'status' => WorkOrder::STATUS_IN_SERVICE,
        ]);

        WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'status' => WorkOrder::STATUS_COMPLETED,
        ]);

        WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'status' => WorkOrder::STATUS_CANCELLED,
        ]);

        $data = $this->getJson("/api/v1/portal/dashboard/{$this->customer->id}")
            ->assertOk()
            ->json('data');

        $this->assertSame(3, $data['stats']['os_pending']);
        $this->assertSame(1, $data['stats']['os_completed']);
        $this->assertSame(5, $data['stats']['total_os']);
    }
}
