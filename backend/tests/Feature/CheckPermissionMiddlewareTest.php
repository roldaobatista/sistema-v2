<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\CheckReportExportPermission;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CheckPermissionMiddlewareTest extends TestCase
{
    private const MISSING_PERMISSION = 'iam.testing.missing_permission';

    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withMiddleware([
            CheckPermission::class,
            CheckReportExportPermission::class,
        ]);
        Gate::before(fn () => true);

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);
        $this->user->tenants()->attach($this->tenant->id, ['is_default' => true]);

        Sanctum::actingAs($this->user, ['*']);
        app()->instance('current_tenant_id', $this->tenant->id);
        setPermissionsTeamId($this->tenant->id);

        Route::middleware(['auth:sanctum', 'check.tenant', 'check.permission:'.self::MISSING_PERMISSION])
            ->get('/api/v1/testing/missing-permission', fn () => response()->json(['ok' => true]));
    }

    public function test_returns_clear_message_when_permission_is_not_configured(): void
    {
        $response = $this->getJson('/api/v1/testing/missing-permission');

        $response->assertForbidden()
            ->assertJsonPath('message', 'Acesso negado. Permissao nao configurada: '.self::MISSING_PERMISSION)
            ->assertJsonPath('missing_permissions.0', self::MISSING_PERMISSION);
    }

    public function test_returns_required_permission_message_when_permission_exists_but_user_does_not_have_it(): void
    {
        Permission::firstOrCreate(['name' => self::MISSING_PERMISSION, 'guard_name' => 'web']);

        $response = $this->getJson('/api/v1/testing/missing-permission');

        $response->assertForbidden()
            ->assertJsonPath('message', 'Acesso negado. Permissao necessaria: '.self::MISSING_PERMISSION);
    }
}
