<?php

namespace Tests\Feature\Api\V1;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\ServiceCallTemplate;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ServiceCallTemplateControllerTest extends TestCase
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

    private function createTemplate(?int $tenantId = null, string $name = 'Template Padrão'): ServiceCallTemplate
    {
        return ServiceCallTemplate::create([
            'tenant_id' => $tenantId ?? $this->tenant->id,
            'name' => $name,
            'priority' => 'normal',
            'observations' => 'Observações padrão',
            'equipment_ids' => [],
            'is_active' => true,
        ]);
    }

    public function test_index_returns_only_current_tenant(): void
    {
        $mine = $this->createTemplate();

        $otherTenant = Tenant::factory()->create();
        $foreign = $this->createTemplate($otherTenant->id, 'Foreign');

        $response = $this->getJson('/api/v1/service-call-templates');

        $response->assertOk();
        $rows = $response->json('data.data') ?? $response->json('data');
        $ids = collect($rows)->pluck('id')->all();
        $this->assertContains($mine->id, $ids);
        $this->assertNotContains($foreign->id, $ids);
    }

    public function test_active_list_returns_only_active(): void
    {
        $response = $this->getJson('/api/v1/service-call-templates/active');

        $response->assertOk();
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/service-call-templates', []);

        $response->assertStatus(422);
    }

    public function test_store_creates_template(): void
    {
        $response = $this->postJson('/api/v1/service-call-templates', [
            'name' => 'Novo Template',
            'priority' => 'high',
            'observations' => 'Urgente',
            'is_active' => true,
        ]);

        $this->assertContains($response->status(), [200, 201]);
        $this->assertDatabaseHas('service_call_templates', [
            'tenant_id' => $this->tenant->id,
            'name' => 'Novo Template',
        ]);
    }

    public function test_destroy_removes_template(): void
    {
        $template = $this->createTemplate();

        $response = $this->deleteJson("/api/v1/service-call-templates/{$template->id}");

        $this->assertContains($response->status(), [200, 204]);
    }
}
