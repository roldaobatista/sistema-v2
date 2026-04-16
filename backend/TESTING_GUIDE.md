# Guia de Testes — KALIBRIUM ERP

> **Atualizado**: 2026-04-13 | **9.629 testes** | **27.448 assertions** | **0 falhas** | **~4min49s local**

---

## Arquitetura de Testes

| Componente | Tecnologia | Versão |
|------------|-----------|--------|
| **Framework** | Pest (sobre PHPUnit) | Pest 3.8 / PHPUnit 11.5 |
| **Parallel** | ParaTest (via Pest --parallel) | 7.8.5 |
| **DB de Testes** | SQLite in-memory | — |
| **Schema** | Dump pré-gerado (`database/schema/sqlite-schema.sql`) | 466 tabelas |
| **Trait** | `LazilyRefreshDatabase` (carrega schema dump, não roda migrations) | — |
| **Processos** | 16 paralelos (1 por CPU lógica) | — |
| **Tempo observado** | ~4min49s nesta auditoria local (varia por maquina) | — |

## Como Rodar

```bash
# Suite completa (recomendado — parallel, sem coverage)
cd backend
./vendor/bin/pest --parallel --processes=16 --no-coverage

# Via composer (equivalente)
composer test-fast

# Com coverage
composer test-coverage

# Apenas Unit tests
./vendor/bin/pest --testsuite=Unit --no-coverage

# Apenas Feature tests
./vendor/bin/pest --testsuite=Feature --no-coverage

# Filtrar por arquivo
./vendor/bin/pest tests/Feature/FinanceTest.php --no-coverage

# Filtrar por nome do teste
./vendor/bin/pest --filter='create_account_payable' --no-coverage

# Apenas testes modificados (dev diário)
./vendor/bin/pest --dirty --parallel --no-coverage

# Profile (ver testes mais lentos)
./vendor/bin/pest --parallel --profile --no-coverage
```

## Schema Dump SQLite

O schema dump é o segredo da velocidade. Em vez de rodar 376 migrations por processo, o `LazilyRefreshDatabase` carrega o dump em ~100ms.

### Quando regenerar o schema dump

Regenerar SEMPRE que:
- Criar uma nova migration
- Alterar uma migration existente
- Adicionar nova tabela

```bash
# Regenerar (requer MySQL rodando no Docker)
php generate_sqlite_schema.php
```

O script:
1. Conecta ao MySQL `kalibrium_testing` (porta 3307)
2. Extrai DDL de todas as 466 tabelas
3. Converte MySQL → SQLite (tipos, indexes, constraints)
4. Preserva UNIQUE indexes
5. Inclui registros de migrations
6. Verifica carregamento em SQLite in-memory
7. Salva em `database/schema/sqlite-schema.sql`

### Pré-requisitos

- MySQL rodando em Docker: `docker start sistema_mysql`
- Database `kalibrium_testing` migrado: `php artisan migrate --env=testing --force`
- PHP com extensão pdo_sqlite

## Configuração

### Arquivos de Configuração

| Arquivo | Propósito |
|---------|-----------|
| `phpunit.xml` | Suites, env vars, source directories |
| `.env.testing` | Variáveis de ambiente para testes |
| `tests/TestCase.php` | Base class com `LazilyRefreshDatabase` + seed de roles |
| `tests/UnitTestCase.php` | Base class LEVE para unit tests sem DB |
| `tests/bootstrap.php` | Bootstrap com filtro de warnings |
| `database/schema/sqlite-schema.sql` | Schema dump (292KB, 466 tabelas) |
| `generate_sqlite_schema.php` | Script de geração do schema dump |

### Variáveis de Ambiente (testes)

```env
DB_CONNECTION=sqlite
DB_DATABASE=:memory:
CACHE_STORE=array
MAIL_MAILER=array
QUEUE_CONNECTION=sync
SESSION_DRIVER=array
BCRYPT_ROUNDS=4
PULSE_ENABLED=false
TELESCOPE_ENABLED=false
OTEL_SDK_DISABLED=true
```

## Padrão de Teste (Template)

### Feature Test (com banco de dados)

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

        // Bypass de permissões para testar funcionalidade pura
        Gate::before(fn () => true);
        $this->withoutMiddleware([
            \App\Http\Middleware\EnsureTenantScope::class,
            \App\Http\Middleware\CheckPermission::class,
        ]);

        // Setup tenant + user
        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        // Contexto de tenant
        app()->instance('current_tenant_id', $this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    public function test_index_returns_paginated_data(): void
    {
        MeuModel::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);

        $this->getJson('/api/v1/meu-recurso')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta']);
    }

    public function test_store_creates_record(): void
    {
        $payload = ['name' => 'Teste', 'valor' => 100];

        $this->postJson('/api/v1/meu-recurso', $payload)
            ->assertCreated()
            ->assertJsonPath('data.name', 'Teste');

        $this->assertDatabaseHas('meu_recurso', [
            'tenant_id' => $this->tenant->id,
            'name' => 'Teste',
        ]);
    }

    public function test_store_validates_required_fields(): void
    {
        $this->postJson('/api/v1/meu-recurso', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_tenant_isolation(): void
    {
        $otherTenant = Tenant::factory()->create();
        MeuModel::factory()->create(['tenant_id' => $otherTenant->id]);

        $response = $this->getJson('/api/v1/meu-recurso');
        $ids = collect($response->json('data'))->pluck('id')->toArray();

        // BelongsToTenant scope garante isolamento
        $this->assertEmpty($ids);
    }
}
```

### Unit Test (sem banco de dados)

```php
<?php

namespace Tests\Unit\Services;

use Tests\UnitTestCase;

class MeuServiceTest extends UnitTestCase
{
    public function test_calculo_retorna_valor_correto(): void
    {
        $service = new \App\Services\MeuService();
        $resultado = $service->calcular(100, 0.1);

        $this->assertEquals(110, $resultado);
    }
}
```

## Regra Obrigatória: Verificar e Criar Testes

**Ao tocar em QUALQUER funcionalidade (criar, alterar, corrigir):**

1. **VERIFICAR** se existem testes dedicados para essa funcionalidade
   - Procurar em `tests/Feature/` e `tests/Unit/` por arquivo correspondente ao controller/service
   - Ex: `ProductController` → procurar `ProductTest.php`, `ProductControllerTest.php`, `ProductFieldsCompletenessTest.php`
2. **Se NÃO existir teste** → CRIAR teste profissional ANTES de considerar a tarefa completa
3. **Se existir teste** → RODAR para garantir que suas mudanças não quebram nada
4. **Cobertura mínima por funcionalidade**:
   - Caso de sucesso (200/201)
   - Validação de campos obrigatórios (422)
   - Tenant isolation (dados de outro tenant = 404)
   - Edge cases relevantes
5. **Funcionalidade sem teste = tarefa INCOMPLETA**

## Regras Invioláveis

### O que FAZER

- Testar endpoints reais com `getJson`, `postJson`, `putJson`, `deleteJson`
- Usar `assertDatabaseHas` / `assertDatabaseMissing` para verificar persistência
- Usar factories com `tenant_id` explícito
- Testar validação (422) com payloads incompletos
- Testar tenant isolation (dados de outro tenant não aparecem)
- Corrigir o **código de produção** quando o teste falha (não o teste)

### O que NUNCA FAZER

- `markTestSkipped()` — se precisa de algo, implemente
- `markTestIncomplete()` — complete o teste agora
- `assertTrue(true)` — assertion fake, proibido
- Relaxar assertions para aceitar valor errado
- Remover teste que expõe bug
- Usar `catch` genérico que engole erros
- Testar endpoint inexistente sem criar a rota

### Tenant Isolation

Todo model que usa `BelongsToTenant` trait:
- Global scope filtra automaticamente por `tenant_id`
- Acesso a registro de outro tenant retorna **404** (não 403)
- Sempre incluir `tenant_id` no factory create

### Spatie Permissions

```php
// Registrar permissão antes de atribuir
\Spatie\Permission\Models\Permission::firstOrCreate(
    ['name' => 'modulo.action.view', 'guard_name' => 'web']
);
$user->givePermissionTo('modulo.action.view');

// Ou usar Gate::before para bypass total (recomendado em testes de funcionalidade)
Gate::before(fn () => true);
```

## Estrutura de Diretórios

```
tests/
├── TestCase.php              # Base com LazilyRefreshDatabase + roles seed
├── UnitTestCase.php          # Base leve sem DB
├── bootstrap.php             # Bootstrap com warning filter
├── Unit/
│   ├── Models/               # Testes de models (relationships, casts, scopes)
│   ├── Services/             # Testes de services (lógica de negócio)
│   └── ...
├── Feature/
│   ├── Api/V1/               # Testes de controllers por módulo
│   │   ├── Financial/        # Payables, Receivables, Categories
│   │   ├── Crm/              # CRM controllers
│   │   ├── Os/               # Work Orders
│   │   ├── Stock/            # Stock/Inventory
│   │   ├── Fleet/            # Fleet/Vehicles
│   │   ├── Fiscal/           # NF-e, Invoices
│   │   ├── Portal/           # Customer portal
│   │   ├── Quality/          # Quality/Calibration
│   │   └── ...
│   ├── Auth/                 # Authentication tests
│   ├── EdgeCases/            # Edge cases por domínio
│   ├── Integration/          # Testes de integração cross-module
│   ├── Jobs/                 # Queue jobs tests
│   └── Rbac/                 # Testes de permissões por role
├── Smoke/                    # Smoke tests (health check rápido)
├── Arch/                     # Architecture tests
├── Critical/                 # Testes críticos (rodar sob demanda)
└── E2E/                      # End-to-end (rodar sob demanda)
```

## MySQL de Desenvolvimento

Para testes que precisam de MySQL real (raro):

```bash
# MySQL está em Docker na porta 3307
docker start sistema_mysql

# Credenciais
# Host: 127.0.0.1
# Port: 3307
# User: sistema / Password: sistema
# Root: root / Password: root
# Database: kalibrium_testing

# Wrappers em C:\mysql-tools\ (docker exec transparente)
# mysql.cmd e mysqldump.cmd
```

## Troubleshooting

### "Table X not found" ao rodar testes
Schema dump desatualizado. Regenerar: `php generate_sqlite_schema.php`

### Testes muito lentos (>5min)
1. Verificar se `database/schema/sqlite-schema.sql` existe e tem conteúdo
2. Verificar se está usando `--parallel --processes=16`
3. Verificar se não tem processos PHP zumbis: `taskkill /F /IM php.exe`

### "SQLite does not support X"
Alguma migration usa SQL específico de MySQL (ex: `dropForeign` por nome). O schema dump bypass isso. Se o erro persistir, regenerar o schema.

### "UNIQUE constraint failed" em testes paralelos
Cada processo tem seu próprio DB in-memory — isso não deveria acontecer. Verificar se o teste não usa dados hardcoded (IDs fixos).

### Teste passa sozinho mas falha em parallel
Provavelmente depende de estado global. Verificar: static properties, singletons, cache.
