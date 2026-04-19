# Re-auditoria Camada 1 — data-expert
Data: 2026-04-17
Perímetro: Camada 1 — Fundação (Schema + Migrations)

Metodologia: inspeção de `backend/database/schema/sqlite-schema.sql` (dump canônico
pós-migrate, 8600 linhas, 521 tabelas, 657 índices), 471 migrations em
`backend/database/migrations/`, 388 Models em `backend/app/Models/`, trait
`BelongsToTenant` em `backend/app/Models/Concerns/BelongsToTenant.php`, e
`backend/config/database.php`. Proibições respeitadas (sem git log/diff/show/blame,
sem leitura de `docs/handoffs`, `docs/audits`, `docs/plans`).

## Sumário

- **Total: 9 findings**
- **S1 (blocker): 0**
- **S2 (major): 3**
- **S3 (minor): 4**
- **S4 (advisory): 2**

## Seções verificadas (sem problema)

- **Cobertura `tenant_id`** — 477 de 477 tabelas de negócio têm a coluna.
  Tabelas sem `tenant_id` (44) são todas infraestrutura (jobs, cache, sessions,
  permissions, etc.) OU pivots/filhas que herdam tenant via FK-pai OU globais
  de plataforma (`saas_plans`, `minimum_wages`, `inss_brackets`, `irrf_brackets`).
- **Índice em `tenant_id`** — 100% das 477 tabelas têm índice começando por
  `tenant_id` (simples ou composto). `no_tenant_idx = 0`.
- **Índice em `deleted_at`** — 100% das tabelas com SoftDeletes declarado no
  schema têm índice em `deleted_at`. `deleted_no_idx = 0`.
- **Status em inglês lowercase (defaults)** — zero defaults `'pendente'`,
  `'ativo'`, `'pago'`, `'parcial'`, `'concluido'`, `'emitido'`, `'aberto'`,
  `'fechado'`, `'aprovado'`, `'rejeitado'`, `'cancelado'` em DEFAULT de colunas.
- **Secrets/PII em `encrypted` cast** — `MarketingIntegration.api_key`,
  `PaymentGatewayConfig.api_key`/`api_secret`, `SsoConfig.client_secret`,
  `WhatsappConfig.api_key`, `TwoFactorAuth.secret`, `ClientPortalUser.two_factor_secret`,
  `Customer.document`, `Supplier.document`, `EmployeeDependent.cpf`,
  `User.cpf`/`google_calendar_token`/`google_calendar_refresh_token`,
  `FiscalWebhook.secret`, `InmetroWebhook.secret`, `Webhook.secret`,
  `InmetroBaseConfig.psie_password`, `EmailAccount.imap_password` — todos com
  `'encrypted'` cast declarado em `$casts`.
- **Convenção `expenses.created_by`** — confirmado (sem `user_id`, tem
  `created_by`).
- **Convenção `schedules.technician_id`** — confirmado (sem `user_id`, tem
  `technician_id`).
- **Convenção `travel_expense_reports.created_by`** — confirmado.
- **Charset/collation `utf8mb4`** — `backend/config/database.php` L84 e L110
  (conexões `mysql` e `mariadb`) ambas com `utf8mb4` / `utf8mb4_unicode_ci`.
- **UNIQUE composto `(tenant_id, <campo>)`** — 93 índices UNIQUE encontrados;
  tabelas críticas como `work_orders(tenant_id, number)`, `invoices(tenant_id,
  invoice_number)`, `quotes(tenant_id, quote_number)`, `chart_of_accounts(tenant_id,
  code)`, `products(tenant_id, code)`, `customers(tenant_id, document_hash,
  document_hash_active_key)`, `suppliers(tenant_id, document_hash, ...)` — todas
  devidamente escopadas por tenant.
- **SoftDeletes sem coluna `deleted_at`** — 84 Models usam trait; tabelas
  correspondentes (`journey_policies`, `hour_bank_policies`, etc.) todas têm
  coluna `deleted_at`. Zero inconsistências.
- **Schema dump sincronizado** — `sqlite-schema.sql` mtime 2026-04-17 19:44,
  última migration `2026_04_17_330000_normalize_visit_report_visit_type_to_english.php`
  mtime 19:44. Sincronizados.
- **Cascade delete em tabelas críticas** — `expenses`, `invoices`,
  `fiscal_invoices`, `fiscal_notes`, `customers`, `suppliers`, `work_orders`,
  `payments`, `payment_receipts`, `equipments`, `equipment_calibrations`,
  `accounts_payable`, `accounts_receivable` — nenhum FK com `ON DELETE CASCADE`
  para entidade não-tenant detectado no dump.
- **Polymorphic sem FK (pattern Laravel)** — detectados `taggable_type/id`,
  `priceable_type/id`, `favoritable_type/id`, `trackable_type/id`,
  `sourceable_type/id`, `matched_type/id` etc. Padrão Eloquent por design;
  aceito. Integridade delegada à aplicação — não é finding.

## Findings

### DATA-RA-01 [S2]
- **Arquivo:linha:** `backend/database/schema/sqlite-schema.sql:557-567` (e migration fonte `backend/database/migrations/2026_02_18_500000_create_central_subtasks_and_attachments.php:18`)
- **Descrição:** Tabela `central_attachments` mantém coluna em português
  `nome varchar(255) NOT NULL`. Viola convenção CLAUDE.md §1.5 (colunas sempre
  em inglês; exceções aceitáveis são apenas `cpf`, `cnpj`, `cep`, `uf`, `ie`,
  `rg`, `inscricao_estadual`).
- **Evidência:**
  ```sql
  CREATE TABLE "central_attachments" (
   "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
   "tenant_id" integer NOT NULL,
   "agenda_item_id" integer NOT NULL,
   "nome" varchar(255) NOT NULL,   -- <-- PT
   "path" varchar(500) NOT NULL,
   ...
  ```
  A migration Wave 6 `2026_04_17_300000_rename_central_pt_columns_to_english.php`
  renomeia colunas apenas em `central_items`, `central_rules`, `central_subtasks`,
  `central_time_entries` e `central_templates` (parcialmente) — `central_attachments`
  não foi incluída no mapa `$renames`.
- **Impacto:** Inconsistência terminológica dentro do próprio domínio Central
  (já migrado para EN nas demais tabelas); quebra de contrato com convenção
  EN-only anunciada; confunde integradores e gera viés ao gerar código novo.
- **Recomendação:** Nova migration `Schema::table('central_attachments', ...)
  ->renameColumn('nome', 'name')` com guards `hasTable`/`hasColumn` + alinhar
  Model `CentralAttachment` (se houver mapping explícito). Regenerar
  `sqlite-schema.sql`. Revisar frontend/consumers que referenciem `nome`.

---

### DATA-RA-02 [S2]
- **Arquivo:linha:** `backend/database/schema/sqlite-schema.sql:8121` (linha única, tabela `central_templates`) e migration fonte `backend/database/migrations/2026_02_24_200001_create_central_templates_table.php:16,24,27`
- **Descrição:** Após Wave 6, `central_templates` ainda carrega 3 colunas PT
  (`nome`, `categoria`, `ativo`) + 1 valor de enum PT em UPPERCASE
  (`type DEFAULT ('TAREFA')`).
- **Evidência:**
  ```sql
  CREATE TABLE "central_templates" (
    "id" integer primary key autoincrement not null,
    "tenant_id" integer not null,
    "nome" varchar(150) not null,
    "description" text,
    "type" varchar(20) not null default ('TAREFA'),
    "priority" varchar not null default 'medium',
    "visibility" varchar not null default 'team',
    "categoria" varchar(60) default (NULL),
    ...
    "ativo" tinyint not null default ('1'),
    ...
  )
  ```
  A migration `2026_04_17_300000_rename_central_pt_columns_to_english.php`
  inclui `central_templates` no mapa mas renomeia APENAS
  `descricao→description`, `tipo→type`, `prioridade→priority`,
  `visibilidade→visibility`. Deixa de fora `nome`, `categoria`, `ativo`.
  Além disso, o default `'TAREFA'` não foi normalizado para valor inglês
  lowercase (`'task'`), e a migration Wave 6
  `2026_04_17_290000_normalize_central_enums_defaults_to_english.php` não
  cobriu esta coluna (apenas `central_items` e outras foram normalizadas).
- **Impacto:** Mesmo tipo de viés da DATA-RA-01. Adicionalmente, o default
  `'TAREFA'` contamina todas as novas inserções com valor PT maiúsculo,
  quebrando invariante "status sempre em inglês lowercase" (CLAUDE.md Lei 4).
- **Recomendação:** Nova migration que (a) renomeie `nome→name`,
  `categoria→category`, `ativo→active`; (b) normalize o default e valores
  existentes de `type` para `'task'` (UPDATE + `change()->default('task')`),
  idempotente com guards.

---

### DATA-RA-03 [S2]
- **Arquivo:linha:** `backend/database/migrations/2026_02_07_300000_create_cadastros_tables.php:14,25,36,78,101` (e outros ~223 sites de declaração)
- **Descrição:** ~223 declarações de `tenant_id` em migrations usam
  `$table->unsignedBigInteger('tenant_id')` **sem** `->constrained()` / sem
  `$table->foreign('tenant_id')->references('id')->on('tenants')`. Resultado:
  coluna existe, mas **não existe foreign key constraint no banco**.
  Contagens:
  - `foreignId('tenant_id')->constrained` → 227 sites
  - `foreign('tenant_id')` (statement separado) → 103 sites
  - `unsignedBigInteger('tenant_id')` sem FK → 223 sites
- **Evidência (amostra):**
  ```php
  // backend/database/migrations/2026_02_07_300000_create_cadastros_tables.php:14
  $table->unsignedBigInteger('tenant_id');
  // (nenhum foreign('tenant_id') neste arquivo)
  ```
  ```
  backend/database/migrations/2026_02_09_300001_create_chart_of_accounts.php:13:
    $table->unsignedBigInteger('tenant_id');
  backend/database/migrations/2026_02_09_400001_enrich_calibration_tables.php:46:
    $table->unsignedBigInteger('tenant_id');
  ```
- **Impacto:** Multi-tenant isolation fica 100% dependente de Eloquent global
  scope (`BelongsToTenant`). Qualquer INSERT via raw SQL (seeders ad-hoc,
  scripts de import, DB nativo) pode gravar com `tenant_id` inválido ou
  pertencente a outro tenant sem violação de integridade referencial. Se um
  tenant for deletado, filhos ficam órfãos silenciosamente (sem ON DELETE
  CASCADE/RESTRICT declarado). Princípio "banco é o guardião da verdade"
  violado: constraint fora do banco = constraint inexistente.
- **Recomendação:** Migration de remediação — para cada tabela com `tenant_id`
  sem FK, criar `$table->foreign('tenant_id')->references('id')->on('tenants')
  ->restrictOnDelete()` (ou `cascadeOnDelete` conforme domínio; financeiro
  e certificados devem ser `restrict`). Idempotente com guards, rodar por
  batch (80 migrations afetadas estimadas pelo gap 223 sem — 103 com
  statement separado = ~120 tabelas reais). Pré-condição: backfill/cleanup
  de registros órfãos via `SELECT ... WHERE tenant_id NOT IN (SELECT id FROM tenants)`
  antes de ligar a FK, senão a criação falhará.

---

### DATA-RA-04 [S3]
- **Arquivo:linha:** `backend/database/schema/sqlite-schema.sql` — 30 tabelas (ver lista abaixo)
- **Descrição:** 30 tabelas têm coluna `tenant_id` sem `NOT NULL`. Algumas são
  legítimas (pivots `calibration_standard_weight`, `email_email_tag`,
  `equipment_model_product`, `quote_quote_tag`, `service_call_equipments`,
  `service_skills`, `work_order_equipments`, `work_order_technicians` —
  migration `2026_04_17_160000_revert_tenant_id_not_null_on_pivots.php` parece
  tê-los explicitamente revertido) mas outras são tabelas de fato de negócio
  onde nullable não deveria existir.
- **Evidência (lista completa):**
  - Pivots aceitáveis (tenant via pai): `calibration_standard_weight`,
    `email_email_tag`, `equipment_model_product`, `quote_quote_tag`,
    `service_call_equipments`, `service_skills`, `work_order_equipments`,
    `work_order_technicians`.
  - **Questionáveis (tabelas de eventos/negócio — deveriam ser NOT NULL):**
    `asset_tag_scans`, `biometric_configs`, `cameras`,
    `central_item_comments`, `central_item_history`,
    `crm_web_form_submissions`, `inmetro_history`, `inmetro_instruments`,
    `inmetro_locations`, `inventory_items`, `inventory_tables_v3`,
    `mobile_notifications`, `operational_snapshots`, `purchase_quotation_items`,
    `qr_scans`, `user_favorites`, `user_preferences`, `user_sessions`,
    `warehouse_stocks`, `webhook_logs`.
  - Infra/globais (OK): `roles` (usa padrão teams/tenant de spatie),
    `users` (tem `current_tenant_id`, não `tenant_id`).
  - Exemplo: `asset_tag_scans` — "scan de QR em ativo físico" registra ação
    por tenant; permitir `tenant_id NULL` abre janela para evento órfão.
- **Impacto:** Registros anônimos podem ser gravados sem tenant; global scope
  `BelongsToTenant` filtra apenas quando `current_tenant_id` está ligado,
  mas um INSERT via raw SQL ou factory sem cuidado cria registro não
  pertencente a nenhum tenant, invisível via API mas visível via query direta
  cross-tenant.
- **Recomendação:** Revisar caso a caso. Para tabelas de eventos/negócio
  acima, nova migration que faça backfill (`UPDATE ... SET tenant_id = (SELECT ...)`
  via FK-pai) + `change()` para NOT NULL. Para pivots já revertidos por
  `2026_04_17_160000`, documentar em `TECHNICAL-DECISIONS.md` a decisão
  explícita de manter nullable com justificativa (operação cross-tenant?).

---

### DATA-RA-05 [S3]
- **Arquivo:linha:** múltiplas migrations pré-Wave-6 que fazem `Schema::table(...)` sem guards `hasTable`/`hasColumn`
- **Descrição:** Dos 271 arquivos que contêm `Schema::table(`, pelo menos 30+
  não têm `hasTable`/`hasColumn` em lugar algum do arquivo. Viola regra H3
  do CLAUDE.md ("migration mergeada é fóssil; novas alterações via nova
  migration com guards `hasTable`/`hasColumn`"). Risco é baixo porque são
  migrations já rodadas, mas se alguma falhar em ambiente desalinhado
  (feature toggle, sharding, ambiente de staging reset), quebra o rollback.
- **Evidência (amostra):**
  ```
  2026_02_07_200001_add_tenant_fields_to_users.php
  2026_02_08_100001_add_soft_deletes_to_schedules_and_time_entries.php
  2026_02_08_500000_alter_commission_rules_v2.php
  2026_02_09_000001_add_cost_price_to_work_order_items_table.php
  2026_02_09_100005_create_suppliers_table.php
  2026_02_10_060000_make_branch_code_nullable.php
  2026_02_11_200001_add_resolution_and_comments_to_service_calls.php
  2026_02_13_170000_inmetro_v3_50features.php
  ...
  ```
- **Impacto:** Baixo em ambientes já migrados; moderado para disaster recovery
  (rodar de zero em DB existente, ou pular migration intermediária em
  partial rollback). Não bloqueia runtime atual.
- **Recomendação:** (a) Não alterar as migrations antigas (regra H3 — fossil).
  (b) Documentar a dívida técnica em `TECHNICAL-DECISIONS.md`. (c) Exigir
  guards em 100% das novas migrations via PR template + `ArchTest` que
  escaneie diretório de migrations e falhe PR sem guards em arquivos novos.

---

### DATA-RA-06 [S3]
- **Arquivo:linha:** `backend/database/schema/sqlite-schema.sql` — índices UNIQUE globais em ~20 tabelas tenant-scoped
- **Descrição:** Vários índices `CREATE UNIQUE INDEX` sobre colunas de negócio
  em tabelas com `tenant_id` não incluem `tenant_id` na chave. Isso significa
  que dois tenants não podem ter o mesmo valor nessas colunas mesmo quando
  deveriam poder — ou pior: gera leak de existência por colisão.
- **Evidência (candidatos a revisão — o token é legítimamente único global
  em muitos casos, mas os marcados são colunas de negócio):**
  ```
  products_sku_unique                  products.sku           GLOBAL
  products_qr_hash_unique              products.qr_hash       GLOBAL
  material_requests_reference_unique   material_requests.reference
  purchase_quotes_reference_unique     purchase_quotes.reference
  stock_disposals_reference_unique     stock_disposals.reference
  rma_requests_rma_number_unique       rma_requests.rma_number
  non_conformances_number_unique       non_conformances.number
  non_conformities_nc_number_unique    non_conformities.nc_number
  fiscal_notes_access_key_unique       fiscal_notes.access_key    (dúvida: access_key é global NFS-e — aceitável)
  asset_tags_tag_code_unique           asset_tags.tag_code
  emails_message_id_unique             emails.message_id          (RFC Message-ID — global por definição, aceitável)
  ```
  Legítimos globais: `users.email`, `personal_access_tokens.token`,
  `portal_guest_links.token`, `visit_surveys.token`,
  `crm_interactive_proposals.token`, `quotes.magic_token`,
  `work_orders.rating_token`, `equipments.qr_token`,
  `gamification_badges.slug`, `referral_codes.code`,
  `saas_plans.slug`, `service_catalogs.slug`,
  `lgpd_data_requests.protocol`, `lgpd_security_incidents.protocol` (tokens
  emitidos por sistema, unicidade global necessária).
- **Impacto:**
  - Bloqueio indevido: tenant A cria SKU "ABC" → tenant B não pode criar "ABC".
  - Leak de existência: tenant B descobre por erro 422 que o código existe
    em outro tenant.
  - Para `fiscal_notes.access_key` — se for só o nosso número interno e não
    a access key SEFAZ, seria indevido; se for access key SEFAZ (chave de
    acesso NF-e 44 dígitos), é de fato global.
- **Recomendação:** Confirmar caso a caso o domínio de cada UNIQUE:
  - SKU de produto é por tenant → trocar para `(tenant_id, sku)`.
  - `qr_hash` de produto — verificar se é token público ou código interno.
  - Referências internas (`material_requests.reference`, `purchase_quotes.reference`,
    `stock_disposals.reference`, `rma_requests.rma_number`, `non_conformances.number`,
    `non_conformities.nc_number`, `asset_tags.tag_code`) — compor com
    `tenant_id`. Registrar decisão de tokens globais em `TECHNICAL-DECISIONS.md`.

---

### DATA-RA-07 [S3]
- **Arquivo:linha:** `backend/database/schema/sqlite-schema.sql` (~100 colunas monetárias) + `backend/database/migrations/2026_04_17_220000_normalize_monetary_precision.php:44-51`
- **Descrição:** Migration Wave 6 reconhece o problema (comentário no arquivo:
  "outras 100+ colunas decimais permanecem como estão") e normaliza apenas
  5 tabelas de agregados core (`invoices.total`, `accounts_payable.amount`/
  `amount_paid`, `accounts_receivable.amount`/`amount_paid`, `payments.amount`,
  `expenses.amount`). Fica fora:
  - Agregados de payroll: `payrolls.total_gross`, `total_deductions`,
    `total_net`, `total_fgts`, `total_inss_employer`.
  - Agregados de quote/work_order: `quotes.total`, `quotes.subtotal`,
    `work_orders.total`, `work_orders.total_cost`.
  - `fiscal_invoices.total`, `fiscal_notes.total_amount`,
    `purchase_quotations.total`/`total_amount`.
  - `commission_settlements.total_amount`/`paid_amount`/`total`.
  - `travel_expense_reports.total_expenses`/`total_advances`/`balance`.
- **Evidência:** comentário explícito na migration (linhas 23-26):
  "Escopo desta migration: APENAS colunas de TOTAL/SALDO de domínio
  financeiro core (5 alterações). Outras 100+ colunas decimais permanecem
  como estão — alteração em massa traria risco operacional sem ganho
  proporcional".
- **Impacto:** Baixo-médio em produção MySQL: `decimal(12,2)` suporta até
  R$ 9.999.999.999,99. Payroll/quote totals de tenants enterprise podem,
  em 3-5 anos, aproximar desse teto. `numeric` em SQLite é affinity-based
  (sem risco em teste).
- **Recomendação:** Plano Wave N+1 para ampliar precisão de totais agregados
  de payroll, fiscal, quotes e travel para `decimal(15,2)`. Registrar como
  dívida técnica com prazo em `TECHNICAL-DECISIONS.md §14.10`.

---

### DATA-RA-08 [S4]
- **Arquivo:linha:** `backend/database/schema/sqlite-schema.sql:8121` e similares — uso de `numeric` sem precisão em SQLite
- **Descrição:** O schema dump mostra colunas monetárias como `numeric` (sem
  `(p,s)`) em centenas de sites. Isso é ARTEFATO de SQLite (affinity-based
  typing): as migrations originalmente usaram `decimal(p,s)`, mas o dump
  perde a precisão. Não é bug de código-fonte — é limitação do dumper.
- **Evidência:** migration-fonte mostra `decimal(12,2)` ou `decimal(10,2)`;
  dump SQLite mostra `numeric`. MySQL produção preserva precisão.
- **Impacto:** Nenhum em runtime MySQL; apenas em ambiente de teste SQLite
  os testes NÃO validam overflow de precisão. Não é regressão.
- **Recomendação:** Advisory. Considerar teste específico em MySQL
  (container isolado CI) para validar overflow decimal, OU adicionar nota em
  `backend/TESTING_GUIDE.md` alertando que precisão decimal não é coberta
  em SQLite.

---

### DATA-RA-09 [S4]
- **Arquivo:linha:** `backend/database/schema/sqlite-schema.sql` — tabelas sem `tenant_id` que são business-critical
- **Descrição:** Três tabelas de negócio sem `tenant_id` e sem FK óbvia para
  um pai tenant-scoped precisam de confirmação explícita:
  - `marketplace_partners` — sem `tenant_id`. Parece plataforma (catálogo
    de parceiros compartilhado entre tenants, como um "App Store").
    Verificar intenção.
  - `competitor_instrument_repairs` — sem `tenant_id`. FK é `competitor_id`
    / `instrument_id`. Depende se `inmetro_competitors` e `inmetro_instruments`
    são tenant-scoped (`inmetro_instruments` também está em `tenant_id NULLABLE`
    — ver DATA-RA-04).
  - `permission_groups` — sem `tenant_id`. Agrupamento de permissões Spatie;
    tipicamente global.
- **Evidência:**
  ```sql
  CREATE TABLE "marketplace_partners" (
   "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
   "name" varchar(255) NOT NULL,
   "category" varchar(50) NOT NULL,
   ...
  )
  ```
  (sem tenant_id; sem FK para tenants)
- **Impacto:** Se qualquer uma for de fato por tenant, vaza dados entre
  tenants. Se são globais, OK.
- **Recomendação:** Documentar em `TECHNICAL-DECISIONS.md` a lista de
  tabelas GLOBAIS-POR-DESIGN (com justificativa): `saas_plans`,
  `saas_plan_*`, `inss_brackets`, `irrf_brackets`, `minimum_wages`,
  `permissions`, `permission_groups`, `gamification_badges`, `marketplace_partners`.
  Para `competitor_instrument_repairs`, confirmar cadeia de FK e, se `competitor`
  for tenant-scoped, adicionar `tenant_id` desnormalizado para evitar JOIN
  em global scope.
