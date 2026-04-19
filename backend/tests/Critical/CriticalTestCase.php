<?php

namespace Tests\Critical;

use App\Models\Tenant;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Critical Business Tests — Executados semanalmente (~2h)
 *
 * Testam cenários de negócio de alto risco:
 * - Financeiro (AR/AP, Comissões, INSS/IRRF)
 * - Estoque (Kardex, Reserva, Devolução)
 * - OS (Fluxo completo field-service, 13+ estados)
 * - Permissões (RBAC cross-tenant isolation)
 * - Multi-tenant (Isolamento de dados total)
 *
 * Para rodar apenas critical:
 *   vendor/bin/pest tests/Critical --parallel
 */
abstract class CriticalTestCase extends TestCase
{
    protected Tenant $tenant;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        app()->instance('current_tenant_id', $this->tenant->id);
        setPermissionsTeamId($this->tenant->id);
        $this->user->tenants()->syncWithoutDetaching([$this->tenant->id => ['is_default' => true]]);
        $this->user->assignRole('super_admin');
        Sanctum::actingAs($this->user, ["tenant:{$this->tenant->id}"]);
    }
}
