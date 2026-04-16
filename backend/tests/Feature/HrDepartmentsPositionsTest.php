<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class HrDepartmentsPositionsTest extends TestCase
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
            'is_active' => true,
        ]);

        app()->instance('current_tenant_id', $this->tenant->id);
        setPermissionsTeamId($this->tenant->id);

        Permission::firstOrCreate(['name' => 'hr.organization.view', 'guard_name' => 'web']);
        $this->user->givePermissionTo('hr.organization.view');

        Sanctum::actingAs($this->user, ['*']);
    }

    public function test_hr_departments_list_returns_ok(): void
    {
        $response = $this->getJson('/api/v1/hr/departments');

        $response->assertOk();
    }

    public function test_hr_positions_list_returns_ok(): void
    {
        $response = $this->getJson('/api/v1/hr/positions');

        $response->assertOk();
    }
}
