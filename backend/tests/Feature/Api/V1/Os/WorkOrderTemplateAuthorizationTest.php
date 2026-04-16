<?php

namespace Tests\Feature\Api\V1\Os;

use App\Http\Middleware\EnsureTenantScope;
use App\Models\Tenant;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

use function setPermissionsTeamId;

use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class WorkOrderTemplateAuthorizationTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([EnsureTenantScope::class]);

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $this->user->tenants()->attach($this->tenant->id, ['is_default' => true]);

        foreach ([
            'os.work_order.view',
            'os.work_order.create',
            'os.work_order.update',
            'os.work_order.delete',
        ] as $permissionName) {
            Permission::findOrCreate($permissionName, 'web');
        }

        app()->instance('current_tenant_id', $this->tenant->id);
        setPermissionsTeamId($this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    // ── Deny without permission ──

    public function test_index_requires_view_permission(): void
    {
        $this->user->syncPermissions([]);

        $response = $this->getJson('/api/v1/work-order-templates');

        $response->assertStatus(403);
    }

    public function test_store_requires_create_permission(): void
    {
        $this->user->syncPermissions([]);

        $response = $this->postJson('/api/v1/work-order-templates', [
            'name' => 'Template Teste',
        ]);

        $response->assertStatus(403);
    }

    public function test_update_requires_update_permission(): void
    {
        $this->user->syncPermissions([]);

        $response = $this->putJson('/api/v1/work-order-templates/1', [
            'name' => 'Template Atualizado',
        ]);

        $response->assertStatus(403);
    }

    public function test_delete_requires_delete_permission(): void
    {
        $this->user->syncPermissions([]);

        $response = $this->deleteJson('/api/v1/work-order-templates/1');

        $response->assertStatus(403);
    }

    // ── Allow with correct permission ──

    public function test_index_succeeds_with_permission(): void
    {
        $this->user->syncPermissions(['os.work_order.view']);

        $response = $this->getJson('/api/v1/work-order-templates');

        $response->assertStatus(200);
    }
}
