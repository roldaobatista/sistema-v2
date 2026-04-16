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

class ServiceCallTemplateTest extends TestCase
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

    private function createTemplate(array $overrides = []): ServiceCallTemplate
    {
        return ServiceCallTemplate::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'name' => 'Template Chamado',
            'priority' => 'normal',
            'observations' => 'Observações do template',
            'is_active' => true,
        ], $overrides));
    }

    // ───── INDEX ─────

    public function test_index_returns_all_templates(): void
    {
        $this->createTemplate(['name' => 'Template 1']);
        $this->createTemplate(['name' => 'Template 2']);

        $response = $this->getJson('/api/v1/service-call-templates');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(2, $data);
    }

    public function test_index_does_not_return_other_tenant_templates(): void
    {
        $this->createTemplate(['name' => 'Mine']);

        $otherTenant = Tenant::factory()->create();
        ServiceCallTemplate::create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Other',
            'priority' => 'normal',
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/service-call-templates');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name')->all();
        $this->assertContains('Mine', $names);
        $this->assertNotContains('Other', $names);
    }

    // ───── ACTIVE LIST ─────

    public function test_active_list_returns_only_active_templates(): void
    {
        $this->createTemplate(['name' => 'Ativo', 'is_active' => true]);
        $this->createTemplate(['name' => 'Inativo', 'is_active' => false]);

        $response = $this->getJson('/api/v1/service-call-templates/active');

        $response->assertOk();
        $data = $response->json('data');
        $names = collect($data)->pluck('name')->all();
        $this->assertContains('Ativo', $names);
        $this->assertNotContains('Inativo', $names);
    }

    // ───── STORE ─────

    public function test_store_creates_template(): void
    {
        $response = $this->postJson('/api/v1/service-call-templates', [
            'name' => 'Novo Template',
            'priority' => 'high',
            'observations' => 'Detalhes importantes',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.name', 'Novo Template');

        $this->assertDatabaseHas('service_call_templates', [
            'name' => 'Novo Template',
            'tenant_id' => $this->tenant->id,
            'priority' => 'high',
        ]);
    }

    public function test_store_validates_name_required(): void
    {
        $response = $this->postJson('/api/v1/service-call-templates', [
            'priority' => 'normal',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name']);
    }

    public function test_store_validates_priority_values(): void
    {
        $response = $this->postJson('/api/v1/service-call-templates', [
            'name' => 'Invalid Priority',
            'priority' => 'super-high',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['priority']);
    }

    public function test_store_with_equipment_ids(): void
    {
        $response = $this->postJson('/api/v1/service-call-templates', [
            'name' => 'Com Equipamentos',
            'priority' => 'normal',
            'equipment_ids' => [1, 2, 3],
        ]);

        $response->assertStatus(201);

        $template = ServiceCallTemplate::where('name', 'Com Equipamentos')->first();
        $this->assertEquals([1, 2, 3], $template->equipment_ids);
    }

    public function test_store_validates_name_max_length(): void
    {
        $response = $this->postJson('/api/v1/service-call-templates', [
            'name' => str_repeat('A', 151),
            'priority' => 'normal',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name']);
    }

    // ───── UPDATE ─────

    public function test_update_modifies_template(): void
    {
        $template = $this->createTemplate();

        $response = $this->putJson("/api/v1/service-call-templates/{$template->id}", [
            'name' => 'Atualizado',
            'priority' => 'urgent',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.name', 'Atualizado');

        $this->assertDatabaseHas('service_call_templates', [
            'id' => $template->id,
            'name' => 'Atualizado',
            'priority' => 'urgent',
        ]);
    }

    public function test_update_rejects_other_tenant_template(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherTemplate = ServiceCallTemplate::create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Alien',
            'priority' => 'normal',
            'is_active' => true,
        ]);

        $response = $this->putJson("/api/v1/service-call-templates/{$otherTemplate->id}", [
            'name' => 'Hacked',
        ]);

        $response->assertStatus(404);
    }

    // ───── DESTROY ─────

    public function test_destroy_deletes_template(): void
    {
        $template = $this->createTemplate();

        $response = $this->deleteJson("/api/v1/service-call-templates/{$template->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('service_call_templates', ['id' => $template->id]);
    }

    public function test_destroy_rejects_other_tenant_template(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherTemplate = ServiceCallTemplate::create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Delete Protected',
            'priority' => 'normal',
            'is_active' => true,
        ]);

        $response = $this->deleteJson("/api/v1/service-call-templates/{$otherTemplate->id}");

        $response->assertStatus(404);
        $this->assertDatabaseHas('service_call_templates', ['id' => $otherTemplate->id]);
    }

    // ───── Auth ─────

    public function test_unauthenticated_user_cannot_access(): void
    {
        app('auth')->forgetGuards();

        $response = $this->getJson('/api/v1/service-call-templates');

        $response->assertStatus(401);
    }
}
