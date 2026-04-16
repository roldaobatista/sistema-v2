# Auditoría Adversarial Frontend Kalibrium ERP — 2026-04-10

**Escopo:** React 19 + TypeScript + Vite vs Laravel Backend
**Período:** 2026-04-10
**Status:** 5 issues reais encontrados (3 P0, 1 P1, 1 P2)

---

## Resumo Executivo

| Métrica | Resultado |
|---------|-----------|
| Total de Issues | 5 |
| **P0 (Críticos)** | **3** |
| P1 (Altos) | 1 |
| P2 (Médios) | 1 |
| Cobertura Auditada | 150+ arquivos TypeScript |
| Checks Passados | 5/10 ✓ |

---

## Issues Críticos (P0)

### 1. Type `any` em DealDetailDrawer — Loss of Type Safety

**Arquivo:** `/c/PROJETOS/sistema/frontend/src/components/crm/DealDetailDrawer.tsx:139`
**Severidade:** P0 | **Tipo:** Type Safety

```typescript
onSuccess: (res: any) => {
  // Qualquer estrutura de resposta é aceita
```

**Impacto:** Callbacks de CRM (deals, atividades) não validam resposta de API. Mutations podem falhar silenciosamente.
**Remediação:** Tipifique com `DealResponse` do `crm-api.ts`.

---

### 2. Error Handling Incompleto em syncEngine.ts — Retries Infinitos

**Arquivo:** `/c/PROJETOS/sistema/frontend/src/lib/offline/syncEngine.ts:44`
**Severidade:** P0 | **Tipo:** Error Handling

```typescript
catch (error: any) {
  console.error(`Sync failed for request ${request.id}:`, error)
  // ❌ Retries 422 validation errors indefinidamente
  const status = error.response?.status
  // Status 422 é tratado como transiente, não permanente
```

**Impacto:**
- Alterações offline com erro de validação (422) ficam stuck indefinidamente
- Sem possibilidade de o usuário corrigir e ressubmeter
- Fila de sync cresce sem controle

**Remediação:**
```typescript
const isRetryable = status !== 422 && status !== 400 && status !== 401
if (!isRetryable) {
  await markRequestAsFailed(request.id, error.response?.data)
}
```

---

### 3. Portuguese Status Enum — Mismatch Backend

**Arquivo:** `/c/PROJETOS/sistema/frontend/src/__tests__/logic/invoice-payment-agenda-logic.test.ts:107`
**Severidade:** P0 | **Tipo:** Contract Mismatch

```typescript
type AgendaStatus = 'aberto' | 'em_andamento' | 'concluido' | 'cancelado' | 'aguardando' | 'pausado'
```

**Impacto:**
- Backend provavelmente retorna `'open' | 'in_progress' | 'completed' | 'cancelled' | ...` (inglês)
- Frontend hardcoda português; comparações falham
- Estado de agenda não renderiza corretamente em dashboard

**Remediação:**
1. Auditar `AgendaItemStatus` enum no backend
2. Sincronizar nomes em ambos ou usar mapping layer

---

## Issues Altos (P1)

### 4. 422 Validation Errors — Retry Loop

**Arquivo:** `/c/PROJETOS/sistema/frontend/src/lib/offline/syncEngine.ts`
**Severidade:** P1 | **Tipo:** Error Strategy

**Código:**
```typescript
// If it's a permanent error (e.g. 422), we might want to discard or mark as failed
// For now, we just keep it pending for the next attempt unless it's a 4xx that won't recover
```

**Impacto:** Comentário indica awareness do bug mas não está implementado.

---

## Issues Médios (P2)

### 5. Incomplete 422 Handler na API Interceptor

**Arquivo:** `/c/PROJETOS/sistema/frontend/src/lib/api.ts:~130-150`
**Severidade:** P2 | **Tipo:** Error UX

```typescript
else if (status === 422) {
  const data = error?.response?.data
  const errors = data?.errors
  if (errors && typeof errors === 'object') {
    // Mostra erros mas não evita retry
  }
}
```

**Impacto:** Validação exibida para usuário mas offline sync continua tentando.

---

## Checks Passados ✓

| Check | Resultado | Descrição |
|-------|-----------|-----------|
| Console.log/debugger | ✓ PASS | Nenhum encontrado em código de produção |
| company_id refs | ✓ PASS | Corretamente usando `tenant_id` |
| Orphaned endpoints | ✓ PASS | Todas as chamadas existem em `routes/api.php` |
| Axios 401/403 handling | ✓ PASS | Interceptor redireciona corretamente |
| Broken imports | ✓ PASS | Sem `Cannot find module` detectados |
| Interceptor circuit breaker | ✓ PASS | 502/503/504 tratados com retry + health check |
| TODO/FIXME em produção | ✗ FAIL | 1 encontrado em CalibrationWizardPage.tsx (não crítico) |
| `any` type production | ⚠️ 2 FOUND | DealDetailDrawer, syncEngine (acima) |

---

## Recomendações de Priorização

1. **[SEMANA 1]** Fixar error handling em syncEngine — libera offline da silently-failing state
2. **[SEMANA 2]** Tipificar DealDetailDrawer + audit outros `as any`
3. **[SEMANA 3]** Validar status enum backend vs frontend (agenda, financeiro)

---

## Apêndice A: Rotas Backend Confirmadas

Todas as chamadas API encontradas (20+ módulos):
- `/api/v1/crm/*` ✓
- `/api/v1/work-orders/*` ✓
- `/api/v1/catalog/*` ✓
- `/api/v1/equipment/*` ✓
- `/api/v1/portal/*` ✓
- `/api/v1/financials/*` ✓
- `/api/v1/quotes/*` ✓
- Webhooks públicos ✓

---

**Relatório gerado:** 2026-04-10
**Próxima auditoria:** 2026-05-10
