<?php

namespace Tests\Feature\Api\V1;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Branch;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BranchControllerTest extends TestCase
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

    private function createBranch(?int $tenantId = null, string $name = 'Filial Centro'): Branch
    {
        return Branch::create([
            'tenant_id' => $tenantId ?? $this->tenant->id,
            'name' => $name,
            'code' => 'BR'.uniqid(),
            'address_city' => 'São Paulo',
            'address_state' => 'SP',
        ]);
    }

    public function test_index_returns_only_current_tenant_branches(): void
    {
        $mine = $this->createBranch();

        $otherTenant = Tenant::factory()->create();
        $foreign = $this->createBranch($otherTenant->id, 'Foreign');

        $response = $this->getJson('/api/v1/branches');

        $response->assertOk()->assertJsonStructure(['data']);
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($mine->id, $ids);
        $this->assertNotContains($foreign->id, $ids);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/branches', []);

        $response->assertStatus(422);
    }

    public function test_store_creates_branch_with_tenant(): void
    {
        $response = $this->postJson('/api/v1/branches', [
            'name' => 'Filial Norte',
            'code' => 'BRN01',
            'address_city' => 'Manaus',
            'address_state' => 'AM',
        ]);

        $this->assertContains($response->status(), [200, 201]);
        $this->assertDatabaseHas('branches', [
            'tenant_id' => $this->tenant->id,
            'name' => 'Filial Norte',
            'code' => 'BRN01',
        ]);
    }

    public function test_show_returns_branch(): void
    {
        $branch = $this->createBranch();

        $response = $this->getJson("/api/v1/branches/{$branch->id}");

        $response->assertOk();
    }

    public function test_show_rejects_cross_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $foreign = $this->createBranch($otherTenant->id, 'Foreign');

        $response = $this->getJson("/api/v1/branches/{$foreign->id}");

        $this->assertContains($response->status(), [403, 404]);
    }

    public function test_update_modifies_branch(): void
    {
        $branch = $this->createBranch();

        $response = $this->putJson("/api/v1/branches/{$branch->id}", [
            'name' => 'Filial Atualizada',
        ]);

        $this->assertContains($response->status(), [200, 201]);
        $this->assertDatabaseHas('branches', [
            'id' => $branch->id,
            'name' => 'Filial Atualizada',
        ]);
    }
}
