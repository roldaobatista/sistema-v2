<?php

namespace Tests\Feature\Api\V1\Os;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WorkOrderApprovalControllerTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    private User $approver;

    private WorkOrder $workOrder;

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

        $this->approver = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $this->approver->tenants()->attach($this->tenant->id, ['is_default' => true]);

        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'status' => WorkOrder::STATUS_OPEN,
        ]);

        $this->setTenantContext($this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    public function test_index_returns_empty_list_initially(): void
    {
        $response = $this->getJson("/api/v1/work-orders/{$this->workOrder->id}/approvals");

        $response->assertOk()->assertJsonStructure(['data']);
    }

    public function test_request_validates_required_approver_ids(): void
    {
        $response = $this->postJson("/api/v1/work-orders/{$this->workOrder->id}/approvals/request", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['approver_ids']);
    }

    public function test_request_rejects_approver_from_other_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $foreignUser = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'current_tenant_id' => $otherTenant->id,
        ]);

        $response = $this->postJson("/api/v1/work-orders/{$this->workOrder->id}/approvals/request", [
            'approver_ids' => [$foreignUser->id],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['approver_ids.0']);
    }

    public function test_request_creates_pending_approval_and_changes_status(): void
    {
        $response = $this->postJson("/api/v1/work-orders/{$this->workOrder->id}/approvals/request", [
            'approver_ids' => [$this->approver->id],
            'notes' => 'Aprovação urgente',
        ]);

        $response->assertStatus(201);

        $approval = DB::table('work_order_approvals')
            ->where('work_order_id', $this->workOrder->id)
            ->first();

        $this->assertNotNull($approval);
        $this->assertSame('pending', $approval->status);
        $this->assertSame($this->approver->id, $approval->approver_id);

        // Status da OS muda para waiting_approval
        $this->assertSame(
            WorkOrder::STATUS_WAITING_APPROVAL,
            $this->workOrder->fresh()->status
        );
    }

    public function test_request_rejects_second_pending_approval(): void
    {
        // Primeiro request
        $this->postJson("/api/v1/work-orders/{$this->workOrder->id}/approvals/request", [
            'approver_ids' => [$this->approver->id],
        ])->assertStatus(201);

        // Tentativa duplicada enquanto a primeira ainda está pending
        $anotherApprover = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $anotherApprover->tenants()->attach($this->tenant->id);

        $response = $this->postJson("/api/v1/work-orders/{$this->workOrder->id}/approvals/request", [
            'approver_ids' => [$anotherApprover->id],
        ]);

        $response->assertStatus(422);
    }

    public function test_request_rejects_for_invalid_status(): void
    {
        $this->workOrder->update(['status' => WorkOrder::STATUS_CANCELLED]);

        $response = $this->postJson("/api/v1/work-orders/{$this->workOrder->id}/approvals/request", [
            'approver_ids' => [$this->approver->id],
        ]);

        $response->assertStatus(422);
    }

    public function test_index_returns_404_for_cross_tenant_work_order(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherCustomer = Customer::factory()->create(['tenant_id' => $otherTenant->id]);
        $otherUser = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'current_tenant_id' => $otherTenant->id,
        ]);
        $foreignWo = WorkOrder::factory()->create([
            'tenant_id' => $otherTenant->id,
            'customer_id' => $otherCustomer->id,
            'created_by' => $otherUser->id,
        ]);

        $response = $this->getJson("/api/v1/work-orders/{$foreignWo->id}/approvals");

        $response->assertStatus(404);
    }
}
