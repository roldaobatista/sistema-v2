<?php

namespace Tests\Feature;

use App\Enums\CommissionDisputeStatus;
use App\Enums\CommissionEventStatus;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\CommissionDispute;
use App\Models\CommissionEvent;
use App\Models\CommissionRule;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Laravel\Sanctum\Sanctum;

use function setPermissionsTeamId;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CommissionDisputeAuthorizationTest extends TestCase
{
    private Tenant $tenant;

    private User $requester;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            EnsureTenantScope::class,
        ]);

        $this->tenant = Tenant::factory()->create();
        $this->requester = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        app()->instance('current_tenant_id', $this->tenant->id);
        setPermissionsTeamId($this->tenant->id);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::findOrCreate('commissions.dispute.create', 'web');
        Permission::findOrCreate('commissions.dispute.view', 'web');
        Permission::findOrCreate('commissions.dispute.resolve', 'web');
        Permission::findOrCreate('commissions.dispute.delete', 'web');
    }

    public function test_user_with_create_but_without_resolve_cannot_open_dispute_for_other_users_event(): void
    {
        $this->requester->givePermissionTo([
            'commissions.dispute.create',
            'commissions.dispute.view',
        ]);

        $event = $this->createCommissionEventForAnotherUser();

        Sanctum::actingAs($this->requester, ['*']);

        $this->postJson('/api/v1/commission-disputes', [
            'commission_event_id' => $event->id,
            'reason' => 'Tentativa indevida de contestar evento de terceiro.',
        ])->assertForbidden()
            ->assertJsonPath('message', 'Sem permissao para contestar eventos de outros usuarios.');

        $this->assertDatabaseMissing('commission_disputes', [
            'tenant_id' => $this->tenant->id,
            'commission_event_id' => $event->id,
        ]);
    }

    public function test_user_with_resolve_can_open_dispute_for_other_users_event(): void
    {
        $this->requester->givePermissionTo([
            'commissions.dispute.create',
            'commissions.dispute.resolve',
        ]);

        $event = $this->createCommissionEventForAnotherUser();

        Sanctum::actingAs($this->requester, ['*']);

        $this->postJson('/api/v1/commission-disputes', [
            'commission_event_id' => $event->id,
            'reason' => 'Supervisor abrindo contestacao operacional.',
        ])->assertCreated();

        $this->assertDatabaseHas('commission_disputes', [
            'tenant_id' => $this->tenant->id,
            'commission_event_id' => $event->id,
            'user_id' => $this->requester->id,
        ]);

        $this->assertDatabaseHas('notifications', [
            'tenant_id' => $this->tenant->id,
            'user_id' => $event->user_id,
            'type' => 'commission_dispute',
            'title' => 'Comissao contestada',
        ]);
    }

    public function test_user_with_resolve_can_resolve_dispute_without_view_permission(): void
    {
        $this->requester->givePermissionTo('commissions.dispute.resolve');
        $dispute = $this->createDisputeFor($this->createCommissionEventForAnotherUser());

        Sanctum::actingAs($this->requester, ['*']);

        $this->putJson("/api/v1/commission-disputes/{$dispute->id}/resolve", [
            'status' => CommissionDisputeStatus::REJECTED->value,
            'resolution_notes' => 'Contestacao rejeitada com policy explicita.',
        ])->assertOk();

        $this->assertDatabaseHas('commission_disputes', [
            'id' => $dispute->id,
            'status' => CommissionDisputeStatus::REJECTED->value,
            'resolved_by' => $this->requester->id,
        ]);
    }

    public function test_owner_with_delete_permission_can_cancel_own_open_dispute(): void
    {
        $this->requester->givePermissionTo('commissions.dispute.delete');
        $event = $this->createCommissionEventForUser($this->requester);
        $dispute = $this->createDisputeFor($event, $this->requester);

        Sanctum::actingAs($this->requester, ['*']);

        $this->deleteJson("/api/v1/commission-disputes/{$dispute->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('commission_disputes', [
            'id' => $dispute->id,
        ]);
    }

    public function test_non_owner_with_delete_permission_cannot_cancel_other_users_dispute(): void
    {
        $this->requester->givePermissionTo('commissions.dispute.delete');
        $event = $this->createCommissionEventForAnotherUser();
        $dispute = $this->createDisputeFor($event, $event->user);

        Sanctum::actingAs($this->requester, ['*']);

        $this->deleteJson("/api/v1/commission-disputes/{$dispute->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('commission_disputes', [
            'id' => $dispute->id,
            'status' => CommissionDisputeStatus::OPEN->value,
        ]);
    }

    private function createCommissionEventForAnotherUser(): CommissionEvent
    {
        $beneficiary = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->requester->id,
            'assigned_to' => $beneficiary->id,
            'status' => WorkOrder::STATUS_AWAITING_RETURN,
        ]);

        $rule = CommissionRule::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => null,
            'name' => 'Disputa de terceiro',
            'type' => 'percentage',
            'value' => 10,
            'applies_to' => 'all',
            'active' => true,
            'calculation_type' => CommissionRule::CALC_PERCENT_GROSS,
            'applies_to_role' => CommissionRule::ROLE_TECHNICIAN,
            'applies_when' => CommissionRule::WHEN_OS_COMPLETED,
        ]);

        return CommissionEvent::create([
            'tenant_id' => $this->tenant->id,
            'commission_rule_id' => $rule->id,
            'work_order_id' => $workOrder->id,
            'user_id' => $beneficiary->id,
            'base_amount' => 1000,
            'commission_amount' => 100,
            'status' => CommissionEventStatus::PENDING,
        ]);
    }

    private function createCommissionEventForUser(User $beneficiary): CommissionEvent
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->requester->id,
            'assigned_to' => $beneficiary->id,
            'status' => WorkOrder::STATUS_AWAITING_RETURN,
        ]);

        $rule = CommissionRule::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => null,
            'name' => 'Disputa de usuario especifico',
            'type' => 'percentage',
            'value' => 10,
            'applies_to' => 'all',
            'active' => true,
            'calculation_type' => CommissionRule::CALC_PERCENT_GROSS,
            'applies_to_role' => CommissionRule::ROLE_TECHNICIAN,
            'applies_when' => CommissionRule::WHEN_OS_COMPLETED,
        ]);

        return CommissionEvent::create([
            'tenant_id' => $this->tenant->id,
            'commission_rule_id' => $rule->id,
            'work_order_id' => $workOrder->id,
            'user_id' => $beneficiary->id,
            'base_amount' => 1000,
            'commission_amount' => 100,
            'status' => CommissionEventStatus::PENDING,
        ]);
    }

    private function createDisputeFor(CommissionEvent $event, ?User $owner = null): CommissionDispute
    {
        return CommissionDispute::create([
            'tenant_id' => $this->tenant->id,
            'commission_event_id' => $event->id,
            'user_id' => ($owner ?? $this->requester)->id,
            'reason' => 'Contestacao em aberto para validacao de autorizacao.',
            'status' => CommissionDisputeStatus::OPEN,
        ]);
    }
}
