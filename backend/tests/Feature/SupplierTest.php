<?php

namespace Tests\Feature;

use App\Models\Supplier;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;
use Tests\Traits\DisablesTenantMiddleware;
use Tests\Traits\SetupTenantUser;

class SupplierTest extends TestCase
{
    use DisablesTenantMiddleware;
    use SetupTenantUser;

    protected function setUp(): void
    {
        parent::setUp();
        Gate::before(fn () => true);
        $this->setUpDisablesTenantMiddleware();
        $this->setUpTenantUserAdmin();
    }

    public function test_create_supplier_pj(): void
    {
        $response = $this->postJson('/api/v1/suppliers', [
            'type' => 'PJ',
            'name' => 'Fornecedor Teste',
            'document' => '12.345.678/0001-99',
            'email' => 'fornecedor@test.com',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Fornecedor Teste');
    }

    public function test_list_suppliers(): void
    {
        $this->createTenantModel(Supplier::class);
        $this->createTenantModel(Supplier::class);
        $this->createTenantModel(Supplier::class);

        $response = $this->getJson('/api/v1/suppliers');

        $response->assertOk()
            ->assertJsonPath('total', 3);
    }

    public function test_show_supplier(): void
    {
        $supplier = $this->createTenantModel(Supplier::class);

        $response = $this->getJson("/api/v1/suppliers/{$supplier->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $supplier->id);
    }

    public function test_update_supplier(): void
    {
        $supplier = $this->createTenantModel(Supplier::class);

        $response = $this->putJson("/api/v1/suppliers/{$supplier->id}", [
            'name' => 'Nome Atualizado',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Nome Atualizado');
    }

    public function test_delete_supplier(): void
    {
        $supplier = $this->createTenantModel(Supplier::class);

        $response = $this->deleteJson("/api/v1/suppliers/{$supplier->id}");

        $response->assertStatus(204);
    }
}
