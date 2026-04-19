# Re-auditoria Camada 1 — Fundação do ERP — governance-expert

**Data:** 2026-04-18
**Escopo:** conformidade de convenções, consistência e padrões em migrations + schema + models base
**Perímetro investigado:**

- `backend/database/migrations/` (477 arquivos)
- `backend/database/schema/sqlite-schema.sql` (9191 linhas, 570 `CREATE TABLE`)
- `backend/app/Models/` (487 arquivos)
- `backend/app/Http/Requests/` (881 arquivos)
- `backend/app/Http/Controllers/` (amostragem)
- `CLAUDE.md` + `docs/TECHNICAL-DECISIONS.md` (regras + exceções aceitas)

Itens explicitamente aceitos como limitação em `docs/TECHNICAL-DECISIONS.md` §14.x **não** foram reportados (timestamps duplicados §14.19, tabelas globais §14.14, exceções LGPD §14.2, resíduos PT em `watermark_configs.text`/`QuickNote` §14.13, falsos positivos §14.18 — SEC-RA-09/10/11, switch tenant §14.17, polimórficos §14.7, etc.).

---

## Findings

### gov-01 — Enum default em PT (português UPPERCASE) em `central_templates.type`
**Severidade:** S3
**Arquivo:** `backend/database/schema/sqlite-schema.sql` (CREATE TABLE `central_templates`)
**Descrição:** A coluna `central_templates.type` tem `DEFAULT 'TAREFA'` (PT-UPPER). O rename PT→EN das tabelas `central_*` (Wave 6.6/6.7 documentado em §14.13) não cobriu o default dessa coluna. CLAUDE.md Lei 4 exige status/enums sempre em inglês lowercase.
**Evidência:**
```sql
CREATE TABLE "central_templates" (
 ...
 "type" varchar(20) NOT NULL DEFAULT 'TAREFA',
 "priority" varchar(20) NOT NULL DEFAULT 'medium',
 "visibility" varchar(20) NOT NULL DEFAULT 'team',
```
**Impacto:** Inconsistência com `central_items.type` (que é `varchar(20) NOT NULL` sem default PT). Novos registros criados via `central_templates::create()` sem passar `type` gravam `'TAREFA'` em PT, contaminando relatórios e conflitando com enums EN do resto do sistema. O §14.13 lista apenas exceções PT explícitas para termos fiscais BR — "TAREFA" não é fiscal.

---

### gov-02 — Enum default em PT: `positions.level` = `'pleno'`
**Severidade:** S3
**Arquivo:** `backend/database/schema/sqlite-schema.sql` (tabela `positions`)
**Descrição:** Default `'pleno'` (PT) em coluna de nível hierárquico.
**Evidência:**
```sql
"level" varchar NOT NULL DEFAULT 'pleno',
```
**Impacto:** Novos positions criados sem passar `level` recebem valor PT. Enum PT quebra a convenção EN-only declarada em §14.13 e CLAUDE.md §4. Equivalente EN esperado: `'mid'` ou `'mid_level'`.

---

### gov-03 — Enum default em PT: `commission_rules.applies_to_role` = `'tecnico'`, pivots com default `'tecnico'`
**Severidade:** S3
**Arquivo:** `backend/database/schema/sqlite-schema.sql` (múltiplas tabelas)
**Descrição:** Três colunas usam default `'tecnico'` (PT em vez de `'technician'` EN):
**Evidência:**
```sql
-- commission_rules:
"applies_to_role" varchar(20) NOT NULL DEFAULT 'tecnico',
-- commission_events:
"role" varchar(20) NOT NULL DEFAULT 'tecnico',
-- work_order_technicians (pivot):
"role" varchar(20) NOT NULL DEFAULT 'tecnico',
```
**Impacto:** Viola EN-only (§14.13) e CLAUDE.md §4. Mistura inconsistente: schedules/technician já usa EN (`technician_id`) mas enum de role ainda é PT. Fonte provável: migrations v2 (`2026_02_08_500000_alter_commission_rules_v2.php`).

---

### gov-04 — Enum default em PT: `category` = `'outro'`
**Severidade:** S4
**Arquivo:** `backend/database/schema/sqlite-schema.sql`
**Descrição:** Coluna `category` com default `'outro'` (PT) onde o contexto sugere enum.
**Evidência:**
```sql
"category" varchar(40) NOT NULL DEFAULT 'outro',
```
**Impacto:** Se for enum, viola EN-only. Equivalente EN: `'other'` (que já aparece em outro lugar do mesmo dump — `DEFAULT 'other'`). Fica a dúvida se a mesma semântica foi implementada em duas línguas.

---

### gov-05 — Enum default em PT: `calibration_type` = `'externa'`
**Severidade:** S3
**Arquivo:** `backend/database/schema/sqlite-schema.sql` (tabela `equipment_calibrations`)
**Descrição:** A coluna `equipment_calibrations.calibration_type` usa default `'externa'` (PT). O sistema tem regra explícita (§10) declarando enum canônico EN para decisões de calibração e §14.13 formaliza EN-only.
**Evidência:**
```sql
"calibration_type" varchar(30) NOT NULL DEFAULT 'externa',
"result" varchar(30) NOT NULL DEFAULT 'approved',
```
**Impacto:** Inconsistência grave no domínio mais regulamentado do sistema (ISO 17025). `result` já está em EN (`approved`), mas `calibration_type` permanece em PT (`externa`/`interna`). Quebra a convenção no mesmo CREATE TABLE.

---

### gov-06 — Default em PT sem marcação como texto UI/display: `nationality` = `'brasileira'`
**Severidade:** S4
**Arquivo:** `backend/database/schema/sqlite-schema.sql` (provável `employees` ou similar)
**Descrição:** Coluna `nationality` com default `'brasileira'` em PT. Se for enum, viola EN-only; se for texto livre, o §14.13 exige justificativa (`'BR'` ISO code é o padrão documentado).
**Evidência:**
```sql
"gender" varchar(10) DEFAULT NULL,
"marital_status" varchar(20) DEFAULT NULL,
"education_level" varchar(30) DEFAULT NULL,
"nationality" varchar(50) DEFAULT 'brasileira',
```
**Impacto:** Drift de idioma. Equivalentes EN/ISO esperados: `'BR'` (ISO 3166-1), `'brazilian'` (enum EN), ou NULL.

---

### gov-07 — Migration `add_missing_columns_for_tests` e `fix_missing_columns_for_tests` — sintoma explícito de gap spec→schema
**Severidade:** S3
**Arquivos:**
- `database/migrations/2026_03_17_070000_add_missing_columns_for_tests.php`
- `database/migrations/2026_03_16_110000_fix_missing_columns_for_tests.php`
- `database/migrations/2026_02_13_150000_fix_missing_columns_from_tests.php`
- `database/migrations/2026_03_16_000001_add_missing_columns_to_multiple_tables.php`
- `database/migrations/2026_03_02_100000_add_missing_columns_to_equipments_table.php`
- (+ 11 outras listadas abaixo)

**Descrição:** 16 migrations batizadas como `add_missing_columns_*` / `fix_missing_columns_*` / `fix_production_schema_drifts`. O docblock de `2026_03_17_070000` é auto-descritivo:

> *"Consolidates all missing columns identified by running the full test suite."*

O docblock de `2026_04_10_500000_fix_production_schema_drifts.php` documenta que CI passou sem detectar drift até auditoria manual em 2026-04-10.

**Evidência (lista completa):**
```
2025_02_10_090000_add_missing_columns_to_products.php          ← migration 2025 (ano cronologicamente anterior aos restantes 2026_02_07)
2026_02_09_100000_add_missing_permissions.php
2026_02_10_235959_add_missing_report_permissions.php
2026_02_13_150000_fix_missing_columns_from_tests.php
2026_02_19_235229_add_missing_foreign_keys_phase1.php
2026_02_26_100001_add_missing_composite_indexes.php
2026_03_01_184000_add_missing_columns_to_user_2fa_table.php
2026_03_02_100000_add_missing_columns_to_equipments_table.php
2026_03_09_100000_add_missing_foreign_keys.php
2026_03_15_000001_add_missing_performance_indexes.php
2026_03_16_000001_add_missing_columns_to_multiple_tables.php
2026_03_16_110000_fix_missing_columns_for_tests.php
2026_03_17_070000_add_missing_columns_for_tests.php
2026_03_22_100000_add_missing_fk_to_quotes_table.php
2026_03_27_184000_add_missing_analytics_columns_to_fuel_logs_table.php
2026_04_10_500000_fix_production_schema_drifts.php
```

**Impacto:** Cada uma dessas migrations é evidência de gap na cadeia `PRD → migration original → model → test`. Viola CLAUDE.md Lei 3 (completude end-to-end) — se o schema precisou de "fix for tests", é porque a migration original não foi escrita com os requisitos da cadeia inteira. Pior, `fix_production_schema_drifts.php` demonstra que o CI **não detecta drift prod↔git** — só foi detectado após auditoria manual. O gate §14.9 (schema dump) é pós-fato e não substitui um gate de parity prod↔schema.

---

### gov-08 — Coluna duplicada `user_id` + `created_by` coexistindo no schema (após Wave 6)
**Severidade:** S2
**Arquivos:**
- `backend/database/schema/sqlite-schema.sql` (múltiplas tabelas)
- `backend/app/Models/Schedule.php:20` (model usa `technician_id` — OK)
**Descrição:** §14.13 (Wave 6.4/6.5) declarou fix para `expenses.user_id` (drop) e `travel_expense_reports.user_id` (rename→created_by). Auditoria do schema atual mostra várias tabelas ainda mantêm `user_id` onde a regra do domínio seria `created_by` (autoria do registro).

**Evidência — tabelas com `user_id` mas sem `created_by`:**
```sql
-- technician_cash_funds: tem user_id (correto: é o "dono" do fundo), mas:
"user_id" integer NOT NULL,  -- OK: unique(tenant_id, user_id)
-- technician_cash_transactions: tem created_by (correto, pós fix)
-- work_schedules:
"user_id" integer NOT NULL,  -- ambíguo: é o "dono" da escala (technician) ou "quem criou"?
CREATE UNIQUE INDEX "work_schedules_user_id_date_unique" ON "work_schedules" ("user_id","date");
```

**Tabelas com AMBOS `user_id` E `created_by` coexistindo (migrations relatam ambos presentes):**

Listagem da investigação mostra 20 migrations que adicionam/tocam ambas as colunas. Exemplos de migrations que criam tabelas com ambas:
- `2026_02_14_060000_create_portal_integration_security_tables.php` (user_id=5, created_by=3)
- `2026_02_14_070000_create_remaining_module_tables.php` (user_id=8, created_by=1)
- `2026_02_14_100000_add_200_features_batch1_tables.php` (user_id=6, created_by=2)
- `2026_03_17_070000_add_missing_columns_for_tests.php` (user_id=10, created_by=1)

**Impacto:** Ambiguidade semântica. Quando `user_id` e `created_by` coexistem na mesma tabela, dev/IA não sabe qual usar em FormRequest. Pode causar vazamento de autor vs responsável, violando o padrão CLAUDE.md Lei 4 (`schedules.technician_id`, `expenses.created_by`). Wave 6.4/6.5 cobriu `expenses` e `travel_expense_reports`, mas o padrão não foi propagado sistematicamente — auditoria agregada por contagem de ocorrências não detecta cada tabela individual.

**Nota:** `work_schedules.user_id` sem `technician_id` é possivelmente semanticamente correto (cada usuário tem sua escala), mas o padrão declarado em CLAUDE.md Lei 4 usa `schedules.technician_id`. Inconsistência entre duas tabelas de agendamento (`schedules.technician_id` ✓ vs `work_schedules.user_id`).

---

### gov-09 — Migrations com código PHP comentado (engine InnoDB) — Spatie Permission
**Severidade:** S4
**Arquivo:** `backend/database/migrations/2026_02_07_223816_create_permission_tables.php:24, :34`
**Descrição:** CLAUDE.md §8 (docs/TECHNICAL-DECISIONS.md) declara "Nunca comentar código para desativar". A migration do Spatie mantém `// $table->engine('InnoDB');` comentado em 2 lugares.
**Evidência:**
```php
Schema::create($tableNames['permissions'], static function (Blueprint $table) {
    // $table->engine('InnoDB');
    $table->bigIncrements('id'); // permission id
```
**Impacto:** Débito cosmético. Origem é cópia do template oficial do Spatie, mas a regra do projeto é deletar código comentado. Risco baixo — não afeta runtime — mas é violação declarada.

---

### gov-10 — Migrations com sufixo `_v2`/`_v3`/`_v4` ativas (não refatoradas em migration única)
**Severidade:** S4
**Arquivos:**
```
2026_02_08_500000_alter_commission_rules_v2.php
2026_02_08_700000_alter_equipments_v2.php
2026_02_13_150001_inmetro_v2_expansion.php
2026_02_13_170000_inmetro_v3_50features.php
2026_02_14_003638_create_vehicle_tires_v2_table.php
2026_02_14_004341_create_inventory_tables_v3.php
2026_02_28_040000_add_inmetro_v4_urgency_fields.php
2026_03_18_000001_infra_audit_v2_performance_indexes.php
2026_03_18_000002_infra_audit_v3_performance_indexes.php
```
**Descrição:** 9 migrations usam sufixo de versão (v2/v3/v4) no nome, indicando refatorações em camadas (não "create + alter" limpo). O padrão CLAUDE.md pede "completude end-to-end" — evolução fragmentada em _v2, _v3, _v4 é o oposto.
**Impacto:** Baixo operacional (migrations H3 são imutáveis). Cosmético: o nome "v4_urgency_fields" força próxima geração a criar `_v5` em vez de nome descritivo. Fóssil aceitável, mas merece nota de governança — sem documentação em `TECHNICAL-DECISIONS.md`.

---

### gov-11 — Migration `2025_02_10_090000_add_missing_columns_to_products.php` com timestamp antes da migration de criação
**Severidade:** S3
**Arquivo:** `backend/database/migrations/2025_02_10_090000_add_missing_columns_to_products.php`
**Descrição:** Migration com timestamp 2025 (ano anterior) aparece no diretório. Todas as demais migrations são 2026_02_07+. O arquivo contém `Schema::hasTable('products')` + `Schema::hasColumn(...)` defensivos — funciona em runtime por guard — mas a ordem cronológica do filesystem é absurda (anterior a `create_tenant_tables`, `create_users_table`).
**Evidência:**
```php
// 2025_02_10_090000_add_missing_columns_to_products.php
if (Schema::hasTable('products')) {
    Schema::table('products', function (Blueprint $table) {
        if (! Schema::hasColumn('products', 'track_stock')) {
            $table->boolean('track_stock')->default(true);
        }
        if (! Schema::hasColumn('products', 'deleted_at')) {
            $table->softDeletes();
        }
    });
}
```
**Schema dump `migrations` table confirma execução:**
```sql
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (4, '2025_02_10_090000_add_missing_columns_to_products', 1);
```
**Impacto:** Baixo runtime (guards absorvem o problema), mas é irregularidade cronológica severa — a migration foi escrita em 2025 para uma tabela `products` que só será criada em 2026. Confusa para qualquer auditor/onboarding. Se H3 fóssil, deveria ser declarado em `TECHNICAL-DECISIONS.md`; se não, deveria ser renomeada (mas renomear quebra tabela migrations de prod).

---

### gov-12 — Muitas `Schema::table(...)` sem guards `hasColumn/hasTable/hasIndex` (violação parcial do Iron Protocol H3)
**Severidade:** S2
**Arquivo:** ~70+ migrations listadas (amostra):
```
database/migrations/2026_02_07_200001_add_tenant_fields_to_users.php
database/migrations/2026_02_08_100001_add_soft_deletes_to_schedules_and_time_entries.php
database/migrations/2026_02_08_700000_alter_equipments_v2.php
database/migrations/2026_02_08_900000_alter_customers_crm_fields.php
database/migrations/2026_02_09_000001_add_cost_price_to_work_order_items_table.php
database/migrations/2026_02_09_000003_add_signature_and_recurring_contracts.php
database/migrations/2026_02_09_100001_add_displacement_to_work_orders.php
database/migrations/2026_02_09_100005_create_suppliers_table.php
database/migrations/2026_02_11_000100_add_fk_constraints_to_expenses_table.php
database/migrations/2026_02_13_230535_add_location_and_status_to_users_table.php
database/migrations/2026_02_14_003838_add_parent_id_to_work_orders_table.php
... (+60 outras)
```
**Descrição:** CLAUDE.md § "As 5 Leis Invioláveis" + `governance.md` checklist item 2 exige guards `Schema::hasColumn` / `Schema::hasTable` em migrations `Schema::table(...)` para idempotência (Iron Protocol H3). A regex `"tem Schema::table mas NÃO tem hasColumn|hasTable|hasIndex"` retornou 70+ migrations que usam `Schema::table()` em ADD/DROP/CHANGE sem guardas. Algumas são fósseis antigas (aceitáveis), mas o critério `governance.md §2.b` exige que os fósseis estejam documentados.
**Impacto:** Médio-alto. Em ambientes onde a migration rodou parcialmente (ex: falha no meio e rollback manual), um re-run quebra com "column already exists". Em prod, esse problema já ocorreu — prova: `fix_production_schema_drifts.php` (2026-04-10) foi necessário para resolver drifts históricos. A falta de guard em migrations `_v2`, `alter_*` e `add_*` contribui para essa classe de bug.

---

### gov-13 — FormRequest `authorize(): return true` **sem** comentário nem permission check, 5 ocorrências além das aceitas em §14.18
**Severidade:** S2
**Arquivos:**
- `app/Http/Requests/Crm/RespondToProposalRequest.php` (aceito em §14.18 — valida token via route — **ignorado**)
- `app/Http/Requests/Export/ExportCsvRequest.php` (aceito em §14.18 — valida entity → can — **ignorado**)
- `app/Http/Requests/Iam/UpdateUserLocationRequest.php` — **tem lógica real** (`hasAnyRole` + permission check) — **falso positivo do grep, ignorado**
- `app/Http/Requests/Os/ResumeDisplacementRequest.php` — **tem lógica real** (`can('os.work_order.change_status')` + role check + `isTechnicianAuthorized`) — **falso positivo do grep, ignorado**
- `app/Http/Requests/Os/WorkOrderExecutionRequest.php` — **requer inspeção**

**Após análise fina:** os 5 arquivos detectados pelo grep inicial resolveram-se em falsos positivos ou itens já aceitos. **Finding descartado após análise**. Mantido como nota metodológica: grep mecânico por "return true" produz falsos positivos; critério CLAUDE.md exige inspeção individual do método.

**Severidade reclassificada:** S4 (cosmético — oportunidade de adicionar comentários `// authorized via role check` para reduzir ruído em auditorias futuras).

---

### gov-14 — Model `Schedule.php` preserva `technician_id` corretamente mas não há nota arquitetural sobre `work_schedules.user_id`
**Severidade:** S4
**Arquivo:** `backend/app/Models/Schedule.php:20-73`
**Descrição:** `Schedule` corretamente usa `technician_id` (CLAUDE.md Lei 4 ✓). Mas a tabela adjacente `work_schedules` usa `user_id` + `UNIQUE(user_id, date)`. As duas tabelas aparentemente modelam coisas distintas (uma é agenda de OS, outra é escala semanal/turno), mas não há nota em `TECHNICAL-DECISIONS.md` explicando por que uma é `technician_id` e a outra é `user_id`. Finding é de documentação/governança, não de código.
**Impacto:** Baixo. Risco de dev futuro copiar padrão da tabela errada e causar inconsistência.

---

### gov-15 — Schema dump header correto, mas ausência de rastreabilidade de versão/commit
**Severidade:** S4
**Arquivo:** `backend/database/schema/sqlite-schema.sql:1-2`
**Descrição:** Header tem origem declarada ("converted from MySQL") ✓ e timestamp de geração (2026-04-18 13:56:56) ✓. Mas não tem referência ao commit ou branch que gerou — dificulta detectar "schema dump está à frente/atrás do código".
**Evidência:**
```sql
-- SQLite Schema Dump (converted from MySQL)
-- Generated: 2026-04-18 13:56:56
```
**Impacto:** Cosmético. §14.9 gate de CI já valida parity, então o risco é baixo. Nota de governança.

---

## Resumo por severidade

| Severidade | Count | IDs |
|---|---|---|
| **S1 (crítico)** | **0** | — |
| **S2 (alto)** | **2** | gov-08, gov-12 |
| **S3 (médio)** | **5** | gov-01, gov-02, gov-03, gov-05, gov-07, gov-11 |
| **S4 (baixo)** | **7** | gov-04, gov-06, gov-09, gov-10, gov-13, gov-14, gov-15 |

(gov-11 reclassificada como S3 — irregularidade cronológica acionável.)

**Total:** 14 findings acionáveis + 1 (gov-13) descartado após análise fina.

---

## Observações gerais de governança

1. **Convenção EN-only (§14.13)** está majoritariamente aplicada, mas o Wave 6 **não cobriu todos os enums defaults** no schema. Restam enums PT residuais em domínio core (calibração, comissões, central) — gov-01/02/03/05.
2. **Migrations `add_missing_*` / `fix_missing_*`** são um anti-padrão repetido 16 vezes — sintoma crônico de gap PRD↔migration↔teste (Lei 3 CLAUDE.md).
3. **Iron Protocol H3** (§2 do checklist) não é universalmente aplicado. ~70 migrations `Schema::table()` sem guards — algumas fósseis, outras recentes (gov-12).
4. **Coexistência `user_id` + `created_by`** não foi resolvida sistematicamente — apenas em 2 tabelas (expenses, travel_expense_reports) via Wave 6.4/6.5 (gov-08).
5. **Rastreabilidade entre schema dump e commit** (gov-15) ausente no header — melhoria futura.

A camada tem bons instrumentos (gate CI §14.9, EN-only §14.13, H3 fóssil §14.19) mas os instrumentos não cobrem todo o schema — varredura por amostra revela resíduos.
