<?php

namespace Tests\Performance;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Performance Tests -- query counting, timing, memory, concurrency.
 *
 * Run only performance tests:
 *   vendor/bin/pest tests/Performance
 */
abstract class PerformanceTestCase extends TestCase
{
    protected Tenant $tenant;

    protected User $user;

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
        $permissions = [
            'finance.receivable.view',
            'finance.payable.view',
        ];
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        $this->user->givePermissionTo($permissions);
        Sanctum::actingAs($this->user, ['*']);
    }
}
