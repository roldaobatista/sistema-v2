<?php

namespace Tests\Feature\Api\V1;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Department;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DepartmentControllerTest extends TestCase
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

    private function createDepartment(?int $tenantId = null, string $name = 'Vendas'): Department
    {
        return Department::create([
            'tenant_id' => $tenantId ?? $this->tenant->id,
            'name' => $name,
            'is_active' => true,
        ]);
    }

    public function test_index_returns_only_current_tenant_departments(): void
    {
        $mine = $this->createDepartment();

        $otherTenant = Tenant::factory()->create();
        $foreign = $this->createDepartment($otherTenant->id, 'Foreign');

        $response = $this->getJson('/api/v1/departments');

        $response->assertOk()->assertJsonStructure(['data']);
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($mine->id, $ids);
        $this->assertNotContains($foreign->id, $ids);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/departments', []);

        $response->assertStatus(422);
    }

    public function test_store_creates_department(): void
    {
        $response = $this->postJson('/api/v1/departments', [
            'name' => 'Financeiro',
        ]);

        $this->assertContains($response->status(), [200, 201]);
        $this->assertDatabaseHas('departments', [
            'tenant_id' => $this->tenant->id,
            'name' => 'Financeiro',
        ]);
    }

    public function test_show_returns_department(): void
    {
        $dept = $this->createDepartment();

        $response = $this->getJson("/api/v1/departments/{$dept->id}");

        $response->assertOk();
    }

    public function test_update_modifies_department(): void
    {
        $dept = $this->createDepartment();

        $response = $this->putJson("/api/v1/departments/{$dept->id}", [
            'name' => 'Vendas B2B',
        ]);

        $this->assertContains($response->status(), [200, 201]);
        $this->assertDatabaseHas('departments', [
            'id' => $dept->id,
            'name' => 'Vendas B2B',
        ]);
    }

    public function test_destroy_removes_department(): void
    {
        $dept = $this->createDepartment();

        $response = $this->deleteJson("/api/v1/departments/{$dept->id}");

        $this->assertContains($response->status(), [200, 204]);
    }
}
