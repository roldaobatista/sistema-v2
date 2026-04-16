<?php

namespace Tests\Feature\Api\V1\Os;

use App\Http\Middleware\EnsureTenantScope;
use App\Models\Customer;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Laravel\Sanctum\Sanctum;

use function setPermissionsTeamId;

use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class WorkOrderChatPermissionsTest extends TestCase
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
        Sanctum::actingAs($this->user, ['*']);
    }

    public function test_chat_store_requires_update_permission_even_when_user_can_view(): void
    {
        $this->user->syncPermissions(['os.work_order.view']);

        $this->postJson("/api/v1/work-orders/{$this->workOrder->id}/chats", [
            'message' => 'Mensagem sem permissao de escrita',
            'type' => 'text',
        ])->assertStatus(403);
    }

    public function test_chat_store_allows_user_with_update_permission(): void
    {
        $this->user->syncPermissions(['os.work_order.view', 'os.work_order.update']);

        $this->postJson("/api/v1/work-orders/{$this->workOrder->id}/chats", [
            'message' => 'Mensagem com permissao de escrita',
            'type' => 'text',
        ])->assertStatus(201)
            ->assertJsonPath('data.message', 'Mensagem com permissao de escrita');
    }

    public function test_scoped_technician_cannot_write_chat_for_unlinked_work_order(): void
    {
        $this->user->syncPermissions(['os.work_order.view', 'os.work_order.update']);
        $this->user->assignRole(Role::firstOrCreate([
            'name' => Role::TECNICO,
            'guard_name' => 'web',
            'tenant_id' => $this->tenant->id,
        ]));
        $otherCreator = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);

        $otherWorkOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->workOrder->customer_id,
            'created_by' => $otherCreator->id,
            'assigned_to' => null,
        ]);

        $this->postJson("/api/v1/work-orders/{$otherWorkOrder->id}/chats", [
            'message' => 'Mensagem indevida',
            'type' => 'text',
        ])->assertStatus(403);
    }
}
