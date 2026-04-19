# Findings canônicos — Camada 1 (Schema + Migrations)

> **Uso:** lista imutável usada pelo coordenador do `/reaudit` como baseline para set-difference após a re-auditoria. **Este arquivo NÃO é enviado ao agente especialista** — ver skill `audit-prompt`.

**Data da consolidação:** 2026-04-17
**Origem:** `docs/audits/camada-1-rodada-1-2026-04-17.md` (Rodada 1) + `docs/audits/camada-1-rodada-2-2026-04-17.md` (Rodada 2) + arquivos por expert em `docs/audits/*r{1,2}.json` e `docs/audits/camada-1/rodada-3/`.

## Instruções para o coordenador

Ao rodar `/reaudit "Camada 1"`, cada finding deste arquivo pode cair em um de três conjuntos após a re-auditoria:

1. **Resolvido** — não apareceu no output do expert (o problema desapareceu do código).
2. **Não resolvido** — expert encontrou o mesmo problema (match por arquivo:linha + descrição).
3. **Regressão não se aplica** — finding aceito como dívida documentada em `TECHNICAL-DECISIONS.md` (coluna "Tratamento documentado" ≠ vazio).

Achados do expert que **não** batem com nenhum ID abaixo = **novos findings**.

## Legenda de severidade

- **S0** — bloqueador de decisão (exige input produto/legal)
- **S1** — crítico (bloqueia merge)
- **S2** — alto (bloqueia fechamento de camada)
- **S3** — médio (dívida rastreável)
- **S4** — baixo (cosmético/informacional)

---

## Findings originais — Rodada 1 (2026-04-17)

### S0 — decisão necessária (3)

| ID | Título | Arquivo/escopo | Tratamento documentado |
|---|---|---|---|
| DATA-015 | Padrão arquitetural multi-tenant (schema separation vs row-level) | global | `TECHNICAL-DECISIONS.md §14.1` |
| SEC-008 | LGPD — base legal, consentimento e retenção de dados | `lgpd_consents`, `data_retention_policies` | `TECHNICAL-DECISIONS.md §14.2` |
| PROD-014 | PRD desatualizado vs schema (450 tabelas, 24 RFs) | `docs/PRD-KALIBRIUM.md` | `TECHNICAL-DECISIONS.md §14.3` (rebaixado a S3) |

### S1 — críticos (11 + 1 faltante na tabela original)

| ID | Título | Arquivo:linha |
|---|---|---|
| DATA-001 | 340 de 433 tabelas com `tenant_id` sem índice | múltiplos — schema global |
| DATA-003 | 33 child tables sem `tenant_id` (vazamento via parent) | múltiplos |
| SEC-001 | `payment_gateway_configs.api_key/api_secret` em plain text | `backend/database/migrations/2026_02_14_070000_create_remaining_module_tables.php:80` |
| SEC-002 | `user_2fa.secret` em plain text (TOTP + backup_codes) | `backend/database/migrations/2026_02_14_060000_create_portal_integration_security_tables.php:222` |
| SEC-003 | `marketing_integrations.api_key` em plain text | `backend/database/migrations/2026_02_14_060000_create_portal_integration_security_tables.php:209` |
| SEC-004 | `whatsapp_configs.api_key` em plain text | `backend/database/migrations/2026_02_14_060000_create_portal_integration_security_tables.php` |
| SEC-005 | OAuth `client_secret` em plain text | `backend/database/migrations/2026_02_14_060000_create_portal_integration_security_tables.php:183` |
| SEC-006 | `webhooks.secret` / `inmetro_webhooks.secret` / `fiscal_webhooks.secret` em plain text | `backend/database/migrations/2026_02_13_170000_inmetro_v3_50features.php:121` |
| SEC-007 | CPF/CNPJ em plain text em `customers`, `suppliers`, `employee_dependents`, `users` | `backend/database/migrations/2026_02_07_300000_create_cadastros_tables.php:39` |
| GOV-001 | 42 migrations ALTER/add sem guards `hasColumn`/`hasTable` (Iron Protocol H3) | múltiplos |
| GOV-002 | Migration `update_to_english` mantém PT no enum down() | `backend/database/migrations/2026_03_16_600001_update_standard_weight_status_to_english.php:30-43` |

> Rodada 1 declara "12 S1" mas a tabela consolidada listou 11. 1 S1 pode estar anexado aos JSON dos experts e não foi capturado na consolidação. Coordenador deve confirmar diretamente em `docs/audits/camada-1-rodada-1-2026-04-17.md` antes do set-difference.

### S2 — altos (21)

| ID | Título resumido | Arquivo/tabela |
|---|---|---|
| DATA-002 | 104 tabelas com `deleted_at` sem índice | global |
| DATA-004 | `audit_logs.tenant_id` NULLABLE | `audit_logs` |
| DATA-005 | 279 `onDelete cascade` vs 11 `restrict` (cascata em críticos) | múltiplas migrations |
| DATA-007 | Falta UNIQUE composto `(tenant_id, document)` em `customers`/`suppliers` | `customers`, `suppliers` |
| DATA-009 | `inmetro_v3.secret` sem cast encrypted | `inmetro_v3_50features` migration |
| DATA-013 | 44 models `SoftDeletes` trait sem coluna `deleted_at` | múltiplos models |
| SEC-009 | 40 tabelas de domínio sem coluna `tenant_id` (suspeita vazamento) | múltiplos |
| SEC-010 | 69 tabelas com `tenant_id` NULLABLE | múltiplos |
| SEC-011 | ~50 tabelas `tenant_id` sem índice individual (DoS amplificador) | múltiplos |
| SEC-012 | FK `tenant_id ON DELETE CASCADE` em 100% das tabelas (637 ocorrências) | global |
| PROD-001 | `equipment_calibrations.result` DEFAULT `'aprovado'` (PT) | `backend/database/schema/sqlite-schema.sql:2608` |
| PROD-002 | `central_items.status`/`prioridade`/`visibilidade` DEFAULTS PT-MAIÚSCULO | `central_items` |
| PROD-003 | Família `central_*` (11 cols × 5 tabelas) com nomes PT | `backend/database/schema/sqlite-schema.sql:853` |
| PROD-004 | `customer_locations` mistura colunas EN/PT (`nome_propriedade`, `endereco`, `bairro`, `cidade`) | `customer_locations` |
| PROD-005 | `expenses` tem AMBOS `created_by` E `user_id` (duplicidade) | `expenses` |
| GOV-003 | Enum `standard_weights.status` criado com valores PT | `2026_02_12_200000_create_standard_weights_tables.php` |
| GOV-004 | `expenses.user_id` adicionado violando convenção `created_by` | `2026_03_17_070000_add_missing_columns_for_tests.php` |
| GOV-005 | `travel_expense_reports` usa `user_id` em vez de `created_by` | `travel_expense_reports` |
| GOV-009 | Drift legado `company_id` em correção (validar conclusão) | múltiplos |
| GOV-014 | Migration `add_missing_columns_for_tests` viola Lei 3 (completude) | `2026_03_17_070000_add_missing_columns_for_tests.php` |

### S3 — médios (23)

| ID | Título | Arquivo/tabela |
|---|---|---|
| DATA-006 | 6 migrations `Schema::table` sem guards (subset de GOV-001) | múltiplos |
| DATA-008 | 12 polymorphic `morphs()` sem FK no banco | múltiplos |
| DATA-010 | Inconsistência precision em decimais financeiros | múltiplos |
| DATA-011 | Timestamps sem timezone (risco trabalhista CLT) | global |
| DATA-014 | Migration `fix_production_schema_drifts` indica gap de CI | `2026_04_10_500000_fix_production_schema_drifts.php` |
| SEC-013 | `accounts_payable`/`accounts_receivable` sem `created_by`/`updated_by` | `accounts_payable`, `accounts_receivable` |
| SEC-014 | `audit_logs.tenant_id` NULLABLE (duplicado de DATA-004) | `audit_logs` |
| SEC-015 | `client_portal_users` sem hardening (lockout/history) | `client_portal_users` |
| SEC-016 | Spatie permission `teams` precisa validação | `permissions` config |
| SEC-020 | 2FA `backup_codes` em plain (devem ser hashed) | `user_2fa` |
| PROD-006 | Terminologia inconsistente entre módulos | múltiplos |
| PROD-007 | Duplicação de domínio (módulos redundantes) | múltiplos |
| PROD-008 | Gap PRD reverso — módulos não documentados | `docs/PRD-KALIBRIUM.md` |
| PROD-009 | Gap PRD reverso — RF faltante | `docs/PRD-KALIBRIUM.md` |
| PROD-010 | Gap PRD reverso — AC faltante | `docs/PRD-KALIBRIUM.md` |
| PROD-011 | Gap PRD reverso — regra de negócio faltante | `docs/PRD-KALIBRIUM.md` |
| PROD-012 | Gap PRD reverso — fluxo faltante | `docs/PRD-KALIBRIUM.md` |
| PROD-013 | `schedules.technician_id` correto, `work_schedules`/`work_order_technicians` usam `user_id` | múltiplos |
| GOV-007 | Timestamps anômalos em migrations | múltiplos |
| GOV-008 | Código comentado em migrations | múltiplos |
| GOV-010 | Conflitos de timestamp duplicados | múltiplos |
| GOV-011 | Convenções de naming inconsistentes | múltiplos |
| GOV-012 | Comentários de "desativação" em migrations | múltiplos |

### S4 — baixos (5)

| ID | Título | Arquivo/tabela |
|---|---|---|
| DATA-012 | Charset `utf8` (sem mb4) em 2 connections secundárias | config/database.php |
| DATA-016 | Sem schema dump consolidado para MySQL | `backend/database/schema/` |
| SEC-017 | Refinos auditoria — `DB::raw` constantes internas em Analytics | `AnalyticsController` |
| SEC-018 | 9 migrations alteram `users` (informacional) | múltiplos |
| SEC-019 | Cascade chains DoS (informacional) | múltiplos |
| GOV-006 | Cosmetic naming | múltiplos |
| GOV-013 | Cosmetic naming | múltiplos |

---

## Findings novos — Rodada 2 (pós-Wave 1)

Introduzidos pelas correções da Wave 1 / 1B.

| ID | Sev | Título | Arquivo/escopo |
|---|---|---|---|
| DATA-NEW-001 / GOV-R2-015 | S1 | Schema dump SQLite stale (faltam ~42 migrations de abril) | `backend/database/schema/sqlite-schema.sql` |
| GOV-R2-016 | — | (detalhes em `docs/audits/camada-1-governance-r2.json`) | — |
| SEC-021 | S3 | `document_hash`/`cpf_hash` faltam em `$hidden` — vazam em response JSON | Models `Customer`, `Supplier`, `User`, `EmployeeDependent` |
| SEC-022 | S3 | `BelongsToTenant` ainda usa event listener — quebra com `Event::fake()` | `app/Traits/BelongsToTenant.php` |
| SEC-023 | S3 | Migration backfill síncrono pesado — risco lock em produção | `2026_04_17_120000_add_document_hash_for_encrypted_search.php` |
| SEC-024 | S3 | `hashSearchable()` estático com lista hardcoded — fragiliza extensão | models PII |
| SEC-NEW-025 | S1 | (detalhe em `docs/audits/_audit_camada1_security_findings.json`) | client portal |
| DATA-NEW-006 | S2 | 52 tabelas órfãs (accounts_payable, work_order_items, audit_logs hot-path) | múltiplos |
| PROD-015 | S2 | `PaymentGatewayConfig.fillable` e `MarketingIntegration.fillable` expõem `tenant_id` (mass-assignment) | `PaymentGatewayConfig`, `MarketingIntegration` |
| PROD-016 | — | (detalhe em `docs/audits/camada-1-product-expert-r2.json`) | — |

---

## Findings da Rodada 3 (pós-Wave 6)

Rodada 3 teve apenas `product-expert-r3.json` em `docs/audits/camada-1/rodada-3/`. Coordenador deve ler esse JSON e incorporar à baseline antes do set-difference — ou tratar como "última rodada conhecida antes da re-auditoria canônica".

---

## Mapa de correções alegadas (informativo — NÃO para o agente)

Consolidado a partir do handoff `handoff-camada-1-fechada-wave6-completa-2026-04-17.md`. **Uso exclusivo do coordenador** — serve para mapear qual wave supostamente tratou qual finding. Re-auditoria confirma ou reabre cada um.

| Wave / decisão | IDs alegadamente tratados |
|---|---|
| §14.1 / DevOps | DATA-015, DATA-016 |
| §14.2 | SEC-008 |
| §14.3 | PROD-014 |
| Wave 1 (encryption) | SEC-001..007, DATA-009, SEC-020 |
| Rodada 2 fix | 6 issues Wave 1 (document_hash, BelongsToTenant observer, hidden, backfill) |
| Wave 2A | DATA-003, SEC-009 |
| Wave 2B-fix | SEC-015 (pivots M2M) |
| Wave 2C | DATA-001, SEC-011 |
| Wave 2D | DATA-002 |
| Wave 2E | DATA-NEW-006 |
| Wave 3 (segurança) | SEC-NEW-025, SEC-015 (hardening), SEC-013 |
| §14.7 | DATA-008 |
| §14.8 | DATA-011 |
| §14.9 | DATA-014 |
| §14.10 | DATA-010 |
| §14.11 | DATA-007 |
| §14.12 | DATA-013 |
| Wave 6.1 | GOV-003 |
| Wave 6.2 | PROD-001 |
| Wave 6.3 | PROD-004 |
| Wave 6.4 | PROD-005 / GOV-004 |
| Wave 6.5 | GOV-005 |
| Wave 6.6 | PROD-002 |
| Wave 6.7 | PROD-003 |
| Wave 6.8 | GOV-002 |

### Aceitos por Lei H3 / fósseis / informacional (não bloqueia)

| ID | Motivo |
|---|---|
| GOV-001 | 42 migrations antigas sem H3 guards — fósseis imutáveis (Lei H3) |
| GOV-006..014 | Timestamps anômalos, código comentado — cosmético |
| SEC-017 | DB::raw com constantes internas — sem injection possível |
| SEC-018 | 9 migrations alteram users — informacional |
| DATA-006 | Subset de GOV-001 |

---

## Campos sem correção alegada (confiar mais na re-auditoria)

Findings cuja correção não está explicitamente mapeada no handoff — a re-auditoria é o único caminho para saber o estado:

- DATA-004, DATA-005, DATA-012
- SEC-010, SEC-012, SEC-014, SEC-016
- PROD-006, PROD-007, PROD-008, PROD-009, PROD-010, PROD-011, PROD-012, PROD-013, PROD-016
- GOV-009, GOV-014, GOV-R2-016

---

## Referências

- `docs/audits/camada-1-rodada-1-2026-04-17.md` — consolidado Rodada 1
- `docs/audits/camada-1-rodada-2-2026-04-17.md` — consolidado Rodada 2
- `docs/audits/_audit_camada1.json` — findings brutos (data-expert R1)
- `docs/audits/_audit_camada1_security_findings.json` — security-expert R1
- `docs/audits/camada-1-governance-r2.json` — governance R2
- `docs/audits/camada-1-product-expert-r1.json` — product R1
- `docs/audits/camada-1-product-expert-r2.json` — product R2
- `docs/audits/camada-1/rodada-3/product-expert-r3.json` — product R3
- `docs/TECHNICAL-DECISIONS.md` §14.1–§14.13 — decisões duráveis
- `docs/handoffs/handoff-camada-1-fechada-wave6-completa-2026-04-17.md` — mapa de correções alegadas (informativo)
