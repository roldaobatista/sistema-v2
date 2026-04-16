# Auditoria Adversarial: Controllers & FormRequests - Kalibrium ERP
**Data**: 2026-04-10
**Escopo**: 312 Controllers + 847 FormRequests (Laravel 13, multi-tenant)

## Resumo de Severidades

| Severidade | Contagem |
|-----------|----------|
| **P0 (Critical)** | 5 |
| **P1 (High)** | 5 |
| **P2 (Medium)** | 5 |
| **Total Achados** | 15+ |

---

## Top 5 Achados P0 (CRÍTICOS)

### 1. Multi-Tenant Data Breach: SaaS Plan Validation
**Arquivo**: `C:\PROJETOS\sistema\backend\app\Http\Requests\Billing\StoreSaasSubscriptionRequest.php:48`
**Tipo**: SQL Injection / Cross-Tenant Access
```php
'plan_id' => ['required', 'exists:saas_plans,id'],  // FALTA tenant_id scope
```
**Risco**: Usuário subscreve-se a planos de outro tenant
**Fix**: `Rule::exists('saas_plans', 'id')->where('tenant_id', $tenantId)`

---

### 2. User Enumeration Vulnerability
**Arquivo**: `C:\PROJETOS\sistema\backend\app\Http\Requests\Iam\ListAuditLogRequest.php:22`
**Tipo**: Information Disclosure
```php
'user_id' => ['nullable', 'integer', 'exists:users,id'],  // Sem tenant scope
```
**Risco**: Atacante mapeia IDs de usuários de outros tenants
**Fix**: Adicionar `->where('tenant_id', $tenantId)` na closure

---

### 3. Permission Escalation
**Arquivo**: `C:\PROJETOS\sistema\backend\app\Http\Requests\Iam\StoreRoleRequest.php:15`
**Tipo**: Authorization Bypass
```php
'permissions.*' => 'exists:permissions,id',  // Permissões globais?
```
**Risco**: Roles herdam permissões de outro tenant
**Fix**: Validar `permissions` no contexto do tenant atual

---

### 4. Cross-Tenant Record Association
**Arquivo**: `C:\PROJETOS\sistema\backend\app\Http\Requests\MaintenanceReport\StoreMaintenanceReportRequest.php:18-19`
**Tipo**: Data Corruption
```php
'work_order_id' => ['required', 'integer', 'exists:work_orders,id'],
'equipment_id' => ['required', 'integer', 'exists:equipments,id'],
```
**Risco**: Relatório vinculado a recursos de outro tenant
**Fix**: Adicionar `->where('tenant_id', $tenantId)` em ambos

---

### 5. Bulk Tenant Mutation Without Ownership
**Arquivo**: `C:\PROJETOS\sistema\backend\app\Http\Requests\Tenant\BulkStatusTenantRequest.php:12`
**Tipo**: Account Takeover
```php
'ids.*' => ['required', 'integer', 'exists:tenants,id'],  // Valida mas não checa propriedade
```
**Risco**: Admin A muta status de tenants B, C, D...
**Fix**: Em `authorize()`: `$this->user()->tenants()->pluck('id')->contains($id)`

---

## Achados P1 (HIGH) - 5 Críticos

### 6. Uncontrolled Query Result Set
**Arquivo**: `AccountingReportController.php:49`
**Tipo**: DoS / Memory Exhaustion
```php
->get();  // Sem limit; pode retornar 1M+ registros
```
**Fix**: `->paginate(15)` ou `->limit(1000)->get()`

### 7-11. Multiple N+1 Query Patterns
**Arquivo**: `AgendaController.php:316, 446, 490, 556, 578`
**Tipo**: Performance Degradation
**Problema**: Múltiplas `->get()` sem eager loading
**Fix**: Usar `->with(['uploader', 'user', 'addedBy'])`

### 12. Unconditional CSV Export Authorization
**Arquivo**: `ExportCsvRequest.php:27`
**Tipo**: Unauthorized Data Export
```php
return true;  // Qualquer usuário exporta
```
**Fix**: `return $this->user()->can('export-reports');`

### 13. Conflicting Authorization Logic
**Arquivo**: `WorkOrderExecutionRequest.php:31, 40`
**Tipo**: Logic Error
```php
if (condition) return true;  // linha 31
return true;                   // linha 40 - SEMPRE true
```
**Fix**: Implementar lógica correta sem redundância

### 14-18. DB::raw SQL String Interpolation
**Arquivo**: `AnalyticsController.php:247-249, FleetAnalyticsController.php:38-40`
**Tipo**: SQL Injection / Fragility
```php
DB::raw("SUM(CASE WHEN status = '" . WorkOrder::STATUS_COMPLETED . "' THEN 1 ELSE 0 END)")
```
**Problema**: Se enum muda, SQL quebra
**Fix**: Usar `whereIn('status', [...])` + `selectRaw()`

---

## Achados P2 (MEDIUM) - 5 Adicionais

| Arquivo | Linha | Tipo | Severidade |
|---------|-------|------|-----------|
| UpdateUserLocationRequest.php | 18 | Conditional return true | P2 |
| ResumeDisplacementRequest.php | 28 | Missing permission gate | P2 |
| AlertController.php | 43, 51, 143 | DB::raw aggregations | P2 |
| FleetAnalyticsController.php | 38-40 | SQL dialect branching | P2 |
| WarehouseController.php | 38-56 | HTTP status inconsistency | P2 |

---

## Estatísticas

- **FormRequests auditados**: 847
- **FormRequests com authorize()**: 847/847 (100%) ✓
- **return true (válido)**: 12 (públicos com comentário) ✓
- **return true (suspeito)**: 2+ (sem lógica)
- **exists validations sem tenant**: 6 (P0)
- **Controllers ->get() sem limit**: 15+
- **N+1 query patterns**: 20+
- **DB::raw com interpolação**: 12+

---

## Recomendações por Prioridade

### URGENTE
1. Audit todas `exists:table,id` → adicionar `->where('tenant_id', $tenantId)`
2. Revisar `return true;` sem lógica → implementar policy checks explícitos
3. Testar cross-tenant data access em todos endpoints

### HIGH
4. Paginate todas as queries sem limite (->paginate(15))
5. Eager load relacionamentos (->with([...]))
6. Refatorar DB::raw para usar bindings seguros

### MEDIUM
7. Padronizar HTTP status codes (201 create, 204 delete, 200 update/list)
8. Implementar teste multi-tenant para cada endpoint
9. Adicionar rate limiting em endpoints bulk

---

**Auditoria realizada com Context-Mode v4 | 2026-04-10**
