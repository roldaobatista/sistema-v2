<?php

namespace Tests\Feature\Api\V1\Financial;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\CommissionRule;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CommissionRuleControllerTest extends TestCase
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

    private function createRule(?int $tenantId = null, string $name = 'Regra Padrão'): CommissionRule
    {
        return CommissionRule::create([
            'tenant_id' => $tenantId ?? $this->tenant->id,
            'name' => $name,
            'type' => CommissionRule::TYPE_PERCENTAGE,
            'value' => 5,
            'calculation_type' => array_key_first(CommissionRule::CALCULATION_TYPES),
            'applies_to' => CommissionRule::APPLIES_ALL,
            'applies_to_role' => CommissionRule::ROLE_TECHNICIAN,
            'applies_when' => CommissionRule::WHEN_OS_COMPLETED,
            'active' => true,
        ]);
    }

    public function test_rules_returns_only_current_tenant(): void
    {
        $this->createRule();

        $otherTenant = Tenant::factory()->create();
        $this->createRule($otherTenant->id, 'Regra de outro tenant');

        $response = $this->getJson('/api/v1/commission-rules');

        $response->assertOk()->assertJsonStructure(['data']);

        foreach ($response->json('data') as $row) {
            $this->assertEquals($this->tenant->id, $row['tenant_id']);
        }
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/commission-rules', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'value', 'calculation_type']);
    }

    public function test_store_rejects_percentage_above_100(): void
    {
        $response = $this->postJson('/api/v1/commission-rules', [
            'name' => 'Invalid Percentage',
            'type' => CommissionRule::TYPE_PERCENTAGE,
            'value' => 150,
            'calculation_type' => array_key_first(CommissionRule::CALCULATION_TYPES),
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['value']);
    }

    public function test_store_creates_rule_with_tenant(): void
    {
        $response = $this->postJson('/api/v1/commission-rules', [
            'name' => '5% sobre serviços',
            'type' => CommissionRule::TYPE_PERCENTAGE,
            'value' => 5,
            'calculation_type' => array_key_first(CommissionRule::CALCULATION_TYPES),
            'applies_to' => CommissionRule::APPLIES_SERVICES,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('commission_rules', [
            'tenant_id' => $this->tenant->id,
            'name' => '5% sobre serviços',
            'value' => 5,
        ]);
    }

    public function test_show_returns_404_for_cross_tenant_rule(): void
    {
        $otherTenant = Tenant::factory()->create();
        $foreign = $this->createRule($otherTenant->id, 'Foreign');

        $response = $this->getJson("/api/v1/commission-rules/{$foreign->id}");

        $response->assertStatus(404);
    }
}
