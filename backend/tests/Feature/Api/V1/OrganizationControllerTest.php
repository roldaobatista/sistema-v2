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

class OrganizationControllerTest extends TestCase
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

    public function test_index_departments_returns_list(): void
    {
        Department::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'RH',
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/hr/departments');

        $response->assertOk();
    }

    public function test_index_positions_returns_list(): void
    {
        $response = $this->getJson('/api/v1/hr/positions');

        $response->assertOk();
    }

    public function test_org_chart_returns_structure(): void
    {
        $response = $this->getJson('/api/v1/hr/org-chart');

        $response->assertOk();
    }

    public function test_store_department_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/hr/departments', []);

        $response->assertStatus(422);
    }

    public function test_store_department_creates_department(): void
    {
        $response = $this->postJson('/api/v1/hr/departments', [
            'name' => 'TI',
        ]);

        $this->assertContains($response->status(), [200, 201]);
        $this->assertDatabaseHas('departments', [
            'tenant_id' => $this->tenant->id,
            'name' => 'TI',
        ]);
    }

    public function test_store_position_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/hr/positions', []);

        $response->assertStatus(422);
    }
}
