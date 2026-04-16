<?php

namespace Tests\Feature\Api\V1\Os;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\ServiceChecklist;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrderTemplate;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WorkOrderTemplateTest extends TestCase
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
        app()->instance('current_tenant_id', $this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    private function createTemplate(array $overrides = []): WorkOrderTemplate
    {
        return WorkOrderTemplate::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'name' => 'Manutenção Preventiva',
            'description' => 'Template padrão de preventiva',
            'priority' => 'normal',
            'created_by' => $this->user->id,
        ], $overrides));
    }

    // ───── INDEX ─────

    public function test_index_returns_templates_for_current_tenant(): void
    {
        $this->createTemplate(['name' => 'Template A']);
        $this->createTemplate(['name' => 'Template B']);

        $response = $this->getJson('/api/v1/work-order-templates');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(2, $data);
    }

    public function test_index_does_not_return_other_tenant_templates(): void
    {
        $this->createTemplate(['name' => 'Mine']);

        $otherTenant = Tenant::factory()->create();
        WorkOrderTemplate::create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Other Tenant Template',
            'created_by' => $this->user->id,
        ]);

        $response = $this->getJson('/api/v1/work-order-templates');

        $response->assertOk();
        $data = $response->json('data');
        // Only the template from our tenant should appear
        $names = collect($data)->pluck('name')->all();
        $this->assertContains('Mine', $names);
        $this->assertNotContains('Other Tenant Template', $names);
    }

    public function test_index_filters_by_search(): void
    {
        $this->createTemplate(['name' => 'Manutenção Corretiva']);
        $this->createTemplate(['name' => 'Instalação de Equipamento']);

        $response = $this->getJson('/api/v1/work-order-templates?search=Corretiva');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('Manutenção Corretiva', $data[0]['name']);
    }

    public function test_index_respects_pagination(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->createTemplate(['name' => "Template {$i}"]);
        }

        $response = $this->getJson('/api/v1/work-order-templates?per_page=2');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(2, $data);
        $this->assertEquals(5, $response->json('total'));
    }

    // ───── STORE ─────

    public function test_store_creates_template(): void
    {
        $response = $this->postJson('/api/v1/work-order-templates', [
            'name' => 'Nova Template',
            'description' => 'Descrição da template',
            'priority' => 'high',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.name', 'Nova Template');
        $response->assertJsonPath('data.priority', 'high');

        $this->assertDatabaseHas('work_order_templates', [
            'name' => 'Nova Template',
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
        ]);
    }

    public function test_store_with_default_items(): void
    {
        $items = [
            ['type' => 'product', 'description' => 'Filtro de Ar', 'quantity' => 2, 'unit_price' => 35.50],
            ['type' => 'service', 'description' => 'Mão de Obra', 'quantity' => 1, 'unit_price' => 100],
        ];

        $response = $this->postJson('/api/v1/work-order-templates', [
            'name' => 'Template com itens',
            'default_items' => $items,
        ]);

        $response->assertStatus(201);
        $template = WorkOrderTemplate::find($response->json('data.id'));
        $this->assertIsArray($template->default_items);
        $this->assertCount(2, $template->default_items);
    }

    public function test_store_validates_name_required(): void
    {
        $response = $this->postJson('/api/v1/work-order-templates', [
            'description' => 'Sem nome',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name']);
    }

    public function test_store_validates_priority_values(): void
    {
        $response = $this->postJson('/api/v1/work-order-templates', [
            'name' => 'Invalid Priority',
            'priority' => 'super-ultra',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['priority']);
    }

    // ───── SHOW ─────

    public function test_show_returns_template_with_relations(): void
    {
        $template = $this->createTemplate();

        $response = $this->getJson("/api/v1/work-order-templates/{$template->id}");

        $response->assertOk();
        $response->assertJsonPath('data.id', $template->id);
        $response->assertJsonPath('data.name', $template->name);
    }

    public function test_show_returns_404_for_other_tenant_template(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherTemplate = WorkOrderTemplate::create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Other tenant',
            'created_by' => $this->user->id,
        ]);

        $response = $this->getJson("/api/v1/work-order-templates/{$otherTemplate->id}");

        $response->assertStatus(404);
    }

    // ───── UPDATE ─────

    public function test_update_modifies_template(): void
    {
        $template = $this->createTemplate();

        $response = $this->putJson("/api/v1/work-order-templates/{$template->id}", [
            'name' => 'Nome Atualizado',
            'priority' => 'urgent',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.name', 'Nome Atualizado');
        $response->assertJsonPath('data.priority', 'urgent');

        $this->assertDatabaseHas('work_order_templates', [
            'id' => $template->id,
            'name' => 'Nome Atualizado',
        ]);
    }

    public function test_update_rejects_other_tenant_template(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherTemplate = WorkOrderTemplate::create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Alien',
            'created_by' => $this->user->id,
        ]);

        $response = $this->putJson("/api/v1/work-order-templates/{$otherTemplate->id}", [
            'name' => 'Hacked',
        ]);

        $response->assertStatus(404);
    }

    // ───── DESTROY ─────

    public function test_destroy_deletes_template(): void
    {
        $template = $this->createTemplate();

        $response = $this->deleteJson("/api/v1/work-order-templates/{$template->id}");

        $response->assertStatus(204);
        $this->assertSoftDeleted('work_order_templates', ['id' => $template->id]);
    }

    public function test_destroy_rejects_other_tenant_template(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherTemplate = WorkOrderTemplate::create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Alien Delete',
            'created_by' => $this->user->id,
        ]);

        $response = $this->deleteJson("/api/v1/work-order-templates/{$otherTemplate->id}");

        $response->assertStatus(404);
        $this->assertDatabaseHas('work_order_templates', ['id' => $otherTemplate->id]);
    }

    // ───── Edge cases ─────

    public function test_store_with_checklist_id(): void
    {
        $checklist = ServiceChecklist::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Checklist Teste',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/work-order-templates', [
            'name' => 'Template com checklist',
            'checklist_id' => $checklist->id,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('work_order_templates', [
            'name' => 'Template com checklist',
            'checklist_id' => $checklist->id,
        ]);
    }

    public function test_unauthenticated_user_cannot_access_templates(): void
    {
        // Reset auth
        app('auth')->forgetGuards();

        $response = $this->getJson('/api/v1/work-order-templates');

        $response->assertStatus(401);
    }
}
