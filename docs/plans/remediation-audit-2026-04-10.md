# Plano de Remediação — Auditoria Adversarial 2026-04-10

> **Para agentes:** Use `superpowers:subagent-driven-development` ou `superpowers:executing-plans` para implementar. Cada step usa checkbox (`- [ ]`) para tracking. **NUNCA avance Etapa N+1 sem 100% de Etapa N com Gate Final verde** (Iron Protocol Lei 7).

**Goal:** Remediar 13 achados P0 + 54 P1 + 245 P2 da auditoria 2026-04-10, sem regressão de comportamento e com cobertura de testes obrigatória.

**Architecture:** Remediação em 4 fases sequenciais — (1) Segurança P0 crítica, (2) Consistência Models/Schema, (3) Performance/qualidade P1, (4) Cobertura de testes P2. Toda mudança de código é precedida por teste falhando (TDD).

**Tech Stack:** Laravel 13, Pest/PHPUnit, MySQL 8 (SQLite in-memory para testes), React 19 + TS.

**Fontes da auditoria:**
- `docs/audits/audit-controllers-2026-04-10.md`
- `docs/audits/audit-models-schema-2026-04-10.md`
- `docs/audits/audit-security-2026-04-10.md`
- `docs/audits/audit-frontend-2026-04-10.md`
- `docs/audits/audit-tests-quality-2026-04-10.md`

**Comandos base (Runner):**
- Testes escopados: `cd backend && ./vendor/bin/pest tests/<path> --no-coverage`
- Suite completa (Gate Final apenas): `cd backend && ./vendor/bin/pest --parallel --processes=16 --no-coverage`
- Regenerar schema dump após migration: `cd backend && php generate_sqlite_schema.php`
- Frontend types: `cd frontend && npm run typecheck`
- Lint: `cd backend && ./vendor/bin/pint --test` e `cd frontend && npm run lint`

**Regra de Completude (Iron Protocol Lei 1 + 2):** Toda task = código + teste + Gate Final verde + evidência. Teste mascarado = violação P0.

---

## FASE 1 — SEGURANÇA P0 (BLOCKER)

Prioridade máxima. Vazamento multi-tenant, SQL injection, authorization bypass. **Nenhum deploy antes desta fase fechar.**

---

### Task 1: Adicionar `BelongsToTenant` em `User.php`

**Contexto:** Model com `tenant_id` fillable mas sem trait → queries de User ignoram scope de tenant. Risco: enumeração cross-tenant, RBAC bypass.

**Files:**
- Modify: `backend/app/Models/User.php`
- Test: `backend/tests/Feature/Models/UserTenantScopeTest.php` (criar)

- [ ] **Step 1.1: Teste cross-tenant falhando**

```php
<?php
// backend/tests/Feature/Models/UserTenantScopeTest.php
use App\Models\Tenant;
use App\Models\User;

it('prevents cross-tenant user enumeration via global scope', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $userA = User::factory()->create(['tenant_id' => $tenantA->id]);
    $userB = User::factory()->create(['tenant_id' => $tenantB->id]);

    $this->actingAs($userA);
    $userA->current_tenant_id = $tenantA->id;
    $userA->save();

    expect(User::find($userB->id))->toBeNull();
    expect(User::count())->toBe(1);
});
```

- [ ] **Step 1.2: Rodar teste (deve FALHAR)**

Run: `cd backend && ./vendor/bin/pest tests/Feature/Models/UserTenantScopeTest.php`
Esperado: FAIL — `User::find($userB->id)` retorna o user B.

- [ ] **Step 1.3: Aplicar trait `BelongsToTenant` em `User.php`**

Adicionar `use App\Concerns\BelongsToTenant;` no topo e `use BelongsToTenant;` no corpo da classe. Revisar se o trait tem bypass para rotas de login/logout (senão auth quebra).

- [ ] **Step 1.4: Rodar teste (deve PASSAR)**

Run: `cd backend && ./vendor/bin/pest tests/Feature/Models/UserTenantScopeTest.php`

- [ ] **Step 1.5: Rodar grupo auth e iam para detectar regressão**

Run: `cd backend && ./vendor/bin/pest tests/Feature/Auth tests/Feature/Iam --parallel`
Esperado: ALL PASS. Se falhar login/logout → rota de login precisa `withoutGlobalScope(BelongsToTenantScope::class)`.

- [ ] **Step 1.6: Commit**

```bash
git add backend/app/Models/User.php backend/tests/Feature/Models/UserTenantScopeTest.php
git commit -m "fix(security): enforce BelongsToTenant on User model (P0 audit-2026-04-10)"
```

---

### Task 2: Adicionar `BelongsToTenant` em `Role.php`

**Contexto:** Role estende SpatieRole, tem `tenant_id` mas sem trait → roles vazam entre tenants.

**Files:**
- Modify: `backend/app/Models/Role.php`
- Test: `backend/tests/Feature/Models/RoleTenantScopeTest.php` (criar)

- [ ] **Step 2.1: Teste cross-tenant**

```php
it('scopes roles by tenant automatically', function () {
    $tA = Tenant::factory()->create();
    $tB = Tenant::factory()->create();
    Role::create(['name' => 'admin', 'tenant_id' => $tA->id, 'guard_name' => 'web']);
    Role::create(['name' => 'admin', 'tenant_id' => $tB->id, 'guard_name' => 'web']);

    $userA = User::factory()->create(['tenant_id' => $tA->id, 'current_tenant_id' => $tA->id]);
    $this->actingAs($userA);

    expect(Role::count())->toBe(1);
});
```

- [ ] **Step 2.2: Rodar teste (FAIL)**
- [ ] **Step 2.3: Adicionar `use BelongsToTenant;` em `Role.php`**
- [ ] **Step 2.4: Rodar teste (PASS) + tests/Feature/Iam/Roles — sem regressão**
- [ ] **Step 2.5: Commit**

```bash
git commit -m "fix(security): enforce BelongsToTenant on Role model (P0 audit-2026-04-10)"
```

---

### Task 3: SQL Injection em `AnalyticsController.php` (linhas 247, 262, 273, 285)

**Contexto:** `DB::raw("{$monthCreatedExpr} as month")` e `DB::raw("SUM(CASE WHEN status = '".Constant."' ...")` — padrão perigoso. Ainda que hoje use constantes, abre CWE-89 ao refatorar.

**Files:**
- Modify: `backend/app/Http/Controllers/Api/V1/Analytics/AnalyticsController.php:247-290`
- Test: `backend/tests/Feature/Analytics/AnalyticsSqlInjectionTest.php`

- [ ] **Step 3.1: Ler o método inteiro** (`Read` AnalyticsController 200-320) e mapear todas as constantes usadas.

- [ ] **Step 3.2: Teste de regressão de output**

Criar teste que chama o endpoint `/api/v1/analytics/...` e valida o JSON contra fixture congelada (snapshot do comportamento atual). Se o refactor mudar o resultado, o teste pega.

- [ ] **Step 3.3: Refatorar**

Substituir `DB::raw("SUM(CASE WHEN status = '".X."'"` por:
```php
DB::raw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as completed')
```
com binding. Para `{$monthCreatedExpr}`, mover para método que retorna `Expression` já validada contra whitelist de dialects.

- [ ] **Step 3.4: Rodar testes de analytics**

Run: `cd backend && ./vendor/bin/pest tests/Feature/Analytics --parallel`

- [ ] **Step 3.5: Commit**

```bash
git commit -m "fix(security): remove SQL interpolation in AnalyticsController (CWE-89, P0)"
```

---

### Task 4: `StoreSaasSubscriptionRequest` — `exists` sem tenant scope

**Files:**
- Modify: `backend/app/Http/Requests/Billing/StoreSaasSubscriptionRequest.php:48`
- Test: `backend/tests/Feature/Billing/SaasSubscriptionCrossTenantTest.php`

- [ ] **Step 4.1: Teste cross-tenant**

```php
it('rejects subscription to plan from another tenant', function () {
    $tA = Tenant::factory()->create();
    $tB = Tenant::factory()->create();
    $planB = SaasPlan::factory()->create(['tenant_id' => $tB->id]);
    $userA = User::factory()->create(['tenant_id' => $tA->id, 'current_tenant_id' => $tA->id]);

    $this->actingAs($userA)
        ->postJson('/api/v1/billing/saas-subscriptions', ['plan_id' => $planB->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['plan_id']);
});
```

- [ ] **Step 4.2: FAIL esperado** (hoje aceita)

- [ ] **Step 4.3: Fix**

```php
'plan_id' => [
    'required',
    Rule::exists('saas_plans', 'id')
        ->where('tenant_id', $this->user()->current_tenant_id),
],
```

- [ ] **Step 4.4: PASS** — `cd backend && ./vendor/bin/pest tests/Feature/Billing`

- [ ] **Step 4.5: Commit**

```bash
git commit -m "fix(security): scope saas_plans exists rule by tenant (P0)"
```

---

### Task 5: `ListAuditLogRequest` — user enumeration

**Files:**
- Modify: `backend/app/Http/Requests/Iam/ListAuditLogRequest.php:22`
- Test: `backend/tests/Feature/Iam/AuditLogCrossTenantTest.php`

- [ ] **Step 5.1: Teste cross-tenant**
- [ ] **Step 5.2: FAIL**
- [ ] **Step 5.3: Fix** — `Rule::exists('users','id')->where('tenant_id', $this->user()->current_tenant_id)`
- [ ] **Step 5.4: PASS**
- [ ] **Step 5.5: Commit**

---

### Task 6: `StoreRoleRequest` — permission escalation

**Files:**
- Modify: `backend/app/Http/Requests/Iam/StoreRoleRequest.php:15`
- Test: `backend/tests/Feature/Iam/RoleCrossTenantPermissionsTest.php`

- [ ] **Step 6.1: Teste — criar role com permission_id de outro tenant → 422**
- [ ] **Step 6.2: FAIL**
- [ ] **Step 6.3: Fix** — validar permissions.* com closure que checa `tenant_id` ou fazer `whereIn('id', ...)->where('tenant_id', ...)`
- [ ] **Step 6.4: PASS**
- [ ] **Step 6.5: Commit**

---

### Task 7: `StoreMaintenanceReportRequest` — cross-tenant record association

**Files:**
- Modify: `backend/app/Http/Requests/MaintenanceReport/StoreMaintenanceReportRequest.php:18-19`
- Test: `backend/tests/Feature/MaintenanceReport/MaintenanceReportCrossTenantTest.php`

- [ ] **Step 7.1: Teste cross-tenant para `work_order_id` E `equipment_id`** (2 cases)
- [ ] **Step 7.2: FAIL**
- [ ] **Step 7.3: Fix** — ambos com `Rule::exists(...)->where('tenant_id', ...)`
- [ ] **Step 7.4: PASS**
- [ ] **Step 7.5: Commit**

---

### Task 8: `BulkStatusTenantRequest` — account takeover bulk

**Contexto:** Admin bulk-muta status de tenants que não lhe pertencem.

**Files:**
- Modify: `backend/app/Http/Requests/Tenant/BulkStatusTenantRequest.php:12`
- Test: `backend/tests/Feature/Tenant/BulkStatusTenantOwnershipTest.php`

- [ ] **Step 8.1: Teste**

```php
it('rejects bulk status change when any id belongs to another owner', function () {
    $owner = User::factory()->create();
    $myTenant = Tenant::factory()->create(['owner_id' => $owner->id]);
    $otherTenant = Tenant::factory()->create();

    $this->actingAs($owner)
        ->patchJson('/api/v1/tenants/bulk-status', [
            'ids' => [$myTenant->id, $otherTenant->id],
            'status' => 'suspended',
        ])
        ->assertStatus(403);
});
```

- [ ] **Step 8.2: FAIL**
- [ ] **Step 8.3: Fix em `authorize()`** — validar ownership de TODOS os ids
- [ ] **Step 8.4: PASS**
- [ ] **Step 8.5: Commit**

---

### Task 9: Mass Assignment — 6 models com `$guarded = ['id']`

**Contexto:** AgendaItem, Email, EmailAccount, EmailRule + 2 outros. Atacante POSTa `tenant_id: 999` e escapa. CWE-915.

**Files:**
- Modify: `backend/app/Models/AgendaItem.php`
- Modify: `backend/app/Models/Email.php`
- Modify: `backend/app/Models/EmailAccount.php`
- Modify: `backend/app/Models/EmailRule.php`
- Modify: (identificar os outros 2 via `grep "\$guarded = \['id'\]" backend/app/Models/`)
- Test: `backend/tests/Feature/Security/MassAssignmentTenantHijackTest.php`

- [ ] **Step 9.1: Grep para confirmar os 6 arquivos exatos**

Run: `grep -rl "\$guarded = \['id'\]" backend/app/Models/`

- [ ] **Step 9.2: Teste para CADA model**

```php
it('prevents tenant_id mass assignment on AgendaItem', function () {
    $tA = Tenant::factory()->create();
    $tB = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tA->id, 'current_tenant_id' => $tA->id]);
    $this->actingAs($user);

    $item = AgendaItem::create([
        'title' => 'test',
        'tenant_id' => $tB->id,  // tentativa hijack
    ]);

    expect($item->tenant_id)->toBe($tA->id);
});
```

- [ ] **Step 9.3: FAIL**
- [ ] **Step 9.4: Substituir `$guarded` por `$fillable` explícito** SEM `tenant_id`. O trait `BelongsToTenant` já atribui tenant_id no `creating` event.
- [ ] **Step 9.5: PASS** + rodar testes do domínio de cada model
- [ ] **Step 9.6: Commit**

```bash
git commit -m "fix(security): replace \$guarded=['id'] with explicit \$fillable (6 models, CWE-915)"
```

---

### Task 10: `syncEngine.ts` — retry infinito em 422

**Files:**
- Modify: `frontend/src/lib/offline/syncEngine.ts:44`
- Test: `frontend/src/lib/offline/__tests__/syncEngine.test.ts`

- [ ] **Step 10.1: Teste**

```ts
it('marks request as failed on 422 (does not retry)', async () => {
  const request = makeRequest({ status: 'pending' });
  mockAxios.onPost().reply(422, { errors: { name: ['required'] } });

  await processRequest(request);

  expect(request.status).toBe('failed');
  expect(request.failureReason).toEqual({ name: ['required'] });
});
```

- [ ] **Step 10.2: FAIL**
- [ ] **Step 10.3: Fix**

```ts
const status = error.response?.status
const isRetryable = !status || (status >= 500 || status === 408 || status === 429)
if (!isRetryable) {
  await markRequestAsFailed(request.id, error.response?.data)
  return
}
```

- [ ] **Step 10.4: PASS** — `cd frontend && npm run test -- syncEngine`
- [ ] **Step 10.5: Commit**

---

### Task 11: `IntegrationControllerTest.php:107` — remover `markTestSkipped`

**Contexto:** Teste pulado por "webhooks table not available". Iron Protocol Lei 2: testes são sagrados. Solução: criar migration + schema dump para incluir `webhooks` em SQLite.

**Files:**
- Modify: `backend/tests/Feature/Integration/IntegrationControllerTest.php:107`
- Modify: `backend/database/schema/sqlite-schema.sql` (regenerar)
- Potentially: criar migration se tabela não existe em nenhum lugar

- [ ] **Step 11.1: Ler o teste e ver o que ele valida**
- [ ] **Step 11.2: Verificar se `webhooks` existe em alguma migration** — `grep -r "Schema::create('webhooks'"`
- [ ] **Step 11.3: Se não existir, criar migration** (definir schema mínimo que o teste precisa)
- [ ] **Step 11.4: Regenerar schema dump** — `php generate_sqlite_schema.php`
- [ ] **Step 11.5: Remover `markTestSkipped` e rodar o teste real**
- [ ] **Step 11.6: PASS**
- [ ] **Step 11.7: Commit**

---

### Task 12: `ExpiredStandardBlocksTest.php` — remover `assertTrue(true)` (2x)

**Contexto:** 2 assertions vazias. Test inútil. Reescrever para validar comportamento real.

**Files:**
- Modify: `backend/tests/.../ExpiredStandardBlocksTest.php`

- [ ] **Step 12.1: Ler o arquivo inteiro** e mapear o que cada teste DEVERIA validar (pelo nome/contexto)
- [ ] **Step 12.2: Reescrever cada `assertTrue(true)`** com asserts reais sobre o comportamento esperado (ex: `assertEquals`, `assertDatabaseHas`, `assertJson`)
- [ ] **Step 12.3: Rodar o teste**
- [ ] **Step 12.4: Commit**

---

### Task 13: Gate Final da FASE 1

- [ ] **Step 13.1: Suite completa**

Run: `cd backend && ./vendor/bin/pest --parallel --processes=16 --no-coverage`
Esperado: 100% verde.

- [ ] **Step 13.2: Typecheck + lint backend**

Run: `cd backend && ./vendor/bin/pint --test && ./vendor/bin/phpstan analyse` (se configurado)

- [ ] **Step 13.3: Typecheck + lint frontend**

Run: `cd frontend && npm run typecheck && npm run lint`

- [ ] **Step 13.4: Evidência em `docs/audits/remediation-fase1-evidence.md`** — colar saídas dos comandos.

- [ ] **Step 13.5: Commit de consolidação**

```bash
git commit --allow-empty -m "chore(audit): FASE 1 P0 remediation complete — gate final green"
```

**NÃO AVANCE PARA FASE 2 SEM FASE 1 100% VERDE.**

---

## FASE 2 — CONSISTÊNCIA MODELS/SCHEMA (P0/P1)

---

### Task 14: Corrigir 44 models com `SoftDeletes` sem `deleted_at`

**Contexto:** 44 models declaram `SoftDeletes` mas schema não tem a coluna. Qualquer `->delete()` quebra silenciosamente ou restore é impossível.

**Files:**
- Create: `backend/database/migrations/2026_04_10_600000_add_deleted_at_to_missing_soft_delete_tables.php`
- Modify: `backend/database/schema/sqlite-schema.sql` (regenerar)
- Modify: `backend/app/Models/Fleet.php` (DECIDIR: adicionar trait OU remover deleted_at)

- [ ] **Step 14.1: Listar os 44 models exatos**

Run: `grep -rl "use SoftDeletes" backend/app/Models/ | xargs grep -L "softDeletes"` — cruzar com migrations.

Script auxiliar em `scripts/audit/soft-delete-mismatch.php` que:
1. Lista todos os models que usam `SoftDeletes`
2. Para cada, localiza a tabela (`$table` ou snake do nome)
3. Verifica se a coluna `deleted_at` existe no schema dump
4. Imprime os mismatches

- [ ] **Step 14.2: Decidir para cada model** — adicionar coluna OU remover trait. Default: **adicionar coluna** (preserva comportamento declarado). Exceção: Fleet (decidir caso especial).

- [ ] **Step 14.3: Criar uma migration com todas as colunas faltantes**

```php
public function up(): void {
    foreach ([
        'accounts_payable',
        'accounts_payable_categories',
        'accounts_receivable',
        'central_items',
        'batches',
        'crm_territories',
        'expense_categories',
        // ... completar com os 44
    ] as $table) {
        if (Schema::hasTable($table) && !Schema::hasColumn($table, 'deleted_at')) {
            Schema::table($table, fn(Blueprint $t) => $t->softDeletes());
        }
    }
}
```

- [ ] **Step 14.4: Rodar migration em DB de dev**

Run: `cd backend && php artisan migrate`

- [ ] **Step 14.5: Regenerar schema dump**

Run: `cd backend && php generate_sqlite_schema.php`

- [ ] **Step 14.6: Rodar suite escopada aos domínios afetados** — financials, agenda, crm, warehouse

Run: `cd backend && ./vendor/bin/pest tests/Feature/Financials tests/Feature/Agenda tests/Feature/Crm tests/Feature/Warehouse --parallel`

- [ ] **Step 14.7: Decidir Fleet** — se o schema já tem `deleted_at`, adicionar `use SoftDeletes;` no model. Senão, remover a coluna (reverter).

- [ ] **Step 14.8: Commit**

```bash
git commit -m "fix(schema): add deleted_at to 44 soft-delete tables (P1 audit-2026-04-10)"
```

---

### Task 15: Consolidar uso de `current_tenant_id` via trait

**Contexto:** 7 models usam propriedade `current_tenant_id` manualmente em vez de depender do global scope. Quebra o princípio DRY e é fonte de bugs.

**Files:**
- Modify: AgendaItem, AuditLog, Equipment + 4 outros (identificar via grep)

- [ ] **Step 15.1: Grep para listar** — `grep -rn "current_tenant_id" backend/app/Models/`
- [ ] **Step 15.2: Para cada caso:** verificar se o trait já cobre. Se sim, remover código duplicado. Se não, adicionar ao trait.
- [ ] **Step 15.3: Rodar testes dos domínios afetados**
- [ ] **Step 15.4: Commit**

---

### Task 16: Gate Final FASE 2

- [ ] Suite completa verde
- [ ] Schema dump up-to-date
- [ ] Evidência em `docs/audits/remediation-fase2-evidence.md`
- [ ] Commit de consolidação

---

## FASE 3 — PERFORMANCE & QUALIDADE P1

---

### Task 17: Paginar 15+ endpoints index com `->get()` sem limit

**Contexto:** DoS/memory exhaustion. Principal: `AccountingReportController.php:49`. Precisa de varredura sistemática.

**Files:**
- Audit script first, then fix by module

- [ ] **Step 17.1: Grep sistemático**

Run: `grep -rn "::all()\|->get();" backend/app/Http/Controllers/Api/ | grep -v "->with\|->where.*->first\|->limit"`

Listar controllers que retornam lista sem paginação.

- [ ] **Step 17.2: Para cada controller (batch por módulo):**
  - Criar teste `it('paginates index response', function () { ... assertJsonStructure(['data','meta','links']) })`
  - Substituir `->get()` por `->paginate($request->integer('per_page', 15))`
  - Atualizar frontend correspondente para consumir paginado (se aplicável)

- [ ] **Step 17.3: Guardrail de escopo (Iron Protocol)** — se >5 arquivos, PARAR, consolidar relatório, reportar.

- [ ] **Step 17.4: Commits por módulo** (financials, agenda, crm, warehouse, iam, etc.)

---

### Task 18: Eager loading em 20+ endpoints com N+1

**Contexto:** `AgendaController.php:316, 446, 490, 556, 578` e outros. Performance P1.

- [ ] **Step 18.1: Ativar proteção `Model::preventLazyLoading()` em env `testing`** — isso faz o teste falhar em N+1 e mostra exatamente onde.

- [ ] **Step 18.2: Rodar suite** — coletar todos os pontos de lazy loading que estouram.

- [ ] **Step 18.3: Para cada ponto, adicionar `->with([...])`** na query base do controller.

- [ ] **Step 18.4: Commits por módulo.**

---

### Task 19: `ExportCsvRequest` — `return true` incondicional (P1)

**Files:** `backend/app/Http/Requests/ExportCsvRequest.php:27`

- [ ] Teste: usuário sem permissão `export-reports` → 403
- [ ] FAIL
- [ ] Fix: `return $this->user()->can('export-reports');`
- [ ] PASS
- [ ] Commit

---

### Task 20: `WorkOrderExecutionRequest` — lógica contradita (linha 31/40)

- [ ] Ler o arquivo inteiro e entender a INTENÇÃO original
- [ ] Teste para cada ramo de autorização
- [ ] Fix removendo o `return true` cego da linha 40
- [ ] PASS
- [ ] Commit

---

### Task 21: Portal routes — adicionar policies (`->can()`)

**Files:** `backend/routes/api.php:35-50` + controllers portal

- [ ] Identificar recursos portal (work-orders, quotes, certificates)
- [ ] Para cada, criar/completar Policy em `app/Policies/`
- [ ] Registrar em `AuthServiceProvider`
- [ ] Adicionar `$this->authorize(...)` nos controllers
- [ ] Teste: acesso cross-tenant portal → 403
- [ ] PASS
- [ ] Commit

---

### Task 22: Webhook HMAC per-endpoint

**Contexto:** `verify.webhook` middleware único para Evolution API + Email. Atacante que vaza um secret pode forjar em ambos.

- [ ] Separar middlewares: `verify.webhook.evolution`, `verify.webhook.email`
- [ ] Cada um valida HMAC com secret próprio (em `config/services.php`)
- [ ] Teste: secret de A não autentica webhook de B
- [ ] Log de falhas (tentativa de forja)
- [ ] Commit

---

### Task 23: Frontend — tipificar `DealDetailDrawer.tsx:139`

**Files:** `frontend/src/components/crm/DealDetailDrawer.tsx:139`

- [ ] Definir/usar `DealResponse` de `crm-api.ts`
- [ ] Remover `: any` do callback
- [ ] `npm run typecheck` verde
- [ ] Commit

---

### Task 24: Frontend — sincronizar status enum agenda (pt→en)

**Files:** `frontend/src/__tests__/logic/invoice-payment-agenda-logic.test.ts:107`

- [ ] Verificar `AgendaItemStatus` no backend (model/enum/DB)
- [ ] Atualizar type TS para bater com backend (inglês lowercase)
- [ ] Corrigir qualquer uso que depende da string pt
- [ ] `npm run test` verde
- [ ] Commit

---

### Task 25: Gate Final FASE 3

- [ ] Suite completa backend verde
- [ ] Frontend typecheck + test verde
- [ ] Evidência
- [ ] Commit consolidação

---

## FASE 4 — COBERTURA DE TESTES (P2 — 238 FINDINGS)

**Contexto:** 103 controllers sem NENHUM teste + 135 com <4 testes. CLAUDE.md exige mínimo 4-5 testes por CRUD. Esta fase é massiva e precisa ser segmentada.

**Estratégia:** Não é um único PR. Segmentar por domínio. Cada subtask é um módulo. Usar template de tests em `backend/tests/README.md`.

---

### Task 26: Priorizar os 103 controllers sem testes por risco

- [ ] **Step 26.1: Classificar controllers** em 3 tiers:
  - **Tier 1 (financeiro/tenant/auth):** crítico — cobertura obrigatória imediata
  - **Tier 2 (operacional):** alto — 1 semana
  - **Tier 3 (relatório/comando):** médio — backlog

- [ ] **Step 26.2: Gerar matriz** em `docs/audits/test-coverage-backlog.md` com: controller, tier, testes existentes, testes alvo, dono

---

### Tasks 27-N: Cobertura por módulo (exemplo para Tier 1)

Cada módulo vira uma task individual:

**Template por controller:**

- [ ] Ler o controller e mapear rotas (index/store/show/update/destroy)
- [ ] Criar `tests/Feature/<Module>/<Controller>Test.php` com os **5 cenários mínimos:**
  1. `it('creates resource with valid data')` — 201 + JsonStructure
  2. `it('returns 422 for invalid data')`
  3. `it('returns 404 for cross-tenant resource')`
  4. `it('returns 403 without permission')`
  5. `it('returns paginated list with eager-loaded relations')`
- [ ] Rodar `./vendor/bin/pest tests/Feature/<Module>/<Controller>Test.php`
- [ ] Fix qualquer bug exposto no controller (Iron Protocol Lei 1)
- [ ] Commit

**Módulos Tier 1 (exemplos identificados na auditoria):**
- RouteOptimization
- SystemImprovementsAgingReport
- AuvoExportController
- BootstrapSecurityRegression
- CommissionCrossIntegrationRegression
- AuditPermissionsCommand
- CheckExpiredQuotes
- RefreshAnalyticsDatasets
- ValidateRouteControllersCommand
- CrmReferenceSeeder

(Completar matriz no Step 26.2)

---

### Task Final: Gate Final Geral

- [ ] Suite completa verde (< 5 min)
- [ ] Cobertura ≥ 80% (opcional — mensurar)
- [ ] 0 `markTestSkipped`, 0 `assertTrue(true)`, 0 `$guarded = ['id']`
- [ ] Todos os 13 P0 com teste de regressão dedicado
- [ ] `docs/audits/audit-controllers-2026-04-10.md`, `audit-security-...`, etc. anotados com status "✅ RESOLVIDO" por achado
- [ ] Relatório final em `docs/audits/remediation-final-report-2026-04-10.md`
- [ ] Commit consolidação

---

## Referências

- Iron Protocol: `.agent/rules/iron-protocol.md`
- Test Policy: `.agent/rules/test-policy.md`
- Test Runner: `.agent/rules/test-runner.md`
- Template de teste: `backend/tests/README.md`
- Guia de testes: `backend/TESTING_GUIDE.md`
- CLAUDE.md do projeto (convenções + padrões obrigatórios)

## Estimativa de esforço

| Fase | Tasks | Esforço | Bloqueante |
|------|-------|---------|------------|
| FASE 1 (P0) | 1-13 | Curto | DEPLOY PROD |
| FASE 2 (Models) | 14-16 | Curto-Médio | Schema consistency |
| FASE 3 (P1) | 17-25 | Médio | Quality gate |
| FASE 4 (P2) | 26+ | Longo | Backlog contínuo |

## Regras inegociáveis

1. **NUNCA** avançar fase sem Gate Final verde da anterior
2. **NUNCA** pular/mascarar/simplificar teste para passar
3. **NUNCA** usar `--no-verify`, `--skip-*` ou qualquer flag de bypass
4. **SEMPRE** teste falhando ANTES do código (TDD)
5. **SEMPRE** commit atômico por task (não misturar tasks)
6. **SEMPRE** preservar comportamento no refactor (Iron Protocol Lei 8)
7. **Se >5 arquivos em cascata não planejada:** PARAR, reportar, consolidar.
