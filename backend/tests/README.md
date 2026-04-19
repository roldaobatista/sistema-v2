# Tests — Kalibrium ERP

> **9.629 testes | 27.448 assertions | ~4min49s local | 0 falhas**

## Quick Start

```bash
# Rodar todos os testes (parallel, 16 processos, SQLite in-memory)
./vendor/bin/pest --parallel --processes=16 --no-coverage

# Ou via composer
composer test-fast
```

## Pirâmide de Escalada (CLAUDE.md §Pirâmide de testes)

Durante desenvolvimento, **nunca** rodar a suite completa no meio de uma task. Escalar progressivamente e corrigir ali se falhar — não escalar para cima:

```bash
# 1. Teste específico (file, fastest, use primeiro)
./vendor/bin/pest tests/Feature/Customers/CustomerControllerTest.php

# 2. Filtro por nome (group of tests)
./vendor/bin/pest --filter="creates a customer"

# 3. Testsuite dedicada — escolher a relevante:
./vendor/bin/pest --testsuite=Unit       # ~rápido, lógica pura, sem DB
./vendor/bin/pest --testsuite=Feature    # HTTP + DB, cobertura de controllers
./vendor/bin/pest --testsuite=Smoke      # sanity checks end-to-end
./vendor/bin/pest --testsuite=Arch       # regras arquiteturais (Pest Arch)
./vendor/bin/pest --testsuite=Critical   # fluxos críticos (financeiro, tenant)
./vendor/bin/pest --testsuite=E2E        # end-to-end browser (quando habilitado)

# 4. Suite completa — SÓ no final, como gate antes do commit
./vendor/bin/pest --parallel --processes=16 --no-coverage
```

### Comandos compostos (via composer)

| Comando | O que roda |
|---|---|
| `composer test` | Pest paralelo (Default: Unit+Feature+Smoke+Arch) |
| `composer test:ci` | Igual CI: paralelo 16 processos sem cobertura |
| `composer test:coverage` | Paralelo + cobertura min 80% |
| `composer test:dirty` | Só testes afetados por mudanças git (rápido) |
| `composer test:profile` | Paralelo com profiling |
| `composer test:critical` | Só `tests/Critical` |
| `composer test:arch` | Só `tests/Arch` |

**Regra:** o comando padrão (`vendor/bin/pest` sem `--testsuite`) usa o testsuite `Default` que agrupa Unit+Feature+Smoke+Arch. Esse é o comando usado em CI (`.github/workflows/ci.yml`). Não alterar `defaultTestSuite` em `phpunit.xml` sem atualizar CI e composer scripts em cascata.

## Arquitetura

- **DB**: SQLite in-memory (schema dump em `database/schema/sqlite-schema.sql`)
- **Parallel**: 16 processos via ParaTest
- **Trait**: `LazilyRefreshDatabase` carrega schema dump (NÃO roda 376 migrations)
- **Roles**: Seed automático no `TestCase::seedRolesIfNeeded()`

## Base Classes

| Classe | Uso | DB |
|--------|-----|-----|
| `TestCase` | Feature tests que precisam de DB | Sim (LazilyRefreshDatabase) |
| `UnitTestCase` | Unit tests que NAO tocam DB (boota framework Laravel normalmente — apenas sem `LazilyRefreshDatabase`) | Nao |

## Padrão de setUp

```php
protected function setUp(): void
{
    parent::setUp();
    Gate::before(fn () => true);  // bypass permissões
    $this->withoutMiddleware([EnsureTenantScope::class, CheckPermission::class]);

    $this->tenant = Tenant::factory()->create();
    $this->user = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'current_tenant_id' => $this->tenant->id,
        'is_active' => true,
    ]);

    app()->instance('current_tenant_id', $this->tenant->id);
    Sanctum::actingAs($this->user, ['*']);
}
```

## Regra #1: Verificar e Criar Testes

**Ao tocar em QUALQUER funcionalidade:**
1. Verificar se existe teste dedicado (procurar em `Feature/` e `Unit/`)
2. Se NÃO existe → **CRIAR** antes de finalizar a tarefa
3. Se existe → **RODAR** para garantir que não quebrou
4. Funcionalidade sem teste = tarefa INCOMPLETA

## Regras Gerais

- NUNCA mascarar testes (skip, assertTrue(true), relaxar assertions)
- Teste falhou = bug no SISTEMA, corrigir o código de produção
- Todo model com `BelongsToTenant` retorna 404 (não 403) para outros tenants
- Sempre incluir `tenant_id` no factory create
- Schema dump deve ser regenerado ao criar novas migrations: `php generate_sqlite_schema.php`

## Cenários MÍNIMOS por Controller (OBRIGATÓRIO)

Todo controller DEVE ter no mínimo **8 testes** cobrindo estes 5 cenários:

1. **Sucesso CRUD** — index (200 + count), store (201 + DB), show (200 + structure), update (200 + DB), destroy (200/204)
2. **Validação 422** — campos obrigatórios ausentes, dados inválidos
3. **Cross-Tenant 404** — recurso de outro tenant retorna 404 (NÃO 403)
4. **Permissão 403** — acesso sem permissão adequada (quando aplicável)
5. **Edge cases** — paginação, eager loading, limites

### Template: Teste Cross-Tenant (OBRIGATÓRIO em todo controller)

```php
it('cannot access resources from another tenant', function () {
    $otherTenant = Tenant::factory()->create();
    $otherResource = Resource::factory()->create(['tenant_id' => $otherTenant->id]);

    // BelongsToTenant global scope retorna 404, NÃO 403
    $this->getJson("/api/v1/{resource}/{$otherResource->id}")
        ->assertNotFound();
});

it('only lists resources from own tenant', function () {
    $otherTenant = Tenant::factory()->create();
    Resource::factory()->count(3)->create(['tenant_id' => $otherTenant->id]);
    Resource::factory()->count(2)->create(['tenant_id' => $this->tenant->id]);

    $response = $this->getJson("/api/v1/{resource}");
    $response->assertOk();
    expect($response->json('data'))->toHaveCount(2);
});
```

### Template: Teste de Validação 422

```php
it('fails validation when required fields are missing', function () {
    $response = $this->postJson("/api/v1/{resource}", []);
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['field1', 'field2']);
});
```

### Template: Teste de Estrutura de Resposta

```php
it('returns correct json structure', function () {
    Resource::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);
    $response = $this->getJson('/api/v1/{resource}');
    $response->assertOk()
        ->assertJsonStructure(['data' => [['id', 'name', 'created_at']]]);
});
```

> **Se um controller tem menos de 8 testes, provavelmente está incompleto.** Ver `.agent/rules/test-policy.md` para lista completa.

## Guia Completo

Ver `TESTING_GUIDE.md` na raiz do backend.
