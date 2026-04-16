<?php

namespace Tests\Feature\Api\V1\Financial;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\CommissionRule;
use App\Models\Customer;
use App\Models\RecurringCommission;
use App\Models\RecurringContract;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RecurringCommissionControllerTest extends TestCase
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

    private function createRule(?int $tenantId = null): CommissionRule
    {
        return CommissionRule::create([
            'tenant_id' => $tenantId ?? $this->tenant->id,
            'name' => 'Regra '.uniqid(),
            'type' => 'percentage',
            'value' => 5.0,
            'applies_to' => 'revenue',
            'calculation_type' => 'percentage',
            'active' => true,
        ]);
    }

    private function createContract(?int $tenantId = null): RecurringContract
    {
        $tid = $tenantId ?? $this->tenant->id;
        $customer = Customer::factory()->create(['tenant_id' => $tid]);

        return RecurringContract::create([
            'tenant_id' => $tid,
            'customer_id' => $customer->id,
            'name' => 'Contrato '.uniqid(),
            'frequency' => 'monthly',
            'billing_type' => 'fixed',
            'monthly_value' => 1000,
            'start_date' => now()->toDateString(),
            'next_run_date' => now()->addMonth()->toDateString(),
            'priority' => 'normal',
            'is_active' => true,
            'created_by' => $this->user->id,
        ]);
    }

    public function test_index_returns_only_current_tenant(): void
    {
        $rule = $this->createRule();
        $contract = $this->createContract();
        $mine = RecurringCommission::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'recurring_contract_id' => $contract->id,
            'commission_rule_id' => $rule->id,
            'status' => 'active',
        ]);

        $otherTenant = Tenant::factory()->create();
        $otherRule = $this->createRule($otherTenant->id);
        $otherContract = $this->createContract($otherTenant->id);
        $otherUser = User::factory()->create(['tenant_id' => $otherTenant->id]);
        $foreign = RecurringCommission::create([
            'tenant_id' => $otherTenant->id,
            'user_id' => $otherUser->id,
            'recurring_contract_id' => $otherContract->id,
            'commission_rule_id' => $otherRule->id,
            'status' => 'active',
        ]);

        $response = $this->getJson('/api/v1/recurring-commissions');

        $response->assertOk()->assertJsonStructure(['data']);
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($mine->id, $ids);
        $this->assertNotContains($foreign->id, $ids);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/recurring-commissions', []);

        $response->assertStatus(422);
    }

    public function test_store_creates_recurring_commission(): void
    {
        $rule = $this->createRule();
        $contract = $this->createContract();

        $response = $this->postJson('/api/v1/recurring-commissions', [
            'user_id' => $this->user->id,
            'recurring_contract_id' => $contract->id,
            'commission_rule_id' => $rule->id,
        ]);

        $this->assertContains($response->status(), [200, 201]);
        $this->assertDatabaseHas('recurring_commissions', [
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'recurring_contract_id' => $contract->id,
            'commission_rule_id' => $rule->id,
        ]);
    }

    public function test_store_rejects_contract_from_other_tenant(): void
    {
        $rule = $this->createRule();
        $otherTenant = Tenant::factory()->create();
        $foreignContract = $this->createContract($otherTenant->id);

        $response = $this->postJson('/api/v1/recurring-commissions', [
            'user_id' => $this->user->id,
            'recurring_contract_id' => $foreignContract->id,
            'commission_rule_id' => $rule->id,
        ]);

        $response->assertStatus(422);
    }
}
