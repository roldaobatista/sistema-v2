# Auditoria Consolidada — Camada 1 — Rodada 2

**Data:** 2026-04-17 13:30
**Modo:** Autônomo
**Findings R1 corrigidos:** 12 (encryption + meta tests)
**Findings R1 remanescentes:** 50
**Findings NOVOS introduzidos pela Wave 1:** 11

## Sumário

| Auditor | Total | Corrigidos R1 | Remanescentes R1 | Novos R2 |
|---|---|---|---|---|
| data-expert | 18 | 2 | 13 | 5 |
| security-expert | 14 | 8 | 10 | 4 |
| product-expert | 16 | 1 | 13 | 2 |
| governance | 16 | 0 | 14 | 2 |
| **Total** | **64** | **11** | **50** | **13** |

## 🚨 BLOCKER (necessita correção imediata)

### `DATA-NEW-001` / `GOV-R2-015` — Schema dump SQLite stale
- **Problema:** `backend/database/schema/sqlite-schema.sql` termina em migration de 2026-03-28; faltam ~42 migrations de abril, incluindo `add_document_hash`. Wave 1B alegou regeneração mas dump físico está desatualizado.
- **Por que testes passaram:** Pest provavelmente usa `RefreshDatabase` que roda migrations from scratch em runtime.
- **Risco:** Se algum cenário de teste depende do dump direto (ex: import via raw SQL), falha em produção mas passa local.
- **Correção:** subir MySQL e rodar `php generate_sqlite_schema.php`, OU usar caminho alternativo (SQLite-only generator).

## 🔴 NOVOS introduzidos pela Wave 1B (4 S3)

| ID | Título |
|---|---|
| `SEC-021` | `document_hash`/`cpf_hash` faltam em `$hidden` — vazam em response JSON |
| `SEC-022` | `BelongsToTenant` ainda usa event listener — quebra com `Event::fake()` |
| `SEC-023` | Migration backfill síncrono pesado — risco lock em produção (mover para Job assíncrono) |
| `SEC-024` | `hashSearchable()` estático com lista hardcoded — fragiliza extensão |
| `PROD-015` | `PaymentGatewayConfig.fillable` e `MarketingIntegration.fillable` expõem `tenant_id` (mass-assignment risk) |

## 🟡 50 Remanescentes da R1 (próximas waves)

**Wave 2 — Tenant Safety estrutural** (8 findings):
- DATA-001 (340 tabelas sem índice tenant_id)
- DATA-002 (104 tabelas sem índice deleted_at)
- DATA-003 (33 child tables sem tenant_id)
- DATA-004 (audit_logs.tenant_id NULLABLE)
- SEC-009 (40 tabelas sem tenant_id)
- SEC-010 (69 tabelas tenant_id NULLABLE)
- SEC-011 (~50 tenant_id sem índice individual)
- SEC-014 (audit_logs.tenant_id NULLABLE)

**Wave 3 — Convenções PT/EN** (8 findings):
- PROD-001 (equipment_calibrations.result default 'aprovado')
- PROD-002 (central_items.status 'ABERTO')
- PROD-003 (família central_* em PT)
- PROD-004 (customer_locations mistura EN/PT)
- PROD-005 (expenses.user_id duplicidade)
- GOV-002, GOV-003 (standard_weights enum PT)
- GOV-004, GOV-005 (user_id em vez de created_by)

**Wave 4 — Cascade + Auditoria** (5 findings):
- DATA-005 (279 cascade vs 11 restrict)
- SEC-012 (cascadeOnDelete tenant_id em 100% das tabelas)
- SEC-013 (accounts_payable/receivable sem created_by)
- DATA-006 (6 migrations Schema::table sem H3 guards)
- GOV-001 (42 migrations sem H3 guards)

**Wave 5 — Polymorphic + Decimal + Timezone + Charset + SoftDelete drift** (8 findings):
- DATA-008 (12 polymorphic morphs() sem FK)
- DATA-010 (precision decimais financeiros)
- DATA-011 (timestamps sem timezone)
- DATA-012 (charset utf8 sem mb4 em conexões secundárias) — possível false positive (data-expert R2 marcou como FP)
- DATA-013 (44 models SoftDeletes sem coluna)
- SEC-015 (client_portal_users sem hardening)
- SEC-018 (9 migrations alteram users)
- SEC-019 (cascade chains DoS)

**Wave 6 — Naming + Comentários + S4** (resto):
- GOV-006..014 (timestamps anômalos, código comentado, conflitos timestamp duplicados)
- PROD-006..013 (terminologia, duplicação de domínio, gaps PRD reverso)
- SEC-017 (DB::raw com interpolação em AnalyticsController)
- DATA-007 (UNIQUE composto faltando — parcialmente corrigido)
- DATA-014, DATA-016 (CI gate, schema dump consolidado)

## Plano de Execução

| Wave | Escopo | Findings |
|---|---|---|
| **1D** (próxima) | BLOCKER schema dump + 5 S3 introduzidos | 6 |
| 2 | Tenant safety estrutural | 8 |
| 3 | Convenções PT/EN | 8 |
| 4 | Cascade + auditoria + H3 guards | 5 |
| 5 | Polymorphic + decimal + timezone + softdelete drift | 8 |
| 6 | Naming + comentários + resto | 15 |

**Após cada wave:** re-auditoria completa (Rodada N+1).
**Modo:** Autônomo — só escalo se S0 NOVO ou impasse técnico.

## Próximo Passo

**Wave 1D** — corrigir BLOCKER + 5 S3 da Wave 1B antes de avançar.
