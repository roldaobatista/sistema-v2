---
type: architecture_enforcement
id: enforcement-rules
last_updated: 2026-03-25
---
# Enforcement Rules — Mecanismos de Garantia Arquitetural

> **[AI_RULE_CRITICAL]** Este documento define os mecanismos automaticos de enforcement das regras arquiteturais do Kalibrium. Toda regra critica possui teste de arquitetura Pest, checklist de code review ou utilidade de runtime.

---

## 1. BelongsToTenant Enforcement `[AI_RULE_CRITICAL]`

### 1.1 Regra

Todo Model que possua coluna `tenant_id` na migration DEVE usar a trait `BelongsToTenant`. Essa trait aplica automaticamente um Global Scope que filtra por `tenant_id`, garantindo isolamento de dados entre tenants.

### 1.2 Teste de Arquitetura (Pest)

```php
// tests/Architecture/TenantIsolationTest.php

use Illuminate\Support\Facades\Schema;

arch('all models with tenant_id column must use BelongsToTenant trait')
    ->expect('App\\Models')
    ->toUseTraitWhen(
        \App\Traits\BelongsToTenant::class,
        function (string $className) {
            $model = new $className();
            $table = $model->getTable();

            if (!Schema::hasTable($table)) {
                return false;
            }

            return Schema::hasColumn($table, 'tenant_id');
        }
    );
```

**Teste alternativo (PHPUnit classico):**

```php
// tests/Architecture/BelongsToTenantTest.php

public function test_all_models_with_tenant_id_use_belongs_to_tenant_trait(): void
{
    $modelFiles = glob(app_path('Models/*.php'));
    $violations = [];

    foreach ($modelFiles as $file) {
        $className = 'App\\Models\\' . pathinfo($file, PATHINFO_FILENAME);

        if (!class_exists($className)) {
            continue;
        }

        $model = new $className();
        $table = $model->getTable();

        if (!Schema::hasTable($table)) {
            continue;
        }

        if (Schema::hasColumn($table, 'tenant_id')) {
            $traits = class_uses_recursive($className);
            if (!in_array(\App\Traits\BelongsToTenant::class, $traits)) {
                $violations[] = $className;
            }
        }
    }

    $this->assertEmpty(
        $violations,
        'Models with tenant_id column missing BelongsToTenant trait: ' . implode(', ', $violations)
    );
}
```

### 1.3 Modelo Mental para Agentes IA

```
ANTES de criar ou editar um Model:
1. A migration tem tenant_id? -> OBRIGATORIO usar BelongsToTenant
2. A migration NAO tem tenant_id? -> NAO usar BelongsToTenant
3. Nunca filtrar manualmente por tenant_id em queries -> o Global Scope ja faz isso
4. Para queries que precisam ignorar tenant (admin, jobs): usar withoutGlobalScope(TenantScope::class)
5. tenant_id vem SEMPRE de $request->user()->current_tenant_id
```

> **[AI_RULE_CRITICAL]** Vazamento de dados entre tenants e uma falha de seguranca CRITICA. O teste de arquitetura deve rodar em CI e bloquear merge se falhar.

---

## 2. No Direct Model Access Between Modules `[AI_RULE_CRITICAL]`

### 2.1 Regra

Controllers de um modulo NAO devem referenciar diretamente Models de outros modulos. A comunicacao entre modulos deve ocorrer via:

- Events (desacoplamento total)
- Service Contracts / Interfaces (inversao de dependencia)
- Facade de servico do modulo dono

### 2.2 Teste de Arquitetura (Pest)

```php
// tests/Architecture/ModuleBoundariesTest.php

arch('OS controllers should not reference Finance models directly')
    ->expect('App\\Http\\Controllers\\Api\\V1\\Os')
    ->not->toUse([
        'App\\Models\\AccountPayable',
        'App\\Models\\AccountReceivable',
        'App\\Models\\Invoice',
        'App\\Models\\Payment',
    ]);

arch('Finance controllers should not reference OS models directly')
    ->expect('App\\Http\\Controllers\\Api\\V1\\Financial')
    ->not->toUse([
        'App\\Models\\WorkOrder',
        'App\\Models\\Schedule',
    ]);

arch('HR controllers should not reference Fleet models directly')
    ->expect('App\\Http\\Controllers\\Api\\V1\\HR')
    ->not->toUse([
        'App\\Models\\Vehicle',
        'App\\Models\\FuelLog',
    ]);

arch('CRM controllers should not reference HR models directly')
    ->expect('App\\Http\\Controllers\\Api\\V1\\Crm')
    ->not->toUse([
        'App\\Models\\Employee',
        'App\\Models\\TimeClockEntry',
    ]);
```

### 2.3 Nota sobre Codigo Existente

> **[AI_RULE]** O codigo legado pode conter violacoes desta regra (ex: controllers que acessam models de outros modulos diretamente). Essas violacoes sao aceitas como divida tecnica. **Todo codigo NOVO deve obedecer esta regra.** Ao refatorar codigo existente, migrar para o padrao de Service Contracts.

### 2.4 Padrao Correto

```php
// ERRADO: Controller de OS acessando model de Finance diretamente
class WorkOrderController extends Controller
{
    public function invoice(WorkOrder $wo)
    {
        $invoice = Invoice::create([...]); // VIOLACAO
    }
}

// CORRETO: Controller de OS usa Service Contract
class WorkOrderController extends Controller
{
    public function invoice(WorkOrder $wo, InvoicingServiceContract $invoicing)
    {
        $invoice = $invoicing->generateFromWorkOrder($wo); // OK
    }
}
```

---

## 3. Cache Tags Tenant-Aware `[AI_RULE_CRITICAL]`

### 3.1 Regra

**NUNCA** usar `Cache::get()`, `Cache::put()`, `Cache::forget()` diretamente. Todo acesso ao cache deve ser tenant-aware, usando `CacheHelper` ou cache tags com prefixo do tenant.

### 3.2 CacheHelper Utility Class

```php
// app/Helpers/CacheHelper.php

namespace App\Helpers;

use Illuminate\Support\Facades\Cache;

class CacheHelper
{
    /**
     * Gera tag de cache com prefixo do tenant.
     * Uso: CacheHelper::tenantTag('customers') => 'tenant_5_customers'
     */
    public static function tenantTag(string $tag, ?int $tenantId = null): string
    {
        $tenantId = $tenantId ?? auth()->user()?->current_tenant_id ?? app('current_tenant_id');

        if (!$tenantId) {
            throw new \RuntimeException('Tenant ID not available for cache tag generation');
        }

        return "tenant_{$tenantId}_{$tag}";
    }

    /**
     * Operacao de cache com isolamento automatico de tenant.
     * Uso: CacheHelper::forTenant('customers_list', 3600, fn() => Customer::all())
     */
    public static function forTenant(
        string $key,
        int $ttlSeconds,
        callable $callback,
        ?int $tenantId = null
    ): mixed {
        $tenantKey = self::tenantTag($key, $tenantId);
        return Cache::remember($tenantKey, $ttlSeconds, $callback);
    }

    /**
     * Invalida cache de um tenant para uma tag especifica.
     */
    public static function forgetTenant(string $key, ?int $tenantId = null): bool
    {
        $tenantKey = self::tenantTag($key, $tenantId);
        return Cache::forget($tenantKey);
    }

    /**
     * Invalida todas as caches de um tenant (por pattern).
     * Nota: requer driver de cache que suporte tags (Redis, Memcached).
     */
    public static function flushTenant(?int $tenantId = null): bool
    {
        $tenantId = $tenantId ?? auth()->user()?->current_tenant_id;

        if (!$tenantId) {
            return false;
        }

        return Cache::tags(["tenant_{$tenantId}"])->flush();
    }
}
```

### 3.2.1 Relação entre CacheHelper::forTenant() e Cache::tags()

> **[AI_RULE]** `CacheHelper::forTenant()` é o método canônico. Internamente, ele chama `Cache::tags()` para garantir isolamento por tenant. O uso direto de `Cache::tags()` no código de aplicação é **desencorajado** — sempre use o helper para garantir consistência de prefixo e evitar colisões entre tenants. `Cache::tags()` só deve aparecer dentro da implementação do próprio `CacheHelper`.

### 3.3 Checklist de Code Review

Ao revisar PRs que envolvem cache:

- [ ] **Cache::get/put/forget usado diretamente?** -> REJEITAR. Usar `CacheHelper::forTenant()` ou `CacheHelper::tenantTag()`.
- [ ] **Cache key inclui tenant_id?** -> Verificar que usa `CacheHelper::tenantTag()` e nao string concatenada manualmente.
- [ ] **TTL definido?** -> Todo cache DEVE ter TTL explicito. Nunca `Cache::forever()` em dados de tenant.
- [ ] **Invalidacao event-driven existe?** -> Cache que armazena dados mutaveis deve ter invalidacao via Observer/Listener (ex: `TenantObserver` invalida `tenant_status_{id}`).
- [ ] **Cache de dados sensíveis?** -> Nunca cachear senhas, tokens, ou dados pessoais (LGPD).
- [ ] **Teste de isolamento?** -> Verificar que tenant A nao consegue ler cache de tenant B.

### 3.4 Excecoes Permitidas

| Cenario | Pode usar Cache:: direto? | Motivo |
|---------|--------------------------|--------|
| Cache global (sem tenant) | Sim, com prefixo `global_` | Ex: config do sistema, feature flags |
| Cache de rate limiting | Sim (Laravel throttle) | Gerenciado pelo framework |
| Cache de sessao | Sim (Laravel session) | Gerenciado pelo framework |
| Qualquer dado de tenant | **NAO** | Usar CacheHelper |

---

## 4. Service Contracts as Interfaces `[AI_RULE_CRITICAL]`

### 4.1 Regra

Todo servico que e consumido por modulos diferentes do seu modulo dono DEVE ter:

1. Uma **Interface (Contract)** em `app/Contracts/`
2. Uma **implementacao** em `app/Services/`
3. Um **binding** em `AppServiceProvider` ou ServiceProvider dedicado

### 4.2 Teste de Arquitetura (Pest)

```php
// tests/Architecture/ServiceContractsTest.php

// Nota: o matcher areUsedIn() não existe na API do Pest arch().
// Usar toBeUsedIn() ou estruturar como teste inverso (recomendado):
arch('cross-module services must implement a contract interface')
    ->expect('App\\Services\\InvoicingService')
    ->toImplement('App\\Contracts\\InvoicingServiceContract');

// Alternativa para verificar que controllers usam apenas services via contract:
arch('controllers should only use service contracts, not concrete services directly')
    ->expect('App\\Http\\Controllers')
    ->not->toUse([
        'App\\Services\\InvoicingService',
        'App\\Services\\CommissionService',
        'App\\Services\\StockService',
    ]);

// Convencao: Service classes devem ter Contract correspondente
arch('services used across modules should have matching contracts')
    ->expect('App\\Services\\InvoicingService')->toImplement('App\\Contracts\\InvoicingServiceContract')
    ->expect('App\\Services\\CommissionService')->toImplement('App\\Contracts\\CommissionServiceContract')
    ->expect('App\\Services\\StockService')->toImplement('App\\Contracts\\StockServiceContract');
```

### 4.3 Convencao de Nomes

| Camada | Padrao | Exemplo |
|--------|--------|---------|
| Contract (Interface) | `App\Contracts\{Name}Contract` | `InvoicingServiceContract` |
| Implementation | `App\Services\{Name}Service` | `InvoicingService` |
| Provider Binding | `AppServiceProvider::register()` | `$this->app->bind(InvoicingServiceContract::class, InvoicingService::class)` |

### 4.4 Padrao de Binding

```php
// app/Providers/AppServiceProvider.php

public function register(): void
{
    // Service Contracts — Cross-Module Services
    $this->app->bind(
        \App\Contracts\InvoicingServiceContract::class,
        \App\Services\InvoicingService::class
    );

    $this->app->bind(
        \App\Contracts\CommissionServiceContract::class,
        \App\Services\CommissionService::class
    );

    $this->app->bind(
        \App\Contracts\StockServiceContract::class,
        \App\Services\StockService::class
    );
}
```

### 4.5 Quando Criar um Contract

```
FLUXO DE DECISAO:
1. O servico e usado APENAS dentro do seu modulo? -> NAO precisa de Contract
2. O servico e usado por Controllers/Services de OUTROS modulos? -> PRECISA de Contract
3. O servico pode ter implementacoes alternativas (test doubles, mocks)? -> PRECISA de Contract
4. Em duvida? -> Criar Contract. O custo e baixo e o beneficio e alto.
```

---

## 5. Execucao dos Testes de Enforcement

### 5.1 Comando

```bash
# Rodar todos os testes de arquitetura
php artisan test --filter=Architecture

# Rodar teste especifico
php artisan test tests/Architecture/TenantIsolationTest.php
php artisan test tests/Architecture/ModuleBoundariesTest.php
php artisan test tests/Architecture/ServiceContractsTest.php
```

### 5.2 Integracao CI/CD

Os testes de arquitetura DEVEM ser executados no pipeline de CI e bloquear merge se falharem. Configurar em `.github/workflows/ci.yml`:

```yaml
- name: Architecture Tests
  run: php artisan test --filter=Architecture --stop-on-failure
```

---

> **[AI_RULE_CRITICAL]** Nenhuma das 4 regras acima pode ser ignorada ou contornada. Se um teste de arquitetura falhar, o problema esta no codigo, NAO no teste.
