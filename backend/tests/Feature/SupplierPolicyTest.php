<?php

namespace Tests\Feature;

use App\Models\Supplier;
use App\Models\Tenant;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;
use Tests\Traits\DisablesTenantMiddleware;
use Tests\Traits\SetupTenantUser;

/**
 * Testa isolamento por tenant e permissões da SupplierPolicy.
 */
class SupplierPolicyTest extends TestCase
{
    use DisablesTenantMiddleware;
    use SetupTenantUser;

    protected function setUp(): void
    {
        parent::setUp();
        Gate::before(fn () => true);
        $this->setUpDisablesTenantMiddleware();
        $this->setUpTenantUser();
    }

    public function test_cannot_access_other_tenant_supplier(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherSupplier = Supplier::factory()->create(['tenant_id' => $otherTenant->id]);

        $this->getJson("/api/v1/suppliers/{$otherSupplier->id}")
            ->assertNotFound();
    }

    public function test_can_access_own_tenant_supplier(): void
    {
        $supplier = $this->createTenantModel(Supplier::class);

        $this->getJson("/api/v1/suppliers/{$supplier->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $supplier->id);
    }
}
