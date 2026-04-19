# Re-auditoria Camada 1 — product-expert (2026-04-18)

> Auditoria independente, sem viés, do estado atual do schema, migrations e perímetro do cadastro central (customers, suppliers, employees), financeiro (accounts_payable/receivable, expenses), operacional base (schedules, work_orders, quotes), calibração, central_items e inmetro_*.

---

## Resumo executivo (contagem por severidade)

- **S1 (crítico):** 0
- **S2 (alto):** 3
- **S3 (médio):** 3
- **S4 (baixo):** 2
- **Total:** 8 findings

---

## Findings

### prod-01 [S2] — `equipment_calibrations.calibration_type` DEFAULT em PT

- **Arquivo:linha:** `backend/database/schema/sqlite-schema.sql:2720`
- **Descrição:** Coluna `calibration_type` tem DEFAULT `'externa'` (português). A convenção declarada em `TECHNICAL-DECISIONS.md §3` e §14.13 exige **enums e valores de coluna em inglês lowercase**. Valores PT esperados seriam `'externa'` vs `'interna'` → EN `'external'` vs `'internal'`.
- **Evidência:**
  ```sql
  "calibration_type" varchar(30) NOT NULL DEFAULT 'externa',
  "result" varchar(30) NOT NULL DEFAULT 'approved',
  ```
  Note que `result` já foi migrado para EN (`approved`), mas `calibration_type` foi esquecido.
- **Busca em TECHNICAL-DECISIONS.md:** `calibration_type`/`externa` **não** estão documentados como limitação permanente aceita.
- **Impacto no negócio:** Mistura PT/EN no mesmo domínio (calibração) confunde desenvolvedores, quebra convenção declarada e cria débito de normalização. Resources/Resources frontend precisam traduzir um valor e não o outro. Relatórios ISO 17025 podem sair com o enum PT inesperado.

---

### prod-02 [S2] — `priority` enum inconsistente entre módulos do mesmo ERP

- **Arquivo:linha (amostras):**
  - `backend/database/schema/sqlite-schema.sql:920` — `central_items.priority` DEFAULT `'medium'`
  - `backend/database/schema/sqlite-schema.sql:1001` — `central_templates.priority` DEFAULT `'medium'`
  - `backend/database/schema/sqlite-schema.sql:3956` — `inmetro_owners.priority` DEFAULT `'normal'`
  - `backend/database/schema/sqlite-schema.sql:4742` — `material_requests.priority` DEFAULT `'normal'`
  - `backend/database/schema/sqlite-schema.sql:5453` — `portal_tickets.priority` DEFAULT `'normal'`
  - `backend/database/schema/sqlite-schema.sql:6224` — `recurring_contracts.priority` DEFAULT `'normal'`
  - `backend/database/schema/sqlite-schema.sql:6708` — `service_call_templates.priority` DEFAULT `'normal'`
  - `backend/database/schema/sqlite-schema.sql:6725` — `service_calls.priority` DEFAULT `'normal'`
  - `backend/database/schema/sqlite-schema.sql:8583` — `work_orders.priority` DEFAULT `'normal'`
  - `backend/database/schema/sqlite-schema.sql:5713` — `projects.priority` DEFAULT `'medium'`
  - `backend/database/schema/sqlite-schema.sql:6887` — `sla_policies.priority` DEFAULT `'medium'`
  - `backend/database/schema/sqlite-schema.sql:7111` — `support_tickets.priority` DEFAULT `'medium'`
- **Descrição:** Duas famílias de valores convivem no mesmo ERP:
  - **Família A:** `low`, `normal`, `high`, `urgent` (used in `work_orders`, `service_calls`, `portal_tickets`, `material_requests`, `recurring_contracts`, `inmetro_owners`).
  - **Família B:** `low`, `medium`, `high`, `urgent` (used in `central_items`, `central_templates`, `projects`, `sla_policies`, `support_tickets`, `crm_smart_alerts`).
  A checklist deste perfil (§Checklist 2c) declara explicitamente: *"`priority` values canônicos (`low`/`medium`/`high`/`urgent` — NÃO `normal`)"*. Portanto a família A está em violação.
- **Busca em TECHNICAL-DECISIONS.md:** Nenhuma entrada aceitando `normal` como valor canônico. O documento §14.13 cita "enums canônicos" sem listar `priority` nominalmente.
- **Impacto no negócio:**
  1. Dashboard cruzado de prioridade (OS + Central + Chamados) mistura `normal` e `medium` como se fossem níveis distintos, confundindo o operador.
  2. Qualquer tentativa de criar um `App\Enums\Priority` PHP compartilhado esbarra na divergência DB.
  3. Frontend precisa de mapas de tradução específicos por módulo.
  4. Migração eventual exigirá `UPDATE ... SET priority='medium' WHERE priority='normal'` em 11+ tabelas com risco de quebrar filtros/índices.

---

### prod-03 [S2] — Duplicação de conceito: `accounts_payable` tem `supplier` (varchar) + `supplier_id` (FK) e `category` (varchar) + `category_id` (FK)

- **Arquivo:linha:** `backend/database/schema/sqlite-schema.sql:141-144` (tabela `accounts_payable`, colunas 6-7 e 21-22)
- **Descrição:** A tabela mantém DUAS formas de registrar o mesmo conceito:
  ```sql
  "supplier" varchar(255) DEFAULT NULL,        -- texto livre
  "category" varchar(50) DEFAULT NULL,         -- texto livre
  ...
  "supplier_id" integer DEFAULT NULL,          -- FK
  "category_id" integer DEFAULT NULL,          -- FK
  ```
- **Descrição:** O modelo relacional moderno é `supplier_id` (FK para `suppliers.id`) e `category_id` (FK para `account_payable_categories.id`). Os campos `supplier` e `category` em varchar parecem ser legado não removido, mas o schema atual ainda os mantém ambos populados/populáveis. Não há CHECK constraint forçando "ou um ou outro".
- **Busca em TECHNICAL-DECISIONS.md:** Não encontrei justificativa registrada para manter ambos os caminhos. Se for "ponte de migração", não há deadline documentado para DROP.
- **Impacto no negócio:**
  1. Integridade referencial incerta — `supplier` varchar pode divergir do `suppliers.name` referenciado por `supplier_id`.
  2. Relatórios financeiros ("Contas a Pagar por Fornecedor") podem agrupar de forma diferente dependendo de qual campo o service usou.
  3. Importação CSV pode gravar um lado e esquecer o outro, criando drift silencioso.

---

### prod-04 [S3] — FKs de `central_*` usam nome `agenda_item_id`, não `central_item_id`

- **Arquivo:linha:**
  - `backend/database/schema/sqlite-schema.sql:850` — `central_attachments.agenda_item_id`
  - `backend/database/schema/sqlite-schema.sql:863` — `central_item_comments.agenda_item_id`
  - `backend/database/schema/sqlite-schema.sql:883` — `central_item_history.agenda_item_id`
  - `backend/database/schema/sqlite-schema.sql:893` — `central_item_watchers.agenda_item_id`
  - `backend/database/schema/sqlite-schema.sql:985` — `central_subtasks.agenda_item_id`
  - `backend/database/schema/sqlite-schema.sql:1017` — `central_time_entries.agenda_item_id`
  - `backend/database/schema/sqlite-schema.sql:906` — índice `ciw_item_user_unique` referencia `agenda_item_id`
- **Descrição:** A tabela pai é `central_items` (CREATE TABLE L908). Porém as filhas usam `agenda_item_id` como FK. Isso é um **fóssil semântico** da renomeação `agenda_permissions → central_permissions` (migration `2026_03_02_230000_rename_central_permissions_to_agenda.php` sugere um ping-pong). Convenção Laravel: FK para `central_items` deveria ser `central_item_id`.
- **Busca em TECHNICAL-DECISIONS.md:** §14.13.a menciona `AgendaItem` model aliases por retrocompatibilidade de payload ("`AgendaItem::normalizeLegacyAliases()`"), mas **não documenta `agenda_item_id` como nome aceito de FK**.
- **Impacto no negócio:**
  1. Desenvolvedor novo lendo o schema se confunde: existe `agenda_items`? (resposta: não existe, é `central_items`).
  2. Queries raw do pessoal de BI quebram quando tentam `JOIN central_items c ON c.id = h.central_item_id` (não existe).
  3. Débito arquitetural: ou todos chamam `central_*` ou todos chamam `agenda_*` — misturar é a pior opção.

---

### prod-05 [S3] — `accounts_receivable` tem DUAS colunas polimórfico-like: `origin_type` e `reference_id` separados

- **Arquivo:linha:** `backend/database/schema/sqlite-schema.sql` (tabela `accounts_receivable`):
  - L167 `"origin_type" varchar(30) DEFAULT NULL`
  - L195 `"reference_id" integer DEFAULT NULL`
  - Mais: `work_order_id`, `quote_id`, `invoice_id` (FKs diretas)
- **Descrição:** A tabela expõe três caminhos para modelar "de onde veio este recebível":
  1. Colunas FK específicas (`work_order_id`, `quote_id`, `invoice_id`) — 3 ponteiros tipados.
  2. Par polimórfico **incompleto** `origin_type` + `reference_id` (falta `reference_type` gêmeo? ou `origin_type` é o "tipo" e `reference_id` é o "id"? inconsistência de nomenclatura — convenção Laravel é `{morph}_type` + `{morph}_id`, ex: `origin_type` + `origin_id`).
  3. Canonical chain §14.13.b determina `origem → source → origin`, mas aqui temos `origin_type`/`reference_id` — NÃO é a cadeia canônica.
- **Busca em TECHNICAL-DECISIONS.md:** §14.13.b fala sobre resolução de colisão `source`/`origin` em `central_items`, mas não cobre a duplicidade de caminhos em `accounts_receivable`.
- **Impacto no negócio:** Ambiguidade de integridade: um recebível pode ter `quote_id=5` E `origin_type='quote' reference_id=7` simultaneamente — qual vence? Relatório de conversão (cotação virou receber) fica não-determinístico.

---

### prod-06 [S3] — `work_orders.origin_type` (não `origin`) contradiz cadeia canônica §14.13.b

- **Arquivo:linha:** `backend/database/schema/sqlite-schema.sql:8619` — `"origin_type" varchar(20) DEFAULT NULL`
- **Descrição:** §14.13.b declara cadeia canônica: `origem → source → origin`. Em `central_items`, a coluna final chama-se `origin` (OK). Em `work_orders`, a coluna análoga chama-se `origin_type`. A migration `2026_02_08_300000_alter_work_orders_add_origin` (id 24) sugere que foi para ser `origin`, mas o schema final mostra `origin_type`.
- **Busca em TECHNICAL-DECISIONS.md:** §14.13.b diz a coluna final é `origin` singular. `origin_type` é nome de polimórfico Laravel (`morphTo`), não de enum de "origem do pedido".
- **Impacto no negócio:** Inconsistência de vocabulário: `central_items.origin` (enum `'manual'`, `'system'`, ...) e `work_orders.origin_type` (string livre, pode conter tipos ou fonte) não têm lexicon compartilhado. ETL/analytics que quer "qual % dos pedidos vem de cotação" precisa consultar colunas com nomes diferentes.

---

### prod-07 [S4] — Tabelas no singular sem razão aparente (convenção declarada é plural)

- **Arquivo:linha:** `backend/database/schema/sqlite-schema.sql` (nomes de tabela):
  - `central_item_history` (L880) — deveria ser `central_item_histories` ou aceitar o fóssil H3 `_history` (ver Checklist §7c)
  - `continuous_feedback` — deveria ser `continuous_feedbacks`
  - `expense_status_history` — deveria ser `expense_status_histories` ou aceitar `_history`
  - `fleet_telemetry` — tecnicamente mass noun, aceitável
  - `inmetro_history` — deveria ser `inmetro_histories`
  - `warranty_tracking` — `warranty_trackings`
  - `work_order_status_history` — `work_order_status_histories`
  - `routes_planning` — mistura: `routes` plural + `planning` singular; convenção seria `route_plannings`
  - `portal_white_label` — singular; `portal_white_labels`
  - `sync_queue` — singular
  - `search_index` — singular
- **Descrição:** `TECHNICAL-DECISIONS.md §3` declara: *"Naming: tabelas e colunas em inglês, snake_case, **plural para tabelas**"*. A Checklist §7c aceita "`_history`/`_logs` como sufixo consistente" como padrão histórico Laravel comum — portanto `*_history` pode ser aceito.
- **Busca em TECHNICAL-DECISIONS.md:** Não encontrei lista explícita de "tabelas singulares aceitas". Existe tolerância implícita para `_history`, mas não para `continuous_feedback`, `warranty_tracking`, `routes_planning`, `portal_white_label`.
- **Impacto no negócio:** Baixo — mas desenvolvedor precisa consultar qual é o nome exato; quebra convenção declarada.

---

### prod-08 [S4] — M2M pivot com ordem não alfabética: `email_email_tag`, `quote_quote_tag`, `equipment_model_product`

- **Arquivo:linha:**
  - `backend/database/schema/sqlite-schema.sql` — `CREATE TABLE "email_email_tag"` (pivot)
  - `backend/database/schema/sqlite-schema.sql` — `CREATE TABLE "quote_quote_tag"` (pivot)
  - `backend/database/schema/sqlite-schema.sql` — `CREATE TABLE "equipment_model_product"` (pivot)
- **Descrição:** Convenção Laravel para tabelas M2M: nome das duas tabelas em ordem alfabética (singular), ex: `customer_supplier`, `role_user`. Observado:
  - `email_email_tag` — pivot entre `emails` e `email_tags`. Correto alfabético seria `email_email_tag` (confere), mas "email" aparece duas vezes por coincidência — nome tem leitura confusa.
  - `quote_quote_tag` — mesmo padrão duplicado — leitura ruim, mas alfabético tecnicamente correto.
  - `equipment_model_product` — pivot entre `equipment_models` e `products`. Alfabeticamente seria OK (`equipment_model` < `product`), mas o nome não deixa claro que pivot é entre 3 conceitos ou 2.
- **Busca em TECHNICAL-DECISIONS.md:** Convenção alfabética não tem documento de exceção.
- **Impacto no negócio:** Leitura ruim; novo dev precisa abrir migration pra entender qual pivot é qual. Baixo impacto runtime, mas cognitive load.

---

## Seções do checklist verificadas — SEM problema

### Checklist 1 — Coluna de autoria

- **(a) `expenses.created_by` (não `user_id`)** — CONFIRMADO em `backend/database/schema/sqlite-schema.sql` (tabela `expenses`, coluna `created_by` presente, `user_id` ausente). OK.
- **(b) `schedules.technician_id` (não `user_id`)** — CONFIRMADO (`schedules` L5110 `technician_id`, sem `user_id`). OK.
- **(c) `travel_expense_reports.created_by`** — CONFIRMADO (L7705+, `created_by` presente; migration `2026_04_17_280000` renomeou). OK.
- **(d) Sem duplicatas `created_by`+`user_id` simultâneas** — Nenhuma ocorrência encontrada nas tabelas-alvo. OK.

### Checklist 2b — `result` enum EN

- **`equipment_calibrations.result` DEFAULT `'approved'`** — CONFIRMADO (L2721). Nenhum `'aprovado'`/`'rejeitado'` residual no schema (grep global zero). OK.

### Checklist 2a — `status` lowercase EN

- **`accounts_payable.status` DEFAULT `'pending'`** — OK (L137).
- **`accounts_receivable.status` DEFAULT `'pending'`** — OK (L170).
- **`expenses.status` DEFAULT `'pending'`** — OK.
- **`quotes.status` DEFAULT `'draft'`** — OK.
- **`work_orders.status` DEFAULT `'open'`** — OK.
- **Grep global** por `'pago'`/`'pendente'`/`'cancelado'`/`'reprovado'` — zero ocorrências. OK.

### Checklist 2d — DEFAULTS coerentes com enum canônico

- OK para `status`. Violado para `priority` (ver prod-02) e `calibration_type` (ver prod-01).

### Checklist 3 — Terminologia PT vs EN no schema

- Grep global por tokens PT (`nome`, `titulo`, `descricao`, `prioridade`, `visibilidade`, `origem`, `ativo`, `empresa`, `codigo`, `observacao`, `situacao`, `usuario`, `responsavel`, `propriedade`, `endereco`, `bairro`, `cidade`, `razao`, `fantasia`, `municipio`, `fornecedor`, `cliente`, `contato`, `pagamento`, `recebimento`, etc.) em nomes de coluna, escaneando todas as 519 tabelas: **zero matches**. EN-only enforcement foi bem sucedido.
- Exceções fiscais BR (`nosso_numero`, `numero_documento` em `accounts_receivable`): CNAB/boleto BR é termo técnico fiscal sem tradução natural, consistente com `nfse`, `cfop`, `inscricao_estadual` aceitos em §14.13 como exceções fiscais. **Aceito.**
- **Nada encontrado.** OK.

### Checklist 4 — Gap PRD ↔ código

- RFs de cadastro central (customers, suppliers, employees) → tabelas presentes: `customers`, `suppliers`, `employees` (plurais EN). OK.
- RFs de financeiro → `accounts_payable`, `accounts_receivable`, `expenses` presentes. OK.
- RFs operacional base → `schedules`, `work_orders`, `quotes` presentes. OK.
- Módulos no código sem RF aparente (gap reverso): não investigado em profundidade — requer leitura completa do PRD (>2500 linhas). Não reporto finding sem evidência.

### Checklist 5 — Duplicação de domínio

- `central_items` vs `item_generico`: `item_generico` **não existe** no schema (grep zero). OK.
- `central_items` vs `agenda_items`: `agenda_items` **não existe** como tabela (apenas FKs `agenda_item_id` — ver prod-04). Não é duplicação de entidade.

### Checklist 6 — Nomes semanticamente errados

- Cadeia canônica `origem → source → origin` em `central_items`: OK (L912 `origin`).
- Cadeia em `work_orders`: `origin_type` (ver prod-06) — não está coerente.
- `type` genérico: aparece em muitas tabelas (`type` simples) — ambíguo mas convencionado. Não reporto como finding individual sem nome-alvo específico.

### Checklist 7 — Convenções de nomeação

- (a) Plural EN: maioria OK; exceções em prod-07.
- (b) M2M alfabético: verificado em prod-08.
- (c) `_history`/`_logs`: aceito como sufixo em `*_history` (ver prod-07).

### Checklist 8 — Fluxos quebrados (liames obrigatórios)

- `work_orders.quote_id` — PRESENTE (L8596 `"quote_id" integer DEFAULT NULL`). OK.
- `accounts_receivable.quote_id`, `work_order_id`, `invoice_id` — PRESENTES. OK.
- `work_orders.service_call_id`, `recurring_contract_id`, `parent_id` — PRESENTES. OK.

### Checklist 9 — Fósseis H3 aceitos

- Timestamps duplicados 10 pares (§14.19) — fóssil aceito, não reportado.

### Checklist 10 — Consistência de moeda/valor

- (a) `accounts_payable.amount` (`numeric NOT NULL`) vs `accounts_receivable.amount` (`numeric NOT NULL`): **tipo SQLite genérico `numeric` sem precision/scale visível no dump**. MySQL de produção usa `decimal(12,2)` provavelmente. O dump SQLite apaga essa info, então não consigo verificar precision real via dump. Verifiquei algumas migrations (`decimal(12,2)`) — consistente nos pontos sampleados. **Nada encontrado reportável** apenas pelo dump.
- (b) `DEFAULT '0.00'` em `amount_paid`, `balance`, `penalty_amount`, `interest_amount`, `discount_amount` — CONFIRMADO em ambos `accounts_payable` e `accounts_receivable`. OK.

---

## Notas metodológicas

- Audita efetuada sem acesso a `docs/audits/`, `docs/handoffs/`, `docs/plans/`, nem ao histórico git. Toda evidência vem do schema dump atual, migrations, TECHNICAL-DECISIONS.md, CLAUDE.md.
- O grep global por tokens PT foi executado em 519 tabelas do schema dump — exaustivo.
- Findings são achados do estado atual; não há juízo sobre o que foi feito ou não. A decisão de corrigir/aceitar cada finding cabe ao coordenador.
