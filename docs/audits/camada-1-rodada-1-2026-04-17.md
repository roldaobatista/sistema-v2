# Auditoria Consolidada — Camada 1 (Schema + Migrations) — Rodada 1

**Data:** 2026-04-17
**Auditores:** data-expert, security-expert, product-expert, governance (4 em paralelo)
**Status:** ⛔ **ESCALAÇÃO** — 3 findings S0 exigem decisão antes de prosseguir

## Sumário Executivo

**64 findings totais** distribuídos:

| Severidade | Qtd | Significado | Ação |
|---|---|---|---|
| **S0** | **3** | Sistêmicos — exigem decisão usuário | ⛔ ESCALAR |
| **S1** | 12 | Críticos | Corrigir após S0 decidido |
| **S2** | 21 | Altos | Corrigir após S0 decidido |
| **S3** | 23 | Médios | Corrigir após S0 decidido |
| **S4** | 5 | Baixos | Corrigir após S0 decidido |

---

## ⛔ FINDINGS S0 — DECISÃO NECESSÁRIA

### S0-1: `DATA-015` — Padrão arquitetural multi-tenant

**Problema:** 7 models lendo `current_tenant_id` manualmente em vez de confiar no trait `BelongsToTenant`. CLAUDE.md menciona ambos os padrões. Há ambiguidade.

**Decisão necessária:**
- (a) **Padrão único: leitura via trait + escrita via observer `creating`** (recomendado pelo data-expert)
- (b) Manter padrão híbrido (trait + acesso manual) e documentar quando usar cada
- (c) Outro

### S0-2: `SEC-008` — LGPD/Consentimento e Retenção de Dados

**Problema:** Sistema coleta CPF, CNPJ, email, telefone, endereço sem tabela de consentimento (`lgpd_consents`) nem campo de retenção. LGPD art.8 (consentimento rastreável) e art.16 (limite de retenção) violados estruturalmente.

**Decisão necessária (envolve DPO/jurídico):**
- (a) Criar `lgpd_consents` + `data_retention_policies` + endpoint `/api/lgpd/forget` (anonimização)
- (b) Documentar base legal "execução de contrato" (art.7 V) — dispensa consentimento explícito mas exige outras medidas
- (c) Fora de escopo agora (declarar dívida e prosseguir)

### S0-3: `PROD-014` — PRD desatualizado vs schema (450 tabelas, 24 RFs)

**Problema:** Schema tem ~450 tabelas, PRD documenta ~24 RFs. PRD reconhece "Módulos Implementados Não-Documentados" (linha 186) mas não lista quais. Sem listagem, impossível auditar gap funcional.

**Decisão necessária:**
- (a) Sessão de **domain-discovery** agora para mapear: gamification, telescope, on_call, jornadas, sensor_readings, scale_readings, capa, rr_studies, surveys, tv_dashboard. Adicionar RFs ao PRD.
- (b) Atualizar PRD em paralelo (não bloqueia camada 1, ataca em camada paralela)
- (c) Aceitar que PRD não vai cobrir 100% e prosseguir só com domínios principais (OS, Calibração, Financeiro, Estoque, Schedules)

---

## 🔴 FINDINGS S1 (12 — críticos, vão ser corrigidos após S0)

| ID | Título | File |
|---|---|---|
| `DATA-001` | 340 de 433 tabelas com tenant_id sem nenhum índice | MULTIPLE |
| `DATA-003` | Tabelas-filhas sem tenant_id — risco de vazamento via parent (33 tabelas) | MULTIPLE |
| `SEC-001` | `payment_gateway_configs.api_key/api_secret` em plain text | 2026_02_14_070000 |
| `SEC-002` | `user_2fa.secret` em plain text (TOTP exposto se DB vazar) | 2026_02_14_060000 |
| `SEC-003` | `marketing_integrations.api_key` em plain text | 2026_02_14_060000 |
| `SEC-004` | `whatsapp_configs.api_key` em plain text | 2026_02_14_060000 |
| `SEC-005` | OAuth `client_secret` em plain text | 2026_02_14_060000 |
| `SEC-006` | `webhooks.secret`/`inmetro_webhooks.secret`/`fiscal_webhooks.secret` em plain text | 2026_02_13_170000 |
| `SEC-007` | CPF/CNPJ em plain text em customers, suppliers, employee_dependents, users | 2026_02_07_300000 |
| `GOV-001` | 42 migrations alter/add SEM guards hasColumn (Iron Protocol H3 violado) | MULTIPLE |
| `GOV-002` | Migration `update_to_english` MANTÉM PT (bug semântico, perpetua violação) | 2026_03_16_600001 |

## 🟠 FINDINGS S2 (21 — altos)

| ID | Título resumido |
|---|---|
| DATA-002 | 104 tabelas com `deleted_at` sem índice |
| DATA-004 | `audit_logs.tenant_id` é NULLABLE |
| DATA-005 | 279 onDelete cascade vs apenas 11 restrict (cascata em entidades críticas) |
| DATA-007 | Falta UNIQUE composto (tenant_id, document) em customers/suppliers |
| DATA-009 | `inmetro_v3.secret` armazenado sem cast encrypted |
| DATA-013 | 44 models com SoftDeletes trait sem coluna `deleted_at` na tabela |
| SEC-009 | 40 tabelas de domínio sem coluna tenant_id (suspeitas vazamento) |
| SEC-010 | 69 tabelas com tenant_id NULLABLE (incluindo audit_logs) |
| SEC-011 | ~50 tabelas com tenant_id sem índice individual (DoS amplificador) |
| SEC-012 | FK tenant_id ON DELETE CASCADE em 100% das tabelas (637 ocorrências) |
| PROD-001 | `equipment_calibrations.result` default 'aprovado' (PT) viola convenção |
| PROD-002 | `central_items.status` default 'ABERTO' (PT, MAIÚSCULO) |
| PROD-003 | Família `central_*` inteira com colunas em PT (titulo, descricao, etc) |
| PROD-004 | `customer_locations` mistura colunas EN e PT |
| PROD-005 | `expenses` tem AMBOS `created_by` E `user_id` (duplicidade) |
| GOV-003 | Enum `standard_weights.status` criado com valores em PT |
| GOV-004 | `expenses.user_id` adicionado violando convenção `created_by` |
| GOV-005 | `travel_expense_reports` usa `user_id` em vez de `created_by` |
| GOV-009 | Drift legado `company_id` em correção (validar conclusão) |
| GOV-014 | Migration `add_missing_columns_for_tests` viola Lei 3 (completude) |

## 🟡 FINDINGS S3 (23) e S4 (5)

(Detalhes nos arquivos individuais — listagem completa preservada nos JSON dos auditores.)

**S3 destaques:**
- DATA-006: 6 migrations Schema::table sem guards (sub-conjunto do GOV-001)
- DATA-008: 12 polymorphic morphs() sem FK no banco
- DATA-010: Inconsistência precision em decimais financeiros
- DATA-011: Timestamps sem timezone (risco trabalhista CLT)
- DATA-014: Migration `fix_production_schema_drifts` indica gap de CI
- SEC-013: accounts_payable/receivable sem created_by/updated_by
- SEC-014: audit_logs.tenant_id NULLABLE
- SEC-015: client_portal_users sem hardening (lockout/history)
- SEC-016: Spatie permission `teams` precisa validação
- SEC-020: 2FA backup_codes em plain (devem ser hashed)
- PROD-006/007/008/009/010/011/012/013: terminologia, duplicação de domínio, gaps PRD
- GOV-006/007/008/010/011/012/013: timestamps, código comentado, conflitos de timestamp duplicados

**S4:**
- DATA-012: Charset utf8 (sem mb4) em 2 connections secundárias
- DATA-016: Sem schema dump consolidado para MySQL
- SEC-017/018/019: refinos de auditoria
- GOV-006/013: cosmetic naming

---

## Próximo Passo

**Aguardando decisão usuário sobre os 3 S0.** Após decisão:

1. Documentar decisões em `docs/TECHNICAL-DECISIONS.md`
2. Iniciar correção dos 12 S1 + 21 S2 + 23 S3 + 5 S4 = **61 findings restantes**
3. Re-auditar do zero (Rodada 2)
4. Loop até zerar ou rodada 10

## Anexos

- Audit completo data-expert: ver agente a8208f14658a5ab62
- Audit completo security-expert: ver agente a7d6192612e01211f
- Audit completo product-expert: ver agente af4444e6253be9ca3
- Audit completo governance: ver agente a7790598acafd334a
