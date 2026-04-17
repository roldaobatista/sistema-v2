---
name: draft-tests
description: Cria testes Pest profissionais para uma feature/bug do Kalibrium ERP seguindo padrao backend/tests/. Padrao adaptativo (feature: 8+, CRUD: 4-5, bug: regressao+afetados). 5 cenarios obrigatorios. Uso: /draft-tests "area ou feature".
argument-hint: "\"area, controller, ou descricao da feature/bug\""
---

# /draft-tests

## Uso

```
/draft-tests "InvoiceController"
/draft-tests "fix bug schedule cross-tenant"
/draft-tests "modulo NFS-e — emissao por OS"
```

## Por que existe

Testes do Kalibrium ERP devem cobrir os 5 cenarios obrigatorios (sucesso, 422, cross-tenant 404, 403, edge case) e seguir o padrao Pest do `backend/tests/`. Esta skill gera o esqueleto + assertions reais para a area indicada, evitando testes superficiais.

## Quando invocar

- Apos implementar feature nova (criar testes serios)
- Apos `/fix` (criar teste de regressao definitivo)
- Quando `/test-audit` aponta gap em area
- Antes de abrir PR (garantir cobertura adaptativa)

## Pre-condicoes

- Backend acessivel (`cd backend && ./vendor/bin/pest --version`)
- Controller/service/model alvo identificado
- Padrao de teste documentado em `backend/tests/README.md`

## Padrao adaptativo de quantidade

| Tipo de mudanca | Minimo de testes |
|---|---|
| Feature complexa (regras de negocio) | **8+ testes/controller** |
| CRUD simples | **4-5 testes** (sucesso + 422 + cross-tenant + 403) |
| Bug fix | **regressao + 2-3 afetados** |

Menos de 4 testes para CRUD = sempre insuficiente (raro caso justificado).

## 5 cenarios obrigatorios

Para cada controller/endpoint:

1. **Sucesso CRUD** — happy path, status 200/201, `assertJsonStructure`
2. **Validacao 422** — input invalido, retorna 422 + estrutura de erros
3. **Cross-tenant 404** — recurso de outro tenant retorna 404 (NUNCA 403/200)
4. **Permissao 403** — usuario sem permissao retorna 403
5. **Edge cases** — soft-delete, relacionamento vazio, paginacao limite

## Templates Pest

### Estrutura padrao (Feature test)

```php
<?php

use App\Models\Tenant;
use App\Models\User;
use App\Models\<Entidade>;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->otherTenant = Tenant::factory()->create();
    $this->user = User::factory()
        ->for($this->tenant)
        ->create(['current_tenant_id' => $this->tenant->id]);
    $this->user->givePermissionTo(['<entidade>.view', '<entidade>.create', '<entidade>.update', '<entidade>.delete']);
});

it('lists <entidades> of current tenant', function () {
    <Entidade>::factory()->count(3)->for($this->tenant)->create();
    <Entidade>::factory()->for($this->otherTenant)->create();  // nao deve aparecer

    $response = $this->actingAs($this->user)->getJson('/api/<entidades>');

    $response->assertOk()
        ->assertJsonStructure(['data' => [['id', 'name', 'created_at']], 'meta' => ['current_page', 'total']])
        ->assertJsonCount(3, 'data');
});

it('creates <entidade> attached to current tenant', function () {
    $payload = ['name' => 'Test', /* ... */];

    $response = $this->actingAs($this->user)->postJson('/api/<entidades>', $payload);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Test');

    $this->assertDatabaseHas('<entidades>', [
        'name' => 'Test',
        'tenant_id' => $this->tenant->id,
        'created_by' => $this->user->id,
    ]);
});

it('returns 422 on invalid payload', function () {
    $response = $this->actingAs($this->user)->postJson('/api/<entidades>', []);
    $response->assertStatus(422)->assertJsonValidationErrors(['name']);
});

it('returns 404 when accessing <entidade> of another tenant', function () {
    $other = <Entidade>::factory()->for($this->otherTenant)->create();

    $response = $this->actingAs($this->user)->getJson("/api/<entidades>/{$other->id}");

    $response->assertNotFound();
});

it('returns 403 when user lacks permission', function () {
    $this->user->revokePermissionTo('<entidade>.create');

    $response = $this->actingAs($this->user)->postJson('/api/<entidades>', ['name' => 'X']);

    $response->assertStatus(403);
});
```

### Bug fix (teste de regressao)

```php
it('does not leak <entidade> from another tenant via <endpoint>', function () {
    // Esta e a regressao do bug #XXX — corrigido em commit YYY.
    $leaked = <Entidade>::factory()->for($this->otherTenant)->create();

    $response = $this->actingAs($this->user)->getJson('/api/<endpoint>');

    $response->assertOk()
        ->assertJsonMissing(['id' => $leaked->id]);
});
```

## O que faz — passos

### 1. Identificar alvo

```bash
ls backend/app/Http/Controllers/<Area>/
ls backend/app/Models/ | grep -i "<entidade>"
ls backend/database/factories/ | grep -i "<entidade>"
```

Confirmar:
- Controller existe
- Model existe + factory existe
- Permissoes registradas em PermissionsSeeder

### 2. Decidir tipo + quantidade

Aplicar tabela adaptativa.

### 3. Gerar arquivo de teste

Escrever em `backend/tests/Feature/<Area>/<Entidade>ControllerTest.php` seguindo template.

### 4. Confirmar que nasce vermelho (se TDD)

Em fluxo TDD/regressao, rodar antes de implementar:

```bash
cd backend && ./vendor/bin/pest --filter="<Entidade>ControllerTest"
# saida: FAILED — esperado
```

### 5. Apos implementacao, validar verde

```bash
cd backend && ./vendor/bin/pest --filter="<Entidade>ControllerTest"
# saida: PASSED
```

### 6. Reportar no formato Harness 6+1

```
1. Resumo — N testes criados, M cenarios cobertos, area
2. Arquivos criados/alterados — path:LN
3. Motivo tecnico — por que cada teste foi adicionado
4. Comando rodado — pest --filter
5. Resultado — output (esperado: vermelho antes de fix, verde apos)
6. Riscos — gaps remanescentes (ex: edge case raro nao coberto)
```

## Regras invioláveis

- **Proibido `assertTrue(true)` ou teste sem assertion relevante.**
- **Proibido mockar o modulo sob teste** (regra anti-tautologico).
- **Cross-tenant 404 e nao-negociavel** em endpoint multi-tenant.
- **Factory deve usar `->for($tenant)`** para garantir tenant_id correto.
- **Regressao deve testar comportamento**, nao so codigo.

## Erros e recuperacao

| Cenario | Acao |
|---|---|
| Factory nao existe | Criar factory antes de gerar teste. |
| Permissao nao registrada | Adicionar em `PermissionsSeeder` ou usar `user->givePermissionTo` no setup. |
| Teste verde antes do fix (bug fix) | Suspeito — bug pode nao estar reproduzido. Reescrever teste para falhar primeiro. |
| Suite paralela falha por colisao | Verificar se factory tem `unique` em campo necessario. Estabilizar antes. |

## Handoff

- Testes criados verdes -> `/verify` final + commit
- Testes criados vermelhos (TDD) -> implementar codigo, depois `/verify`
- Cobertura adaptativa atingida -> `/test-audit` para validar qualidade
