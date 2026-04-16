<?php

namespace Tests\Feature\Api\V1;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\SlaPolicy;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SlaPolicyControllerTest extends TestCase
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

    private function createPolicy(?int $tenantId = null, string $priority = 'high'): SlaPolicy
    {
        return SlaPolicy::create([
            'tenant_id' => $tenantId ?? $this->tenant->id,
            'name' => 'SLA '.$priority,
            'response_time_minutes' => 60,
            'resolution_time_minutes' => 240,
            'priority' => $priority,
            'is_active' => true,
        ]);
    }

    public function test_index_returns_only_current_tenant(): void
    {
        $mine = $this->createPolicy();

        $otherTenant = Tenant::factory()->create();
        $foreign = $this->createPolicy($otherTenant->id, 'low');

        $response = $this->getJson('/api/v1/sla-policies');

        $response->assertOk();
        $rows = $response->json('data.data') ?? $response->json('data');
        $ids = collect($rows)->pluck('id')->all();
        $this->assertContains($mine->id, $ids);
        $this->assertNotContains($foreign->id, $ids);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/sla-policies', []);

        $response->assertStatus(422);
    }

    public function test_store_creates_policy(): void
    {
        $response = $this->postJson('/api/v1/sla-policies', [
            'name' => 'SLA Crítico',
            'response_time_minutes' => 15,
            'resolution_time_minutes' => 60,
            'priority' => 'critical',
            'is_active' => true,
        ]);

        $this->assertContains($response->status(), [200, 201]);
        $this->assertDatabaseHas('sla_policies', [
            'tenant_id' => $this->tenant->id,
            'name' => 'SLA Crítico',
            'priority' => 'critical',
        ]);
    }

    public function test_show_returns_policy(): void
    {
        $policy = $this->createPolicy();

        $response = $this->getJson("/api/v1/sla-policies/{$policy->id}");

        $response->assertOk();
    }

    public function test_update_modifies_policy(): void
    {
        $policy = $this->createPolicy();

        $response = $this->putJson("/api/v1/sla-policies/{$policy->id}", [
            'name' => 'SLA Atualizado',
            'response_time_minutes' => 30,
            'resolution_time_minutes' => 120,
            'priority' => 'high',
            'is_active' => true,
        ]);

        $this->assertContains($response->status(), [200, 201]);
    }
}
