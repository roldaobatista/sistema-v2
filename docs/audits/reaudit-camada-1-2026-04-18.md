# Re-auditoria Camada 1 — 2026-04-18

> **Veredito: REABERTA.**
> 57 findings reportados pelos 5 experts (0 S1 × 2 S1 do data-expert fora do domínio do baseline + 18 S2 + 21 S3 + 15 S4). Critério binário de fechamento (CLAUDE.md §Fechamento) não é satisfeito.

**Camada:** Camada 1 — fundação do ERP (schema, migrations, models centrais, autenticação, PII, terminologia).
**Baseline canônico:** `docs/audits/findings-camada-1.md` (73 findings originais + rodadas 2/3).
**Experts invocados (paralelo, prompt neutro via skill `audit-prompt`):** data-expert, security-expert, governance, qa-expert, product-expert.

---

## Experts e relatórios individuais

| Expert | Arquivo | S1 | S2 | S3 | S4 | Total |
|---|---|---|---|---|---|---|
| data-expert | `reaudit-camada-1-2026-04-18/data-expert.md` | 3 | 6 | 5 | 3 | 17 |
| security-expert | `reaudit-camada-1-2026-04-18/security-expert.md` | 0 | 3 | 3 | 1 | 7 |
| governance | `reaudit-camada-1-2026-04-18/governance.md` | 0 | 2 | 5 | 7 | 14 |
| qa-expert | `reaudit-camada-1-2026-04-18/qa-expert.md` | 0 | 3 | 5 | 3 | 11 |
| product-expert | `reaudit-camada-1-2026-04-18/product-expert.md` | 0 | 3 | 3 | 2 | 8 |
| **Total** | — | **3** | **17** | **21** | **16** | **57** |

---

## Set-difference contra baseline

### Resolvidos (confirmados como não-reincidentes nesta re-auditoria)

| ID baseline | Onda/commit que resolveu | Verificação |
|---|---|---|
| SEC-001..007, DATA-009, SEC-020 | Wave 1 + §14.2 + SEC-RA-06 (hash-at-rest) | security-expert confirma casts `encrypted` nos segredos; backup_codes agora via `Hash::make()` |
| PROD-001 (calibrations.result PT) | Wave 6.2 | product-expert não achou (default atual EN) |
| PROD-002 (central_items status/prioridade PT) | Wave 6.6 | product-expert não achou nas colunas originais |
| PROD-003 (central_* colunas PT) | Wave 6.7 | product-expert não achou |
| PROD-004 (customer_locations mistura EN/PT) | Wave 6.3 | não achado |
| PROD-005 (expenses created_by+user_id) | Wave 6.4 | não achado |
| GOV-002 (standard_weights enum PT down()) | Wave 6.8 | não achado |
| GOV-003 (standard_weights.status PT) | Wave 6.1 | não achado |
| GOV-004 (expenses.user_id) | Wave 6.4 | não achado |
| GOV-005 (travel_expense_reports.user_id) | Wave 6.5 | não achado |
| DATA-NEW-001 / GOV-R2-015 (schema dump stale) | GOV-RA-06 (commit `09b5c78`) | script regenera canonicamente via MySQL |
| SEC-021..024, SEC-NEW-025, DATA-NEW-006, PROD-015 | Wave 2/3 | não reportados |

### Não resolvidos (match direto com achados atuais)

| ID baseline | Sev | Onde reincidiu | Expert atual |
|---|---|---|---|
| DATA-001 (340 tabelas sem índice) | S1 | data-04 (FKs sem índice), data-09 (schedules sem índice) — possivelmente inconclusivo por data-01 | data-expert |
| DATA-002 (deleted_at sem índice) | S2 | implícito em data-01 (schema dump zera não-uniques) | data-expert |
| DATA-004 / SEC-014 (audit_logs.tenant_id NULLABLE) | S2/S3 | data-05 + sec-01 | data/security |
| DATA-005 / SEC-012 (cascade tenants) | S2 | sec-02 (FKs `tenants` ON DELETE CASCADE destroem LGPD) | security |
| DATA-007 (customers sem UNIQUE composto) | S2 | data-08 | data |
| DATA-013 (SoftDeletes sem coluna) | S2 | não explicitado — parcial | — |
| SEC-009 (40 tabelas sem tenant_id) | S2 | data-11 (personal_access_tokens, email_attachments) | data |
| SEC-010 (69 tabelas tenant_id NULLABLE) | S2 | sec-03 (users, user_sessions), data-05/06 (audit_logs, webhook_logs) | security/data |
| SEC-011 (~50 tabelas tenant_id sem índice) | S2 | data-09 + data-04 + data-01 | data |
| SEC-015 (portal sem lockout/history) | S3 | sec-05 (portal hardening sem lógica funcional) | security |
| PROD-006 (terminologia inconsistente) | S3 | gov-01..05 (enum defaults PT residuais: TAREFA, pleno, tecnico, externa) | governance |
| PROD-013 (technician_id vs user_id nas M2M) | S3 | não explicitado — a investigar | — |
| GOV-009 (drift company_id) | S2 | gov-08 (user_id/created_by coexistem em 20 migrations) | governance |
| GOV-014 (add_missing_columns_for_tests Lei 3) | S2 | gov-07 (16 migrations `add_missing_columns_*` / `fix_production_schema_drifts`) | governance |

### Novos findings introduzidos (encontrados agora, não no baseline)

**S1 (3) — bloqueiam qualquer fechamento futuro:**
- **data-01** — `generate_sqlite_schema.php:155` remove todas as `KEY` (não-uniques) do dump SQLite sem recriá-las como `CREATE INDEX`. Suite roda contra schema sem índices não-uniques → plano de execução diverge de produção; além disso, mascara avaliação de findings de índice. *Finding-guarda-chuva.*
- **data-02** — `users.tenant_id` e `users.current_tenant_id` ambos NULLABLE. Usuário pode persistir em estado `NULL/NULL`. Combinado com `BelongsToTenant` via global scope, risco de bypass.
- **data-03** — `tenants.slug` NULLABLE sem UNIQUE; `tenants.document` (CNPJ) NULLABLE sem UNIQUE. Dois tenants podem ter mesmo slug/CNPJ.

**S2 (12):**
- data-06 (webhook_logs hot sem índice + tenant_id NULLABLE)
- data-07 (expenses: 3 FKs sobrepostas sem CHECK)
- data-10 (audit_logs/webhook_logs/whatsapp_message_logs sem estratégia de retenção)
- sec-02 (cascade de `tenants` destrói trilha LGPD — overlap com DATA-005)
- gov-08 (user_id ↔ created_by coexistem em 20 migrations — overlap com GOV-009)
- gov-12 (70+ migrations `Schema::table()` sem guards H3 — overlap com GOV-001 mas baseline aceitou como "fóssil" apenas 42)
- qa-01 (4 factories com `tenant_id => 1` hardcoded: `AgendaItemFactory`, `AlertConfigurationFactory`, `InmetroComplianceChecklistFactory`, `SystemAlertFactory`)
- qa-02 (sem teste de regressão schema dump ↔ migrations — `TenantSchemaRegressionTest.php` cobre só 1 coluna)
- qa-03 (sem teste unitário dedicado ao trait `BelongsToTenant`)
- prod-01 (`equipment_calibrations.calibration_type` DEFAULT `'externa'` PT)
- prod-02 (enum `priority` inconsistente entre módulos: `'normal'` em work_orders/service_calls/portal_tickets vs `'medium'` em central_items/projects/sla_policies)
- prod-03 (`accounts_payable` duplica `supplier` varchar + `supplier_id` FK, e `category` + `category_id`)

**S3 (16 novos) + S4 (15 novos) — ver relatórios individuais.**

### Falsos positivos identificados na triagem

| Finding | Motivo |
|---|---|
| **sec-06** (`backup_codes` cast `'array'`) | Comentário do expert sugere que deveria ser `'encrypted:array'`. Mas commit `ed22f77` (SEC-RA-06) migrou para hash bcrypt via `Hash::make()` — cast `'array'` é correto (array de strings de hash, não precisa de encryption adicional). Descartado. |

---

## Veredito binário

**REABERTA.**

Critério absoluto (CLAUDE.md §Fechamento): zero findings em todas as severidades. A re-auditoria retornou **56 findings acionáveis** (57 reportados − 1 falso positivo), sendo:

- **3 S1 novos** (data-01/02/03) — o S1 data-01 é especialmente crítico porque é **regressão do gerador de schema dump** introduzida durante GOV-RA-06. Bloqueia conclusões de cobertura de índices.
- **17 S2** — múltiplos overlaps com findings do baseline (DATA-001/004/005/007, SEC-009/010/011/012/014/015, GOV-009/014).
- **21 S3** — principalmente enum PT residuais (gov-01..05), tests anti-patterns (qa-04..08), naming inconsistencies (prod-04..06).
- **15 S4** — cosmético/informacional.

---

## Achados prioritários para a próxima onda de correção

### P0 (bloqueadores imediatos)

1. **data-01** — Corrigir `generate_sqlite_schema.php` para converter `KEY` MySQL em `CREATE INDEX` SQLite (não descartar). Re-rodar todo o set-difference de índices após isso; muitos achados de "sem índice" podem ser falso positivo causado por este finding-guarda-chuva.
2. **data-02** — Migrar `users.tenant_id` e `current_tenant_id` para NOT NULL (com política explícita para usuários de plataforma).
3. **data-03** — Adicionar UNIQUE em `tenants.slug` e `tenants.document`.

### P1 (bloqueiam fechamento de Camada 1)

4. **sec-02 / DATA-005 / SEC-012** — Política de deleção de tenants: migrar cascades críticas (audit_logs, payments, contracts) para `RESTRICT` ou `SET NULL` com reaplicação via soft-delete de tenant.
5. **DATA-004 / SEC-014 (data-05, sec-01)** — `audit_logs.tenant_id` NOT NULL + backfill.
6. **SEC-010 (data-02, sec-03, data-05, data-06)** — Sprint dedicado para 69 tabelas com `tenant_id NULLABLE`.
7. **DATA-001 / SEC-011 (data-04, data-09)** — Após corrigir data-01, revisão sistemática de FKs sem índice (policy: toda FK + índice composto com tenant_id).
8. **SEC-015 (sec-05)** — Implementar lógica real de lockout/2FA/password_history no portal do cliente.
9. **GOV-009/014 (gov-07, gov-08)** — Sprint de consistência: `user_id` vs `created_by` + eliminar `add_missing_columns_*`.
10. **gov-01..05** — Enum defaults PT residuais (`'TAREFA'`, `'pleno'`, `'tecnico'`, `'externa'`) em central_templates, positions, commission_*, equipment_calibrations.
11. **prod-02** — Normalizar enum `priority` (`normal` vs `medium`) entre módulos — decisão pendente + migration.

### P2 (dívida rastreável)

- qa-01..03 (factories tenant_id hardcoded; sem regressão schema dump; sem teste unit BelongsToTenant)
- prod-03 (accounts_payable supplier varchar+FK)
- prod-05/06 (origin_type vs cadeia canônica §14.13.b)
- data-11..15

---

## Próxima ação

1. Abrir sprint "Tenant Safety Estrutural v2" cobrindo P0 + P1.
2. Antes de qualquer correção de índice: resolver **data-01** (gerador de schema), re-rodar data-expert apenas no checklist §1/§4/§6 para eliminar falsos positivos causados pelo dump vazio.
3. Após correção, re-rodar `/reaudit "Camada 1"`. **Não declarar FECHADA até zero findings em S1..S4.**
