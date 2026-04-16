<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\Tenant;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;
use Tests\Traits\DisablesTenantMiddleware;
use Tests\Traits\SetupTenantUser;

class ServiceCrudTest extends TestCase
{
    use DisablesTenantMiddleware;
    use SetupTenantUser;

    protected Tenant $otherTenant;

    protected function setUp(): void
    {
        parent::setUp();
        Gate::before(fn () => true);
        $this->setUpDisablesTenantMiddleware();
        $this->setUpTenantUser();
        $this->otherTenant = Tenant::factory()->create();
    }

    public function test_service_crud_and_tenant_isolation(): void
    {
        $create = $this->postJson('/api/v1/services', [
            'name' => 'Servico Master',
            'code' => 'SRV-MASTER-001',
            'default_price' => 120.00,
            'tenant_id' => $this->otherTenant->id,
        ]);

        $create->assertCreated()
            ->assertJsonPath('data.name', 'Servico Master')
            ->assertJsonPath('data.tenant_id', $this->tenant->id);

        $serviceId = (int) $create->json('data.id');

        Service::factory()->create([
            'tenant_id' => $this->otherTenant->id,
            'name' => 'Servico Outro Tenant',
            'code' => 'SRV-OTHER-001',
        ]);

        $this->getJson('/api/v1/services')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta'])
            ->assertJsonPath('meta.total', 1);

        $this->getJson("/api/v1/services/{$serviceId}")
            ->assertOk()
            ->assertJsonPath('data.id', $serviceId);

        $foreignService = Service::withoutGlobalScope('tenant')
            ->where('tenant_id', $this->otherTenant->id)
            ->firstOrFail();

        $this->getJson("/api/v1/services/{$foreignService->id}")
            ->assertStatus(404);

        $this->putJson("/api/v1/services/{$serviceId}", [
            'name' => 'Servico Master Atualizado',
            'default_price' => 150.00,
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Servico Master Atualizado');

        $this->assertDatabaseHas('services', [
            'id' => $serviceId,
            'name' => 'Servico Master Atualizado',
        ]);
    }
}
