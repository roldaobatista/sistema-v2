<?php

namespace Tests\Feature\Api\V1;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Checklist;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChecklistControllerTest extends TestCase
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

    private function createChecklist(?int $tenantId = null, string $name = 'Checklist Balança'): Checklist
    {
        return Checklist::create([
            'tenant_id' => $tenantId ?? $this->tenant->id,
            'name' => $name,
            'description' => 'Checklist padrão',
            'items' => [
                ['id' => 'q1', 'text' => 'Verificação inicial', 'type' => 'boolean'],
                ['id' => 'q2', 'text' => 'Registro de leitura', 'type' => 'text'],
            ],
            'is_active' => true,
        ]);
    }

    public function test_index_returns_only_current_tenant_checklists(): void
    {
        $mine = $this->createChecklist();

        $otherTenant = Tenant::factory()->create();
        $foreign = $this->createChecklist($otherTenant->id, 'Foreign');

        $response = $this->getJson('/api/v1/checklists');

        $response->assertOk();
        $data = $response->json('data');
        $ids = collect(is_array($data) && isset($data['data']) ? $data['data'] : $data)->pluck('id')->all();
        $this->assertContains($mine->id, $ids);
        $this->assertNotContains($foreign->id, $ids);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/checklists', []);

        $response->assertStatus(422);
    }

    public function test_store_validates_items_required(): void
    {
        $response = $this->postJson('/api/v1/checklists', [
            'name' => 'Checklist sem items',
        ]);

        $response->assertStatus(422);
    }

    public function test_store_creates_checklist(): void
    {
        $response = $this->postJson('/api/v1/checklists', [
            'name' => 'Checklist novo',
            'description' => 'Descrição do checklist',
            'items' => [
                ['id' => 'q1', 'text' => 'Etapa 1', 'type' => 'boolean'],
                ['id' => 'q2', 'text' => 'Etapa 2', 'type' => 'text'],
            ],
        ]);

        $this->assertContains($response->status(), [200, 201]);
        $this->assertDatabaseHas('checklists', [
            'tenant_id' => $this->tenant->id,
            'name' => 'Checklist novo',
        ]);
    }

    public function test_show_returns_checklist(): void
    {
        $checklist = $this->createChecklist();

        $response = $this->getJson("/api/v1/checklists/{$checklist->id}");

        $response->assertOk();
    }

    public function test_update_modifies_checklist(): void
    {
        $checklist = $this->createChecklist();

        $response = $this->putJson("/api/v1/checklists/{$checklist->id}", [
            'name' => 'Checklist atualizado',
            'items' => [
                ['id' => 'q1', 'text' => 'Novo item', 'type' => 'boolean'],
            ],
        ]);

        $this->assertContains($response->status(), [200, 201]);
        $this->assertDatabaseHas('checklists', [
            'id' => $checklist->id,
            'name' => 'Checklist atualizado',
        ]);
    }
}
