# Re-auditoria Camada 1 — 2026-04-20 — governance

## Achados

### gov-01 — S2 — `.claude/allowed-mcps.txt` ausente
- **Arquivo:** `.claude/allowed-mcps.txt` (não existe)
- **Descrição:** Referenciado por CLAUDE.md, agents/governance.md, skills/mcp-check.md, commands/mcp-check.md, mas o arquivo não existe.
- **Impacto:** `/mcp-check` e checklist `mcps-autorizados` apontam para fonte inexistente.

### gov-02 — S3 — Skill `draft-tests` órfã
- **Arquivo:** `.claude/skills/draft-tests.md`
- **Descrição:** Nenhum command a invoca, não listada no CLAUDE.md.
- **Impacto:** Harness inconsistente — skill não descobrível.

### gov-03 — S3 — Skill `master-audit` órfã
- **Arquivo:** `.claude/skills/master-audit.md`
- **Descrição:** Nenhum command a invoca, não listada no CLAUDE.md.
- **Impacto:** Skill não descobrível via workflow documentado.

### gov-04 — S2 — Commands não declaram skills/agents referenciadas
- **Arquivo:** `.claude/commands/*.md` (14 arquivos)
- **Descrição:** Nenhum command referencia explicitamente paths de skills/agents que orquestra.
- **Impacto:** Checklist `commands-coerentes` não validável mecanicamente. Quebra silenciosa em rename.

### gov-05 — S2 — `tenant_id` do body em `AuthController::switchTenant` sem comentário justificando exceção
- **Arquivo:** `backend/app/Http/Controllers/Api/V1/Auth/AuthController.php:258`
- **Descrição:** `$tenantId = (int) $request->validated()['tenant_id'];` — exceção necessária (endpoint de switch precisa do ID-alvo) mas sem comentário justificativo à Lei 4.
- **Impacto:** Viola norma `withoutGlobalScope exige justificativa explícita por escrito`, por analogia.

### gov-06 — S3 — Fallback para `$user->tenant_id`
- **Arquivos:**
  - `backend/app/Http/Controllers/Api/V1/MetrologyQualityController.php:249`
  - `backend/app/Http/Controllers/Api/V1/PeopleAnalyticsController.php:20`
  - `backend/app/Http/Controllers/Api/V1/PortalQuickQuoteApprovalController.php:21`
  - `backend/app/Models/Concerns/AppliesTenantScope.php:17`
- **Descrição:** `$tenantId = app('current_tenant_id') ?? $request->user()->tenant_id;` — Lei 4 exige `current_tenant_id`.
- **Impacto:** Inconsistência com a regra.

### gov-07 — S3 — `withoutGlobalScopes()` (plural) sem justificativa adjacente
- **Arquivos:**
  - `backend/app/Http/Controllers/Api/V1/BankReconciliationController.php:223, 227`
  - `backend/app/Http/Controllers/Api/V1/CatalogController.php:34`
  - `backend/app/Listeners/AutoEmitNFeOnInvoice.php:18`
  - `backend/app/Listeners/HandleWorkOrderInvoicing.php:117, 338, 357`
  - `backend/app/Console/Commands/ScanOverdueFinancials.php:51, 63, 76, 111, 123, 135`
  - `backend/app/Console/Commands/CheckUnbilledWorkOrders.php:27, 56, 64`
- **Descrição:** `withoutGlobalScopes()` remove também soft-delete — mais agressivo que `withoutGlobalScope('tenant')`. CLAUDE.md Lei 4 exige justificativa.
- **Impacto:** Quebra mecânica da regra.

### gov-08 — S4 — Referências a `docs/.archive/` em docs ativos
- **Arquivos:**
  - `docs/CORRECTION_PLAN.md:101`
  - `docs/auditoria/AUDITORIA-CONSOLIDADA-2026-04-10.md:97`
- **Descrição:** Referências são instruções sobre a pasta (falso-positivo esperável).
- **Impacto:** Baixo; cosmético.

## Resumo
- **S1:** 0 · **S2:** 3 · **S3:** 3 · **S4:** 1 · **Total:** 7

## Veredito do expert (apenas reporte, não julgamento)
**Findings: 7** — coordenador compara contra baseline para decidir fechamento.
