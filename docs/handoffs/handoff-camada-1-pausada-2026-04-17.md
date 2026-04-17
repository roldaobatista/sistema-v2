# Handoff — Camada 1 Pausada para Retomada

**Data:** 2026-04-17
**Estado:** ~85% concluída, suite verde, pronta para Wave 6 em sessão dedicada
**Branch:** main (sincronizada com origin + sistema-v2)
**Último commit:** `0e10764 stabilize(layer-1): Wave 5 - quick wins (DATA-010 + DATA-013 + DATA-007)`

## Como retomar

```bash
cd C:/PROJETOS/sistema
/resume
# Ler este handoff + docs/plans/estabilizacao-bottom-up.md
# Decidir Wave 6 (PT/EN) ou pular para Camada 2
```

## 10 commits stabilize executados nesta sessão

```
0e10764 Wave 5  - quick wins (DATA-010 precisão decimal + DATA-013 false positive + DATA-007 UNIQUE composto)
eb34629 Wave 4  - CI gate schema dump + timezone MySQL + polymorphic decisão (§14.7-§14.9)
6fc9619 Wave 3  - segurança (SEC-NEW-025 H1 + SEC-015 hardening estrutural + SEC-013 audit trail)
a659cf2 Wave 2E - 52 tabelas órfãs (DATA-NEW-006: hot-path accounts_payable, work_order_items, audit_logs)
5b36529 Wave 2D - 113 índices deleted_at (DATA-002)
ac591d1 Wave 2C - 292 índices tenant_id (DATA-001 + SEC-011, parcial fechado em 2E)
21f26e2 Wave 2B - 66 tabelas tenant_id NOT NULL + revert 12 pivots M2M (§14.5)
d9af381 Wave 2A - tenant_id em 12 standalone tables (DATA-003 + SEC-009)
80787ca Rodada 2- fix 6 issues introduzidos pela Wave 1
86564cc Wave 1  - encryption casts + searchability hash determinístico + meta tests fix
```

## Suite

**9752 passed / 0 failed** (sempre verde ao fim de cada wave)
Tempo médio: 230-260s, 16 processos paralelos, SQLite in-memory

## Migrations adicionadas (12 novas)

```
2026_04_17_120000_add_document_hash_for_encrypted_search.php   (Wave 1B)
2026_04_17_140000_add_tenant_id_to_tenant_safe_tables.php       (Wave 2A)
2026_04_17_150000_backfill_tenant_id_and_make_not_null.php      (Wave 2B)
2026_04_17_160000_revert_tenant_id_not_null_on_pivots.php       (Wave 2B-fix)
2026_04_17_170000_add_tenant_id_indexes_to_remaining_tables.php (Wave 2C)
2026_04_17_180000_add_deleted_at_indexes_to_remaining_tables.php(Wave 2D)
2026_04_17_190000_add_tenant_id_indexes_wave2e.php              (Wave 2E)
2026_04_17_200000_add_hardening_to_client_portal_users.php      (Wave 3)
2026_04_17_210000_add_updated_by_deleted_by_to_financial_tables (Wave 3)
2026_04_17_220000_normalize_monetary_precision.php              (Wave 5)
2026_04_17_230000_add_unique_composite_for_documents.php        (Wave 5)
```

Todas H3-compliant (guards `hasTable`/`hasColumn`/`indexExists`).

## TECHNICAL-DECISIONS.md §14.1-§14.12 (decisões duráveis)

- §14.1 Padrão multi-tenant único (BelongsToTenant + observer creating)
- §14.2 LGPD base legal art.7 V (execução de contrato B2B)
- §14.3 PRD evolui em paralelo, não bloqueia camadas
- §14.4 Tabelas system-wide (marketplace_partners + critérios de categorização)
- §14.5 Pivots M2M tenant_id NULLABLE (dívida arquitetural — SEC-015 — fix futuro via override `newPivot()`)
- §14.6 Client portal hardening estrutural (lógica de lockout/2FA pendente)
- §14.7 Polymorphic morphs() sem FK (dívida aceita — risco baixo, mitigação opcional Job)
- §14.8 Timezone MySQL (-03:00 = America/Sao_Paulo)
- §14.9 CI gate de schema dump (`.github/workflows/ci.yml`)
- §14.10 Padrão precisão decimal (TOTAL=15,2; ITEM=12,2; QUANTITY=15,4; PERCENTAGE=7,4)
- §14.11 UNIQUE com sentinela (GENERATED COLUMN STORED para coexistir com SoftDeletes)
- §14.12 DATA-013 falso positivo (76 models SoftDeletes, não 11)

## Findings remanescentes para próxima(s) sessão(ões)

### 🔴 Wave 6 — Convenções PT/EN (S2, ~2-3h, AFETA backend+frontend)

| ID | Tabela/coluna |
|---|---|
| PROD-001 | `equipment_calibrations.result` default `'aprovado'` (PT) |
| PROD-002 | `central_items.status='ABERTO'`, prioridade='MEDIA', visibilidade='EQUIPE' (PT) |
| PROD-003 | Família `central_*` colunas em PT (titulo, descricao, criado_por_user_id, etc) |
| PROD-004 | `customer_locations` mistura EN/PT (nome_propriedade, endereco, bairro, cidade) |
| PROD-005 | `expenses.user_id` duplicidade com created_by |
| GOV-002 | Migration `update_to_english` mantém PT no enum |
| GOV-003 | `standard_weights` enum em PT |
| GOV-004 | `expenses.user_id` (mesmo que PROD-005) |
| GOV-005 | `travel_expense_reports.user_id` em vez de created_by |

**Impacto:** renomear coluna afeta migration + model + casts + factories + controllers + tipos TS + componentes frontend + i18n. Cada renomeação = ~5-10 arquivos. Total estimado: 50-100 arquivos.

### 🟠 Wave 7 — Cascade audit (S2, ~2h)

- DATA-005: 179 ocorrências `cascadeOnDelete` sem auditoria caso-a-caso
- SEC-019 (sobrepõe): cascade chains em entidades de histórico financeiro/normativo
- Decisão: para tabelas críticas (calibrations, invoices, accounts_*) substituir cascade por restrict + soft-delete

### 🟡 Backlog produto (S3, sem prazo)

- PROD-006..013 + PROD-016: gaps PRD reverso (modules não documentados — gamification, telescope, on_call, jornadas, sensor_readings, etc)
- Documentar no PRD via `bmad-domain-research` ou similar

### ⚪ Aceitos por Lei H3 ou informacional (não bloqueia)

- GOV-001 (42 migrations antigas sem H3 guards) — fósseis imutáveis
- GOV-006..014 (timestamps anômalos, código comentado, conflitos timestamp) — fósseis
- SEC-017 (DB::raw constantes internas em Analytics) — risco real zero
- SEC-018 (9 migrations users) — informacional
- DATA-006 (subset de GOV-001)
- DATA-016 (MySQL dump consolidado) — DevOps task

## Comando para retomada da Wave 6

```
Invocar orchestrator com:

"Continuar estabilização Camada 1 — Wave 6 (Convenções PT/EN).

Ler docs/plans/estabilizacao-bottom-up.md (v1.2) e
docs/handoffs/handoff-camada-1-pausada-2026-04-17.md.

Findings:
- PROD-001..005, GOV-002..005

Estratégia:
- Para CADA coluna PT renomear via migration (add EN col + backfill +
  drop PT col em deploy seguro), atualizar Model casts/fillable,
  factories, controllers, tipos TS, frontend i18n.
- Multi-step: nova coluna primeiro, código usando ambas, drop antiga em
  segunda migration.
- Suite tem que ficar verde após cada coluna renomeada.

Após Wave 6 completa, fazer Rodada 4 (re-auditoria completa) para confirmar
fechamento da Camada 1 e iniciar Camada 2 (Models + BelongsToTenant)."
```

## Dúvida estratégica para o próximo turno

A **Wave 6 (PT/EN)** afeta colunas de domínio em produção. Antes de executar, confirmar com PO:
- Há clientes em produção com dados em colunas PT?
- O renomear vai exigir migração de dados ou apenas estrutural?
- API pública usa essas colunas? (breaking change para frontend)

Se sim a qualquer das 3, considerar manter PT como dívida documentada (§14.13) e seguir para Camada 2.

## Estado da arquitetura pós-sessão

- 521 tabelas, 657 índices, 461 migrations
- 12 models com encryption-at-rest
- 1 trait HasEncryptedSearchableField + 1 trait HasAuditUserFields
- 1 Job (BackfillDocumentHashJob) + 1 Command (BackfillDocumentHashCommand)
- 1 script alternativo (generate_sqlite_schema_from_artisan.php) para Windows sem Docker
- CI gate ativo em `.github/workflows/ci.yml`
- TECHNICAL-DECISIONS.md com 12 seções §14
