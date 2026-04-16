<?php

namespace Tests\Feature\Api\V1\Os;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrderTemplate;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WorkOrderTemplateControllerTest extends TestCase
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

    private function createTemplate(?int $tenantId = null, array $overrides = []): WorkOrderTemplate
    {
        return WorkOrderTemplate::create(array_merge([
            'tenant_id' => $tenantId ?? $this->tenant->id,
            'name' => 'Template Padrão',
            'description' => null,
            'default_items' => null,
            'created_by' => $this->user->id,
        ], $overrides));
    }

    public function test_index_returns_only_current_tenant_templates(): void
    {
        $this->createTemplate();

        $otherTenant = Tenant::factory()->create();
        $foreignUser = User::factory()->create(['tenant_id' => $otherTenant->id]);
        $this->createTemplate($otherTenant->id, [
            'name' => 'Template estranho',
            'created_by' => $foreignUser->id,
        ]);

        $response = $this->getJson('/api/v1/work-order-templates');

        $response->assertOk()->assertJsonStructure(['data']);

        foreach ($response->json('data') as $row) {
            $this->assertEquals($this->tenant->id, $row['tenant_id']);
        }
    }

    public function test_store_validates_required_name(): void
    {
        $response = $this->postJson('/api/v1/work-order-templates', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_store_creates_template_with_tenant_and_created_by(): void
    {
        $response = $this->postJson('/api/v1/work-order-templates', [
            'name' => 'Calibração Padrão',
            'description' => 'Template para calibrações de rotina',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('work_order_templates', [
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'name' => 'Calibração Padrão',
        ]);
    }

    public function test_show_returns_404_for_cross_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $foreignUser = User::factory()->create(['tenant_id' => $otherTenant->id]);
        $foreign = $this->createTemplate($otherTenant->id, [
            'name' => 'Foreign',
            'created_by' => $foreignUser->id,
        ]);

        $response = $this->getJson("/api/v1/work-order-templates/{$foreign->id}");

        $response->assertStatus(404);
    }

    public function test_destroy_returns_404_for_cross_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $foreignUser = User::factory()->create(['tenant_id' => $otherTenant->id]);
        $foreign = $this->createTemplate($otherTenant->id, [
            'name' => 'Foreign',
            'created_by' => $foreignUser->id,
        ]);

        $response = $this->deleteJson("/api/v1/work-order-templates/{$foreign->id}");

        $response->assertStatus(404);
        $this->assertDatabaseHas('work_order_templates', ['id' => $foreign->id]);
    }
}
