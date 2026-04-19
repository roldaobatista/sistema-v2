# Re-auditoria Camada 1 — product-expert
Data: 2026-04-17

Auditoria independente da Fundação (Schema + Migrations) do Kalibrium ERP contra:
1. CLAUDE.md §4 (Lei 4 — status em inglês lowercase; colunas EN)
2. Glossário ISO 17025 / VIM (incerteza, certificado, padrão rastreável)
3. PRD-KALIBRIUM.md (normativo)
4. Convenção EN-only (§14.13 do TECHNICAL-DECISIONS)

Método: grep/glob sobre `backend/database/schema/sqlite-schema.sql`, `backend/database/migrations/`, `backend/app/Models/`, `backend/app/Http/Controllers/`, `frontend/src/`. Proibições respeitadas (ver fim).

---

## Sumário
- **Total: 11 findings**
- **S1: 1** | **S2: 5** | **S3: 4** | **S4: 1**

Distribuição por tema:
- Terminologia / glossário ISO 17025 (laudo vs. certificado, erro vs. incerteza): **3**
- Colunas PT residuais pós-Wave EN-only: **4**
- Enums/defaults UPPERCASE PT: **2**
- Duplicação de domínio (user_id + created_by): **1**
- Coerência frontend↔backend pós-migração EN: **1**

---

## Seções verificadas (sem problema ou fora de escopo deste expert)

- **Tenant safety — tenant_id nunca do body**: grep em `backend/app/` — todas as 20+ ocorrências usam `$request->user()->current_tenant_id` (ou `$request->tenantId()`). Nenhuma violação do Law 4 de CLAUDE.md encontrada no controller layer.
- **`schedules.technician_id`**: schema (linha 5110) e model `backend/app/Models/Schedule.php:21` usam `technician_id`. CLAUDE.md §4 respeitado. O trecho `schedules.*.user_id` em `BatchScheduleEntryRequest.php:21` refere-se ao domínio HR (`work_schedules`/`on_call_schedules`) e é semanticamente correto.
- **`withoutGlobalScope`**: 20+ ocorrências em `Console/Commands/` e batch jobs (`CheckExpiringContracts`, `ScanOverdueFinancials`, etc.). Uso técnico legítimo para jobs que varrem múltiplos tenants. Não auditei linha a linha justificativa textual (escopo do security-expert/governance).
- **Multi-tenant estrutural**: todas as tabelas de negócio inspecionadas no schema têm `tenant_id` NOT NULL com índice composto ou unique. Sem tabelas "órfãs" de tenant detectadas em amostragem.
- **Rastreabilidade metrológica no schema**: `equipment_calibrations` tem `standard_used`, `traceability`, `laboratory_accreditation`, `traceability_chain` (via `standard_weights`), `accreditation_scope_id`, `coverage_factor_k`, `confidence_level`, `guard_band_mode`, `uncertainty_budget`, `decision_rule`. Cobertura ISO 17025 no nível de schema — adequada.
- **Incerteza armazenada**: `uncertainty`, `expanded_uncertainty`, `type_a_uncertainty`, `combined_uncertainty` presentes em `equipment_calibrations`, `calibration_readings`, `linearity_tests`. Cobertura adequada.

---

## Findings

### PROD-RA-01 [S1]
- **Arquivo:linha:** `backend/database/migrations/2026_02_07_400000_create_work_order_tables.php:43`; `backend/app/Models/WorkOrder.php` (campo `technical_report`); `frontend/src/pages/os/WorkOrderDetailPage.tsx:1439,1445,1451`; `frontend/src/pages/portal/PortalWorkOrderDetailPage.tsx:226,230`; `backend/app/Http/Controllers/Api/V1/EquipmentController.php:507`; `backend/app/Http/Requests/Equipment/UploadEquipmentDocumentRequest.php:25`
- **RF/AC do PRD (se aplicável):** domínio Calibração / ISO 17025 (emissão de certificados e relatórios técnicos)
- **Descrição:** A coluna `work_orders.technical_report` está rotulada no comentário da migration como "Laudo técnico" e exposta no frontend/portal do cliente como **"Laudo Técnico"**. Em paralelo, o sistema usa massivamente o termo `certificate`/`certificado` (438 ocorrências no frontend) para calibração. **O glossário de metrologia brasileiro (VIM/ABNT/ISO 17025) distingue rigorosamente "certificado de calibração" (documento formal com incerteza, rastreabilidade, decisão de conformidade) de "laudo técnico" (parecer/relatório opinativo sem força metrológica).** Usar "laudo" como sinônimo de "relatório técnico genérico de OS" é defensável, mas na mesma OS já existe o fluxo de `certificate_emission_checklists` / `certificate_templates` / `certificate_signatures`. Não há no schema nenhuma distinção formal entre **"laudo técnico de OS de conserto"** (opinião do técnico) e **"certificado de calibração"** (saída metrológica rastreável). `EquipmentController.php:507` inclusive mistura os dois numa lista flat de `documentTypes`: `['certificado', 'manual', 'foto', 'laudo', 'relatorio']`.
- **Evidência:**
  - `backend/database/migrations/2026_02_07_400000_create_work_order_tables.php:43`: `$t->text('technical_report')->nullable(); // Laudo técnico`
  - `frontend/src/pages/os/WorkOrderDetailPage.tsx:1445`: `placeholder="Escreva o laudo técnico..."`
  - `frontend/src/pages/portal/PortalWorkOrderDetailPage.tsx:230`: `<CheckCircle /> Laudo Técnico`
  - `backend/app/Http/Requests/Equipment/UploadEquipmentDocumentRequest.php:25`: `'type' => 'required|in:certificado,manual,foto,laudo,relatorio'`
- **Impacto no negócio:** Em auditoria RBC/Cgcre, um cliente pode apresentar um "Laudo Técnico" do ERP achando que é o documento metrológico rastreável, quando não é. Risco de **confusão documental em auditoria de acreditação**, reclamação de cliente, e não-conformidade formal (ISO 17025 §7.8 — Relato de resultados). Em contencioso (ex.: discussão fiscal sobre IPEM), um "laudo" sem rigor metrológico sendo apresentado como se fosse certificado pode invalidar a defesa.
- **Recomendação:** Separar formalmente os dois conceitos. Opção A: renomear a coluna para `service_report` ou `technician_report` e reservar "certificado" exclusivamente para o fluxo de `equipment_calibrations`/`certificate_*`. Opção B: adicionar documentação explícita no PRD e no UI rotulando claramente que "Laudo Técnico" (OS) ≠ "Certificado de Calibração" (metrologia). Em todos os casos, remover "laudo" da mesma lista de `documentTypes` onde aparece "certificado" em `UploadEquipmentDocumentRequest` — ou diferenciar por subtipo. **Severidade S1** porque afeta conformidade ISO 17025, contrato com cliente e defesa em auditoria — não é cosmético.

---

### PROD-RA-02 [S2]
- **Arquivo:linha:** `backend/database/schema/sqlite-schema.sql:8121` (tabela `central_templates`)
- **RF/AC do PRD (se aplicável):** Convenção EN-only (§14.13 TECHNICAL-DECISIONS) + CLAUDE.md §4 (colunas EN)
- **Descrição:** A tabela `central_templates` tem **quatro colunas PT residuais** e **um default UPPERCASE PT** após a Wave de normalização. A migration `2026_04_17_300000_rename_central_pt_columns_to_english.php` explicitamente renomeou apenas `descricao→description`, `tipo→type`, `prioridade→priority`, `visibilidade→visibility` para `central_templates`. Ficaram de fora: `nome` (deveria ser `name`), `categoria` (deveria ser `category`), `ativo` (deveria ser `active`), e o default da coluna `type` é `'TAREFA'` — UPPERCASE PT (§4 proíbe).
- **Evidência (DDL atual do schema dump):**
  ```sql
  CREATE TABLE "central_templates" (
    ... "nome" varchar(150) not null,
    ... "type" varchar(20) not null default ('TAREFA'),
    ... "categoria" varchar(60) default (NULL),
    ... "ativo" tinyint not null default ('1'),
    ...
  );
  ```
- **Impacto no negócio:** Inconsistência de esquema. Desenvolvedores novos tropeçam (ora EN, ora PT). Quebra a premissa da convenção EN-only que a Wave 6 formalizou. Se o frontend migrar para `name`/`active` assumindo EN, quebra silenciosamente templates de tarefa.
- **Recomendação:** Migration de rename adicional: `nome→name`, `categoria→category`, `ativo→active`. Migration de UPDATE para `TAREFA→task` + alterar default para `'task'`.

---

### PROD-RA-03 [S2]
- **Arquivo:linha:** `backend/database/schema/sqlite-schema.sql:629` (tabela `central_subtasks`)
- **RF/AC do PRD (se aplicável):** Convenção EN-only (§14.13) + CLAUDE.md §4
- **Descrição:** `central_subtasks` mantém duas colunas PT: `concluido` (deveria ser `completed`) e `ordem` (deveria ser `position` ou `sort_order`). Não foram incluídas na migration `2026_04_17_300000_rename_central_pt_columns_to_english.php`.
- **Evidência (schema dump, linhas 629-640):**
  ```sql
  CREATE TABLE "central_subtasks" (
    ... "title" varchar(255) NOT NULL,
    "concluido" tinyint NOT NULL DEFAULT '0',
    "ordem" integer NOT NULL DEFAULT '0',
    ...
  );
  ```
- **Impacto no negócio:** Mesma família de inconsistência que PROD-RA-02. Aumenta custo cognitivo e chance de bug ao desenvolver feature de subtarefas.
- **Recomendação:** Incluir `concluido→completed` e `ordem→position` no próximo batch de rename. Atualizar model `CentralSubtask` (se existir) e queries.

---

### PROD-RA-04 [S2]
- **Arquivo:linha:** `backend/database/schema/sqlite-schema.sql` (tabela `central_items`, linha 8115, coluna `source`)
- **RF/AC do PRD (se aplicável):** §14.13.b (decisão declara que `origem` foi renomeada para `origin`, não `source`, para evitar colisão polimórfica)
- **Descrição:** Contradição interna entre decisão arquitetural documentada e schema real. O `TECHNICAL-DECISIONS.md §14.13.b` declara: *"`origem` foi renomeada para `origin` (não `source`) para evitar colisão com relationship polimórfica `source()` em `AgendaItem` que usa `ref_type`/`ref_id`"*. Porém a migration `2026_04_17_300000_rename_central_pt_columns_to_english.php` renomeia explicitamente `'origem' => 'source'` em `central_items`. Ou seja: **a migration fez o oposto do que a decisão dizia que seria feito**. O DDL efetivo de `central_items` após migration exibe `"origin" varchar not null default 'manual'` (linha 8115), mas o script de rename aponta para `source`. Isto sugere que existe uma migration *posterior* `2026_04_17_310000_rename_central_source_to_origin.php` que corrige — e de fato ela existe na listagem. **Porém o estado final é: decisão escrita contradiz o que um leitor encontra em 2026_04_17_300000 isoladamente, e uma migration de correção imediata foi necessária. Isto é débito de governança de migrations.**
- **Evidência:**
  - Migration `2026_04_17_300000_rename_central_pt_columns_to_english.php` (indexada): `'origem' => 'source'` para `central_items`.
  - Listagem `ls .../migrations/`: existe `2026_04_17_310000_rename_central_source_to_origin.php` (rename corretivo ~5 min depois).
  - TECHNICAL-DECISIONS §14.13.b: afirma que o rename final foi `origem→origin` direto, sem passar por `source`.
- **Impacto no negócio:** Historial de migrations com conflito interno. Em rollback granular (raro mas possível), o `down()` de `2026_04_17_300000` restaura `source→origem`, mas a tabela já não tem `source` (foi movida para `origin` por `310000`). Resultado: rollback quebra. Também polui o rastro para auditoria de conformidade.
- **Recomendação:** Consolidar os dois renames em migration única (sem executar em prod, apenas refactor de arquivos de migration ainda não executados em prod). Se em prod, documentar explicitamente o "double-rename" e ajustar `down()` com guards `hasColumn`.

---

### PROD-RA-05 [S2]
- **Arquivo:linha:** `backend/database/schema/sqlite-schema.sql:8115` (tabela `central_items`, colunas `user_id` e `completed`)
- **RF/AC do PRD (se aplicável):** CLAUDE.md §4 (duplicação de domínio proibida) + Lei 5 (preservação vs. simplificação)
- **Descrição:** `central_items` tem, ao final do DDL, duas colunas legacy penduradas: `"user_id" integer default (NULL)` e `"completed" tinyint default (NULL)`. Porém a tabela já tem semanticamente equivalentes EN-only: `assignee_user_id`, `created_by_user_id`, `closed_at`, `closed_by`. Ou seja: **duplicação de domínio**. Não fica claro quem popula `user_id` e `completed`, nem qual é a fonte de verdade para "concluído" — `completed=1` ou `closed_at IS NOT NULL`?
- **Evidência (schema dump, linha 8115, fim do CREATE):**
  ```sql
  ... "visibility_departments" text default (NULL),
      "visibility_users" text default (NULL),
      "user_id" integer default (NULL),
      "completed" tinyint default (NULL));
  ```
- **Impacto no negócio:** Bugs silenciosos. Relatório que filtra por `closed_at IS NOT NULL` vs. outro que filtra por `completed=1` podem divergir. Em auditoria de tarefas da Central de Tarefas, duas fontes de verdade = dados inconsistentes no dashboard.
- **Recomendação:** Migration de limpeza: migrar dados legacy (`user_id → assignee_user_id`, `completed → closed_at`) e dropar as duas colunas. Se não for seguro dropar, documentar explicitamente qual é a oficial.

---

### PROD-RA-06 [S2]
- **Arquivo:linha:** `backend/database/migrations/2026_02_07_400000_create_work_order_tables.php:42`
- **RF/AC do PRD (se aplicável):** Convenção EN-only §14.13
- **Descrição:** A migration original de `work_orders` declara `$t->string('priority', 10)->default('normal'); // low, normal, high, urgent`, o que está **correto** em EN lowercase. Porém o enum real em uso no domínio ISO 17025/ABNT é **`low`/`medium`/`high`/`urgent`** (consistente com `central_items` que usa `medium`). Existe divergência interna: `work_orders.priority` usa `normal` como baseline, enquanto `central_items.priority` e `central_templates.priority` (após rename) usam `medium`. Não está claro se "normal" e "medium" são sinônimos ou níveis diferentes; não há tabela de enum centralizada (enum DB ou PHP enum class).
- **Evidência:**
  - `work_orders` migration L42: `default('normal'); // low, normal, high, urgent`
  - `central_items` schema L8115: `"priority" varchar not null default 'medium'`
  - `central_templates` schema L8121: `"priority" varchar not null default 'medium'`
- **Impacto no negócio:** Inconsistência semântica entre OS e Central de Tarefas. Relatórios cruzados (dashboard de prioridades) misturam conceitos. Usuário não sabe se "normal" da OS é igual a "medium" da Central.
- **Recomendação:** Padronizar em um dos dois vocabulários (recomendo `medium` por alinhamento ISO). Criar enum PHP centralizado (`App\Enums\Priority`) e migração de dados.

---

### PROD-RA-07 [S3]
- **Arquivo:linha:** `backend/database/schema/sqlite-schema.sql:8109` (tabela `equipment_calibrations`, colunas `error_found`, `max_error_found`, `error_after_adjustment`, `errors_found`, `max_permissible_error`)
- **RF/AC do PRD (se aplicável):** ISO 17025 §7.6 (Avaliação de incerteza), VIM §2.16 (erro de medição) vs. §2.26 (incerteza de medição)
- **Descrição:** O schema de calibração usa **"erro"** consistentemente (`error_found`, `error_after_adjustment`, `max_permissible_error`, `max_error_found`, `errors_found` JSON). **"Erro de medição" é um termo VIM legítimo** (§2.16: "valor medido menos valor de referência"), **então esta auditoria NÃO classifica como terminologia errada.** Porém o checklist do task diz: "incerteza de medição (não erro)". A intenção do glossário interno parece ser: para a **grandeza rastreável divulgada no certificado**, use "incerteza" (U, U_A, U_B, expanded_uncertainty — corretamente presentes). Para a **diferença instantânea medida-padrão**, use "erro". O schema faz a distinção correta: `error_*` (leitura individual) e `uncertainty_*` / `expanded_uncertainty` (resultado metrológico). **Porém**, na UI (`CalibrationWizardPage.tsx:879`, `CalibrationReadingsPage.tsx:164`) a coluna exibida ao técnico se chama apenas **"Erro"** — sem adjetivo. Para técnicos de bancada acostumados ao VIM, "Erro" isolado é ambíguo: é "erro de indicação"? "erro máximo admissível"? "erro após ajuste"?
- **Evidência:**
  - `frontend/src/pages/calibracao/CalibrationReadingsPage.tsx:164`: `<th>Erro Calculado</th>` (OK, tem adjetivo)
  - `frontend/src/pages/calibracao/CalibrationWizardPage.tsx:879`: `<th>Erro</th>` (ambíguo)
  - `frontend/src/components/calibration/CalibrationGuide.tsx:82`: texto ao usuário diz "O sistema calcula automaticamente: erro, EMA e conformidade" — aqui "erro" é genérico e correto em contexto.
- **Impacto no negócio:** Risco baixo-médio. Técnico experiente entende pelo contexto, mas é uma ambiguidade que não passa por auditoria rigorosa de documentação (ISO 17025 §8.4 — Controle de registros).
- **Recomendação:** Na tabela do wizard usar rótulo completo: "Erro de indicação" ou "Erro encontrado". Checklist do emission template já usa "Incerteza de medição determinada" (correto). Não renomear colunas do schema — a nomenclatura técnica está adequada.

---

### PROD-RA-08 [S3]
- **Arquivo:linha:** `backend/database/migrations/2026_02_09_700000_create_central_tables.php:31-32`
- **RF/AC do PRD (se aplicável):** Convenção EN-only §14.13 + CLAUDE.md §4
- **Descrição:** A migration original de criação de `central_items` usa defaults UPPERCASE PT: `default('ABERTO')` e `default('MEDIA')`. Embora a migration posterior `2026_04_17_290000_normalize_central_enums_defaults_to_english.php` tenha **mapeado dados** (`ABERTO→open`, `MEDIA→medium`) e alterado o default para `'open'`/`'medium'`, a migration _original_ permanece no histórico sem correção textual. **Risco operacional:** em ambiente novo sendo criado do zero sem `php artisan migrate` completar a cadeia (ou com `--stop-on-error`), a tabela fica com default UPPERCASE PT até a migração de normalização rodar. Em dev-test com SQLite in-memory isto provavelmente funciona, mas em staging/prod com falha parcial pode deixar registros legacy com `'ABERTO'`.
- **Evidência:**
  - `2026_02_09_700000_create_central_tables.php:31`: `$table->string('status', 20)->default('ABERTO');`
  - `2026_02_09_700000_create_central_tables.php:32`: `$table->string('prioridade', 20)->default('MEDIA');`
  - `2026_04_17_290000_normalize_central_enums_defaults_to_english.php:59`: `$table->string('status', 20)->default('ABERTO')->change();` (ainda usa ABERTO como "nova" string no down)
- **Impacto no negócio:** Baixo se pipeline de migration é atômico. Relevante se houver seed/import que insira dados sem passar por todas as migrations.
- **Recomendação:** Adotar convenção: migration criadora não pode ser editada após merge, mas a migration de normalização deve ter teste de regressão que verifica: "após toda a cadeia de migration rodar, o default efetivo de `central_items.status` é `'open'`". Hoje o normalize faz `default('open')->change()` corretamente, mas não há teste.

---

### PROD-RA-09 [S3]
- **Arquivo:linha:** `backend/database/schema/sqlite-schema.sql:5387` (tabela `standard_weights`, coluna `traceability_chain` via ALTER)
- **RF/AC do PRD (se aplicável):** ISO 17025 §6.5 (Rastreabilidade metrológica), Portaria Inmetro 157/2022
- **Descrição:** A coluna `standard_weights.traceability_chain varchar` (sem comprimento declarado) é do tipo texto livre, sem estrutura. Em auditoria RBC, a **cadeia de rastreabilidade** precisa ser navegável: padrão X foi calibrado pelo laboratório Y no certificado Z na data D, que por sua vez foi calibrado por... Isto demanda uma tabela relacional (`standard_traceability_links` ou similar) com FKs para padrão parent, certificado, data, laboratório. Armazenar a cadeia como string prosaica força processo manual/visual para reconstituir — frágil em auditoria.
- **Evidência (schema dump, linha 5413):**
  ```sql
  ... "laboratory_accreditation" varchar, "traceability_chain" varchar);
  ```
- **Impacto no negócio:** Em auditoria Cgcre, se auditor pede "mostrar cadeia de rastreabilidade do padrão de 1kg classe E2 usado na calibração X", o sistema entrega um texto sem garantia de integridade (sem FK, sem validação). Não é impeditivo, mas é inferior ao estado da arte.
- **Recomendação:** Modelar cadeia como entidade relacional. P1 (não bloqueia operação, mas limita maturidade ISO 17025).

---

### PROD-RA-10 [S3]
- **Arquivo:linha:** `backend/database/schema/sqlite-schema.sql:8109` (tabela `equipment_calibrations`, coluna `approved_by` vs. ausência de `reviewed_by`/`technical_reviewer_id`)
- **RF/AC do PRD (se aplicável):** ISO 17025 §7.8.6 (revisão e autorização de certificados antes de emissão) — separação de papéis
- **Descrição:** `equipment_calibrations` tem `performed_by` e `approved_by`. ISO 17025 exige que a **revisão técnica** dos resultados seja feita por pessoa distinta do executante, e **antes** da aprovação/liberação ao cliente. Idealmente são três papéis: `performed_by` (técnico executante), `reviewed_by` (revisor técnico), `approved_by` (signatário autorizado / RT). O schema funde revisão técnica + aprovação num único campo. `certificate_emission_checklists` tem `verified_by`, mas isto cobre o checklist pré-emissão, não a **revisão técnica do conteúdo do certificado**. Além disso, `expenses` tem `reviewed_by`/`reviewed_at` + `approved_by` — separação correta para despesas mas **ausente para certificados**, onde o risco regulatório é maior.
- **Evidência:**
  - `equipment_calibrations` (L8109): tem `performed_by`, `approved_by`. Sem `reviewed_by` ou `technical_reviewer_id`.
  - `expenses` (L2355+, schema dump): `approved_by`, `reviewed_by`, `reviewed_at` — separação correta.
- **Impacto no negócio:** Em auditoria RBC, falta evidência documental de que a revisão técnica foi feita por pessoa distinta da que executou. Risco de não-conformidade ISO 17025 §7.8.6.
- **Recomendação:** Adicionar `reviewed_by` + `reviewed_at` + `reviewer_notes` a `equipment_calibrations`. Garantir via Policy/FormRequest que `reviewed_by != performed_by` e `approved_by != performed_by`.

---

### PROD-RA-11 [S4]
- **Arquivo:linha:** `backend/database/schema/sqlite-schema.sql` — tabelas `central_item_comments`, `central_item_history`
- **RF/AC do PRD (se aplicável):** Multi-tenancy (CLAUDE.md §4) + LGPD (isolamento)
- **Descrição:** Ambas as tabelas tiveram `tenant_id` adicionado **posteriormente** via migration `2026_04_09_100000_add_tenant_id_to_central_item_history_and_comments.php`, como **nullable** (`$table->unsignedBigInteger('tenant_id')->nullable()->after('id')`). No schema atual: `"tenant_id" integer` (sem NOT NULL). Isto significa que linhas históricas podem ter `tenant_id = NULL`, criando risco de vazamento cross-tenant se uma query filtrar por `tenant_id = X` e esquecer do `OR IS NULL` ou global scope falhar.
- **Evidência (schema dump, linhas 570-588):**
  ```sql
  CREATE TABLE "central_item_comments" (
    "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
    "agenda_item_id" integer NOT NULL,
    ...
    , "tenant_id" integer);   -- nullable
  CREATE TABLE "central_item_history" (
    ...
    , "tenant_id" integer);   -- nullable
  ```
- **Impacto no negócio:** Baixo (S4) **se** nenhum registro NULL existe em prod. Médio se registros legacy NULL circulam. Risco de leak em relatórios agregados.
- **Recomendação:** Rodar `UPDATE ... SET tenant_id = (SELECT tenant_id FROM central_items WHERE central_items.id = central_item_comments.agenda_item_id) WHERE tenant_id IS NULL` (backfill), depois migration `ALTER TABLE ... MODIFY tenant_id INT NOT NULL`. Isto é trabalho de data-expert / security-expert confirmar, mas o produto está exposto.

---

## Confirmação de proibições respeitadas

- Não li `docs/handoffs/`, `docs/audits/` (apenas criei arquivo novo em subpasta designada), `docs/plans/`.
- Não rodei `git log`, `git diff`, `git show`, `git blame`.
- Li apenas: código-fonte (schema, migrations, models, controllers, frontend), `CLAUDE.md`, `TECHNICAL-DECISIONS.md` §14.13 (normativo permitido para confirmar convenção EN-only), `PRD-KALIBRIUM.md` (resultado vazio na grep específica, não inferido).
- Não assumi que correções prévias foram bem feitas. Findings reportam estado atual do código **como encontrado hoje**.
- Não aprovei nem validei nada. Findings são adversariais.
