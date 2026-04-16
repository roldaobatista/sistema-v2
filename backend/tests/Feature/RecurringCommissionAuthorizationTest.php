<?php

namespace Tests\Feature;

use App\Enums\CommissionEventStatus;
use App\Enums\RecurringCommissionStatus;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\CommissionRule;
use App\Models\Customer;
use App\Models\RecurringCommission;
use App\Models\RecurringContract;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Laravel\Sanctum\Sanctum;

use function setPermissionsTeamId;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RecurringCommissionAuthorizationTest extends TestCase
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

        foreach ([
            'commissions.recurring.view',
            'commissions.recurring.create',
            'commissions.recurring.update',
            'commissions.recurring.delete',
        ] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
    }

    public function test_user_with_recurring_view_can_list_without_create_permission(): void
    {
        $this->requester->givePermissionTo('commissions.recurring.view');
        $this->createRecurringCommission();

        Sanctum::actingAs($this->requester, ['*']);

        $this->getJson('/api/v1/recurring-commissions')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_user_with_only_recurring_create_cannot_list_recurring_commissions(): void
    {
        $this->requester->givePermissionTo('commissions.recurring.create');
        $this->createRecurringCommission();

        Sanctum::actingAs($this->requester, ['*']);

        $this->getJson('/api/v1/recurring-commissions')
            ->assertForbidden();
    }

    public function test_user_with_recurring_create_can_store_without_update_permission(): void
    {
        [$contract, $rule] = $this->createRecurringDependencies($this->requester);
        $this->requester->givePermissionTo('commissions.recurring.create');

        Sanctum::actingAs($this->requester, ['*']);

        $this->postJson('/api/v1/recurring-commissions', [
            'user_id' => $this->requester->id,
            'recurring_contract_id' => $contract->id,
            'commission_rule_id' => $rule->id,
        ])->assertCreated();
    }

    public function test_user_with_recurring_update_can_change_status_without_create_permission(): void
    {
        $recurring = $this->createRecurringCommission();
        $this->requester->givePermissionTo('commissions.recurring.update');

        Sanctum::actingAs($this->requester, ['*']);

        $this->putJson("/api/v1/recurring-commissions/{$recurring->id}/status", [
            'status' => RecurringCommissionStatus::PAUSED->value,
        ])->assertOk();

        $this->assertDatabaseHas('recurring_commissions', [
            'id' => $recurring->id,
            'status' => RecurringCommissionStatus::PAUSED->value,
        ]);
    }

    public function test_user_with_only_recurring_create_cannot_change_status(): void
    {
        $recurring = $this->createRecurringCommission();
        $this->requester->givePermissionTo('commissions.recurring.create');

        Sanctum::actingAs($this->requester, ['*']);

        $this->putJson("/api/v1/recurring-commissions/{$recurring->id}/status", [
            'status' => RecurringCommissionStatus::PAUSED->value,
        ])->assertForbidden();
    }

    public function test_user_with_recurring_create_can_process_monthly_without_update_permission(): void
    {
        $recurring = $this->createRecurringCommission();
        $this->seedCurrentMonthWorkOrder($recurring->recurringContract, $recurring->user);
        $this->requester->givePermissionTo('commissions.recurring.create');

        Sanctum::actingAs($this->requester, ['*']);

        $this->postJson('/api/v1/recurring-commissions/process-monthly')
            ->assertOk()
            ->assertJsonPath('data.generated', 1);

        $this->assertDatabaseHas('commission_events', [
            'tenant_id' => $this->tenant->id,
            'user_id' => $recurring->user_id,
            'status' => CommissionEventStatus::PENDING->value,
        ]);
    }

    public function test_user_with_recurring_delete_can_destroy_without_update_permission(): void
    {
        $recurring = $this->createRecurringCommission();
        $this->requester->givePermissionTo('commissions.recurring.delete');

        Sanctum::actingAs($this->requester, ['*']);

        $this->deleteJson("/api/v1/recurring-commissions/{$recurring->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('recurring_commissions', [
            'id' => $recurring->id,
        ]);
    }

    private function createRecurringCommission(?User $beneficiary = null): RecurringCommission
    {
        [$contract, $rule] = $this->createRecurringDependencies($beneficiary ?? $this->requester);

        return RecurringCommission::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => ($beneficiary ?? $this->requester)->id,
            'recurring_contract_id' => $contract->id,
            'commission_rule_id' => $rule->id,
            'status' => RecurringCommissionStatus::ACTIVE,
        ]);
    }

    private function createRecurringDependencies(User $beneficiary): array
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $contract = RecurringContract::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->requester->id,
            'assigned_to' => $beneficiary->id,
            'monthly_value' => 1200,
            'is_active' => true,
        ]);

        $rule = CommissionRule::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $beneficiary->id,
            'name' => 'Recurring authorization rule',
            'type' => 'percentage',
            'value' => 10,
            'applies_to' => 'all',
            'active' => true,
            'calculation_type' => CommissionRule::CALC_PERCENT_GROSS,
            'applies_to_role' => CommissionRule::ROLE_TECHNICIAN,
            'applies_when' => CommissionRule::WHEN_OS_COMPLETED,
        ]);

        return [$contract, $rule];
    }

    private function seedCurrentMonthWorkOrder(RecurringContract $contract, User $beneficiary): WorkOrder
    {
        return WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $contract->customer_id,
            'recurring_contract_id' => $contract->id,
            'created_by' => $this->requester->id,
            'assigned_to' => $beneficiary->id,
            'received_at' => now()->startOfMonth(),
            'created_at' => now()->startOfMonth()->addDay(),
            'status' => WorkOrder::STATUS_AWAITING_RETURN,
            'total' => 1200,
        ]);
    }
}
