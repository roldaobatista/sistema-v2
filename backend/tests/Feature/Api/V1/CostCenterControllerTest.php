<?php

namespace Tests\Feature\Api\V1;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\CostCenter;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CostCenterControllerTest extends TestCase
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

    private function createCostCenter(?int $tenantId = null, string $name = 'CC Operacional'): CostCenter
    {
        return CostCenter::create([
            'tenant_id' => $tenantId ?? $this->tenant->id,
            'name' => $name,
            'code' => 'CC-'.uniqid(),
            'is_active' => true,
        ]);
    }

    public function test_index_returns_only_current_tenant(): void
    {
        $mine = $this->createCostCenter();

        $otherTenant = Tenant::factory()->create();
        $foreign = $this->createCostCenter($otherTenant->id, 'Foreign');

        $response = $this->getJson('/api/v1/advanced/cost-centers');

        $response->assertOk()->assertJsonStructure(['data']);
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($mine->id, $ids);
        $this->assertNotContains($foreign->id, $ids);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/advanced/cost-centers', []);

        $response->assertStatus(422);
    }

    public function test_store_creates_cost_center(): void
    {
        $response = $this->postJson('/api/v1/advanced/cost-centers', [
            'name' => 'CC Marketing',
            'code' => 'MKT01',
        ]);

        $this->assertContains($response->status(), [200, 201]);
        $this->assertDatabaseHas('cost_centers', [
            'tenant_id' => $this->tenant->id,
            'name' => 'CC Marketing',
            'code' => 'MKT01',
        ]);
    }

    public function test_update_modifies_cost_center(): void
    {
        $cc = $this->createCostCenter();

        $response = $this->putJson("/api/v1/advanced/cost-centers/{$cc->id}", [
            'name' => 'CC Atualizado',
        ]);

        $this->assertContains($response->status(), [200, 201]);
        $this->assertDatabaseHas('cost_centers', [
            'id' => $cc->id,
            'name' => 'CC Atualizado',
        ]);
    }

    public function test_destroy_removes_cost_center(): void
    {
        $cc = $this->createCostCenter();

        $response = $this->deleteJson("/api/v1/advanced/cost-centers/{$cc->id}");

        $this->assertContains($response->status(), [200, 204]);
    }
}
