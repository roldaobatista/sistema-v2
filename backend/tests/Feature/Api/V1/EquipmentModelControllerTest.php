<?php

namespace Tests\Feature\Api\V1;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\EquipmentModel;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EquipmentModelControllerTest extends TestCase
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

    private function createModel(?int $tenantId = null, string $name = 'Modelo Padrão'): EquipmentModel
    {
        return EquipmentModel::create([
            'tenant_id' => $tenantId ?? $this->tenant->id,
            'name' => $name,
            'brand' => 'TestBrand',
            'category' => 'balanca',
        ]);
    }

    public function test_index_returns_only_current_tenant_models(): void
    {
        $mine = $this->createModel();

        $otherTenant = Tenant::factory()->create();
        $foreign = $this->createModel($otherTenant->id, 'Modelo Foreign');

        $response = $this->getJson('/api/v1/equipment-models');

        $response->assertOk()->assertJsonStructure(['data']);

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($mine->id, $ids);
        $this->assertNotContains($foreign->id, $ids);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/equipment-models', []);

        $response->assertStatus(422);
    }

    public function test_store_creates_model_with_tenant(): void
    {
        $response = $this->postJson('/api/v1/equipment-models', [
            'name' => 'Balança XYZ',
            'brand' => 'ACME',
            'category' => 'balanca_analitica',
        ]);

        $this->assertContains($response->status(), [200, 201]);
        $this->assertDatabaseHas('equipment_models', [
            'tenant_id' => $this->tenant->id,
            'name' => 'Balança XYZ',
            'brand' => 'ACME',
        ]);
    }

    public function test_show_returns_equipment_model_details(): void
    {
        $model = $this->createModel();

        $response = $this->getJson("/api/v1/equipment-models/{$model->id}");

        $response->assertOk();
    }

    public function test_destroy_rejects_cross_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $foreign = $this->createModel($otherTenant->id, 'Foreign Model');

        $response = $this->deleteJson("/api/v1/equipment-models/{$foreign->id}");

        $this->assertContains($response->status(), [403, 404]);
        $this->assertDatabaseHas('equipment_models', ['id' => $foreign->id]);
    }
}
