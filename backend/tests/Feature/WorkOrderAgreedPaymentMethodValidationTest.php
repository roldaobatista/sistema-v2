<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WorkOrderAgreedPaymentMethodValidationTest extends TestCase
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
            'is_active' => true,
        ]);
        $this->customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        app()->instance('current_tenant_id', $this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    private function makeCompletedWorkOrder(): WorkOrder
    {
        return WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'assigned_to' => $this->user->id,
            'status' => WorkOrder::STATUS_COMPLETED,
        ]);
    }

    public function test_delivered_requires_agreed_payment_method(): void
    {
        $workOrder = $this->makeCompletedWorkOrder();

        $this->putJson("/api/v1/work-orders/{$workOrder->id}/status", [
            'status' => WorkOrder::STATUS_DELIVERED,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['agreed_payment_method']);
    }

    public function test_delivered_rejects_non_enum_agreed_payment_method(): void
    {
        $workOrder = $this->makeCompletedWorkOrder();

        $this->putJson("/api/v1/work-orders/{$workOrder->id}/status", [
            'status' => WorkOrder::STATUS_DELIVERED,
            'agreed_payment_method' => 'PIX',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['agreed_payment_method']);
    }

    public function test_delivered_accepts_valid_agreed_payment_method_code(): void
    {
        $workOrder = $this->makeCompletedWorkOrder();

        $this->putJson("/api/v1/work-orders/{$workOrder->id}/status", [
            'status' => WorkOrder::STATUS_DELIVERED,
            'agreed_payment_method' => 'pix',
        ])
            ->assertOk();

        $this->assertDatabaseHas('work_orders', [
            'id' => $workOrder->id,
            'status' => WorkOrder::STATUS_DELIVERED,
            'agreed_payment_method' => 'pix',
        ]);
    }
}
