# Re-auditoria Camada 1 — governance
Data: 2026-04-17

## Sumário
- Total: 14 findings
- S1: 3 | S2: 4 | S3: 5 | S4: 2

## Seções verificadas (sem problema)

- **Convenção `expenses.created_by`**: confirmado em `backend/database/schema/sqlite-schema.sql:2355+` — coluna `created_by` presente, `user_id` removido por `2026_04_17_270000_drop_user_id_from_expenses.php`. OK.
- **Convenção `schedules.technician_id`**: confirmado em `backend/database/schema/sqlite-schema.sql` (tabela `schedules`, linhas ~5105+): coluna `technician_id` presente; sem `user_id` residual. OK.
- **Convenção `travel_expense_reports.created_by`**: confirmado em `backend/database/schema/sqlite-schema.sql:6867` e migration `2026_04_17_280000_rename_user_id_to_created_by_in_travel_expense_reports.php` — rename com guards `hasTable`/`hasColumn`. OK.
- **Status `equipments` em inglês**: confirmado (`DEFAULT 'active'` em `sqlite-schema.sql`), com migrations de reconciliação PT→EN aplicadas.
- **Status `standard_weights` em inglês**: confirmado (`DEFAULT 'active'` em `sqlite-schema.sql`).
- **FormRequests Auth/Portal com `authorize(): return true`**: verificado em `backend/app/Http/Requests/Auth/*` e `Portal/*` — todos têm comentário justificando (endpoint público / portal token). Não é violação da regra, pois são endpoints públicos legítimos.
- **`company_id` em código produtivo**: não existe em schema corrente; única referência em `2026_04_10_500000_fix_production_schema_drifts.php` é migration de rename `company_id`→`tenant_id` (reparadora, OK).
- **`--no-verify`, `--skip-*` em scripts/CI**: nenhuma ocorrência encontrada em `backend/`, `frontend/` (scripts, YAML, JSON).
- **TODO/FIXME em migrations/models**: única ocorrência é uma string `comment('Acreditação ...')` — não é um TODO.
- **`assertTrue(true)` / `markTestIncomplete`/`markTestSkipped`**: zero ocorrências em `backend/tests/` (apenas menção negativa no README).
- **Paginação no `AgendaItemController::index`**: usa `paginate(per_page, 15)`. OK (exemplo representativo verificado).

## Findings

### GOV-RA-01 [S1]
- **Arquivo:linha:** `backend/database/schema/sqlite-schema.sql:8121` (tabela `central_templates`)
- **Regra violada:** CLAUDE.md — "Convenção de nomes em inglês". Colunas `nome`, `categoria`, `ativo` persistem no schema atual.
- **Descrição:** A tabela `central_templates` mantém colunas em português (`"nome" varchar(150) not null`, `"categoria" varchar(60)`, `"ativo" tinyint not null default ('1')`). A Wave 6 declarou migração EN-only, porém estas três colunas não foram renomeadas no schema consolidado em produção (dump SQLite espelhando MySQL em 2026-04-17 23:44:16).
- **Evidência:**
  ```
  CREATE TABLE "central_templates" (... "nome" varchar(150) not null, ..., "categoria" varchar(60) default (NULL), ..., "ativo" tinyint not null default ('1'), ...);
  ```
  Migration `2026_04_17_300000_rename_central_pt_columns_to_english.php` referencia `'central_templates' => [...]` mas o schema dump regenerado após todas as migrations ainda contém os nomes PT — indicando que as operações de rename não executaram ou a migration recriou sem aplicar os renames reais.
- **Recomendação:** criar migration nova (com guards `hasColumn`) para `renameColumn('nome','name')`, `renameColumn('categoria','category')`, `renameColumn('ativo','is_active')`. Depois regenerar `sqlite-schema.sql` via `php generate_sqlite_schema.php`.

### GOV-RA-02 [S1]
- **Arquivo:linha:** `backend/database/schema/sqlite-schema.sql:629` (tabela `central_subtasks`)
- **Regra violada:** CLAUDE.md — "Convenção de nomes em inglês" (`concluido`, `ordem` são violações explícitas listadas).
- **Descrição:** Tabela `central_subtasks` contém `"concluido" tinyint NOT NULL DEFAULT '0'` e `"ordem" integer NOT NULL DEFAULT '0'` no schema atual. Nenhuma migration subsequente renomeia para `completed`/`position` (ou similar EN).
- **Evidência:**
  ```
  CREATE TABLE "central_subtasks" (
   ... "concluido" tinyint NOT NULL DEFAULT '0',
   "ordem" integer NOT NULL DEFAULT '0',
   ...
  );
  ```
  Confirmado ainda em `backend/app/Models/AgendaSubtask.php:25-26` (`'concluido'`, `'ordem'` no `$fillable`) e `AgendaSubtask.php:34-35` (casts). Lei 3 (completude) não pode mascarar o débito: a convenção EN é declarada e os campos PT continuam autoritativos no schema.
- **Recomendação:** migration `rename_columns_concluido_ordem_in_central_subtasks` com guards, atualizar Model (`$fillable`, `$casts`), atualizar Controller/Resource/Service e testes. Regenerar schema dump.

### GOV-RA-03 [S1]
- **Arquivo:linha:** `backend/database/schema/sqlite-schema.sql:8115` (tabela `central_items`)
- **Regra violada:** Lei 3 (completude end-to-end) + Lei 2 (causa raiz): duas colunas legadas `"user_id" integer default (NULL)` e `"completed" tinyint default (NULL)` coexistem no schema atual com as colunas canônicas `assignee_user_id`, `created_by_user_id` e `status`. Isso caracteriza schema drift: duas fontes de verdade simultâneas para mesmo conceito, sem coluna descontinuada e sem remoção.
- **Descrição:** A tabela `central_items` já tem `assignee_user_id`, `created_by_user_id` e `status` (o estado closed/open/etc.), mas mantém colunas redundantes `user_id` e `completed` no final do CREATE TABLE. Nenhuma das duas tem constraint/uso declarado. Risco de queries lendo/escrevendo em coluna errada e divergindo.
- **Evidência:** (`sqlite-schema.sql:8115`, trecho final do CREATE TABLE)
  ```
  ... "visibility_departments" text default (NULL), "visibility_users" text default (NULL),
  "user_id" integer default (NULL), "completed" tinyint default (NULL));
  ```
- **Recomendação:** auditar se algum código ainda escreve nessas colunas. Se não, criar migration `drop_legacy_user_id_and_completed_from_central_items` com guards. Se sim, consolidar no campo canônico e então dropar. Sem isso, a convenção EN declarada não está fechada.

### GOV-RA-04 [S2]
- **Arquivo:linha:** `backend/database/schema/sqlite-schema.sql:610` (tabela `central_rules`)
- **Regra violada:** CLAUDE.md — Lei 5 (sequenciamento/preservação) + convenção de nomenclatura.
- **Descrição:** A tabela `central_rules` usa `"active" tinyint NOT NULL DEFAULT '1'` (EN), mas a migration criadora (`2026_02_09_700002_create_central_rules_table.php`) declarava `boolean('ativo')`. Schema dump está EN, porém nenhum artefato de rename explícito com guards foi localizado no diretório de migrations — indicando que o dump pode ter sido gerado de um banco corrigido manualmente em produção (drift irreversível entre migration original e schema fonte-de-verdade).
- **Evidência:**
  - `sqlite-schema.sql:612`: `"active" tinyint NOT NULL DEFAULT '1'`
  - `migrations/2026_02_09_700002_create_central_rules_table.php:16`: `$table->boolean('ativo')->default(true);`
  - Migration `2026_04_17_320000_rename_central_rules_pt_columns.php` existe mas foi a referência indireta via grep — precisa ser checada se renomeia `ativo→active` com guard. Se renomeia, correto; se não, o schema dump está desalinhado das migrations.
- **Recomendação:** abrir `2026_04_17_320000_rename_central_rules_pt_columns.php` e confirmar que renomeia `ativo→active`. Se faltar esse rename, adicionar. Caso contrário, documentar que schema dump está fresco e marcar finding como resolvido.

### GOV-RA-05 [S2]
- **Arquivo:linha:** `backend/database/migrations/` (todo o diretório)
- **Regra violada:** Prática operacional — migrations **com timestamp duplicado**, 10 colisões detectadas.
- **Descrição:** 10 pares de migrations compartilham o mesmo prefixo `YYYY_MM_DD_HHMMSS` (Laravel ordena lexicograficamente; em colisão, a ordem depende do filesystem). Exemplos:
  - `2026_04_09_100000_add_tenant_id_to_central_item_history_and_comments.php` + `2026_04_09_100000_add_tenant_id_to_crm_child_tables.php`
  - `2026_03_26_400001_add_retry_fields_to_esocial_events.php` + `2026_03_26_400001_create_non_conformities_table.php`
  - `2026_03_24_000001_add_clt_compliance_fields_to_journey_entries.php` + `2026_03_24_000001_add_work_order_id_to_accounts_payable.php`
  - `2026_03_23_300002`, `2026_03_23_300001`, `2026_03_23_200006`, `2026_03_22_200000`, `2026_03_20_120000`, `2026_03_16_500001`, `2026_03_16_000001` (todos com 2 arquivos cada).
- **Evidência:** output de `ls | uniq -c`:
  ```
  2 2026_04_09_100000
  2 2026_03_26_400001
  2 2026_03_24_000001
  ... (10 pares)
  ```
- **Recomendação:** ordem em produção já aplicada (irreversível). Regra para o futuro: adotar timestamp com segundos reais distintos (`date +%Y_%m_%d_%H%M%S`) ou sufixo incremental explícito a partir de `_500000`, `_500001`, ... Documentar em `docs/TECHNICAL-DECISIONS.md` como regra permanente (H3 adjacente).

### GOV-RA-06 [S2]
- **Arquivo:linha:** `backend/database/schema/sqlite-schema.sql:1` vs `backend/generate_sqlite_schema.php`
- **Regra violada:** CLAUDE.md — "Após criar migration, regenerar schema dump" (operacional). Divergência de ferramenta.
- **Descrição:** O script oficial é `generate_sqlite_schema.php` (requer MySQL Docker). Porém o header do dump atual diz `-- SQLite Schema Dump (generated via artisan migrate)` — indica que o dump foi gerado por outro caminho (possivelmente `php artisan schema:dump` diretamente do SQLite). Resultado: qualquer divergência entre MySQL e SQLite (ex.: enums, collation, coluna duplicada) pode estar sendo mascarada. Os pares PT/EN coexistentes nas tabelas centrais (`central_items` com `user_id`+`completed`, `central_templates` com `nome`/`categoria`/`ativo`) podem refletir esse drift.
- **Evidência:**
  - `sqlite-schema.sql:1`: `-- SQLite Schema Dump (generated via artisan migrate)`
  - `sqlite-schema.sql:2`: `-- Generated: 2026-04-17 23:44:16`
  - `generate_sqlite_schema.php:27`: `"-- SQLite Schema Dump (converted from MySQL)\n"` — não bate com o header atual.
- **Recomendação:** alinhar processo: definir uma única fonte (MySQL em prod via `generate_sqlite_schema.php`) ou atualizar o script oficial para refletir o caminho real usado. Registrar no CLAUDE.md operacional.

### GOV-RA-07 [S2]
- **Arquivo:linha:** `backend/app/Http/Controllers/Api/V1/Financial/ConsolidatedFinancialController.php:58`
- **Regra violada:** Lei 4 (tenant safety absoluto) — "Tenant ID sempre `$request->user()->current_tenant_id`. Jamais do body."
- **Descrição:** Controller lê `$request->input('tenant_id')` como fallback para filtrar consolidação financeira. Qualquer usuário autenticado pode passar `tenant_id` arbitrário no query/body e potencialmente consultar dados de outro tenant (se o service abaixo não revalidar).
- **Evidência:**
  ```php
  // ConsolidatedFinancialController.php:58
  $tenantFilter = $request->input('tenant_filter', $request->input('tenant_id'));
  ```
- **Recomendação:** **CONFIRMAR OU CONTRAPROVAR** — se este endpoint for super-admin multi-tenant (holding view), a leitura do body é legítima, mas DEVE estar protegida por policy `can('viewAnyTenant')` ou middleware super-admin. Se não houver gate, substituir por `$request->user()->current_tenant_id` sem fallback do body. Auditar todos os callers.

### GOV-RA-08 [S3]
- **Arquivo:linha:** `backend/app/Models/AgendaItem.php:254`, `backend/app/Models/AgendaItem.php:656-663`
- **Regra violada:** Convenção EN + Lei 3 (completude).
- **Descrição:** Model `AgendaItem` tem relação `orderBy('ordem')` (coluna PT da tabela `central_subtasks` — ver GOV-RA-02) e mapa de compat PT→EN hardcoded em método (`'titulo' => 'title'`, `'prioridade' => 'priority'`, `'visibilidade' => 'visibility'`, `'responsavel_user_id' => 'assignee_user_id'`, `'criado_por_user_id' => 'created_by_user_id'`). Isso é a "ponte PT→EN" documentada na §14.13 de TECHNICAL-DECISIONS, mas pressupõe que o schema já está EN — o que não é verdade para `central_subtasks` e `central_templates` (ver GOV-RA-01/02).
- **Evidência:**
  ```php
  // AgendaItem.php:254
  return $this->hasMany(AgendaSubtask::class, 'agenda_item_id')->orderBy('ordem');
  // AgendaItem.php:656-663
  'titulo' => 'title',
  'prioridade' => 'priority',
  'visibilidade' => 'visibility',
  'responsavel_user_id' => 'assignee_user_id',
  'criado_por_user_id' => 'created_by_user_id',
  ```
- **Recomendação:** após GOV-RA-01 e GOV-RA-02 serem resolvidos (renames reais no schema), remover mapa de compat PT ou reduzi-lo a pt-accept-only (aceitar PT de clientes antigos, responder EN). Hoje ele se acumula como dívida porque referencia nomes PT que ainda existem em outras tabelas.

### GOV-RA-09 [S3]
- **Arquivo:linha:** `backend/app/Models/AgendaSubtask.php:25-26`, `34-35`
- **Regra violada:** Convenção EN (consistente com GOV-RA-02).
- **Descrição:** Model expõe `'concluido'` e `'ordem'` como `$fillable` e em `$casts`. Model e schema estão alinhados entre si (ambos PT), mas ambos violam a convenção.
- **Evidência:**
  ```php
  protected $fillable = [..., 'concluido', 'ordem', ...];
  protected function casts(): array { return [..., 'concluido' => 'boolean', 'ordem' => 'integer', ...]; }
  ```
- **Recomendação:** atualizar Model ao mesmo tempo que a migration de rename (GOV-RA-02) — senão quebra fillable.

### GOV-RA-10 [S3]
- **Arquivo:linha:** `backend/app/Models/AgendaTemplate.php:32`, `70`
- **Regra violada:** Convenção EN (consistente com GOV-RA-01).
- **Descrição:** `'ativo' => 'boolean'` em casts e `'ordem' => $i` em código. Espelha schema PT de `central_templates`.
- **Evidência:**
  ```php
  // AgendaTemplate.php:32
  'ativo' => 'boolean',
  // AgendaTemplate.php:70
  'ordem' => $i,
  ```
- **Recomendação:** atualizar junto com rename do schema (GOV-RA-01).

### GOV-RA-11 [S3]
- **Arquivo:linha:** `backend/app/Models/AgendaAttachment.php:21`
- **Regra violada:** Convenção EN.
- **Descrição:** `'nome'` exposto em `$fillable` do Model. Verificar se corresponde a coluna `nome` em tabela `central_attachments` (ou correlata) e renomear em conjunto.
- **Evidência:**
  ```php
  // AgendaAttachment.php:21
  'nome',
  ```
- **Recomendação:** identificar a coluna real no schema, criar migration de rename com guard, atualizar model.

### GOV-RA-12 [S3]
- **Arquivo:linha:** 272 de 471 migrations fazem `Schema::table(...)` (ALTER), várias sem guard `hasTable`/`hasColumn`/`hasColumns`.
- **Regra violada:** CLAUDE.md — "Alterar migrations já mergeadas (criar nova com `hasTable`/`hasColumn` guards)". A regra H3 é imutabilidade; porém TODA migration de ALTER posterior ao schema baseline deve ter guards para permitir re-run em ambientes que já receberam fix manual.
- **Descrição:** amostra de migrations sem guard `hasTable|hasColumn|hasColumns` em `Schema::table`:
  - `2026_02_07_200001_add_tenant_fields_to_users.php`
  - `2026_02_08_100001_add_soft_deletes_to_schedules_and_time_entries.php`
  - `2026_02_08_500000_alter_commission_rules_v2.php`
  - `2026_02_08_700000_alter_equipments_v2.php`
  - `2026_02_09_000001_add_cost_price_to_work_order_items_table.php`
  - `2026_02_09_100001_add_displacement_to_work_orders.php`
  - `2026_02_09_300002_alter_recurring_contracts_billing.php`
  - `2026_02_10_600000_add_imports_indexes_and_fix_fk.php`
  - `2026_02_11_000100_add_fk_constraints_to_expenses_table.php`
  - `2026_02_12_222000_add_format_to_bank_statements.php`
  - ... (lista parcial; grep confirma 50+ no mesmo padrão).
- **Evidência:** scan `grep -lL 'hasTable|hasColumn|hasColumns' + has Schema::table`.
- **Recomendação:** para migrations futuras adotar guards sempre. Para migrations antigas sem guard, baixa prioridade (já mergeadas e aplicadas); porém caso apareça ambiente com drift, criar migration nova reparadora com guard (exemplo já feito em `2026_04_10_500000_fix_production_schema_drifts.php`).

### GOV-RA-13 [S4]
- **Arquivo:linha:** `backend/app/Http/Requests/Agenda/StoreAgendaItemRequest.php:55`
- **Regra violada:** nenhuma violação dura, mas nota preventiva.
- **Descrição:** FormRequests de Agenda validam `exists:central_items,id` com filtro `tenant_id`. Correto. Apenas anotar que todos os FormRequests inspecionados (Agenda, Advanced, etc.) usam `Rule::exists(...)->where('tenant_id', $tenantId)`. Este é o padrão esperado pelo CLAUDE.md; nenhum vazamento encontrado. Registrado como advisory de confirmação.
- **Evidência:**
  ```php
  'assignee_user_id' => ['nullable', Rule::exists('users', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
  ```
- **Recomendação:** manter padrão. Adicionar teste de regressão garantindo que novas FormRequests sigam a mesma convenção (lint rule opcional).

### GOV-RA-14 [S4]
- **Arquivo:linha:** `backend/app/Console/Commands/*` e `backend/app/Http/Controllers/Api/V1/BankReconciliationController.php:223,227`
- **Regra violada:** CLAUDE.md — "`withoutGlobalScope` exige justificativa explícita por escrito."
- **Descrição:** ~30 usos de `withoutGlobalScope`/`withoutGlobalScopes` em Console Commands (jobs cron, multi-tenant por design) e em `BankReconciliationController`. A maioria em commands é legítima (processo global), mas não há comentário explícito em cada call justificando. Para finding duro precisa-se revisar cada caso individualmente.
- **Evidência:** amostra:
  - `AuditPruneCommand.php:52,129`
  - `CheckSlaBreaches.php:33,138`
  - `ScanOverdueFinancials.php:51,63,76,111,123,135`
  - `BankReconciliationController.php:223,227` — **atenção**, pois é controller HTTP (não é command cron).
- **Recomendação:** revisar `BankReconciliationController.php:223,227` com prioridade — controller HTTP tipicamente não deve bypassar tenant scope. Nos commands, adicionar docblock justificando para cada call. Ações em arquivos separados (fora do escopo desta Camada 1 se não resolvidos na Wave 6).
