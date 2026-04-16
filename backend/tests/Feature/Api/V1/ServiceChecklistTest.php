<?php

namespace Tests\Feature\Api\V1;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\ServiceChecklist;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ServiceChecklistTest extends TestCase
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

    private function createChecklist(array $overrides = []): ServiceChecklist
    {
        return ServiceChecklist::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'name' => 'Checklist Padrão',
            'description' => 'Descrição do checklist',
            'is_active' => true,
        ], $overrides));
    }

    // ───── INDEX ─────

    public function test_index_returns_checklists_with_items(): void
    {
        $checklist = $this->createChecklist();
        $checklist->items()->create([
            'description' => 'Verificar pressão',
            'type' => 'check',
            'is_required' => true,
            'order_index' => 1,
        ]);

        $response = $this->getJson('/api/v1/service-checklists');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
    }

    public function test_index_does_not_return_other_tenant_checklists(): void
    {
        $this->createChecklist(['name' => 'Mine']);

        $otherTenant = Tenant::factory()->create();
        ServiceChecklist::create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Other',
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/service-checklists');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name')->all();
        $this->assertContains('Mine', $names);
        $this->assertNotContains('Other', $names);
    }

    // ───── STORE ─────

    public function test_store_creates_checklist_with_items(): void
    {
        $response = $this->postJson('/api/v1/service-checklists', [
            'name' => 'Novo Checklist',
            'description' => 'Para calibração',
            'is_active' => true,
            'items' => [
                [
                    'description' => 'Verificar selo',
                    'type' => 'check',
                    'is_required' => true,
                    'order_index' => 1,
                ],
                [
                    'description' => 'Anotar leitura',
                    'type' => 'number',
                    'is_required' => false,
                    'order_index' => 2,
                ],
            ],
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('service_checklists', [
            'name' => 'Novo Checklist',
            'tenant_id' => $this->tenant->id,
        ]);

        $checklist = ServiceChecklist::where('name', 'Novo Checklist')->first();
        $this->assertCount(2, $checklist->items);
    }

    public function test_store_without_items_succeeds(): void
    {
        $response = $this->postJson('/api/v1/service-checklists', [
            'name' => 'Checklist Vazio',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('service_checklists', [
            'name' => 'Checklist Vazio',
        ]);
    }

    public function test_store_validates_name_required(): void
    {
        $response = $this->postJson('/api/v1/service-checklists', [
            'description' => 'Sem nome',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name']);
    }

    public function test_store_validates_item_type(): void
    {
        $response = $this->postJson('/api/v1/service-checklists', [
            'name' => 'Checklist Tipo Invalido',
            'items' => [
                [
                    'description' => 'Item',
                    'type' => 'invalid_type',
                ],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['items.0.type']);
    }

    // ───── SHOW ─────

    public function test_show_returns_checklist_with_items(): void
    {
        $checklist = $this->createChecklist();
        $checklist->items()->create([
            'description' => 'Item 1',
            'type' => 'text',
            'order_index' => 1,
        ]);

        $response = $this->getJson("/api/v1/service-checklists/{$checklist->id}");

        $response->assertOk();
        $data = $response->json('data');
        $this->assertEquals($checklist->id, $data['id']);
    }

    public function test_show_returns_404_for_other_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherChecklist = ServiceChecklist::create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Foreign',
            'is_active' => true,
        ]);

        $response = $this->getJson("/api/v1/service-checklists/{$otherChecklist->id}");

        $response->assertStatus(404);
    }

    // ───── UPDATE ─────

    public function test_update_modifies_checklist_and_replaces_items(): void
    {
        $checklist = $this->createChecklist();
        $checklist->items()->create([
            'description' => 'Old Item',
            'type' => 'check',
            'order_index' => 1,
        ]);

        $response = $this->putJson("/api/v1/service-checklists/{$checklist->id}", [
            'name' => 'Updated Checklist',
            'items' => [
                [
                    'description' => 'New Item A',
                    'type' => 'yes_no',
                    'order_index' => 1,
                ],
                [
                    'description' => 'New Item B',
                    'type' => 'photo',
                    'order_index' => 2,
                ],
            ],
        ]);

        $response->assertOk();

        $checklist->refresh();
        $this->assertEquals('Updated Checklist', $checklist->name);
        $this->assertCount(2, $checklist->items);

        // Old items should be gone
        $this->assertDatabaseMissing('service_checklist_items', [
            'description' => 'Old Item',
            'checklist_id' => $checklist->id,
        ]);
    }

    public function test_update_without_items_preserves_existing(): void
    {
        $checklist = $this->createChecklist();
        $checklist->items()->create([
            'description' => 'Preserved Item',
            'type' => 'check',
            'order_index' => 1,
        ]);

        $response = $this->putJson("/api/v1/service-checklists/{$checklist->id}", [
            'name' => 'Updated Name Only',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('service_checklist_items', [
            'description' => 'Preserved Item',
            'checklist_id' => $checklist->id,
        ]);
    }

    // ───── DESTROY ─────

    public function test_destroy_deletes_checklist_and_items(): void
    {
        $checklist = $this->createChecklist();
        $checklist->items()->create([
            'description' => 'To be deleted',
            'type' => 'check',
            'order_index' => 1,
        ]);

        $response = $this->deleteJson("/api/v1/service-checklists/{$checklist->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('service_checklists', ['id' => $checklist->id]);
        $this->assertDatabaseMissing('service_checklist_items', ['checklist_id' => $checklist->id]);
    }

    public function test_destroy_blocked_when_linked_to_work_orders(): void
    {
        $checklist = $this->createChecklist();
        WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'checklist_id' => $checklist->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->deleteJson("/api/v1/service-checklists/{$checklist->id}");

        $response->assertStatus(409);
        $this->assertDatabaseHas('service_checklists', ['id' => $checklist->id]);
    }

    public function test_destroy_returns_404_for_other_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherChecklist = ServiceChecklist::create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Foreign Delete',
            'is_active' => true,
        ]);

        $response = $this->deleteJson("/api/v1/service-checklists/{$otherChecklist->id}");

        $response->assertStatus(404);
    }

    // ───── Auth ─────

    public function test_unauthenticated_user_cannot_access(): void
    {
        app('auth')->forgetGuards();

        $response = $this->getJson('/api/v1/service-checklists');

        $response->assertStatus(401);
    }
}
