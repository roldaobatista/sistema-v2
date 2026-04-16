<?php

namespace Tests\Feature\Api\V1\Os;

use App\Http\Middleware\EnsureTenantScope;
use App\Models\Customer;
use App\Models\Role;
use App\Models\ServiceChecklist;
use App\Models\ServiceChecklistItem;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderSignature;
use App\Models\WorkOrderTimeLog;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

use function setPermissionsTeamId;

use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class WorkOrderAuxiliaryOwnershipTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    private WorkOrder $workOrder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([EnsureTenantScope::class]);

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $this->user->tenants()->attach($this->tenant->id, ['is_default' => true]);

        $customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $this->workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
        ]);

        foreach (['os.work_order.view', 'os.work_order.update'] as $permissionName) {
            Permission::findOrCreate($permissionName, 'web');
        }

        app()->instance('current_tenant_id', $this->tenant->id);
        setPermissionsTeamId($this->tenant->id);
    }

    private function actingAsScopedTechnician(): void
    {
        $this->user->syncPermissions(['os.work_order.view', 'os.work_order.update']);
        $this->user->assignRole(Role::firstOrCreate([
            'name' => Role::TECNICO,
            'guard_name' => 'web',
            'tenant_id' => $this->tenant->id,
        ]));

        Sanctum::actingAs($this->user, ['*']);
    }

    private function makeUnlinkedWorkOrderWithChecklist(): array
    {
        $otherCreator = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);

        $checklist = ServiceChecklist::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Checklist ownership',
            'is_active' => true,
        ]);

        $item = ServiceChecklistItem::create([
            'checklist_id' => $checklist->id,
            'description' => 'Item obrigatório',
            'type' => 'text',
            'is_required' => true,
            'order_index' => 1,
        ]);

        $foreignWorkOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->workOrder->customer_id,
            'created_by' => $otherCreator->id,
            'assigned_to' => null,
            'checklist_id' => $checklist->id,
        ]);

        return [$foreignWorkOrder, $item];
    }

    public function test_scoped_technician_cannot_list_legacy_signatures_for_unlinked_work_order(): void
    {
        $this->actingAsScopedTechnician();
        [$foreignWorkOrder] = $this->makeUnlinkedWorkOrderWithChecklist();

        $this->getJson('/api/v1/work-order-signatures?work_order_id='.$foreignWorkOrder->id)
            ->assertForbidden();
    }

    public function test_scoped_technician_cannot_store_legacy_signature_for_unlinked_work_order(): void
    {
        $this->actingAsScopedTechnician();
        [$foreignWorkOrder] = $this->makeUnlinkedWorkOrderWithChecklist();

        $this->postJson('/api/v1/work-order-signatures', [
            'work_order_id' => $foreignWorkOrder->id,
            'signer_name' => 'Cliente Indevido',
            'signer_type' => 'customer',
            'signature_data' => 'data:image/png;base64,'.base64_encode('assinatura'),
        ])->assertForbidden();
    }

    public function test_legacy_signature_index_uses_canonical_paginated_envelope(): void
    {
        $this->actingAsScopedTechnician();

        WorkOrderSignature::create([
            'tenant_id' => $this->tenant->id,
            'work_order_id' => $this->workOrder->id,
            'signer_name' => 'Cliente',
            'signer_type' => 'customer',
            'signature_data' => 'data:image/png;base64,'.base64_encode('assinatura'),
            'signed_at' => now(),
        ]);

        $this->getJson('/api/v1/work-order-signatures?work_order_id='.$this->workOrder->id.'&per_page=500')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta' => ['current_page', 'per_page', 'total'], 'current_page', 'per_page'])
            ->assertJsonPath('meta.per_page', 100)
            ->assertJsonCount(1, 'data')
            ->assertJsonMissingPath('data.current_page');
    }

    public function test_legacy_time_log_index_uses_canonical_paginated_envelope(): void
    {
        $this->actingAsScopedTechnician();

        WorkOrderTimeLog::create([
            'tenant_id' => $this->tenant->id,
            'work_order_id' => $this->workOrder->id,
            'user_id' => $this->user->id,
            'started_at' => now()->subHour(),
            'ended_at' => now(),
            'duration_seconds' => 3600,
            'activity_type' => 'service',
        ]);

        $this->getJson('/api/v1/work-order-time-logs?work_order_id='.$this->workOrder->id.'&per_page=500')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta' => ['current_page', 'per_page', 'total'], 'current_page', 'per_page'])
            ->assertJsonPath('meta.per_page', 100)
            ->assertJsonCount(1, 'data')
            ->assertJsonMissingPath('data.current_page');
    }

    public function test_scoped_technician_cannot_list_time_logs_for_unlinked_work_order(): void
    {
        $this->actingAsScopedTechnician();
        [$foreignWorkOrder] = $this->makeUnlinkedWorkOrderWithChecklist();

        WorkOrderTimeLog::create([
            'tenant_id' => $this->tenant->id,
            'work_order_id' => $foreignWorkOrder->id,
            'user_id' => $this->user->id,
            'started_at' => now()->subHour(),
            'ended_at' => now(),
            'duration_seconds' => 3600,
            'activity_type' => 'work',
        ]);

        $this->getJson('/api/v1/work-order-time-logs?work_order_id='.$foreignWorkOrder->id)
            ->assertForbidden();
    }

    public function test_scoped_technician_cannot_start_time_log_for_unlinked_work_order(): void
    {
        $this->actingAsScopedTechnician();
        [$foreignWorkOrder] = $this->makeUnlinkedWorkOrderWithChecklist();

        $this->postJson('/api/v1/work-order-time-logs/start', [
            'work_order_id' => $foreignWorkOrder->id,
            'activity_type' => 'work',
        ])->assertForbidden();

        $this->assertDatabaseMissing('work_order_time_logs', [
            'tenant_id' => $this->tenant->id,
            'work_order_id' => $foreignWorkOrder->id,
            'user_id' => $this->user->id,
            'activity_type' => 'work',
            'ended_at' => null,
        ]);
    }

    public function test_scoped_technician_cannot_list_checklist_responses_for_unlinked_work_order(): void
    {
        $this->actingAsScopedTechnician();
        [$foreignWorkOrder] = $this->makeUnlinkedWorkOrderWithChecklist();

        $this->getJson("/api/v1/work-orders/{$foreignWorkOrder->id}/checklist-responses")
            ->assertForbidden();
    }

    public function test_scoped_technician_cannot_store_checklist_responses_for_unlinked_work_order(): void
    {
        $this->actingAsScopedTechnician();
        [$foreignWorkOrder, $item] = $this->makeUnlinkedWorkOrderWithChecklist();

        $this->postJson("/api/v1/work-orders/{$foreignWorkOrder->id}/checklist-responses", [
            'responses' => [
                [
                    'checklist_item_id' => $item->id,
                    'value' => 'Tentativa indevida',
                ],
            ],
        ])->assertForbidden();
    }

    public function test_user_cannot_respond_approval_on_behalf_of_another_approver(): void
    {
        $this->user->syncPermissions(['os.work_order.update']);
        Sanctum::actingAs($this->user, ['*']);

        $approver = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);

        $this->workOrder->update(['status' => WorkOrder::STATUS_WAITING_APPROVAL]);

        DB::table('work_order_approvals')->insert([
            'work_order_id' => $this->workOrder->id,
            'approver_id' => $approver->id,
            'requested_by' => $this->user->id,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->postJson("/api/v1/work-orders/{$this->workOrder->id}/approvals/{$approver->id}/approve", ['approval_channel' => 'whatsapp', 'terms_accepted' => true])
            ->assertForbidden()
            ->assertJsonFragment(['message' => 'Voce so pode responder a sua propria aprovacao.']);

        $this->assertDatabaseHas('work_order_approvals', [
            'work_order_id' => $this->workOrder->id,
            'approver_id' => $approver->id,
            'status' => 'pending',
        ]);
    }
}
