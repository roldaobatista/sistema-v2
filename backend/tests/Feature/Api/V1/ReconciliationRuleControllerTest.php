<?php

namespace Tests\Feature\Api\V1;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\ReconciliationRule;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReconciliationRuleControllerTest extends TestCase
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

    private function createRule(int $tenantId, array $overrides = []): ReconciliationRule
    {
        return ReconciliationRule::create(array_merge([
            'tenant_id' => $tenantId,
            'name' => 'Rule '.uniqid(),
            'match_field' => 'description',
            'match_operator' => 'contains',
            'match_value' => 'NF-e',
            'action' => 'match_receivable',
            'priority' => 10,
            'is_active' => true,
        ], $overrides));
    }

    public function test_index_returns_only_current_tenant_rules(): void
    {
        $this->createRule($this->tenant->id);
        $this->createRule($this->tenant->id);

        $otherTenant = Tenant::factory()->create();
        $this->createRule($otherTenant->id);

        $response = $this->getJson('/api/v1/reconciliation-rules');

        $response->assertOk();

        $data = $response->json('data') ?? $response->json();
        $this->assertIsArray($data);

        foreach ($data as $rule) {
            if (isset($rule['tenant_id'])) {
                $this->assertEquals(
                    $this->tenant->id,
                    $rule['tenant_id'],
                    'ReconciliationRule cross-tenant vazou'
                );
            }
        }
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/reconciliation-rules', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'match_field', 'match_operator', 'action']);
    }

    public function test_store_rejects_invalid_enums(): void
    {
        $response = $this->postJson('/api/v1/reconciliation-rules', [
            'name' => 'Rule Invalida',
            'match_field' => 'random_field', // fora do enum
            'match_operator' => 'fuzzy', // fora do enum
            'action' => 'do_something', // fora do enum
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['match_field', 'match_operator', 'action']);
    }

    public function test_store_creates_rule_with_valid_payload(): void
    {
        $response = $this->postJson('/api/v1/reconciliation-rules', [
            'name' => 'Regra Match Descricao',
            'match_field' => 'description',
            'match_operator' => 'contains',
            'match_value' => 'FORNECEDOR X',
            'action' => 'match_payable',
            'priority' => 5,
            'is_active' => true,
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('reconciliation_rules', [
            'tenant_id' => $this->tenant->id,
            'name' => 'Regra Match Descricao',
            'action' => 'match_payable',
        ]);
    }

    public function test_show_returns_404_for_cross_tenant_rule(): void
    {
        $otherTenant = Tenant::factory()->create();
        $foreign = $this->createRule($otherTenant->id);

        $response = $this->getJson("/api/v1/reconciliation-rules/{$foreign->id}");

        // Controller faz ReconciliationRule::where('tenant_id', $tenantId)->findOrFail
        $response->assertStatus(404);
    }

    public function test_toggle_active_flips_rule_state(): void
    {
        $rule = $this->createRule($this->tenant->id, ['is_active' => true]);

        $response = $this->postJson("/api/v1/reconciliation-rules/{$rule->id}/toggle");

        $response->assertOk();

        $this->assertFalse(
            (bool) $rule->fresh()->is_active,
            'toggleActive deve inverter estado de is_active'
        );
    }
}
