---
type: architecture_pattern
id: 06
---
# 06. Modelo de Multi-Tenancy

> **[AI_RULE]** Este documento é LEI. O vazamento de dados entre clientes (Tenants) é a falha de segurança mais catastrófica possível de ser gerada por uma IA. Siga rigidamente estas diretrizes.

## 1. Abordagem Lógica (`tenant_id`)

Adotamos o **Multi-Tenancy Lógico**. Todos os clientes coexistem no mesmo banco MySQL, isolados obrigatoriamente pela chave estrangeira `tenant_id`.

Essa abordagem foi escolhida por:

- **Custo operacional baixo**: um único banco de dados, um único deploy.
- **Simplicidade de backups e migrations**: sem necessidade de iterar bancos por tenant.
- **Escalabilidade horizontal**: Redis e read replicas atendem o crescimento sem fragmentar a infra.

## 2. Guard Rails de Negócio `[AI_RULE_CRITICAL]`

> **[AI_RULE_CRITICAL] Escopo Global Obrigatório (Global Scope)**
> Toda entidade principal do negócio que pertence a um Tenant DEVE aplicar o `TenantScope` no método `booted` do Eloquent Model. Um agendamento nunca pode esquecer isso:
>
> ```php
> protected static function booted() {
>     static::addGlobalScope(new TenantScope);
> }
> ```

> **[AI_RULE_CRITICAL] Proibição em Migrations**
> Uma Migration que cria uma tabela de recursos locados JAMAIS pode ir para produção sem a linha: `$table->foreignId('tenant_id')->constrained()->cascadeOnDelete();`

## 3. Exceções e Super Admins

- Contas de status global ("Kalibrium Admin") possuem controle próprio no middleware ignorando o `TenantScope` localmente.
- Usuários devem ter seu `tenant_id` cacheado no payload JWT ou Sessão, nunca confiado via `Request Input` (Prevenção contra Insecure Direct Object Reference).

## 4. Trait `BelongsToTenant` — Implementação de Referência

A trait `BelongsToTenant` é o mecanismo central de isolamento. Ela DEVE ser incluída em todo Model que contenha dados de tenant.

```php
namespace App\Traits;

use App\Models\Tenant;
use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToTenant
{
    protected static function bootBelongsToTenant(): void
    {
        // Aplica o scope global automaticamente em TODAS as queries
        static::addGlobalScope(new TenantScope);

        // Auto-preenche tenant_id na criação
        static::creating(function ($model) {
            if (auth()->check() && !$model->tenant_id) {
                $model->tenant_id = auth()->user()->current_tenant_id;
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
```

> **[AI_RULE_CRITICAL]** A trait faz duas coisas invioláveis:
>
> 1. **Global Scope**: toda query `SELECT` recebe `WHERE tenant_id = X` automaticamente.
> 2. **Auto-fill na criação**: todo `INSERT` recebe o `tenant_id` do usuário autenticado.

## 5. `TenantScope` — O Global Scope

```php
namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (auth()->check()) {
            $builder->where(
                $model->getTable() . '.tenant_id',
                auth()->user()->current_tenant_id
            );
        }
    }
}
```

> **[AI_RULE]** O scope usa `$model->getTable()` para evitar ambiguidade em JOINs. Nunca escrever simplesmente `->where('tenant_id', ...)` sem qualificar a tabela.

## 6. Resolução do Tenant Ativo

O tenant ativo é determinado exclusivamente por `$request->user()->current_tenant_id`.

```php
// CORRETO — fonte única de verdade
$tenantId = $request->user()->current_tenant_id;

// PROIBIDO — jamais aceitar tenant_id do request body
$tenantId = $request->input('tenant_id'); // INSECURE DIRECT OBJECT REFERENCE
```

> **[AI_RULE_CRITICAL]** O campo `current_tenant_id` no model `User` é a UNICA fonte para determinar o tenant ativo. Nunca confiar em parâmetros de URL, body, ou headers.

### Fluxo de Resolução

1. Usuário autentica via Sanctum (token ou cookie).
2. Middleware carrega `auth()->user()`.
3. `current_tenant_id` do user determina o tenant.
4. `TenantScope` aplica o filtro em todas as queries.

## 7. Troca de Tenant (Tenant Switching)

Usuários podem pertencer a múltiplos tenants (ex: consultores que atendem várias empresas). A troca é feita via endpoint dedicado:

```php
// PUT /api/v1/auth/switch-tenant
public function switchTenant(Request $request): JsonResponse
{
    $request->validate([
        'tenant_id' => 'required|exists:tenants,id',
    ]);

    $user = $request->user();

    // Verificar se o usuário TEM acesso ao tenant solicitado
    $hasAccess = $user->tenants()->where('tenant_id', $request->tenant_id)->exists();

    if (!$hasAccess) {
        abort(403, 'Acesso negado a este tenant.');
    }

    $user->update(['current_tenant_id' => $request->tenant_id]);

    // Limpar cache de permissões (Spatie)
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    return response()->json(['message' => 'Tenant alterado com sucesso.']);
}
```

## 8. Integração com Spatie Permissions (Teams)

O Spatie Laravel-Permission opera em **modo teams**, onde cada role/permission é vinculada ao tenant:

```php
// config/permission.php
'teams' => true,
'team_foreign_key' => 'tenant_id',
```

> **[AI_RULE]** Ao verificar permissões, o Spatie automaticamente filtra pelo `tenant_id` do team ativo. Isso significa que um usuário pode ser `admin` no tenant A e `viewer` no tenant B.

### Atribuição de Roles por Tenant

```php
// Atribuir role para o tenant específico
setPermissionsTeamId($tenantId);
$user->assignRole('admin');

// Verificar permissão dentro do contexto do tenant
setPermissionsTeamId($user->current_tenant_id);
$user->hasPermissionTo('work_orders.create'); // true/false
```

## 9. Garantias de Isolamento de Dados

### 9.1 Camadas de Proteção (Defense in Depth)

| Camada | Mecanismo | Falha Protegida |
|--------|-----------|-----------------|
| 1 - Eloquent | `TenantScope` global | Query sem filtro |
| 2 - Migration | `FOREIGN KEY tenant_id` | Registro órfão |
| 3 - Trait | Auto-fill `tenant_id` na criação | INSERT sem tenant |
| 4 - FormRequest | Validação de ownership | Update/Delete cross-tenant |
| 5 - Policy | Autorização no nível do model | Acesso não autorizado |

### 9.2 Validação de Ownership em Updates

```php
// Em FormRequests que recebem IDs de recursos relacionados:
'customer_id' => [
    'required',
    Rule::exists('customers', 'id')->where('tenant_id', auth()->user()->current_tenant_id),
],
```

> **[AI_RULE_CRITICAL]** Todo `Rule::exists()` que referencia uma tabela com `tenant_id` DEVE incluir o filtro de tenant. Caso contrário, um usuário poderia associar recursos de outro tenant.

## 10. Queries Raw e Query Builder

> **[AI_RULE_CRITICAL]** Ao usar `DB::table()` ou queries raw, o `TenantScope` NÃO é aplicado automaticamente (ele só funciona com Eloquent). A IA DEVE adicionar o filtro manualmente:

```php
// ERRADO — sem filtro de tenant
DB::table('work_orders')->where('status', 'open')->get();

// CORRETO — filtro explícito
DB::table('work_orders')
    ->where('tenant_id', auth()->user()->current_tenant_id)
    ->where('status', 'open')
    ->get();
```

## 11. Testando Multi-Tenancy

### 11.1 Estrutura de Testes

```php
public function test_user_cannot_see_other_tenant_work_orders(): void
{
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $userA = User::factory()->create(['current_tenant_id' => $tenantA->id]);
    $userB = User::factory()->create(['current_tenant_id' => $tenantB->id]);

    // Criar ordem no tenant A
    $this->actingAs($userA);
    $woA = WorkOrder::factory()->create(['tenant_id' => $tenantA->id]);

    // Criar ordem no tenant B
    $this->actingAs($userB);
    $woB = WorkOrder::factory()->create(['tenant_id' => $tenantB->id]);

    // User A só vê suas ordens
    $this->actingAs($userA);
    $this->assertCount(1, WorkOrder::all());
    $this->assertTrue(WorkOrder::all()->contains($woA));
    $this->assertFalse(WorkOrder::all()->contains($woB));
}
```

### 11.2 Cenários Obrigatórios de Teste

- Usuário do tenant A **não vê** dados do tenant B em listagens.
- Usuário do tenant A **não consegue** atualizar recurso do tenant B via API.
- Usuário do tenant A **não consegue** deletar recurso do tenant B via API.
- Troca de tenant atualiza o escopo corretamente.
- `Rule::exists` com filtro de tenant rejeita IDs de outros tenants.
- Queries com `withoutGlobalScope(TenantScope::class)` só são usadas em contextos admin.

## 12. Checklist para Novos Models `[AI_RULE]`

Ao criar um novo Model que pertence a um tenant, o agente IA DEVE verificar:

- [ ] Trait `BelongsToTenant` incluída no Model
- [ ] Migration contém `$table->foreignId('tenant_id')->constrained()->cascadeOnDelete()`
- [ ] Migration contém index: `$table->index('tenant_id')`
- [ ] Factory define `tenant_id` via `Tenant::factory()`
- [ ] FormRequests validam ownership com `Rule::exists(...)->where('tenant_id', ...)`
- [ ] Policy verifica `$model->tenant_id === $user->current_tenant_id`
- [ ] Testes cobrem isolamento entre tenants
- [ ] Seeders atribuem `tenant_id` corretamente
