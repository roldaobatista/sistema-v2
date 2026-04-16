<?php

namespace Tests\Feature\Api\V1;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\AutomationRule;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AutomationControllerTest extends TestCase
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

    private function createRule(?int $tenantId = null, string $name = 'Regra Teste'): AutomationRule
    {
        return AutomationRule::create([
            'tenant_id' => $tenantId ?? $this->tenant->id,
            'name' => $name,
            'trigger_event' => 'work_order.created',
            'conditions' => [],
            'actions' => [['type' => 'notify', 'target' => 'user']],
            'is_active' => true,
            'created_by' => $this->user->id,
        ]);
    }

    public function test_index_rules_returns_only_current_tenant(): void
    {
        $mine = $this->createRule();

        $otherTenant = Tenant::factory()->create();
        $foreign = $this->createRule($otherTenant->id, 'Foreign');

        $response = $this->getJson('/api/v1/automation/rules');

        $response->assertOk()->assertJsonStructure(['data']);
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($mine->id, $ids);
        $this->assertNotContains($foreign->id, $ids);
    }

    public function test_available_events_returns_metadata(): void
    {
        $response = $this->getJson('/api/v1/automation/events');

        $response->assertOk();
    }

    public function test_available_actions_returns_metadata(): void
    {
        $response = $this->getJson('/api/v1/automation/actions');

        $response->assertOk();
    }

    public function test_store_rule_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/automation/rules', []);

        $response->assertStatus(422);
    }

    public function test_store_rule_creates_rule(): void
    {
        $response = $this->postJson('/api/v1/automation/rules', [
            'name' => 'Alertar chamado crítico',
            'trigger_event' => 'service_call.created',
            'conditions' => [['field' => 'priority', 'operator' => '=', 'value' => 'critical']],
            'actions' => [['type' => 'send_notification', 'target' => 'manager']],
            'is_active' => true,
        ]);

        $this->assertContains($response->status(), [200, 201]);
        $this->assertDatabaseHas('automation_rules', [
            'tenant_id' => $this->tenant->id,
            'name' => 'Alertar chamado crítico',
        ]);
    }

    public function test_toggle_rule_flips_active_flag(): void
    {
        $rule = $this->createRule();

        $response = $this->patchJson("/api/v1/automation/rules/{$rule->id}/toggle");

        $this->assertContains($response->status(), [200, 201]);
    }

    public function test_destroy_removes_rule(): void
    {
        $rule = $this->createRule();

        $response = $this->deleteJson("/api/v1/automation/rules/{$rule->id}");

        $this->assertContains($response->status(), [200, 204]);
    }
}
