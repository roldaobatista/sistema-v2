# Como Rodar Testes — Guia Operacional para Agentes

> **Prioridade**: Carregar ANTES de rodar qualquer teste. Seguir à risca.

> **⚙️ HARNESS ENGINEERING — Modo operacional sempre-ligado (P-1).** Este guia fornece os *comandos* (pirâmide de escalação: teste específico → grupo → testsuite → suite completa); o Harness define *como* esses comandos aparecem na resposta final. Passo 5 do fluxo Harness (**VERIFICAR**) usa estes comandos; itens 4 e 5 do formato de resposta (**testes executados** + **resultado dos testes**) carregam a saída real — sem parafrasear, sem inventar contagens. Regra **H8**: qualquer falha é bloqueante e volta o fluxo ao passo 6 (**CORRIGIR**) antes de qualquer encerramento. Fonte canônica: `.agent/rules/harness-engineering.md`.

## REGRA OBRIGATÓRIA: Verificar e Criar Testes

**Ao tocar em QUALQUER funcionalidade (criar, alterar, corrigir):**
1. VERIFICAR se existem testes dedicados para essa funcionalidade
2. Se NÃO existir teste → CRIAR teste profissional antes de considerar a tarefa completa
3. Se existir teste → RODAR para garantir que suas mudanças não quebram nada
4. Cobrir: sucesso, erro, validação (422), tenant isolation, edge cases
5. Teste deve testar de VERDADE — HTTP requests reais, assertions no banco, validação de response

## REGRA: Rotas Públicas e ProductionRouteSecurityTest

Ao criar rotas SEM `auth:sanctum` (rotas publicas/guest), OBRIGATÓRIO:

1. Adicionar a URI à lista `$publicUris` em `backend/tests/Feature/ProductionRouteSecurityTest.php`
2. Rodar: `./vendor/bin/pest tests/Feature/ProductionRouteSecurityTest.php --no-coverage`
3. O teste DEVE passar antes de considerar a tarefa completa

**Se esquecer este passo**, o teste `all v1 routes except logins require sanctum authentication` vai FALHAR na suite completa.

```php
// Em ProductionRouteSecurityTest.php, adicionar a nova rota pública:
$publicUris = [
    'api/v1/login',
    'api/v1/portal/guest/{token}',  // ← adicionar aqui
    // ...
];
```

## Comando Principal

```bash
cd backend
./vendor/bin/pest --parallel --processes=16 --no-coverage
```

**Tempo observado**: ~4min49s nesta auditoria local para 9.629 testes (varia por maquina).

## Stack de Testes

| Item | Tecnologia |
|------|-----------|
| Framework | Pest 3.8 (PHPUnit 11.5) |
| Parallel | ParaTest 7.8.5 |
| DB | SQLite in-memory |
| Schema | `database/schema/sqlite-schema.sql` (pré-gerado, 466 tabelas) |
| Trait | `LazilyRefreshDatabase` — carrega schema dump, NÃO roda migrations |
| Processos | 16 (1 por CPU lógica) |

## ANTES de Rodar Testes

1. Verificar que `database/schema/sqlite-schema.sql` existe e tem conteudo (>200KB)
2. Se criou nova migration: regenerar schema dump com `php generate_sqlite_schema.php`
3. Se MySQL Docker parou: `docker start sistema_mysql` (necessário apenas para regenerar schema)

### Por que o Schema Dump é Crítico

O sistema de testes usa **SQLite in-memory** para velocidade (9.600+ testes em ~5min no ambiente local desta auditoria). A trait `LazilyRefreshDatabase` **NÃO roda migrations** — ela carrega o schema dump (`sqlite-schema.sql`) diretamente no SQLite como cache pré-compilado das 466 tabelas.

**Consequência de schema dump desatualizado:**
- Criou migration com nova tabela/coluna → tabela/coluna NÃO existirá no SQLite dos testes
- Testes falham em massa com `SQLSTATE: table not found` ou `column not found`
- O erro **NÃO tem relação aparente** com o código alterado — parece bug fantasma
- Agente perde ciclos debugando código quando o problema é o dump stale

**Regra:** Criou/alterou migration → `php generate_sqlite_schema.php` → DEPOIS rodar testes. Sem exceção.

## Rodar Testes Específicos

```bash
# Um arquivo
./vendor/bin/pest tests/Feature/FinanceTest.php --no-coverage

# Um método
./vendor/bin/pest --filter='create_account_payable' --no-coverage

# Uma suite
./vendor/bin/pest --testsuite=Unit --no-coverage

# Apenas testes modificados (dev diário)
./vendor/bin/pest --dirty --parallel --no-coverage
```

## Padrão de Teste para Novos Testes

```php
<?php

namespace Tests\Feature\Api\V1\MeuModulo;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MeuControllerTest extends TestCase
{
    private Tenant $tenant;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Gate::before(fn () => true);
        $this->withoutMiddleware([
            \App\Http\Middleware\EnsureTenantScope::class,
            \App\Http\Middleware\CheckPermission::class,
        ]);
        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);
        app()->instance('current_tenant_id', $this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    public function test_index_returns_data(): void
    {
        $this->getJson('/api/v1/meu-recurso')
            ->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_store_validates_and_creates(): void
    {
        $this->postJson('/api/v1/meu-recurso', ['name' => 'Teste'])
            ->assertCreated();
        $this->assertDatabaseHas('meu_recurso', ['name' => 'Teste']);
    }

    public function test_store_rejects_invalid_payload(): void
    {
        $this->postJson('/api/v1/meu-recurso', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }
}
```

## Cobertura Mínima Obrigatória por Controller

Todo controller novo ou alterado DEVE ter no mínimo **8 testes** cobrindo **5 cenários**:

| # | Cenário | Assertions obrigatórias | Exemplo |
|---|---------|------------------------|---------|
| 1 | Sucesso CRUD (index/store/show/update/destroy) | `assertOk`, `assertCreated`, `assertDatabaseHas`, `assertJsonCount` | 5 testes |
| 2 | Validação 422 | `assertUnprocessable`, `assertJsonValidationErrors(['field'])` | 1+ testes |
| 3 | Cross-Tenant 404 | Criar recurso de outro tenant → `assertNotFound` | 1+ testes |
| 4 | Permissão 403 (se aplicável) | Remover Gate bypass → `assertForbidden` | 1 teste |
| 5 | Edge cases (paginação, JSON structure) | `assertJsonStructure(['data', 'meta'])` | 1+ testes |

> **Se um controller tem menos de 8 testes, provavelmente está incompleto.** Consultar templates em `backend/tests/README.md` e `.agent/rules/test-policy.md`.

### Verificação Rápida de Cobertura

```bash
# Contar testes em um arquivo
grep -c "function test_\|->test(\|it(" tests/Feature/Api/V1/MeuModulo/MeuControllerTest.php

# Verificar se tem teste cross-tenant
grep -l "otherTenant\|other_tenant\|cannot.*access.*other" tests/Feature/Api/V1/MeuModulo/

# Verificar se tem teste de validação 422
grep -l "assertJsonValidationErrors\|assertUnprocessable" tests/Feature/Api/V1/MeuModulo/
```

## Verificação de Qualidade do Controller (ANTES de rodar testes)

Antes de escrever testes, verificar que o controller segue os padrões:

```bash
# FormRequest authorize() com return true vazio (PROIBIDO)
grep -rn "return true" backend/app/Http/Requests/MeuModulo/ --include="*.php" | grep authorize

# Model::all() sem paginação (PROIBIDO)
grep -rn "::all()" backend/app/Http/Controllers/Api/V1/MeuModulo/ --include="*.php"

# Controller sem eager loading (PROIBIDO se tem relationships)
grep -L "->with(" backend/app/Http/Controllers/Api/V1/MeuModulo/ --include="*.php"
```

Se qualquer verificação falhar, corrigir o controller ANTES de escrever testes.

## Armadilhas Conhecidas

### SQLite vs MySQL
- SQLite nao suporta `dropForeign` por nome — o schema dump contorna isso
- `enum` do MySQL vira `varchar` no SQLite
- `json` do MySQL vira `text` no SQLite
- Unique indexes sao preservados no dump
- `UNSIGNED` nao existe no SQLite — o schema dump remove isso

### Parallel Testing
- NÃO usar `$this->withHeaders(['Authorization' => ''])` para testar unauthenticated — nao funciona com Sanctum::actingAs
- Cada processo tem seu DB in-memory isolado — sem conflitos
- Se um teste depende de estado global (static var), pode falhar em parallel
- Se um teste falha SOMENTE em parallel: procurar por static vars, singletons, cache global, ou dependência de ordem de execução

### Tenant Isolation
- Todo model com `BelongsToTenant` retorna **404** (não 403) para registro de outro tenant
- Sempre incluir `tenant_id` em factory()->create()
- O `current_tenant_id` deve ser setado via `app()->instance('current_tenant_id', $id)`
- Para testar cross-tenant: criar outro Tenant + recurso com outro tenant_id → assertNotFound
- **ERRO COMUM:** Esquecer de setar `app()->instance('current_tenant_id', ...)` no teste — faz o global scope retornar tudo

### Schema Dump
- Se "Table X not found": regenerar com `php generate_sqlite_schema.php`
- Se adicionou migration: SEMPRE regenerar o schema dump
- O script requer MySQL Docker rodando (`docker start sistema_mysql`)
- Se o Docker não está disponível, verificar se o schema dump é recente o suficiente para os testes

### Erros Comuns de Teste (e como resolver)

| Erro | Causa provável | Solução |
|------|---------------|---------|
| `Table X not found` | Schema dump desatualizado | `php generate_sqlite_schema.php` |
| `Column Y not found` | Migration nova sem regenerar dump | `php generate_sqlite_schema.php` |
| Teste passa isolado, falha em parallel | Estado global compartilhado | Eliminar static vars, singletons |
| `assertNotFound` falha (retorna 200) | `current_tenant_id` não setado | Adicionar `app()->instance(...)` no setUp |
| `assertCreated` falha (retorna 422) | Validação do FormRequest | Verificar regras e dados enviados |
| `assertForbidden` falha (retorna 200) | `Gate::before(fn () => true)` ativo | Remover Gate bypass para testar permissão |
| Teste retorna dados de outro tenant | Factory sem `tenant_id` explícito | Sempre passar `['tenant_id' => $this->tenant->id]` |
