<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;

/**
 * Testes de regressao para o scope `forTenant()` do trait BelongsToTenant.
 *
 * Cenario da re-auditoria Camada 1 2026-04-19 (findings data-01 / data-02 / data-08):
 * o scope centraliza a justificativa arquitetural de `withoutGlobalScope('tenant')`
 * usado em jobs cron e services multi-tenant. Regra H2 do CLAUDE.md.
 *
 * Contratos provados aqui:
 *  1. forTenant($id) filtra pelo tenant alvo ignorando o binding atual.
 *  2. forTenant($id) nao confunde registros de outros tenants.
 *  3. forTenant(0) ou negativo lanca InvalidArgumentException (fail-fast).
 *  4. forTenant() compoe com outros where() normalmente.
 */
describe('BelongsToTenant::scopeForTenant', function () {
    it('retorna apenas registros do tenant alvo mesmo quando binding aponta para outro tenant', function () {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $userA = User::factory()->create(['tenant_id' => $tenantA->id]);
        $userB = User::factory()->create(['tenant_id' => $tenantB->id]);

        // Cria WO no tenantA (binding apontado para A)
        app()->instance('current_tenant_id', $tenantA->id);
        $woA = WorkOrder::factory()->create([
            'tenant_id' => $tenantA->id,
            'customer_id' => \App\Models\Customer::factory()->create(['tenant_id' => $tenantA->id])->id,
            'created_by' => $userA->id,
        ]);

        // Cria WO no tenantB (binding apontado para B)
        app()->instance('current_tenant_id', $tenantB->id);
        $woB = WorkOrder::factory()->create([
            'tenant_id' => $tenantB->id,
            'customer_id' => \App\Models\Customer::factory()->create(['tenant_id' => $tenantB->id])->id,
            'created_by' => $userB->id,
        ]);

        // Binding agora aponta para B, mas queremos buscar de A via forTenant
        app()->instance('current_tenant_id', $tenantB->id);

        $fromA = WorkOrder::forTenant($tenantA->id)->get();

        expect($fromA)->toHaveCount(1)
            ->and($fromA->first()->id)->toBe($woA->id)
            ->and($fromA->first()->tenant_id)->toBe($tenantA->id);
    });

    it('compoe com wheres adicionais normalmente', function () {
        $tenantA = Tenant::factory()->create();
        $userA = User::factory()->create(['tenant_id' => $tenantA->id]);
        $customerA = \App\Models\Customer::factory()->create(['tenant_id' => $tenantA->id]);

        app()->instance('current_tenant_id', $tenantA->id);
        WorkOrder::factory()->count(3)->create([
            'tenant_id' => $tenantA->id,
            'customer_id' => $customerA->id,
            'created_by' => $userA->id,
            'status' => WorkOrder::STATUS_OPEN,
        ]);
        WorkOrder::factory()->count(2)->create([
            'tenant_id' => $tenantA->id,
            'customer_id' => $customerA->id,
            'created_by' => $userA->id,
            'status' => WorkOrder::STATUS_COMPLETED,
        ]);

        // Remove binding — prova que forTenant nao depende dele
        app()->forgetInstance('current_tenant_id');

        $opens = WorkOrder::forTenant($tenantA->id)
            ->where('status', WorkOrder::STATUS_OPEN)
            ->get();

        expect($opens)->toHaveCount(3);
    });

    it('lanca InvalidArgumentException quando tenantId e zero', function () {
        WorkOrder::forTenant(0)->get();
    })->throws(InvalidArgumentException::class, 'forTenant');

    it('lanca InvalidArgumentException quando tenantId e negativo', function () {
        WorkOrder::forTenant(-1)->get();
    })->throws(InvalidArgumentException::class, 'forTenant');

    it('nao vaza registros entre tenants mesmo sem binding configurado', function () {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $userA = User::factory()->create(['tenant_id' => $tenantA->id]);
        $userB = User::factory()->create(['tenant_id' => $tenantB->id]);
        $customerA = \App\Models\Customer::factory()->create(['tenant_id' => $tenantA->id]);
        $customerB = \App\Models\Customer::factory()->create(['tenant_id' => $tenantB->id]);

        app()->instance('current_tenant_id', $tenantA->id);
        WorkOrder::factory()->count(2)->create([
            'tenant_id' => $tenantA->id,
            'customer_id' => $customerA->id,
            'created_by' => $userA->id,
        ]);

        app()->instance('current_tenant_id', $tenantB->id);
        WorkOrder::factory()->count(5)->create([
            'tenant_id' => $tenantB->id,
            'customer_id' => $customerB->id,
            'created_by' => $userB->id,
        ]);

        app()->forgetInstance('current_tenant_id');

        expect(WorkOrder::forTenant($tenantA->id)->count())->toBe(2)
            ->and(WorkOrder::forTenant($tenantB->id)->count())->toBe(5);
    });
});
