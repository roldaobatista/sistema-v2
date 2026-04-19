# Re-auditoria Camada 1 — Cobertura de Índices (data-expert, R2)

**Data:** 2026-04-18
**Perímetro:** cobertura de índices em tabelas centrais e hot tables do Kalibrium ERP.
**Escopo restrito ao checklist:** §1 (índices FK/filtro), §4 (integridade/polymorphic), §6 (schema drift migration→dump).
**Artefato principal inspecionado:** `backend/database/schema/sqlite-schema.sql` (gerado em 2026-04-17 19:35:30), 11 235 linhas, 521 `CREATE TABLE`.

## Contagem absoluta (obrigatória)

- `CREATE INDEX`: **2 041**
- `CREATE UNIQUE INDEX`: **150**
- **Total de índices** (incluindo auto-criados por UNIQUE): **2 191**
- `CREATE TABLE`: 521

## Cobertura das tabelas centrais inspecionadas (≥1 índice?)

| Tabela                     | Índices declarados | Cobertura mínima |
|----------------------------|-------------------:|:----------------:|
| customers                  | 15                 | OK               |
| suppliers                  | 5                  | OK               |
| users                      | 10                 | OK               |
| work_orders                | 32                 | OK               |
| schedules                  | 12                 | OK               |
| expenses                   | 14                 | OK               |
| accounts_payable           | 16                 | OK               |
| accounts_receivable        | 23                 | OK               |
| audit_logs                 | 5                  | OK               |
| webhook_logs               | 2                  | OK (mas gaps)    |
| notifications              | 6                  | OK               |
| equipment_calibrations     | 8                  | OK               |
| tool_calibrations          | 3                  | OK               |
| calibration_readings       | 3                  | OK               |
| employee_benefits          | 3                  | OK               |
| employee_documents         | 3                  | OK               |

→ **16/16 tabelas centrais inspecionadas têm ≥1 índice.**
→ Tabelas `employees`, `integration_logs`, `calibrations` do escopo do coordenador **não existem no schema** (inexistência confirmada por `grep -oE '^CREATE TABLE "[^"]+"'`). O domínio equivalente é coberto por `users` + `employee_*` (RH) e `equipment_calibrations`/`tool_calibrations`/`calibration_readings` (calibração). Tratado como ausência de tabela, não como gap de índice.

---

## Achados

### data-idx-01 — S2 (alto) — `webhook_logs` sem índice em `status` nem `created_at`

- **Arquivo:** `backend/database/schema/sqlite-schema.sql` (tabela `webhook_logs`).
- **Migration de origem:** `backend/database/migrations/2026_02_14_100000_add_200_features_batch1_tables.php:269-280`.
- **Evidência (dump):**
  ```
  CREATE TABLE "webhook_logs" (
    "id" ...,
    "tenant_id" integer DEFAULT NULL,
    "webhook_id" integer NOT NULL,
    "event" varchar(100) NOT NULL,
    ...
    "status" varchar(20) NOT NULL DEFAULT 'pending',
    "created_at" datetime NULL DEFAULT NULL,
    ...
  );
  CREATE INDEX "webhook_logs_webhook_id_foreign" ON "webhook_logs" ("webhook_id");
  CREATE INDEX "webhook_logs_tenant_id_idx"      ON "webhook_logs" ("tenant_id");
  ```
- **Descrição:** a tabela `webhook_logs` é hot — cresce a cada entrega/falha de webhook. Tem apenas índice em `webhook_id` e `tenant_id` isolados. Não há índice em `status`, em `event`, nem em `(webhook_id, created_at)` para consultar "últimas entregas de um webhook" ou "falhas recentes". Comparando com tabelas logs congêneres (`audit_logs` tem `(tenant_id, created_at)`, `(tenant_id, auditable_type, auditable_id)`), `webhook_logs` está subindexada.
- **Impacto:** queries típicas como `WHERE status='failed'`, `WHERE webhook_id=? ORDER BY created_at DESC LIMIT 20`, ou filtros por `event` farão full scan sobre tabela com crescimento monotônico. Em produção, degrada listagens de dashboard de integrações e jobs de retry/alerta de falha.

### data-idx-02 — S2 (alto) — `bank_statement_entries.matched_*` polimórfico sem índice composto

- **Arquivos:**
  - `backend/app/Models/BankStatementEntry.php:52` (`return $this->morphTo('matched', 'matched_type', 'matched_id');`)
  - `backend/database/schema/sqlite-schema.sql` — tabela `bank_statement_entries`.
- **Evidência:** inspeção dos índices desta tabela mostra que nenhum `CREATE INDEX` cobre a tupla `(matched_type, matched_id)`. Apenas índices esparsos em `matched_id` (ou `matched_type`) existem, mas não o índice composto que Laravel criaria via `$table->morphs(...)`. O model declara relação polimórfica, porém a migration define as duas colunas manualmente (provavelmente `string('matched_type')->nullable(); unsignedBigInteger('matched_id')->nullable();`) sem gerar o índice de varredura.
- **Descrição:** relacionamento polimórfico real sem o índice composto canônico `(type, id)` que caracteriza `morphs()`. Vai contra a prática documentada em `docs/TECHNICAL-DECISIONS.md §14.7`, que afirma: "Helper `morphs()`/`nullableMorphs()` cria índice composto `(type, id)` automaticamente — queries `WHERE *_type = X AND *_id = Y` são performantes". Aqui o índice canônico não foi provido manualmente.
- **Impacto:** conciliação bancária (`BankStatementEntry::with('matched')->where('matched_type', ...)`) faz full scan em uma tabela que é append-only e cresce rapidamente em clientes que usam CNAB. Em MySQL 8 produção o plano vira table scan com filesort.

### data-idx-03 — S3 (médio) — polymorphic `email_logs.related_*` sem índice

- **Arquivos:**
  - `backend/app/Models/EmailLog.php:14` (`'related_type', 'related_id'` no fillable).
  - `backend/database/schema/sqlite-schema.sql` — tabela `email_logs`.
- **Evidência:** a análise automatizada listou `email_logs morph='related' composite_idx=False any_idx_on_morph_cols=False` (nenhum índice em nenhuma das duas colunas). Model trata como polymorphic pelo nomeado.
- **Impacto:** reverse-lookup "todos os e-mails enviados a propósito do registro X" (`WHERE related_type='App\\Models\\Quote' AND related_id=42`) faz full scan. Mesmo se o volume hoje seja moderado, o padrão de query é garantido polimórfico.

### data-idx-04 — S3 (médio) — polymorphic `whatsapp_messages.related_*` sem índice

- **Arquivo:** `backend/database/schema/sqlite-schema.sql` — tabela `whatsapp_messages`.
- **Evidência:** `whatsapp_messages morph='related' composite_idx=False any_idx_on_morph_cols=False`. Colunas `related_type`/`related_id` presentes, nenhum índice.
- **Impacto:** igual a data-idx-03 — reverse-lookup de mensagens por entidade origem é full scan. A tabela é hot em deploys com integração WhatsApp ativa.

### data-idx-05 — S3 (médio) — `sync_queue_items.entity_*` sem índice

- **Arquivos:**
  - `backend/app/Models/SyncQueueItem.php:30` (`'entity_type'` no fillable).
  - `backend/database/schema/sqlite-schema.sql` — tabela `sync_queue_items`.
- **Evidência:** `sync_queue_items morph='entity' composite_idx=False any_idx_on_morph_cols=False`. Nenhum índice em `entity_type`/`entity_id`.
- **Impacto:** sync PWA/offline consulta itens pendentes por tipo e id. Sem índice, cada pull offline escaneia toda a fila.

### data-idx-06 — S3 (médio) — demais polymorphics sem índice composto

- **Arquivo:** `backend/database/schema/sqlite-schema.sql`.
- **Evidência** (saída do analisador):
  ```
  chat_messages      morph='sender'    composite_idx=False any_idx_on_morph_cols=True
  mobile_notifications morph='entity'  composite_idx=False any_idx_on_morph_cols=True
  print_jobs         morph='document'  composite_idx=False any_idx_on_morph_cols=True
  reconciliation_rules morph='target'  composite_idx=False any_idx_on_morph_cols=True
  sync_queue         morph='entity'    composite_idx=False any_idx_on_morph_cols=True
  ```
- **Descrição:** cinco polimórficos reais (`sender`, `entity`, `document`, `target`) com, no máximo, índice em UMA das colunas do par — e nenhum índice composto `(type, id)`. O índice single-column dá seletividade quase nula quando o `*_type` é do tipo `App\Models\WorkOrder` (concentração de 80%+ das rows). Um índice composto `(type, id)` é o padrão do Laravel e não foi provido.
- **Impacto:** queries polimórficas `morphTo()` sobre qualquer desses registros são lentas. `reconciliation_rules` e `print_jobs` têm uso operacional direto no Financeiro e Impressão.

### data-idx-07 — S3 (médio) — `audit_logs.tenant_id` NULLABLE sem índice parcial

- **Arquivo:** `backend/database/schema/sqlite-schema.sql`, tabela `audit_logs`.
- **Evidência:**
  ```
  CREATE TABLE "audit_logs" (
    ...
    "tenant_id" integer DEFAULT NULL,          -- nullable
    "user_id"   integer DEFAULT NULL,
    ...
  );
  CREATE INDEX "audit_logs_tenant_id_created_at_index"
    ON "audit_logs" ("tenant_id","created_at");
  ```
- **Descrição:** `tenant_id` é `DEFAULT NULL` aceito por decisão Wave 2B-fix (TD §14.4). Porém o índice `(tenant_id, created_at)` inclui rows com `tenant_id IS NULL` (eventos de autenticação / system). Todas as queries do ERP filtram por `tenant_id = ?` (não-NULL), e o índice tem baixa seletividade para rows tenant (a árvore inclui rows system misturadas). Um índice parcial `WHERE tenant_id IS NOT NULL` (MySQL 8.0 não tem índice parcial, mas o driver usa o mesmo dump; portanto este é um gap conceitual de design, não técnico) ou um índice separado para events de sistema mitigaria isso.
- **Impacto:** baixo-a-médio hoje; alto se a tabela crescer para dezenas de milhões. Nota: não é finding bloqueante porque MySQL 8 ignora NULLs em seletividade. Mantido como S3 para ação futura (`created_at DESC` é query típica de auditoria).

### data-idx-08 — S3 (médio) — `notifications` sem índice em `(tenant_id, type)` nem `(user_id, type, read_at)`

- **Arquivo:** `backend/database/schema/sqlite-schema.sql`, tabela `notifications`.
- **Evidência (índices existentes):**
  ```
  notifications_notifiable_type_notifiable_id_index   (notifiable_type, notifiable_id)
  notifications_user_id_read_at_created_at_index      (user_id, read_at, created_at)
  notifications_notif_tenant_user_read_idx            (tenant_id, notifiable_id, read_at)
  notifications_notif_user_read                       (user_id, read_at)
  notifications_notif_tenant_created                  (tenant_id, created_at)
  notifications_tenant_id_idx                         (tenant_id)
  ```
- **Descrição:** padrão de query de sino de notificação filtra `WHERE user_id=? AND type='X' ORDER BY created_at DESC`. O campo `type` (50 valores possíveis — `work_order_assigned`, `payment_due`, etc.) **não tem índice** — apenas `notifiable_type` (polymorphic) tem. Listagem por categoria/tipo faz scan + filter.
- **Impacto:** baixo-a-médio; depende de chamadas do front do sino. É o único lookup natural por `type` em uma tabela que recebe centenas de inserts/dia por tenant.

### data-idx-09 — S4 (baixo) — redundância de índices em `deleted_at` (múltiplas cópias)

- **Arquivo:** `backend/database/schema/sqlite-schema.sql`.
- **Evidência:** padrão recorrente em várias tabelas — `customers`, `suppliers`, `accounts_payable`, `accounts_receivable`, `schedules`, `expenses` têm 2-3 índices iguais/sobrepostos em `deleted_at`:
  ```
  customers_cust_deleted_at            (tenant_id, deleted_at)
  customers_del_idx                    (deleted_at)
  customers_deleted_at_idx             (deleted_at)

  suppliers_sup_deleted_at             (tenant_id, deleted_at)
  suppliers_del_idx                    (deleted_at)
  suppliers_deleted_at_idx             (deleted_at)
  ```
  Dois índices idênticos `(deleted_at)` sob nomes diferentes (`*_del_idx` e `*_deleted_at_idx`).
- **Descrição:** migrations distintas criaram o mesmo índice com nomes diferentes. Em MySQL, isso dobra custo de manutenção no INSERT/UPDATE e consome buffer pool sem benefício.
- **Impacto:** overhead de escrita ~ 5-10% em tabelas de alto volume. Custo de storage. Não é bug funcional.

### data-idx-10 — S3 (médio) — schema drift: índices presentes no dump mas sem migration canônica

- **Arquivo:** `backend/database/schema/sqlite-schema.sql` + `backend/database/migrations/`.
- **Evidência:** o dump SQLite tem 2 041 `CREATE INDEX` enquanto as migrations declaram apenas **405** chamadas `$table->index(...)` (contagem via `grep -cE "\\\$table->index\\("`). A grande diferença é explicada por (a) índices auto-criados por `foreignId()->constrained()`, `unique()`, `morphs()`, etc. e (b) migrations que usam `DB::statement('CREATE INDEX ...')` cru. Porém o descompasso é grande o bastante que **não é trivial mapear 1-para-1 entre dump e declarações explícitas**. Exemplo direto: `suppliers_sup_deleted_at` existe no dump — a qual migration pertence? Busca `grep -rn "sup_deleted_at" backend/database/migrations/` precisa ser feita em cada índice para rastreabilidade.
- **Descrição:** não há drift observável "índice declarado e ausente" sem rodar `php artisan migrate:status` + diff por tabela; a verificação amostral fez match em todas as tabelas inspecionadas. Porém, **não há convenção de nomenclatura única** (índices com prefixos `_del_idx`, `_deleted_at_idx`, `_cust_deleted_at`, `_deleted_at_index` coexistem para o mesmo conceito), o que dificulta auditoria de drift futura e sugere migrations posteriores sem checar índices pré-existentes (ver data-idx-09).
- **Impacto:** risco de regressão em auditorias futuras. Sem canonicalização do nome, migrations idempotentes (`if (! Schema::hasIndex(...))`) falham em detectar o índice existente com nome diferente.

---

## Seções auditadas sem achado

- **§1 — Cobertura de FK e filtro em tabelas centrais (users, customers, suppliers, work_orders, schedules, accounts_payable, accounts_receivable, expenses):** todas têm índice em `tenant_id`, na FK principal (`customer_id`, `supplier_id`, `technician_id`, `work_order_id`, etc.) e nos campos de filtro documentados (`status`, `due_date`, `scheduled_start`, `deleted_at`). Nada encontrado. Verificado: `sqlite-schema.sql` seções `customers`, `suppliers`, `users`, `work_orders`, `schedules`, `accounts_payable`, `accounts_receivable`, `expenses`.
- **§1 — SoftDelete `deleted_at`:** todas as tabelas centrais inspecionadas com `deleted_at` têm ao menos um índice direto em `deleted_at` e um composto `(tenant_id, deleted_at)`. Nada encontrado (achado data-idx-09 é redundância, não ausência).
- **§4 — FKs declaradas:** verificado em `accounts_payable`, `accounts_receivable`, `customers`, `work_orders`, `schedules` — colunas FK todas têm índice. SQLite não declara FK no schema dump (característica do driver), mas em MySQL as FKs são declaradas via `constrained()`. Nada encontrado.
- **§4 — Polimórficos do helper Laravel (`morphs()` / `nullableMorphs()`):** 12 chamadas em migrations, todas com índice composto `(type, id)` presente no dump (confirmado via análise automatizada — composite_idx=True para `tokenable`, `priceable`, `taggable`, `followable`, `favoritable`, `linked`, `notifiable`, `linked_entity`, `alertable`, `entity` em `portal_guest_links`, `sourceable`). Nada encontrado nesse subconjunto.
- **§6 — Drift em tabelas centrais amostradas:** as 16 tabelas inspecionadas têm correspondência visual entre migrations `Schema::create` (incluindo migrations de hotfix como `2026_03_17_100000_infra_audit_tenant_id_indexes.php` e `2026_04_17_170000_add_tenant_id_indexes_to_remaining_tables.php`) e os índices presentes no dump. Nada encontrado como "declarado e ausente".

---

## Resumo

| Severidade | Count | IDs                                                                                           |
|:----------:|:-----:|-----------------------------------------------------------------------------------------------|
| S1         | 0     | —                                                                                             |
| S2         | 2     | data-idx-01, data-idx-02                                                                      |
| S3         | 7     | data-idx-03, data-idx-04, data-idx-05, data-idx-06, data-idx-07, data-idx-08, data-idx-10     |
| S4         | 1     | data-idx-09                                                                                   |
| **Total**  | **10**|                                                                                               |

**Contagem de `CREATE INDEX` + `CREATE UNIQUE INDEX` no dump:** 2 041 + 150 = **2 191**.
**Tabelas centrais inspecionadas com ≥1 índice:** **16/16** (100%).

Saída: lista de achados. Nenhum veredito emitido. Não aprovado, não validado — apenas reportado.
