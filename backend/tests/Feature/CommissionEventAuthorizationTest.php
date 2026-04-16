<?php

namespace Tests\Feature;

use App\Enums\CommissionEventStatus;
use App\Http\Middleware\EnsureTenantScope;
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

class CommissionEventAuthorizationTest extends TestCase
{
    private Tenant $tenant;

    private User $requester;

    private CommissionEvent $event;

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

        foreach ([
            'commissions.event.view',
            'commissions.event.update',
            'commissions.rule.view',
            'commissions.rule.update',
        ] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $this->event = $this->createCommissionEvent();
    }

    public function test_user_with_event_update_can_update_event_status_without_rule_update(): void
    {
        $this->requester->givePermissionTo('commissions.event.update');

        Sanctum::actingAs($this->requester, ['*']);

        $this->putJson("/api/v1/commission-events/{$this->event->id}/status", [
            'status' => CommissionEventStatus::APPROVED->value,
        ])->assertOk();

        $this->assertDatabaseHas('commission_events', [
            'id' => $this->event->id,
            'status' => CommissionEventStatus::APPROVED->value,
        ]);
    }

    public function test_user_with_only_rule_update_cannot_update_event_status(): void
    {
        $this->requester->givePermissionTo('commissions.rule.update');

        Sanctum::actingAs($this->requester, ['*']);

        $this->putJson("/api/v1/commission-events/{$this->event->id}/status", [
            'status' => CommissionEventStatus::APPROVED->value,
        ])->assertForbidden();
    }

    public function test_user_with_event_update_can_batch_update_events(): void
    {
        $secondEvent = $this->createCommissionEvent();
        $this->requester->givePermissionTo('commissions.event.update');

        Sanctum::actingAs($this->requester, ['*']);

        $this->postJson('/api/v1/commission-events/batch-status', [
            'ids' => [$this->event->id, $secondEvent->id],
            'status' => CommissionEventStatus::APPROVED->value,
        ])->assertOk()
            ->assertJsonPath('data.updated', 2)
            ->assertJsonPath('data.skipped', 0);
    }

    public function test_user_with_event_update_can_split_event_without_rule_update(): void
    {
        $beneficiary = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        $this->requester->givePermissionTo('commissions.event.update');

        Sanctum::actingAs($this->requester, ['*']);

        $this->postJson("/api/v1/commission-events/{$this->event->id}/splits", [
            'splits' => [
                ['user_id' => $this->requester->id, 'percentage' => '50.00'],
                ['user_id' => $beneficiary->id, 'percentage' => '50.00'],
            ],
        ])->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_user_with_event_view_can_export_events_without_rule_view(): void
    {
        $this->requester->givePermissionTo('commissions.event.view');

        Sanctum::actingAs($this->requester, ['*']);

        $this->get('/api/v1/commission-events/export')->assertOk();
    }

    public function test_user_with_only_rule_view_cannot_export_events(): void
    {
        $this->requester->givePermissionTo('commissions.rule.view');

        Sanctum::actingAs($this->requester, ['*']);

        $this->get('/api/v1/commission-events/export')->assertForbidden();
    }

    private function createCommissionEvent(): CommissionEvent
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->requester->id,
            'assigned_to' => $this->requester->id,
            'status' => WorkOrder::STATUS_AWAITING_RETURN,
            'total' => 1000,
        ]);

        $rule = CommissionRule::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->requester->id,
            'name' => 'Evento com autorizacao',
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
            'user_id' => $this->requester->id,
            'base_amount' => 1000,
            'commission_amount' => 100,
            'status' => CommissionEventStatus::PENDING,
        ]);
    }
}
