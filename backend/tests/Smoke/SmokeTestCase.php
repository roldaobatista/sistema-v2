<?php

namespace Tests\Smoke;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Smoke Tests — Executados em cada PR (~2 min)
 *
 * Validam que os endpoints mais críticos respondem corretamente.
 * NÃO testam lógica profunda; apenas verificam que o sistema "liga".
 *
 * Para rodar apenas smoke:
 *   php -d memory_limit=512M vendor/bin/phpunit --testsuite=Smoke
 */
abstract class SmokeTestCase extends TestCase
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

        $this->seed(PermissionsSeeder::class);

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        app()->instance('current_tenant_id', $this->tenant->id);
        setPermissionsTeamId($this->tenant->id);
        // Bulk assign all permissions (muito mais rápido que givePermissionTo individual)
        $pivotTable = config('permission.table_names.model_has_permissions', 'model_has_permissions');
        $permIds = Permission::pluck('id');
        $rows = $permIds->map(fn ($id) => [
            'permission_id' => $id,
            'model_type' => get_class($this->user),
            'model_id' => $this->user->id,
        ])->toArray();
        DB::table($pivotTable)->insertOrIgnore($rows);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Sanctum::actingAs($this->user, ['*']);
    }
}
