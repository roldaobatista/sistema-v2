<?php

use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;

/**
 * qa-03: teste unitário do trait BelongsToTenant.
 *
 * Cobre:
 *  - Global scope filtra por current_tenant_id
 *  - creating event preenche tenant_id quando ausente
 *  - Cross-tenant query retorna vazio mesmo com tenant alheio existente
 *  - withoutGlobalScope rompe o isolamento (documenta o único caminho legal)
 */
uses()->group('unit', 'tenant-safety');

beforeEach(function () {
    $this->tenantA = Tenant::factory()->create();
    $this->tenantB = Tenant::factory()->create();

    // user do tenant A operando sob current_tenant_id = A
    $this->userA = User::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'current_tenant_id' => $this->tenantA->id,
    ]);

    app()->instance('current_tenant_id', $this->tenantA->id);
});

afterEach(function () {
    app()->forgetInstance('current_tenant_id');
});

it('global scope filtra registros pelo current_tenant_id ativo', function () {
    Customer::factory()->create(['tenant_id' => $this->tenantA->id, 'name' => 'Cliente A1']);
    Customer::factory()->create(['tenant_id' => $this->tenantA->id, 'name' => 'Cliente A2']);
    Customer::factory()->create(['tenant_id' => $this->tenantB->id, 'name' => 'Cliente B1']);

    $resultado = Customer::query()->pluck('name')->all();

    expect($resultado)->toHaveCount(2)
        ->and($resultado)->toContain('Cliente A1', 'Cliente A2')
        ->and($resultado)->not->toContain('Cliente B1');
});

it('cross-tenant: troca de current_tenant_id isola resultados', function () {
    Customer::factory()->create(['tenant_id' => $this->tenantA->id, 'name' => 'Cliente A']);
    Customer::factory()->create(['tenant_id' => $this->tenantB->id, 'name' => 'Cliente B']);

    // agora vê como tenant B
    app()->instance('current_tenant_id', $this->tenantB->id);

    $names = Customer::query()->pluck('name')->all();

    expect($names)->toBe(['Cliente B']);
});

it('creating event preenche tenant_id automaticamente se ausente', function () {
    $customer = Customer::query()->create([
        'name' => 'Auto-scoped',
        'type' => 'PJ',
        'email' => 'auto@test.com',
    ]);

    expect($customer->tenant_id)->toBe($this->tenantA->id);
});

it('sem current_tenant_id bound o global scope e no-op (documenta contrato atual)', function () {
    Customer::factory()->create(['tenant_id' => $this->tenantA->id]);
    Customer::factory()->create(['tenant_id' => $this->tenantB->id]);

    app()->forgetInstance('current_tenant_id');

    // Contrato atual (BelongsToTenant::bootBelongsToTenant): se current_tenant_id
    // nao esta bound, o scope nao filtra nada — middleware EnsureTenantScope e
    // responsavel por garantir o binding em todas as rotas reais.
    // Ambiente de teste sem middleware ve todos os tenants.
    expect(Customer::query()->count())->toBe(2);
});

it('withoutGlobalScope é o único caminho para consulta cross-tenant', function () {
    Customer::factory()->create(['tenant_id' => $this->tenantA->id, 'name' => 'A']);
    Customer::factory()->create(['tenant_id' => $this->tenantB->id, 'name' => 'B']);

    // Sem global scope, vê tudo — uso administrativo legítimo (regra H2).
    $todos = Customer::query()->withoutGlobalScope('tenant')->pluck('name')->sort()->values()->all();

    expect($todos)->toBe(['A', 'B']);
});
