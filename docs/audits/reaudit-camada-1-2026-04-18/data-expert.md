# Re-auditoria Camada 1 — data-expert

Data: 2026-04-18
Escopo: Camada 1 (fundação do ERP) — schema, migrations, models das entidades centrais e de integração/segurança.

Metodologia: leitura direta do schema dump (`backend/database/schema/sqlite-schema.sql`), das migrations de criação de tabelas e dos models correspondentes. Nenhuma baseline ou histórico consultado.

---

## Sumário por severidade

- **S1 (crítico):** 3
- **S2 (alto):** 6
- **S3 (médio):** 5
- **S4 (baixo):** 3

Total: 17 findings.

---

## S1 — Crítico

### data-01 — Schema dump SQLite descarta TODOS os índices não-uniques (silenciosa divergência MySQL↔SQLite)

- **Severidade:** S1
- **Arquivo:** `backend/generate_sqlite_schema.php:155`
- **Descrição:** O gerador do schema SQLite (usado pela suíte de testes) aplica um `preg_replace` que remove qualquer `KEY \`…\`(…)` da DDL extraída do MySQL **sem recriá-lo como `CREATE INDEX` separado**. Apenas `UNIQUE KEY` é convertido (linhas 140-151). Resultado: todo `$table->index(...)` declarado nas migrations some do schema de testes.
- **Evidência:**
  ```php
  // Extract UNIQUE KEYs ... (linhas 141-151)
  // ...
  // Remove KEY/INDEX definitions
  $sql = preg_replace('/,\s*UNIQUE\s+KEY\s+`[^`]+`\s*\([^)]+\)/i', '', $sql);
  $sql = preg_replace('/,\s*KEY\s+`[^`]+`\s*\([^)]+\)/i', '', $sql);   // <-- descarta sem reaproveitar
  ```
  Verificação empírica: contagem total de `CREATE INDEX` no dump = **0**. Todas as 521 tabelas têm zero índices não-uniques (só unique). Exemplo concreto: migration `2026_02_07_400000_create_work_order_tables.php:63-64` declara `index(['tenant_id','status'])` e `index(['tenant_id','customer_id'])`, mas `work_orders` no dump tem `IDX(0)`. Mesmo para `audit_logs`, `expenses`, `schedules`, etc.
- **Impacto:** (a) Testes rodam contra schema sem índices não-uniques — plano de execução de queries divergente de produção; tests de performance perdem sentido. (b) Mascaramento de todos os outros findings de índice faltante: impossível distinguir se um índice realmente existe em MySQL ou se foi apagado no dump. (c) Qualquer análise baseada neste dump é não-conclusiva; todas as conclusões de índices aqui precisam ser validadas direto no MySQL.

---

### data-02 — `users.tenant_id` NULLABLE mas é o discriminador multi-tenant

- **Severidade:** S1
- **Arquivo:** `backend/database/schema/sqlite-schema.sql` (tabela `users`)
- **Descrição:** A coluna `tenant_id` em `users` está definida como `integer DEFAULT NULL`, e `current_tenant_id` idem. O `User` model usa o padrão `BelongsToTenant`/`current_tenant_id` para scoping e `CLAUDE.md` explicita que **"tenant_id sempre em `$request->user()->current_tenant_id`"** — mas um usuário pode persistir em estado `NULL/NULL`.
- **Evidência:**
  ```sql
  CREATE TABLE "users" (
    ...
    "tenant_id" integer DEFAULT NULL,
    "current_tenant_id" integer DEFAULT NULL,
    ...
  )
  ```
  E `app/Models/User.php:204`:
  ```php
  $resolvedTeamId = $this->current_tenant_id ?? $this->tenant_id ?? $previousTeamId;
  ```
- **Impacto:** Usuário sem `tenant_id` nem `current_tenant_id` cai em `$previousTeamId` (variável externa) ou `NULL`. Se alguma query de negócio se basear em `tenant_id` e o usuário vier com NULL, pode vazar dados cross-tenant ou bypass de `BelongsToTenant` (o global scope não disparará). Considerando que a plataforma é estritamente multi-tenant, a coluna deveria ser `NOT NULL` para qualquer usuário de operação e a regra explicitada (ou usuário global = super-admin de plataforma tem flag separada).

---

### data-03 — `tenants.slug` sem UNIQUE e `tenants.document` (CNPJ) sem UNIQUE nem NOT NULL

- **Severidade:** S1
- **Arquivo:** `backend/database/schema/sqlite-schema.sql` (tabela `tenants`), `backend/database/migrations/2026_02_07_200000_create_tenant_tables.php:11-19`, `backend/database/migrations/2026_03_17_070000_add_missing_columns_for_tests.php:285` (adiciona `slug` sem unique)
- **Descrição:**
  - `slug` é `varchar(255) DEFAULT NULL`, sem UNIQUE INDEX — dois tenants podem ter o mesmo slug, rompendo qualquer rota pública `/{slug}/...` ou lookup determinístico.
  - `document` (CNPJ/CPF) é `varchar(20) DEFAULT NULL`, sem UNIQUE INDEX — duas empresas/tenants com o mesmo CNPJ podem coexistir.
  - Nenhum índice em `tenants` segundo o dump: `IDX(0)`, `UQ(0)`.
- **Evidência:** Em `2026_02_07_200000_create_tenant_tables.php:11-19`:
  ```php
  Schema::create('tenants', function (Blueprint $table) {
      $table->id();
      $table->string('name');
      $table->string('document', 20)->nullable(); // CNPJ/CPF
      ...
  });
  ```
  Nenhum `$table->unique(...)` sobre `tenants` em nenhuma migration subsequente. `slug` adicionado muito depois em `2026_03_17_070000_add_missing_columns_for_tests.php:285`, também sem unique.
- **Impacto:** Risco de colisão de tenants reais. `slug` colidido quebra frontend/rota. CNPJ duplicado permite criar pseudo-tenant no mesmo CNPJ, causando problemas contábeis, fiscais e de isolamento. Em SaaS sério, ambos deveriam ser `UNIQUE`.

---

## S2 — Alto

### data-04 — FKs declaradas nas migrations sem índice próprio (impacto de performance e de `onDelete cascade`)

- **Severidade:** S2
- **Arquivo:** Várias migrations de criação (`create_work_order_tables.php`, `create_expense_tables.php`, `create_financial_tables.php`, `create_tenant_tables.php`, `create_audit_settings_tables.php`, `create_remaining_module_tables.php`).
- **Descrição:** Nas migrations, muitas FKs são criadas via `->foreign(...)` ou `->foreignId(...)` mas **nenhum `->index()` é adicionado sobre a coluna**. Laravel não cria índice automático sobre `foreign()` simples — só para `foreignId()->constrained()` que usa a convenção. E o dump (data-01) confirma que nenhum índice não-unique existe em testes — mas além disso, a leitura das migrations mostra que várias FKs de uso frequente (`cost_center_id`, `chart_of_account_id`, `work_order_id` em `expenses`, `reimbursement_ap_id`, `collection_rule_id`, `parent_work_order_id`, `fleet_vehicle_id`, `auto_assignment_rule_id`, `project_id`, etc.) nunca recebem declaração de índice em nenhuma migration.
- **Evidência:** Ex. `expenses` (arquivo `2026_02_07_800000_create_expense_tables.php`) declara somente `index(['tenant_id','status','expense_date'])`. FKs de `work_order_id`, `chart_of_account_id`, `cost_center_id`, `reimbursement_ap_id`, `payroll_id`, `reference_id` — nenhuma tem index explícito. Dump `expenses`: `IDX(0)`, `UQ(0)`. Mesmo padrão em `accounts_payable`, `accounts_receivable`, `schedules` (dump `IDX(0)`, `UQ(0)`), `work_orders` (dump `IDX(0)`, só unique do number e rating_token).
- **Impacto:** Queries que fazem JOIN ou WHERE por essas FKs varrem tabela inteira. `ON DELETE CASCADE` sem índice na FK causa full-scan em cascata — apagar um work_order pode ler toda `expenses`, `schedules`, etc. Em escala de produção (muitos tenants, muitas OS) vira bottleneck não-diagnosticado.

### data-05 — `audit_logs.tenant_id` NULLABLE permite log órfão de tenant

- **Severidade:** S2
- **Arquivo:** `backend/database/schema/sqlite-schema.sql` (tabela `audit_logs`), `backend/database/migrations/2026_02_07_900000_create_audit_settings_tables.php:17-29`
- **Descrição:** `audit_logs.tenant_id` é `integer DEFAULT NULL`. Se a aplicação logar ações de contexto "sem tenant" (ex: cron job, operação de super-admin), o registro acaba sem tenant atribuído e não aparece em nenhuma consulta `WHERE tenant_id = X`. Consequência: auditorias de conformidade podem perder eventos.
- **Evidência:** `audit_logs` no schema:
  ```sql
  "tenant_id" integer DEFAULT NULL,
  "user_id" integer DEFAULT NULL,
  ```
  Migration: `$t->unsignedBigInteger('tenant_id')->nullable();` (linha aproximada 17-19).
- **Impacto:** Eventos auditáveis podem ser persistidos sem `tenant_id`, escapando de relatórios LGPD / ISO 17025 por tenant. Também quebra o filtro natural de `BelongsToTenant`. Para tabela crítica de compliance, o esperado é `NOT NULL` com uma chave de "platform" se houver ação global.

### data-06 — `webhook_logs.tenant_id` NULLABLE + sem índice (tabela hot)

- **Severidade:** S2
- **Arquivo:** `backend/database/schema/sqlite-schema.sql` (tabela `webhook_logs`)
- **Descrição:** `webhook_logs.tenant_id` é `integer DEFAULT NULL`. Ela recebe tráfego alto (cada webhook disparado = um insert) e carece de qualquer índice (dump `IDX(0)`, `UQ(0)`), inclusive composto por `(tenant_id, created_at)`.
- **Evidência:**
  ```sql
  CREATE TABLE "webhook_logs" (
    "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
    "tenant_id" integer DEFAULT NULL,
    "webhook_id" integer NOT NULL,
    ...
  );
  -- IDX(0) UQ(0)
  ```
- **Impacto:** (a) Logs podem ficar sem tenant; (b) qualquer filtro "últimos logs do tenant X" vira full-scan; (c) sem index em `(webhook_id)` nem em `(status, created_at)`, quem procurar falhas recentes varre tabela completa.

### data-07 — `expenses` com 3 FKs sobrepostas sem explicitação de fluxo (`reimbursement_ap_id`, `reference_type/reference_id`, `work_order_id`)

- **Severidade:** S2
- **Arquivo:** `backend/database/schema/sqlite-schema.sql` (tabela `expenses`) — colunas `reimbursement_ap_id`, `reference_type`, `reference_id`, `work_order_id`, `payroll_id`, `payroll_line_id`
- **Descrição:** A tabela `expenses` carrega múltiplos mecanismos redundantes de apontamento para origem: FK direta `work_order_id`, FK `reimbursement_ap_id` (para accounts_payable), FKs `payroll_id`/`payroll_line_id` e um polimórfico `reference_type`/`reference_id`. Não há regra de integridade (CHECK constraint) garantindo mutual exclusion, nem índice composto em `(reference_type, reference_id)` — típico problema de polimórfico sem MorphIndex. Nenhum desses pares tem índice (dump `IDX(0)`).
- **Evidência:**
  ```sql
  "expense_category_id" integer DEFAULT NULL,
  "work_order_id" integer DEFAULT NULL,
  "reimbursement_ap_id" integer DEFAULT NULL,
  "payroll_id" integer DEFAULT NULL,
  "payroll_line_id" integer DEFAULT NULL,
  "reference_type" varchar(50) DEFAULT NULL,
  "reference_id" integer DEFAULT NULL
  ```
- **Impacto:** Risco de despesa com múltiplas origens atribuídas simultaneamente (incoerente). Consultas "todas despesas de X" precisam scanear. Em polimórfico sem índice é comum em Laravel e deve ser pelo menos `(reference_type, reference_id)` composto, que também está faltando.

### data-08 — `customers` sem UNIQUE natural `(tenant_id, document)` — protege só document_hash

- **Severidade:** S2
- **Arquivo:** `backend/database/schema/sqlite-schema.sql` (tabela `customers`)
- **Descrição:** `customers` tem um unique elaborado `customers_tenant_active_document_hash_unique("tenant_id","document_hash","document_hash_active_key")` (versão soft-delete-aware), mas **nenhuma UNIQUE em `(tenant_id, document)` direto** nem em `(tenant_id, email)`. Se `document` (CPF/CNPJ em texto cru ou encriptado) e `document_hash` divergirem por bug de escrita, o sistema aceitaria dois clientes com o mesmo CPF num mesmo tenant. Também não há unique em `(tenant_id, code)` ou equivalente de código de cliente.
- **Evidência:** dump de `customers` mostra `UQ(1)` — apenas o triplete acima. Ver `backend/database/migrations/2026_02_07_300000_create_cadastros_tables.php:58-60`:
  ```php
  $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
  $table->index(['tenant_id', 'name']);
  $table->index(['tenant_id', 'document']);  // index, não unique
  ```
- **Impacto:** Duplicação silenciosa de cadastro quando o pipeline de gravação falha em sincronizar `document` e `document_hash`. Em multi-tenant regulado por CNPJ, duplicação é erro de compliance.

### data-09 — `schedules` sem índice em `technician_id` nem `scheduled_start` — ponto quente da agenda

- **Severidade:** S2
- **Arquivo:** `backend/database/schema/sqlite-schema.sql` (tabela `schedules`)
- **Descrição:** `schedules` é tabela muito consultada ("agenda do técnico X entre datas"). Dump mostra `IDX(0)`, `UQ(0)`. Sem índices em `technician_id`, `(tenant_id, scheduled_start)`, `(tenant_id, technician_id, scheduled_start)`, cada render da agenda faz full-scan.
- **Evidência:**
  ```sql
  CREATE TABLE "schedules" (
    "id" ...,
    "tenant_id" integer NOT NULL,
    "work_order_id" integer DEFAULT NULL,
    "customer_id" integer DEFAULT NULL,
    "technician_id" integer DEFAULT NULL,
    ...
    "scheduled_start" datetime NOT NULL,
    "scheduled_end" datetime NOT NULL,
    "status" varchar(20) NOT NULL DEFAULT 'scheduled',
    ...
  );
  -- IDX(0) UQ(0)
  ```
  `Schedule` model usa `technician_id` como relation de usuário (`app/Models/Schedule.php:61`).
- **Impacto:** Performance degradada na tela principal do operacional. Em tenants com volume de agendamentos, timeouts e pressão no DB. (Dependência de data-01 para saber se o MySQL real tem índice; mas mesmo lendo as migrations, nenhum `index(['technician_id'...])` ou `index(['scheduled_start'...])` é declarado.)

### data-10 — `audit_logs`, `webhook_logs` e `whatsapp_message_logs` sem estratégia de retenção/arquivamento

- **Severidade:** S2
- **Arquivo:** `backend/database/schema/sqlite-schema.sql` + migrations
- **Descrição:** Tabelas tipicamente "hot" (crescem rápido, queries recentes dominam) não têm nenhuma estratégia estrutural visível: sem índice de `created_at` (dump: IDX(0)), sem partition key, sem TTL em nível de banco, sem arquivamento via job. Em produção, `audit_logs` pode crescer a milhões de linhas/mês; qualquer análise "últimas X horas" fica crítica.
- **Evidência:** Confirmado no schema (nenhuma coluna `archived_at`, `expires_at`, nenhum comentário no migration de job de retenção). A migration do audit_logs declara `index(['tenant_id','created_at'])` mas o schema dump confirma que não foi materializado (ver data-01); em produção fica a dúvida se o MySQL real tem.
- **Impacto:** Em ~6 meses de produção com múltiplos tenants, custo de disco + risco de slowdown global. LGPD também exige política de retenção explícita — nada disso está no schema.

---

## S3 — Médio

### data-11 — Tabelas `personal_access_tokens` e `email_attachments` não têm `tenant_id` mas são domínio escopado

- **Severidade:** S3
- **Arquivo:** `backend/database/migrations/2026_02_08_024851_create_personal_access_tokens_table.php`, `backend/database/migrations/2026_02_07_950003_create_email_attachments_table.php`
- **Descrição:** `personal_access_tokens` (Sanctum) usa `morphs('tokenable')` — esperado padrão do pacote. Mesmo assim, ausência de `tenant_id` e falta de index composto `(tokenable_type, tokenable_id, tenant_id_via_user)` dificulta auditoria/revocação por tenant. `email_attachments` não tem `tenant_id`, apenas `email_id`. A isolação depende do JOIN em `emails` — se `emails` não for sempre filtrado, attachments vazam entre tenants via `WhereIn`.
- **Evidência:**
  ```php
  // email_attachments
  Schema::create('email_attachments', function (Blueprint $table) {
      $table->id();
      $table->foreignId('email_id')->constrained('emails')->...
      $table->string('filename');
      // nenhum tenant_id
  });
  ```
- **Impacto:** Depende da aplicação filtrar via `emails` — é um ponto frágil.

### data-12 — Colisão de timestamp em migrations (múltiplas migrations com mesmo prefixo até o segundo)

- **Severidade:** S3
- **Arquivo:** várias migrations com o mesmo `YYYY_MM_DD_HHMMSS` (ex.: `2026_03_16_000001`, `2026_03_16_500001`, `2026_03_20_120000`, `2026_04_09_100000`, etc.) — 10+ pares encontrados.
- **Descrição:** Laravel ordena migrations por string; colisões resolvem-se por ordem alfabética do sufixo. Se as migrations colididas criarem/alterarem os mesmos objetos, o resultado é dependente de ordem de execução e frágil.
- **Evidência:** Listagem em `backend/database/migrations/`:
  ```
  2026_03_16_000001_... (múltiplas)
  2026_03_20_120000_... (múltiplas)
  2026_03_22_200000_... (múltiplas)
  2026_04_09_100000_add_tenant_id_to_central_item_history_and_comments.php
  2026_04_09_100000_add_tenant_id_to_crm_child_tables.php
  ```
- **Impacto:** Não catastrófico quando as migrations tocam tabelas diferentes, mas é padrão ruim. Em rebase/merge, colisões já ocorrem — é um smell.

### data-13 — Migrations `alter_*` sem guards `hasTable/hasColumn`

- **Severidade:** S3
- **Arquivo:** `backend/database/migrations/2026_02_08_500000_alter_commission_rules_v2.php`, `2026_02_08_700000_alter_equipments_v2.php`, `2026_02_08_900000_alter_customers_crm_fields.php`, `2026_02_09_300002_alter_recurring_contracts_billing.php`, `2026_03_26_182723_alter_corrective_actions_sourceable_nullable.php`
- **Descrição:** CLAUDE.md proíbe alterar migrations mergeadas e pede `hasTable`/`hasColumn` guards em novas migrations. Cinco migrations `alter_*` críticas não possuem esses guards. Se executadas em um ambiente onde a coluna/tabela já foi alterada (ex: hotfix aplicado direto em prod), vão falhar.
- **Evidência:** `grep -L "hasTable\|hasColumn"` retornou esses cinco arquivos.
- **Impacto:** Risco de falha de `migrate` em ambiente onde estado já foi parcialmente aplicado; obriga rollback manual.

### data-14 — `user_2fa` unique por `user_id` apenas (não por `(tenant_id, user_id)`)

- **Severidade:** S3
- **Arquivo:** `backend/database/schema/sqlite-schema.sql` (tabela `user_2fa`), `backend/database/migrations/2026_02_14_060000_create_portal_integration_security_tables.php:218-225` (aprox.)
- **Descrição:** `user_2fa` tem unique `(user_id)` global. `user_id` já é globalmente único, mas o padrão multi-tenant do sistema é `tenant_id + entity_id`. Se um usuário trocar de tenant (improvável, mas o schema sugere via `current_tenant_id`), o 2FA viaja com ele sem reset — o que pode ser um comportamento desejado, mas não está documentado.
- **Evidência:** dump:
  ```
  user_2fa_user_id_unique ("user_id")
  ```
- **Impacto:** Ambiguidade de comportamento em cenário de switch de tenant — não quebra, mas é inconsistente com o padrão.

### data-15 — `payment_gateway_configs` unique por `tenant_id` bloqueia multi-gateway por tenant

- **Severidade:** S3
- **Arquivo:** `backend/database/schema/sqlite-schema.sql`, `backend/database/migrations/2026_02_14_070000_create_remaining_module_tables.php:78`
- **Descrição:** `payment_gateway_configs_tenant_id_unique("tenant_id")` — **apenas 1 gateway por tenant**. Se um cliente quiser PIX (Asaas) + Boleto (Pagar.me) simultaneamente, schema bloqueia. Deveria ser `(tenant_id, gateway)` unique.
- **Evidência:** dump unique; migration:
  ```php
  $t->unsignedBigInteger('tenant_id')->unique();
  $t->string('gateway', 30)->default('none');
  ```
- **Impacto:** Gap funcional — impede arquitetura de múltiplos gateways por tenant, comum em cobranças.

---

## S4 — Baixo

### data-16 — `tenant_id` nullable também em `webhook_logs` e `audit_logs` obriga índice parcial em produção

- **Severidade:** S4 (duplicação de data-05/06, listado para completude)
- **Descrição:** Ver data-05 e data-06; consequência acessória é que qualquer índice composto `(tenant_id, ...)` precisa lidar com NULLs (MySQL trata NULL como valor único, OK, mas consulta `WHERE tenant_id = ?` nunca casará com NULLs). Isso pode gerar relatórios incompletos sem log de alerta.
- **Impacto:** Baixo — mais um efeito colateral.

### data-17 — Colunas `numeric` no dump podem mascarar diferença de precisão

- **Severidade:** S4
- **Arquivo:** `backend/generate_sqlite_schema.php:128`
- **Descrição:** O conversor MySQL→SQLite transforma **qualquer** `decimal(N,M)` em `numeric` (sem precisão). Em SQLite, `numeric` é storage class flexível. Logo, o schema de testes não reflete a precisão real; se uma migration futura introduzir `decimal(8,2)` onde a regra de negócio pedia `decimal(10,2)`, a suíte não pega.
- **Evidência:** `preg_replace('/\bdecimal\(\d+,\s*\d+\)/i', 'numeric', $sql);`
- **Impacto:** Teste pode passar e produção arredondar / truncar.

### data-18 — `suppliers.document` NULLABLE + sem unique; `users` com `email` unique global (não por tenant)

- **Severidade:** S4
- **Arquivo:** `backend/database/schema/sqlite-schema.sql` (tabelas `suppliers`, `users`)
- **Descrição:** `suppliers.document` é `text` nullable, somente `document_hash` existe — análogo a data-08 mas com menor criticidade porque é fornecedor. `users.email` tem unique **global** (`users_email_unique("email")`) — correto para auth Laravel, mas cria acoplamento inter-tenant: um email só pode existir em um tenant. Se um usuário real (mesmo email) tiver que operar dois tenants (SaaS multi-empresa), precisa relogar ou ter logica separada — `current_tenant_id` atende parcialmente.
- **Evidência:**
  ```sql
  -- users
  UQ(1): users_email_unique ("email")
  ```
- **Impacto:** Design decision, não bug, mas bloqueia cenário comum de auditor/contador trabalhando para múltiplas empresas com o mesmo email.

---

## Observações adicionais (não são findings, para contextualização)

- `portal_tickets.assigned_to` FK para `users` sem `onDelete` declarado (via `Schema::create`) — comportamento depende do default do MySQL. Não verifiquei migration linha-a-linha.
- `accounts_payable.supplier` (varchar) coexiste com `supplier_id` (FK) — dupla representação do fornecedor. Pode ser legado, mas confunde.
- `accounts_receivable.nosso_numero`/`numero_documento` sem unique por tenant — cobrança pode gerar dois títulos com mesmo nosso_numero no mesmo tenant.
- Models com SoftDeletes verificado em amostra (Tenant, User, Schedule, Customer): coluna `deleted_at` existe nas tabelas correspondentes. Nenhum caso de SoftDeletes em model sem coluna encontrado na amostra, mas sem índice em `deleted_at` em nenhuma tabela inspeccionada (consequência de data-01).
- Charset/collation: o conversor remove-os (linhas 92-103 do gerador) — não é possível avaliar via dump. Em produção precisa conferência direta no MySQL.

---

## Resumo executivo

- **3 S1:** gerador de schema rouba índices (data-01); `users.tenant_id` nullable (data-02); `tenants.slug`/`document` sem UNIQUE (data-03).
- **6 S2:** FKs sem índice (data-04); `audit_logs.tenant_id` nullable (data-05); `webhook_logs` idem + hot table sem índice (data-06); `expenses` com origens redundantes sem CHECK (data-07); `customers` sem unique natural por tenant+document (data-08); `schedules` sem nenhum índice (data-09); sem retenção para logs hot (data-10).
- **5 S3:** `personal_access_tokens`/`email_attachments` sem tenant_id (data-11); colisão de timestamps em migrations (data-12); alters sem guards (data-13); `user_2fa` unique não composto com tenant (data-14); `payment_gateway_configs` single-gateway por tenant (data-15).
- **3 S4:** consequência de nullability (data-16); precisão decimal perdida em dump (data-17); suppliers.document sem unique + users.email unique global (data-18).

**data-01 é o finding-guarda-chuva**: enquanto o gerador de schema descartar índices não-uniques, qualquer avaliação baseada no dump SQLite superestima a quantidade de índices faltantes (porque muitos podem existir em MySQL). Recomendo corrigir o gerador primeiro e re-rodar a inspeção.
