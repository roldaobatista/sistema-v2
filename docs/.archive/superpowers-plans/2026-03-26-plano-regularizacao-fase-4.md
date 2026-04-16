---
status: active
type: implementation
created: 2026-03-26
description: Plano de regularização do código produzido pelo agente nas fases 4.1-4.8 — corrigir controllers, FormRequests, testes e lixo
---

# Plano de Regularização — Fases 4.1 a 4.8

> **Contexto:** Um agente de IA trabalhou ~5 horas executando o plano de implementação das fases 4.1 a 4.8. A análise revelou que ~60% do trabalho está correto (models, factories, migrations, rotas) mas controllers, FormRequests e testes têm falhas sistemáticas. Este plano corrige tudo sem desperdiçar o trabalho feito.

> **Prioridade:** ALTA — corrigir ANTES de continuar qualquer fase nova.

> **Estimativa:** ~2h de trabalho focado com agentes paralelos.

> **Regras:** Seguir Iron Protocol (`.agent/rules/iron-protocol.md`) incluindo Lei 3b (padrão de controllers). Documentação atualizada em 2026-03-26 com novas regras.

---

## INVENTÁRIO DOS PROBLEMAS

### Dados da Auditoria (2026-03-26)

| Módulo | Controllers | FormRequests | Testes | Problemas |
|--------|------------|-------------|--------|-----------|
| **Contracts** | 2 (39 linhas cada) | 4 | 10 total (5+5) | authorize=true, sem paginação, created_by no FormRequest |
| **Helpdesk** | 3 (39/19/39 linhas) | 4 | 10 total (5+2+3) | authorize=true, sem paginação, TicketCategoryFactory sem sla_policy_id |
| **Procurement** | 3 (13 linhas cada!) | 6 | 15 total (5+5+5) | authorize=true, Supplier::all(), PurchaseQuotation→Customer bug, sem paginação |

### Problemas Sistemáticos (afetam TODOS os módulos)

| # | Problema | Arquivos afetados | Severidade |
|---|---------|-------------------|-----------|
| P1 | `authorize() { return true; }` sem lógica | 14+ FormRequests novos | CRÍTICO |
| P2 | `->get()` sem paginação em index() | 8 controllers | CRÍTICO |
| P3 | `created_by` exposto no FormRequest | 3 FormRequests | ALTO |
| P4 | `tenant_id`/`created_by` NÃO atribuídos no store() | 8 controllers | ALTO |
| P5 | Zero testes cross-tenant em TODOS os módulos | 8 test files | ALTO |
| P6 | Zero testes de validação 422 | 8 test files | ALTO |
| P7 | Testes não validam JSON structure | 8 test files | MÉDIO |
| P8 | Controllers minificados (13 linhas) sem formatação | 3 controllers Procurement | MÉDIO |
| P9 | PurchaseQuotation→supplier() aponta para Customer::class | 1 model | CRÍTICO |
| P10 | TicketCategoryFactory sem sla_policy_id | 1 factory | MÉDIO |
| P11 | 7 arquivos temporários de debug | 7 arquivos | BAIXO |
| P12 | StoreContractMeasurementRequest regressão (items removidos) | 1 FormRequest | ALTO |

---

## ETAPA 1: LIMPEZA (5 min)

### Task 1.1: Remover arquivos temporários

Deletar estes 7 arquivos que NÃO devem ser commitados:

```
backend/error.txt
backend/error2.txt
backend/pest_out.txt
backend/pest_output.txt
backend/make-factories.php
backend/test_factory.php
tests_output.txt
```

**DoD:** Arquivos removidos. `git status` não mostra nenhum deles.

---

## ETAPA 2: CORRIGIR MODELS E FACTORIES (15 min)

### Task 2.1: Corrigir PurchaseQuotation model (P9 — CRÍTICO)

**Arquivo:** `backend/app/Models/PurchaseQuotation.php` linha 42

**Problema:** `$this->belongsTo(Customer::class, 'supplier_id')` — aponta para Customer em vez de Supplier.

**Correção:**
```php
// DE:
return $this->belongsTo(Customer::class, 'supplier_id');
// PARA:
return $this->belongsTo(Supplier::class, 'supplier_id');
```

**Verificar:** Se `Supplier` model existe. Se não existe, verificar se suppliers são na verdade customers no sistema (e ajustar a migration/factory de acordo).

### Task 2.2: Corrigir TicketCategoryFactory (P10)

**Arquivo:** `backend/database/factories/TicketCategoryFactory.php`

**Problema:** `sla_policy_id` está no fillable e tem FK na migration, mas a factory não gera.

**Correção:** Adicionar `'sla_policy_id' => SlaPolicy::factory()` na definição.

### Task 2.3: Verificar TODOS os novos models

Confirmar que todos os novos models têm:
- [ ] `BelongsToTenant` trait ✅ (já confirmado)
- [ ] `HasFactory` trait
- [ ] `$fillable` completo (todos os campos da migration)
- [ ] `$casts` para datas, JSONs, decimais
- [ ] Relationships bidirecionais (se A→B, B→A também)
- [ ] SoftDeletes (se aplicável ao domínio)

**Models a verificar:**
- ContractAddendum
- ContractMeasurement
- EscalationRule
- SlaViolation
- TicketCategory
- MaterialRequest (modificado)
- PurchaseQuotation (modificado)

**DoD:** Todos os models corretos. Relationships coerentes com migrations.

---

## ETAPA 3: CORRIGIR CONTROLLERS (30 min)

### Task 3.1: Reescrever Procurement Controllers (P2, P4, P8)

Os 3 controllers do Procurement são minificados em 13 linhas cada. Reescrever seguindo o padrão `EquipmentController.php`:

**Arquivos:**
- `backend/app/Http/Controllers/Api/V1/Procurement/SupplierController.php`
- `backend/app/Http/Controllers/Api/V1/Procurement/MaterialRequestController.php`
- `backend/app/Http/Controllers/Api/V1/Procurement/PurchaseQuotationController.php`

**Padrão obrigatório para CADA controller:**

```php
public function index(Request $request)
{
    return Entidade::with(['relationship:id,name'])
        ->paginate(min((int) $request->input('per_page', 25), 100));
}

public function store(StoreEntidadeRequest $request)
{
    $entidade = Entidade::create([
        ...$request->validated(),
        'tenant_id' => $request->user()->current_tenant_id,
        'created_by' => $request->user()->id,
    ]);
    return response()->json($entidade, 201);
}

public function show(Entidade $entidade)
{
    return response()->json($entidade->load(['relationship']));
}

public function update(UpdateEntidadeRequest $request, Entidade $entidade)
{
    $entidade->update($request->validated());
    return response()->json($entidade->fresh());
}

public function destroy(Entidade $entidade)
{
    $entidade->delete();
    return response()->noContent();
}
```

**Checklist por controller:**
- [ ] `index()` usa `->paginate()` com safety cap (max 100)
- [ ] `index()` usa `->with([...])` com relationships relevantes
- [ ] `store()` atribui `tenant_id` e `created_by` via `$request->user()`
- [ ] `show()` usa `->load([...])` para eager loading
- [ ] Código formatado (NÃO minificado em 1 linha)

### Task 3.2: Reescrever Contracts Controllers (P2, P4)

**Arquivos:**
- `backend/app/Http/Controllers/Api/V1/Contracts/ContractAddendumController.php`
- `backend/app/Http/Controllers/Api/V1/Contracts/ContractMeasurementController.php`

**Mesmos critérios da Task 3.1.** Adicionar:
- `index()` → `->paginate()` em vez de `->get()`
- `store()` → atribuir `tenant_id` e `created_by`

### Task 3.3: Reescrever Helpdesk Controllers (P2, P4)

**Arquivos:**
- `backend/app/Http/Controllers/Api/V1/Helpdesk/TicketCategoryController.php`
- `backend/app/Http/Controllers/Api/V1/Helpdesk/EscalationRuleController.php`
- `backend/app/Http/Controllers/Api/V1/Helpdesk/SlaViolationController.php` (read-only, sem store)

**Mesmos critérios da Task 3.1.** SlaViolationController é read-only (apenas index + show), manter assim mas adicionar paginação no index.

**DoD Etapa 3:** TODOS os 8 controllers seguem o padrão. Zero `::all()`, zero `->get()` sem paginação, zero store sem tenant_id/created_by.

---

## ETAPA 4: CORRIGIR FORM REQUESTS (30 min)

### Task 4.1: Corrigir authorize() em TODOS os FormRequests novos (P1 — CRÍTICO)

**Regra:** `authorize()` DEVE verificar permissão real. O nome da permissão segue o padrão Spatie do sistema: `modulo.ação`.

**Arquivos e permissões:**

| FormRequest | Permissão |
|------------|-----------|
| StoreContractAddendumRequest | `contracts.create` |
| UpdateContractAddendumRequest | `contracts.update` |
| StoreContractMeasurementRequest | `contracts.create` |
| UpdateContractMeasurementRequest | `contracts.update` |
| StoreTicketCategoryRequest | `helpdesk.create` |
| UpdateTicketCategoryRequest | `helpdesk.update` |
| StoreEscalationRuleRequest | `helpdesk.create` |
| UpdateEscalationRuleRequest | `helpdesk.update` |
| StoreSupplierRequest | `procurement.create` |
| UpdateSupplierRequest | `procurement.update` |
| StoreMaterialRequestRequest | `procurement.create` |
| UpdateMaterialRequestRequest | `procurement.update` |
| StorePurchaseQuotationRequest | `procurement.create` |
| UpdatePurchaseQuotationRequest | `procurement.update` |

**Padrão:**
```php
public function authorize(): bool
{
    return $this->user()->can('modulo.ação');
}
```

### Task 4.2: Remover `created_by` dos FormRequests (P3)

**Arquivos afetados:**
- `StoreContractAddendumRequest.php` — remover `'created_by' => 'required|exists:users,id'`
- `StoreContractMeasurementRequest.php` — remover `'created_by' => 'required|exists:users,id'`
- `StorePurchaseQuotationRequest.php` — remover `'created_by' => 'nullable|exists:users,id'`

**Motivo:** `created_by` é atribuído no controller via `$request->user()->id`. NUNCA expor ao cliente.

### Task 4.3: Corrigir StoreContractMeasurementRequest (P12 — regressão)

**Arquivo:** `backend/app/Http/Requests/Contracts/StoreContractMeasurementRequest.php`

**Problema:** Validações de items foram removidas (description, quantity, unit_price, accepted).

**Correção:** Restaurar validações de items:
```php
'items' => 'nullable|array',
'items.*.description' => 'required_with:items|string|max:255',
'items.*.quantity' => 'required_with:items|numeric|min:0',
'items.*.unit_price' => 'required_with:items|numeric|min:0',
'items.*.accepted' => 'required_with:items|boolean',
```

### Task 4.4: Adicionar validação de FK com tenant

Onde existir `exists:tabela,id`, adicionar `where('tenant_id', ...)`:

```php
'contract_id' => ['required', Rule::exists('contracts', 'id')
    ->where('tenant_id', $this->user()->current_tenant_id)],
```

**Aplicar em:** StoreContractAddendumRequest, StoreContractMeasurementRequest, StoreMaterialRequestRequest, StorePurchaseQuotationRequest.

**DoD Etapa 4:** TODOS os FormRequests com authorize real, zero created_by exposto, validações FK com tenant.

---

## ETAPA 5: REESCREVER TESTES (45 min)

### Task 5.1: Template base para TODOS os testes

Cada arquivo de teste DEVE seguir este template com no MÍNIMO 8 testes:

```
1. it('can list {resources} with pagination')           → assertOk + assertJsonStructure(['data','meta'])
2. it('can create a {resource}')                         → assertCreated + assertDatabaseHas
3. it('can show a {resource}')                           → assertOk + assertJsonPath
4. it('can update a {resource}')                         → assertOk + assertDatabaseHas
5. it('can delete a {resource}')                         → assertNoContent + assertSoftDeleted/assertDatabaseMissing
6. it('fails validation when required fields missing')   → assertUnprocessable + assertJsonValidationErrors
7. it('cannot access {resource} from another tenant')    → assertNotFound
8. it('only lists {resources} from own tenant')          → assertOk + assertJsonCount(own only)
9. it('assigns tenant_id and created_by automatically')  → assertDatabaseHas with user fields
10. it('returns paginated results with correct structure') → assertJsonStructure meta/links
```

### Task 5.2: Reescrever testes — Contracts (2 arquivos)

| Arquivo | Testes atuais | Mínimo | Ação |
|---------|:---:|:---:|------|
| ContractAddendumControllerTest.php | 5 | 10 | Reescrever + adicionar cross-tenant, 422, structure, tenant_id |
| ContractMeasurementControllerTest.php | 5 | 10 | Reescrever + adicionar cross-tenant, 422, structure, tenant_id |

### Task 5.3: Reescrever testes — Helpdesk (3 arquivos)

| Arquivo | Testes atuais | Mínimo | Ação |
|---------|:---:|:---:|------|
| EscalationRuleControllerTest.php | 5 | 10 | Reescrever + adicionar cross-tenant, 422, structure |
| TicketCategoryControllerTest.php | 3 | 10 | Reescrever (muito incompleto) |
| SlaViolationControllerTest.php | 2 | 6 | Reescrever (read-only: index, show, cross-tenant, pagination) |

### Task 5.4: Reescrever testes — Procurement (3 arquivos)

| Arquivo | Testes atuais | Mínimo | Ação |
|---------|:---:|:---:|------|
| MaterialRequestControllerTest.php | 5 | 10 | Reescrever + cross-tenant, 422, structure, tenant_id |
| PurchaseQuotationControllerTest.php | 5 | 10 | Reescrever + cross-tenant, 422, structure, tenant_id |
| SupplierControllerTest.php | 5 | 10 | Reescrever + cross-tenant, 422, structure, tenant_id |

### Cenários OBRIGATÓRIOS em cada arquivo (referência `.agent/rules/test-policy.md`):

```php
// Cross-tenant (OBRIGATÓRIO)
it('cannot access {resource} from another tenant', function () {
    $otherTenant = Tenant::factory()->create();
    $resource = Resource::factory()->create(['tenant_id' => $otherTenant->id]);
    $this->getJson("/api/v1/{module}/{$resource->id}")->assertNotFound();
});

it('only lists {resources} from own tenant', function () {
    $otherTenant = Tenant::factory()->create();
    Resource::factory()->count(3)->create(['tenant_id' => $otherTenant->id]);
    Resource::factory()->count(2)->create(['tenant_id' => $this->tenant->id]);
    $response = $this->getJson("/api/v1/{module}");
    $response->assertOk();
    expect($response->json('data'))->toHaveCount(2);
});

// Validação 422 (OBRIGATÓRIO)
it('fails validation when required fields are missing', function () {
    $this->postJson("/api/v1/{module}", [])->assertUnprocessable()
        ->assertJsonValidationErrors(['field1', 'field2']);
});

// Estrutura JSON (OBRIGATÓRIO)
it('returns paginated response with correct structure', function () {
    Resource::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);
    $this->getJson("/api/v1/{module}")->assertOk()
        ->assertJsonStructure(['data' => [['id', 'name']], 'meta', 'links']);
});

// tenant_id/created_by automáticos (OBRIGATÓRIO)
it('assigns tenant_id and created_by automatically', function () {
    $this->postJson("/api/v1/{module}", $validData)->assertCreated();
    $this->assertDatabaseHas('table', [
        'tenant_id' => $this->tenant->id,
        'created_by' => $this->user->id,
    ]);
});
```

**DoD Etapa 5:** TODOS os 8 arquivos de teste com ≥8 testes cada. Cross-tenant, 422, JSON structure, tenant_id/created_by presentes em TODOS.

---

## ETAPA 6: RODAR TESTES E VALIDAR (15 min)

### Task 6.1: Regenerar schema dump

```bash
cd backend && php generate_sqlite_schema.php
```

**Motivo:** A migration `2026_03_26_120000_create_helpdesk_entities_tables.php` precisa estar no schema dump.

### Task 6.2: Rodar suite completa

```bash
cd backend && ./vendor/bin/pest --parallel --processes=16 --no-coverage
```

**Critério:** Zero falhas. Se algum teste falhar, corrigir o SISTEMA (não o teste).

### Task 6.3: Rodar testes específicos dos módulos corrigidos

```bash
./vendor/bin/pest tests/Feature/Api/V1/Contracts/ --no-coverage
./vendor/bin/pest tests/Feature/Api/V1/Helpdesk/ --no-coverage
./vendor/bin/pest tests/Feature/Api/V1/Procurement/ --no-coverage
```

### Task 6.4: Verificações grep finais

```bash
# Zero authorize return true nos novos FormRequests
grep -rn "return true" backend/app/Http/Requests/Contracts/ backend/app/Http/Requests/Helpdesk/ backend/app/Http/Requests/Procurement/ --include="*.php" | grep authorize

# Zero Model::all() nos novos controllers
grep -rn "::all()" backend/app/Http/Controllers/Api/V1/Contracts/ backend/app/Http/Controllers/Api/V1/Helpdesk/ backend/app/Http/Controllers/Api/V1/Procurement/ --include="*.php"

# Zero created_by nos FormRequests
grep -rn "created_by" backend/app/Http/Requests/Contracts/ backend/app/Http/Requests/Helpdesk/ backend/app/Http/Requests/Procurement/ --include="*.php"

# Todos os testes têm cross-tenant
for f in backend/tests/Feature/Api/V1/Contracts/*.php backend/tests/Feature/Api/V1/Helpdesk/*.php backend/tests/Feature/Api/V1/Procurement/*.php; do
  echo "$f: $(grep -c 'otherTenant\|other_tenant\|another.*tenant' "$f" 2>/dev/null)"
done
```

**Critério:** TODOS os greps retornam zero problemas. Todos os testes têm cross-tenant.

**DoD Etapa 6:** Suite completa verde. Verificações grep limpas. Módulos prontos para produção.

---

## ETAPA 7: COMMIT E DOCUMENTAÇÃO (10 min)

### Task 7.1: Commit do trabalho

```bash
# Staging dos arquivos corrigidos (NÃO incluir arquivos de debug)
git add backend/app/Http/Controllers/Api/V1/Contracts/
git add backend/app/Http/Controllers/Api/V1/Helpdesk/
git add backend/app/Http/Controllers/Api/V1/Procurement/
git add backend/app/Http/Requests/Contracts/
git add backend/app/Http/Requests/Helpdesk/
git add backend/app/Http/Requests/Procurement/
git add backend/app/Models/ContractAddendum.php backend/app/Models/ContractMeasurement.php
git add backend/app/Models/EscalationRule.php backend/app/Models/SlaViolation.php backend/app/Models/TicketCategory.php
git add backend/app/Models/MaterialRequest.php backend/app/Models/PurchaseQuotation.php
git add backend/database/factories/
git add backend/tests/Feature/Api/V1/Contracts/
git add backend/tests/Feature/Api/V1/Helpdesk/
git add backend/tests/Feature/Api/V1/Procurement/
# ... demais arquivos relevantes

git commit -m "fix: regularizar controllers, FormRequests e testes dos módulos Contracts, Helpdesk e Procurement

- Corrigir authorize() com permissões Spatie reais (Lei 3b)
- Adicionar paginação em todos os index()
- Adicionar eager loading em todos os controllers
- Atribuir tenant_id/created_by no controller (remover do FormRequest)
- Corrigir PurchaseQuotation supplier relationship (Customer→Supplier)
- Restaurar validações de items no StoreContractMeasurementRequest
- Reescrever testes com 5 cenários obrigatórios (cross-tenant, 422, JSON structure)
- Remover arquivos temporários de debug"
```

### Task 7.2: Atualizar progresso no plano

Marcar no plano mestre (`2026-03-25-plano-implementacao-completo.md`) que as fases 4.4-4.6 foram regularizadas.

**DoD Etapa 7:** Commit limpo. Plano atualizado. Zero arquivos de debug no repositório.

---

## RESUMO EXECUTIVO

| Etapa | Tempo | Arquivos | Descrição |
|-------|-------|----------|-----------|
| 1. Limpeza | 5 min | 7 deletados | Remover arquivos de debug |
| 2. Models/Factories | 15 min | ~9 editados | PurchaseQuotation bug, TicketCategoryFactory, verificações |
| 3. Controllers | 30 min | 8 reescritos | Paginação, eager loading, tenant_id/created_by |
| 4. FormRequests | 30 min | 14 editados | authorize real, remover created_by, FK com tenant |
| 5. Testes | 45 min | 8 reescritos | 8+ testes cada, cross-tenant, 422, JSON structure |
| 6. Validação | 15 min | 0 | Rodar testes, verificações grep |
| 7. Commit | 10 min | 0 | Commit + documentação |
| **TOTAL** | **~2h30** | **~46 arquivos** | |

### Ordem de Execução com Agentes Paralelos

```
Paralelo 1: Etapa 1 (limpeza) + Etapa 2 (models/factories)
Paralelo 2: Etapa 3 (controllers) — pode rodar em paralelo por módulo:
  - Agente A: Procurement (3 controllers)
  - Agente B: Contracts (2 controllers)
  - Agente C: Helpdesk (3 controllers)
Sequencial: Etapa 4 (FormRequests) — depende da Etapa 3
Paralelo 3: Etapa 5 (testes) — pode rodar em paralelo por módulo:
  - Agente A: Procurement (3 test files)
  - Agente B: Contracts (2 test files)
  - Agente C: Helpdesk (3 test files)
Sequencial: Etapa 6 (validação) → Etapa 7 (commit)
```

**Tempo real com paralelismo: ~1h30**
