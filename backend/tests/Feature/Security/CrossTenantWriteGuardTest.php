<?php

namespace Tests\Feature\Security;

use App\Models\Customer;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * sec-15 (Re-auditoria Camada 1 r3): BelongsToTenant::save() bloqueia
 * reassign de tenant_id em model persistido (imutabilidade de tenant).
 *
 * Model criado em tenant X permanece em X até deletado. Tentar mutar
 * tenant_id = vazamento cross-tenant detectado e abortado.
 */
class CrossTenantWriteGuardTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_reassign_tenant_id_em_model_persistido_lanca_runtime_exception(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $customer = Customer::factory()->create(['tenant_id' => $tenantA->id]);

        // Tentativa de mover customer de tenantA para tenantB — deve falhar.
        $customer->tenant_id = $tenantB->id;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Cross-tenant write blocked/');

        $customer->save();
    }

    public function test_save_sem_mutacao_de_tenant_id_nao_levanta(): void
    {
        $tenant = Tenant::factory()->create();
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);

        // Alterar atributo não-tenant → save normal.
        $customer->name = 'Novo Nome';
        $saved = $customer->save();

        $this->assertTrue($saved);
        $this->assertSame($tenant->id, $customer->fresh()->tenant_id);
    }

    public function test_criacao_nova_com_tenant_id_diferente_do_binding_nao_bloqueia(): void
    {
        // Criação (!exists) não dispara guard — permite seeders/jobs
        // multi-tenant setar tenant_id via factory.
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        app()->instance('current_tenant_id', $tenantA->id);

        $customer = Customer::factory()->create(['tenant_id' => $tenantB->id]);

        $this->assertSame($tenantB->id, $customer->tenant_id);
    }
}
