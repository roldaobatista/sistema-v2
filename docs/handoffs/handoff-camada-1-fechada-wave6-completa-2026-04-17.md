# Handoff — Camada 1 FECHADA (Wave 6 Completa)

**Data:** 2026-04-17
**Estado:** Wave 6 completa (6.1–6.9), suite verde, Camada 1 fechada
**Branch:** main (ahead de sistema-v2/main por N commits)
**Último commit:** `4c0c0ba docs(tech-decisions): §14.13 convenção EN-only + compat PT (Wave 6.9)`

## 19 commits stabilize totais da Camada 1

```
4c0c0ba docs(tech-decisions): §14.13 convenção EN-only + compat PT (Wave 6.9)
f17b53b Wave 6.8  - fechar resíduos update_to_english (GOV-002)
bffe8a1 Wave 6.7  - central_* colunas PT→EN (PROD-003) [67 arquivos]
8cfb8cf Wave 6.6  - central_items defaults PT→EN (PROD-002)
cdf52cf Wave 6.5  - travel_expense_reports.user_id → created_by (GOV-005)
7ec2161 Wave 6.4b - fix expense->user_id → created_by em consumers
eb42b5b Wave 6.4  - drop expenses.user_id duplicate (PROD-005/GOV-004)
5c69246 Wave 6.3  - drop PT columns customer_locations (PROD-004)
3462350 Wave 6.2  - equipment_calibrations.result PT→EN (PROD-001)
981af4c Wave 6.1  - standard_weights.shape PT→EN (GOV-003)
0e10764 Wave 5    - quick wins (DATA-010 + DATA-013 + DATA-007)
eb34629 Wave 4    - CI gate + timezone + polymorphic (§14.7-§14.9)
6fc9619 Wave 3    - segurança (SEC-NEW-025 + SEC-015 + SEC-013)
a659cf2 Wave 2E   - 52 tabelas órfãs (DATA-NEW-006)
5b36529 Wave 2D   - 113 índices deleted_at (DATA-002)
ac591d1 Wave 2C   - 292 índices tenant_id (DATA-001 + SEC-011)
21f26e2 Wave 2B   - 66 tabelas tenant_id NOT NULL + revert pivots
d9af381 Wave 2A   - tenant_id em 12 tables (DATA-003 + SEC-009)
80787ca Rodada 2  - fix issues Wave 1
86564cc Wave 1    - encryption + hash determinístico + meta tests
```

## Suite final

**9752 passed / 0 failed** (igual baseline pré-Wave 6 — zero regressão)
Tempo: ~260s, 16 processos paralelos, SQLite in-memory, 471 migrations

## Estado arquitetural pós-Camada 1

- **521 tabelas, 657 índices, 471 migrations** (era 461 antes da Camada 1)
- **12 models com encryption-at-rest**
- Traits `HasEncryptedSearchableField`, `HasAuditUserFields`
- Job `BackfillDocumentHashJob` + Command `BackfillDocumentHashCommand`
- CI gate de schema dump ativo em `.github/workflows/ci.yml`
- **TECHNICAL-DECISIONS.md §14.1 até §14.13** — 13 seções de decisões duráveis

## Findings da Rodada 1 Camada 1 — status final

### ✅ RESOLVIDOS (S0–S2)

| ID | Descrição | Wave |
|---|---|---|
| DATA-015, DATA-016 | Padrão multi-tenant + MySQL dump | §14.1 / DevOps |
| SEC-008 | LGPD base legal | §14.2 |
| PROD-014 | PRD vs schema sync | §14.3 |
| DATA-003, SEC-009 | tenant_id em standalone tables | 2A |
| SEC-015 | Pivots M2M NULLABLE | 2B-fix |
| DATA-001, SEC-011 | 292 índices tenant_id | 2C |
| DATA-002 | 113 índices deleted_at | 2D |
| DATA-NEW-006 | 52 tabelas órfãs | 2E |
| SEC-NEW-025, SEC-015, SEC-013 | Segurança portal + hardening + audit | 3 |
| DATA-008 | Polymorphic sem FK | §14.7 |
| DATA-011 | Timezone MySQL | §14.8 |
| DATA-014 | CI gate schema dump | §14.9 |
| DATA-010 | Precisão decimal | §14.10 |
| DATA-007 | UNIQUE composto SoftDeletes | §14.11 |
| DATA-013 | SoftDeletes false positive | §14.12 |
| **GOV-003** | standard_weights.shape PT→EN | **6.1** |
| **PROD-001** | equipment_calibrations.result PT→EN | **6.2** |
| **PROD-004** | customer_locations PT drop | **6.3** |
| **PROD-005 / GOV-004** | expenses.user_id duplicate drop | **6.4** |
| **GOV-005** | travel_expense_reports.user_id → created_by | **6.5** |
| **PROD-002** | central_items defaults PT→EN | **6.6** |
| **PROD-003** | central_* colunas PT→EN (11 cols × 5 tabelas) | **6.7** |
| **GOV-002** | update_to_english resíduos (visit_type, channel) | **6.8** |

### ⚪ ACEITOS por Lei H3 ou fósseis/informacional (não bloqueia)

- GOV-001 (42 migrations antigas sem H3 guards)
- GOV-006..014 (timestamps anômalos, código comentado)
- SEC-017 (DB::raw constantes internas em Analytics)
- SEC-018 (9 migrations users informacional)
- DATA-006 (subset GOV-001)

## Dívida aceita §14.13 para ciclo futuro

1. **AgendaItemResource aliases PT** — Resource emite chaves EN canônicas + duplicatas PT (titulo, prioridade, etc) para não quebrar frontend. Remover quando frontend estiver 100% migrado.
2. **Model aliases PT** — `normalizeLegacyAliases()` aceita payloads PT. Remover com frontend.
3. **`QuickNote.php` labels PT** em mapa de tradução UI.
4. **Variáveis PHP internas** com nomes PT em alguns FormRequests (cosmético).
5. **Frontend não migrado para campos EN** — types/agenda.ts, AgendaPage.tsx, KanbanPage ainda usam `titulo`, `prioridade`, etc. Compat funciona via Resource aliases. Migração frontend fica para ciclo futuro.

## Próxima camada: Camada 2 — Models + BelongsToTenant

Escopo esperado:
- Auditoria de models sem `BelongsToTenant`
- Validação de `withoutGlobalScope` com justificativa escrita
- Eager loading faltando (`->with([...])` obrigatório)
- `FormRequest::authorize()` com `return true` sem lógica (proibido per CLAUDE.md)
- Endpoints `index` sem paginação

**Comando sugerido:**
```
Iniciar Camada 2 — auditoria Models + BelongsToTenant + paginação + authorize.
Ler docs/plans/estabilizacao-bottom-up.md para escopo completo.
```

## Migrations Wave 6 (8 novas)

```
2026_04_17_240000_normalize_standard_weight_shape_to_english.php   (6.1)
2026_04_17_250000_normalize_calibration_result_to_english.php      (6.2)
2026_04_17_260000_drop_pt_columns_from_customer_locations.php      (6.3)
2026_04_17_270000_drop_user_id_from_expenses.php                   (6.4)
2026_04_17_280000_rename_user_id_to_created_by_in_travel_expense_reports.php (6.5)
2026_04_17_290000_normalize_central_enums_defaults_to_english.php  (6.6)
2026_04_17_300000_rename_central_pt_columns_to_english.php         (6.7)
2026_04_17_310000_rename_central_source_to_origin.php              (6.7 fix)
2026_04_17_320000_rename_central_rules_pt_columns.php              (6.7 cont.)
2026_04_17_330000_normalize_visit_report_visit_type_to_english.php (6.8)
```

Todas H3-compliant com guards `hasTable`/`hasColumn`.
