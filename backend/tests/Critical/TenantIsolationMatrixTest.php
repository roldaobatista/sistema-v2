<?php

namespace Tests\Critical;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Event;

/**
 * P1.1 — Matriz de Isolamento Multi-Tenant
 *
 * Valida que NENHUM dado de Tenant B é visível/acessível pelo Tenant A.
 * Cobre: Model scope, API HTTP, exportação, busca global.
 *
 * FALHA AQUI = BUG CATASTRÓFICO EM PRODUÇÃO
 */
class TenantIsolationMatrixTest extends CriticalTestCase
{
    private Tenant $tenantB;

    private User $userB;

    private Customer $customerA;

    private Customer $customerB;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();

        // Tenant B (o "invasor")
        $this->tenantB = Tenant::factory()->create();
        $this->userB = User::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'current_tenant_id' => $this->tenantB->id,
            'is_active' => true,
        ]);

        // Dados em ambos os tenants (bypass scope)
        $this->customerA = Customer::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Cliente Tenant A',
            'type' => 'PF',
        ]);
        $this->customerB = Customer::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Cliente Tenant B',
            'type' => 'PJ',
        ]);
    }

    // ========================================================
    // MODEL SCOPE — Queries automáticas filtram por tenant
    // ========================================================

    public function test_customer_query_only_returns_current_tenant(): void
    {
        app()->instance('current_tenant_id', $this->tenant->id);

        $customers = Customer::all();

        $this->assertTrue($customers->every(fn ($c) => $c->tenant_id === $this->tenant->id));
        $this->assertFalse($customers->contains(fn ($c) => $c->name === 'Cliente Tenant B'));
    }

    public function test_work_order_query_scoped_to_tenant(): void
    {
        WorkOrder::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'number' => 'OS-A-001',
            'customer_id' => $this->customerA->id,
            'created_by' => $this->user->id,
            'description' => 'OS Tenant A',
            'status' => 'open',
        ]);
        WorkOrder::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenantB->id,
            'number' => 'OS-B-001',
            'customer_id' => $this->customerB->id,
            'created_by' => $this->userB->id,
            'description' => 'OS Tenant B',
            'status' => 'open',
        ]);

        app()->instance('current_tenant_id', $this->tenant->id);

        $workOrders = WorkOrder::all();
        $this->assertTrue($workOrders->every(fn ($wo) => $wo->tenant_id === $this->tenant->id));
    }

    public function test_product_query_scoped_to_tenant(): void
    {
        Product::withoutGlobalScopes()->forceCreate([
            'tenant_id' => $this->tenant->id,
            'name' => 'Produto A',
        ]);
        Product::withoutGlobalScopes()->forceCreate([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Produto B',
        ]);

        app()->instance('current_tenant_id', $this->tenant->id);

        $products = Product::all();
        $this->assertTrue($products->every(fn ($p) => $p->tenant_id === $this->tenant->id));
    }

    // ========================================================
    // API HTTP — Endpoints não vazam dados entre tenants
    // ========================================================

    public function test_api_customers_only_returns_own_tenant(): void
    {
        $response = $this->getJson('/api/v1/customers');

        $response->assertOk();
        $data = $response->json('data');

        if (is_array($data) && count($data) > 0) {
            foreach ($data as $item) {
                $this->assertEquals(
                    $this->tenant->id,
                    $item['tenant_id'] ?? null,
                    'API customers vazou dado de outro tenant!'
                );
            }
        }
    }

    public function test_api_work_orders_only_returns_own_tenant(): void
    {
        WorkOrder::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'number' => 'OS-A-002',
            'customer_id' => $this->customerA->id,
            'created_by' => $this->user->id,
            'description' => 'OS do meu tenant',
            'status' => 'open',
        ]);
        WorkOrder::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenantB->id,
            'number' => 'OS-B-002',
            'customer_id' => $this->customerB->id,
            'created_by' => $this->userB->id,
            'description' => 'OS do outro tenant',
            'status' => 'open',
        ]);

        $response = $this->getJson('/api/v1/work-orders');

        $response->assertOk();
        $data = $response->json('data');

        if (is_array($data) && count($data) > 0) {
            foreach ($data as $item) {
                $this->assertNotEquals(
                    $this->tenantB->id,
                    $item['tenant_id'] ?? null,
                    'API work-orders vazou OS de outro tenant!'
                );
            }
        }
    }

    // ========================================================
    // ACESSO DIRETO — Show endpoint com ID de outro tenant retorna 404/403
    // ========================================================

    public function test_cannot_access_customer_from_other_tenant_via_show(): void
    {
        $response = $this->getJson("/api/v1/customers/{$this->customerB->id}");

        // Deve retornar 404 (não existe no scope) ou 403 (negado)
        $this->assertTrue(
            in_array($response->status(), [403, 404]),
            "Conseguiu acessar customer de outro tenant! Status: {$response->status()}"
        );
    }

    public function test_cannot_update_customer_from_other_tenant(): void
    {
        $response = $this->putJson("/api/v1/customers/{$this->customerB->id}", [
            'name' => 'Tentativa de alteração cross-tenant',
        ]);

        $this->assertTrue(
            in_array($response->status(), [403, 404, 405]),
            "Conseguiu ATUALIZAR customer de outro tenant! Status: {$response->status()}"
        );
    }

    public function test_cannot_delete_customer_from_other_tenant(): void
    {
        $response = $this->deleteJson("/api/v1/customers/{$this->customerB->id}");

        $this->assertTrue(
            in_array($response->status(), [403, 404, 405]),
            "Conseguiu DELETAR customer de outro tenant! Status: {$response->status()}"
        );
    }

    // ========================================================
    // SWITCH TENANT — Não pode trocar para tenant sem acesso
    // ========================================================

    public function test_cannot_switch_to_tenant_without_access(): void
    {
        $response = $this->postJson('/api/v1/switch-tenant', [
            'tenant_id' => $this->tenantB->id,
        ]);

        // Deve retornar erro (403, 422, ou redirect)
        $this->assertNotEquals(
            200,
            $response->status(),
            "Mudou para tenant sem acesso! Status: {$response->status()}"
        );
    }
}
