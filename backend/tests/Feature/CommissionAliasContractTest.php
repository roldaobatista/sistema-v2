<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureTenantScope;
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

class CommissionAliasContractTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            EnsureTenantScope::class,
        ]);

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        app()->instance('current_tenant_id', $this->tenant->id);
        setPermissionsTeamId($this->tenant->id);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ([
            'commissions.rule.view',
            'commissions.rule.create',
            'commissions.event.view',
            'commissions.settlement.view',
            'commissions.recurring.create',
        ] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $this->user->givePermissionTo([
            'commissions.rule.view',
            'commissions.rule.create',
            'commissions.event.view',
            'commissions.settlement.view',
            'commissions.recurring.create',
        ]);

        Sanctum::actingAs($this->user, ['*']);
    }

    public function test_class_a_aliases_match_canonical_response_shapes_for_listing_endpoints(): void
    {
        $canonicalRules = $this->getJson('/api/v1/commission-rules');
        $aliasRules = $this->getJson('/api/v1/commissions/rules');

        $canonicalRules->assertOk();
        $aliasRules->assertOk();
        $this->assertSame($canonicalRules->json(), $aliasRules->json());

        $canonicalEvents = $this->getJson('/api/v1/commission-events');
        $aliasEvents = $this->getJson('/api/v1/commissions/events');

        $canonicalEvents->assertOk();
        $aliasEvents->assertOk();
        $this->assertSame($canonicalEvents->json(), $aliasEvents->json());

        $canonicalSettlements = $this->getJson('/api/v1/commission-settlements');
        $aliasSettlements = $this->getJson('/api/v1/commissions/settlements');

        $canonicalSettlements->assertOk();
        $aliasSettlements->assertOk();
        $this->assertSame($canonicalSettlements->json(), $aliasSettlements->json());
    }

    public function test_simulate_and_recurring_process_aliases_match_canonical_contracts(): void
    {
        $workOrder = $this->createWorkOrder();

        $canonicalSimulation = $this->postJson('/api/v1/commission-simulate', [
            'work_order_id' => $workOrder->id,
        ]);

        $aliasSimulation = $this->postJson('/api/v1/commissions/simulate', [
            'work_order_id' => $workOrder->id,
        ]);

        $canonicalSimulation->assertOk()->assertJsonStructure(['data']);
        $aliasSimulation->assertOk()->assertJsonStructure(['data']);
        $this->assertSame($canonicalSimulation->json(), $aliasSimulation->json());

        $canonicalProcess = $this->postJson('/api/v1/recurring-commissions/process-monthly');
        $aliasProcess = $this->postJson('/api/v1/recurring-commissions/process');

        $canonicalProcess->assertOk()->assertJsonStructure(['data' => ['generated']]);
        $aliasProcess->assertOk()->assertJsonStructure(['data' => ['generated']]);
        $this->assertSame($canonicalProcess->json(), $aliasProcess->json());
    }

    private function createWorkOrder(): WorkOrder
    {
        $customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        CommissionRule::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'name' => 'Alias contract rule',
            'type' => 'percentage',
            'value' => 10,
            'applies_to' => 'all',
            'active' => true,
            'calculation_type' => CommissionRule::CALC_PERCENT_GROSS,
            'applies_to_role' => CommissionRule::ROLE_TECHNICIAN,
            'applies_when' => CommissionRule::WHEN_OS_COMPLETED,
        ]);

        return WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'assigned_to' => $this->user->id,
            'status' => WorkOrder::STATUS_AWAITING_RETURN,
            'total' => 1000,
        ]);
    }
}
