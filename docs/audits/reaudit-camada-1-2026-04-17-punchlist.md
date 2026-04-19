# Punch-list — Camada 1 (zero gap)

**Data:** 2026-04-17
**Fonte:** `docs/audits/reaudit-camada-1-2026-04-17.md` + 5 relatórios por expert em `docs/audits/reaudit-camada-1-2026-04-17/`.
**Status:** Camada 1 **REABERTA**. 61 findings (10 S1 + 22 S2 + 21 S3 + 8 S4). Zero gap = corrigir todos S1 + S2 obrigatoriamente, S3 como dívida rastreável, S4 como advisory documentado.

---

## Onda 7 — S1 funcionais (corrigir primeiro — bloqueio operacional)

### Encryption regressiva — Wave 1/3 quebrou funcionalidade

| ID | Arquivo:linha | Problema | Ação |
|---|---|---|---|
| **SEC-RA-01** | `backend/app/Http/Controllers/Api/V1/Security/TwoFactorController.php:45,88` + `backend/app/Models/TwoFactorAuth.php:31-34` + `SecurityController.php:36` | Dupla criptografia: controller faz `encrypt()` e Model faz cast `encrypted` → ciphertext duplo no DB. 2FA quebrado funcionalmente. | Remover `encrypt()` manual do controller — deixar só o cast do Model. Testar fluxo 2FA completo. |
| **SEC-RA-02** | `backend/app/Models/Tenant.php:81-92,77-79` + `backend/app/Services/Fiscal/CertificateService.php:45` + `sqlite-schema.sql:6809` | `Tenant.fiscal_certificate_password` criptografado manualmente via Service; `fiscal_nfse_token` em claro. Assimetria + token fiscal em plain text. | Adicionar casts `'fiscal_certificate_password' => 'encrypted'` e `'fiscal_nfse_token' => 'encrypted'` no Model Tenant. Remover `Crypt::encryptString` manual do CertificateService (deixar só o cast). Rotar valores atuais. |
| **SEC-RA-03** | `backend/app/Http/Controllers/Api/V1/IntegrationController.php` | Mesmo padrão de dupla criptografia em credenciais SSO/payment/marketing. | Mesma correção: `encrypt()` manual sai, cast do Model assume. |

### Convenção EN incompleta — Wave 6.7 não fechou PROD-003

| ID | Arquivo:linha | Problema | Ação |
|---|---|---|---|
| **GOV-RA-01 / DATA-RA-02 / PROD-RA-02** | `sqlite-schema.sql:8121` + `migrations/2026_02_24_200001_create_central_templates_table.php` + `migrations/2026_04_17_300000_rename_central_pt_columns_to_english.php` | `central_templates` ainda com `nome`, `categoria`, `ativo` PT + default `'TAREFA'` UPPERCASE. | Nova migration: rename `nome→name`, `categoria→category`, `ativo→is_active` com guards `hasColumn`; normalizar default `'TAREFA'→'task'` (com UPDATE dos registros existentes). Atualizar Model `AgendaTemplate` (ver GOV-RA-10). |
| **GOV-RA-02 / PROD-RA-03** | `sqlite-schema.sql` — `central_subtasks` | `concluido`, `ordem` PT residuais. | Rename `concluido→is_completed`, `ordem→sort_order` com guards. Atualizar Model `AgendaSubtask`. |
| **GOV-RA-03 / PROD-RA-05** | `sqlite-schema.sql` — `central_items` | Legacy `user_id` + `completed` coexistindo com `assignee_user_id`/`closed_at`. | Dropar `user_id` e `completed` após confirmar que nenhum consumer usa (grep + testes). |
| **DATA-RA-01** | `sqlite-schema.sql:557-567` + `migrations/2026_02_18_500000_create_central_subtasks_and_attachments.php:18` | `central_attachments.nome` PT — não incluído no mapa Wave 6.7. | Nova migration: `central_attachments` rename `nome→name` com guards. Atualizar Model `AgendaAttachment` (GOV-RA-11). |

### Terminologia ISO 17025

| ID | Arquivo:linha | Problema | Ação |
|---|---|---|---|
| **PROD-RA-01** | schema + UI + portal cliente (múltiplos arquivos) | "Laudo Técnico" vs "Certificado de Calibração" misturados — risco ISO 17025 §7.8 (documento emitido é certificado, laudo é outro ato). | Decidir terminologia canônica (certificate_of_calibration) e aplicar: schema, Resources, componentes, labels, PRD. Glossário em `docs/TECHNICAL-DECISIONS.md`. |

### Testes — cobertura que explicaria regressões acima

| ID | Arquivo:linha | Problema | Ação |
|---|---|---|---|
| **QA-RA-01** | 17 Models com cast `encrypted`, 6 testes (só status) | Nenhum teste valida `getRawOriginal()` vs plain — explica por que suite passou com 2FA quebrado. | Criar `tests/Critical/Encryption/PiiNeverLeaksTest.php` — para cada Model com `encrypted`, teste que `$model->getRawOriginal('campo') !== $valor` e que `$model->campo === $valor`. |
| **QA-RA-02** | 126/269 controllers (46,9%) sem cross-tenant test | Lei 4 sem malha de segurança. | Datasets-driven ArchTest que falha PR se controller novo não tem `cross_tenant` test correspondente. Corrigir lacuna em 126 controllers listados em `qa-expert.md`. |
| **QA-RA-03** | 17 Models com `$hidden` sem teste de não-vazamento | Campo sensível pode vazar em `toArray()` ou response JSON. | Teste Critical para cada Model: `expect($model->toArray())->not->toHaveKey('<campo hidden>')`. |

---

## Onda 8 — S2 tenant safety estrutural (após Onda 7)

| ID | Arquivo:linha | Problema | Ação |
|---|---|---|---|
| **GOV-RA-07** | `backend/app/Http/Controllers/Api/V1/Financial/ConsolidatedFinancialController.php:58` | Lê `tenant_id` do body como fallback — **Lei 4 violada**. | Confirmar se é super-admin holding view com policy. Se não: remover fallback e usar `$request->user()->current_tenant_id`. |
| **DATA-RA-03** | múltiplas migrations / schema | 223 declarações `tenant_id` sem FK no banco. | Auditoria caso-a-caso: adicionar FK onde aplicável; documentar as globais-por-design em `TECHNICAL-DECISIONS.md`. |
| **DATA-RA-04** | 30 tabelas com `tenant_id NULLABLE` (20 questionáveis) + **PROD-RA-11** `central_item_comments`, `central_item_history` | Risco de vazamento cross-tenant via rows NULL. | Backfill de `tenant_id` para linhas NULL existentes + migration `NOT NULL` com guard. |
| **SEC-RA-04** | ~367 Models com `tenant_id` em `$fillable` (ex: `AuditLog.php:22`, `AccountPayable.php:54`, `TwoFactorAuth.php:24`) | Trait é defesa única. Bypass via `Model::query()->create()` permite forja. | Remover `tenant_id` do `$fillable` de todos Models que usam `BelongsToTenant`. |
| **SEC-RA-05** | `backend/app/Models/Role.php:45-51` | Role (Spatie) sem `BelongsToTenant`, mas `tenant_id` em `$fillable`. Escalação cross-tenant. | Adicionar `BelongsToTenant` + remover `tenant_id` de `$fillable`. |
| **SEC-RA-06** | `backend/app/Models/TwoFactorAuth.php:32-36` + `TwoFactorController.php:87-88` | `backup_codes` armazenado encrypted em vez de hashed (bcrypt). OWASP viola — vazamento de DB expõe códigos em claro. | Hash com `Hash::make()` na geração; comparar via `Hash::check()` no consumo. Migrar códigos existentes (pedir regeneração). |
| **SEC-RA-07** | schema — `account_plan_actions`, `email_attachments`, `competitor_instrument_repairs`, `inventory_count_items`, `management_review_actions`, `marketplace_partners`, `material_request_items`, `portal_ticket_messages`, `price_table_items`, `product_kits`, `purchase_quote_items`, `purchase_quote_suppliers`, `quality_audit_items`, `returned_used_item_dispositions`, `rma_items`, `service_catalog_items`, `service_checklist_items`, `stock_disposal_items`, `stock_transfer_items`, `visit_route_stops`, `work_order_approvals` | Tabelas filhas sem `tenant_id` dependem da FK parent. Risco latente H2. | Auditar controllers que listam por `parent_id`: se filtro manual de tenant ausente = vulnerabilidade; se presente = OK. Adicionar `tenant_id` + FK em tabelas de negócio realmente isoladas. |
| **SEC-RA-08** | schema — cascade delete `tenants` em dezenas de tabelas | DELETE de tenant desencadeia cascata removendo audit trail inclusive. | Mudar para `RESTRICT` em `audit_logs` + `lgpd_*` + fiscal crítico. TenantController::destroy com soft-delete + processo de purga controlado. |

---

## Onda 9 — S2 schema/governance drift

| ID | Arquivo:linha | Problema | Ação |
|---|---|---|---|
| **GOV-RA-04** | `sqlite-schema.sql:610` (`central_rules`) + `migrations/2026_04_17_320000_rename_central_rules_pt_columns.php` | Schema dump mostra `active` (EN), migration original declarou `ativo`. Verificar se migration de rename tem `hasColumn`. | Abrir a migration; se não tem guard, criar nova migration de rename com guard. |
| **GOV-RA-05** | `backend/database/migrations/` — 10 pares de timestamps duplicados (ex: `2026_04_09_100000` × 2; `2026_03_26_400001` × 2; etc.) | Ordem depende do filesystem em colisão. | Futuro: timestamp com segundos + sufixo `_500000+`. Documentar regra permanente em `TECHNICAL-DECISIONS.md`. Colisões atuais são irreversíveis (aceitar + travar para frente). |
| **GOV-RA-06** | `sqlite-schema.sql:1` vs `generate_sqlite_schema.php:27` | Header incoerente com o script — dump pode ter sido gerado por `php artisan schema:dump` em vez do script oficial. | Reexecutar `generate_sqlite_schema.php` com MySQL Docker e confirmar que header bate. Ajustar CI para falhar se header divergir. |
| **PROD-RA-04** | `docs/TECHNICAL-DECISIONS.md §14.13.b` vs cadeia real de migrations | Documentação afirma `origem→origin` direto mas cadeia real foi `origem→source→origin`. | Corrigir `TECHNICAL-DECISIONS §14.13.b` para refletir a cadeia real. |
| **PROD-RA-06** | `work_orders.priority` usa `'normal'`; `central_*.priority` usa `'medium'` | Divergência de vocabulário dentro do mesmo domínio. | Decidir canônico (`low`/`medium`/`high`/`urgent`) + migration de normalização em `work_orders`. |

---

## Onda 10 — S2 cobertura de testes

| ID | Arquivo:linha | Problema | Ação |
|---|---|---|---|
| **QA-RA-04** | 231/429 Models sem Factory (~54%) | Escrever cross-tenant test é proibitivo sem Factory. | Priorizar Factory para os 17 Models com encryption + 60 amostras listadas em `qa-expert.md`. |
| **QA-RA-05** | `backend/phpunit.xml:23-25` declara testsuite `E2E`, `find tests/E2E` = 0 | Suite fantasma. | Ou popular (OS→Quote→Invoice; Calibration emission; Expense flow — 3 mínimos) ou remover do `phpunit.xml`. |
| **QA-RA-06** | `backend/tests/Arch/ArchTest.php` (único) | Pest Arch sub-coberto para 429 Models e 305 controllers. | Criar `ModelsArchTest`, `ControllersArchTest`, `FormRequestsArchTest`, `MigrationsArchTest`. |
| **QA-RA-07** | 881 FormRequests × 7 arquivos de teste | >99% das regras de validação não são testadas em isolamento. | Datasets por FormRequest crítico — priorizar Customer, Supplier, PaymentGatewayConfig, ESocialCertificate, Expense, WorkOrder, FiscalInvoice. |
| **QA-RA-08** | 2179 `Carbon::now()` / `now()` em `tests/` | Flakiness temporal latente em suite paralela 16-worker. | `Carbon::setTestNow()` em `TestCase::setUp()` como padrão. |

---

## Onda 11 — S3 dívida rastreável (21)

Pode ser endereçada em ciclo seguinte, mas **deve ser documentada em `TECHNICAL-DECISIONS.md`** como dívida conhecida com dono e prazo.

### Data (4)

| ID | Arquivo:linha | Problema |
|---|---|---|
| DATA-RA-04 (parcial S3) | 30 tabelas `tenant_id NULLABLE` — subset que não entra na Onda 8 | Review caso-a-caso. |
| DATA-RA-05 | migrations Schema::table sem guards H3 (30+ arquivos) | Fósseis H3 — aceitos por Lei H3. ArchTest para bloquear novas sem guard. |
| DATA-RA-06 | UNIQUE global em colunas tenant-scoped (ex: `products.sku`) | Migrar para UNIQUE composto `(tenant_id, sku)`. |
| DATA-RA-07 | precisão decimal não ampliada em agregados payroll/fiscal/quotes/commission_settlements/travel_expense_reports | Ampliação caso-a-caso em ciclo futuro. |

### Security (4)

| ID | Arquivo:linha | Problema |
|---|---|---|
| SEC-RA-09 | `RespondToProposalRequest.php:28`, `ExportCsvRequest.php:27`, `AuthorizesRoutePermission.php:40` | `authorize() { return true; }` sem justificativa. Adicionar permission check real. |
| SEC-RA-10 | `Advanced/{CompleteFollowUpRequest,IndexCollectionRuleRequest,IndexCostCenterRequest,IndexCustomerDocumentRequest,IndexFollowUpRequest}.php` | Sem override de `authorize()` — herdam default `true`. |
| SEC-RA-11 | `backend/app/Models/Tenant.php:81-92` | Casts incompletos (`rep_p_*`, `fiscal_environment`, `fiscal_nfse_rps_series` faltando). |
| SEC-RA-12 | `BranchController:103,107`, `CatalogController:34`, `CrmMessageController:254..378`, `NumberingSequenceController:26`, `PublicWorkOrderTrackingController:35`, `TenantSettingsController:84`, `BankReconciliationController:223,227`, `Webhook/WhatsAppWebhookController:144..312` | 20+ `withoutGlobalScope` sem justificativa inline padronizada. Adicionar comentário + teste que cada uso aplica filtro manual. |

### Governance (5)

| ID | Arquivo:linha | Problema |
|---|---|---|
| GOV-RA-08 | `backend/app/Models/AgendaItem.php:254,656-663` | `orderBy('ordem')` + mapa compat PT→EN hardcoded. Limpar após GOV-RA-01/02 resolvidos. |
| GOV-RA-09 | múltiplas migrations ALTER sem guards (subset de GOV-001) | Aceito H3 — ArchTest para bloquear novas. |
| GOV-RA-10 | `backend/app/Models/AgendaTemplate.php:32,70` | `'ativo' => 'boolean'` + `'ordem' => $i`. Atualizar junto com GOV-RA-01. |
| GOV-RA-11 | `backend/app/Models/AgendaAttachment.php:21` | `'nome'` em `$fillable`. Atualizar junto com DATA-RA-01. |
| GOV-RA-12 | 272/471 migrations fazem `Schema::table` sem guards | Aceito H3. ArchTest para bloquear novas sem guard. |

### QA (4)

| ID | Arquivo:linha | Problema |
|---|---|---|
| QA-RA-09 | `CoreCrudControllerTest:211`, `PortalTicketControllerTest:55`, `NonConformityControllerTest:41`, `QualityAuditControllerTest:43`, `CashFlowTest:122,129,256,263`, `SupportTicketFlowTest:56`, `CheckDocumentVersionExpiryTest:31`, `QuoteInvoicingFinancialTest:64`, `ReconciliationExportTest:51,52` | 13 testes com `rand()`/`random_int()` não-determinísticos. Substituir por `fake()->unique()->regexify()`. |
| QA-RA-10 | 20+ ControllerTests sem `assertJsonStructure()` | Adicionar assert de estrutura por endpoint. |
| QA-RA-11 | 881 FormRequests sem ArchTest que valida `exists:...,tenant_id` | Criar `FormRequestsArchTest` (subset do QA-RA-06). |
| QA-RA-12 | `tests/Critical/` (19 arquivos) só cobre TenantIsolation | Criar `tests/Critical/Encryption/` + `tests/Critical/SoftDelete/` + `tests/Critical/PiiLeakage/`. |

### Product (4)

| ID | Arquivo:linha | Problema |
|---|---|---|
| PROD-RA-07 | UI wizard de calibração | Rótulo "Erro" sem adjetivo (VIM ambiguity — erro absoluto? relativo? de medição?). Qualificar. |
| PROD-RA-08 | Migration original `central_items` | Defaults PT UPPERCASE sem teste de regressão de pipeline. Adicionar teste. |
| PROD-RA-09 | `sqlite-schema.sql:5387` — `standard_weights.traceability_chain` varchar prosaico | Modelar como entidade relacional (`standard_traceability_links`). Limita maturidade ISO 17025. |
| PROD-RA-10 | `equipment_calibrations.approved_by` | Funde revisão técnica + aprovação (ISO 17025 §7.8.6 exige separação). Separar `reviewed_by` vs `approved_by`. |

---

## Onda 12 — S4 advisory (8)

Documentar em `TECHNICAL-DECISIONS.md` com status "aceito como limitação" ou programar correção de baixo custo.

| ID | Arquivo:linha | Problema |
|---|---|---|
| DATA-RA-08 | `sqlite-schema.sql` colunas `numeric` sem `(p,s)` | Artefato SQLite — em MySQL preserva. Nota em `TESTING_GUIDE.md`. |
| DATA-RA-09 | `marketplace_partners`, `competitor_instrument_repairs`, `permission_groups` sem `tenant_id` | Confirmar intenção global vs tenant — documentar em `TECHNICAL-DECISIONS.md`. |
| SEC-RA-13 | `personal_access_tokens` (Sanctum) sem `tenant_id` | Documentar política: token reemitido a cada switch de tenant OU scoped_tenant_id no token. |
| SEC-RA-14 | `lgpd_*.responded_by/reported_by/executed_by` com `ON DELETE SET NULL` | Mudar para `RESTRICT` ou anonimização. LGPD art.37-38. |
| GOV-RA-13 | `StoreAgendaItemRequest.php:55` | FormRequest segue padrão — advisory de confirmação. Adicionar ArchTest de regressão. |
| GOV-RA-14 | `BankReconciliationController:223,227` (+ 30 usos em Console Commands) | `withoutGlobalScope` em controller HTTP. Prioritizar review do controller; commands aceitos. |
| QA-RA-13 | `backend/phpunit.xml:4-9` `defaultTestSuite="Default"` mistura Unit+Feature+Smoke+Arch | Mudar default para `Unit` (rápido) ou remover. |
| (sem ID) | `DATA-008` polymorphic sem FK (fósseis aceitos §14.7) | Já documentado. |

---

## Checklist de fechamento (obrigatório)

- [ ] **Ondas 7-10 concluídas** (todos S1 + S2 = 32 findings corrigidos)
- [ ] **Onda 11** — cada S3 com issue criada + atribuído dono + prazo (dívida rastreável, não bloqueia)
- [ ] **Onda 12** — cada S4 documentado em `TECHNICAL-DECISIONS.md` como aceito OU em backlog
- [ ] **Suite Pest verde** — evidência no relatório final
- [ ] **Schema dump sincronizado** — `php generate_sqlite_schema.php` + header coerente (GOV-RA-06)
- [ ] **Re-rodar `/reaudit "Camada 1"`** após todas as ondas
- [ ] **Veredito FECHADA** — set-difference mostra zero não-resolvidos + zero novos S1/S2
- [ ] **Atualizar `docs/handoffs/`** com handoff de fechamento real (não prematuro)

## Regra de travamento anti-regressão

Após fechamento, adicionar ArchTests no `tests/Arch/` para impedir retorno:

1. Todo Model com `BelongsToTenant` não pode ter `tenant_id` em `$fillable`.
2. Toda coluna em migrations novas em inglês (lista de exceções configurada).
3. Todo Controller de `index` deve chamar `->paginate(15)`.
4. Todo FormRequest não-`Public` deve sobrescrever `authorize()` com lógica real.
5. Toda migration `Schema::table` nova deve ter guard `hasTable`/`hasColumn`.
6. Todo Model com `encrypted` casts deve ter teste Critical correspondente.
7. Todo Model com `$hidden` deve ter teste Critical de não-vazamento.
8. Todo `exists:<table>,id` em FormRequest deve ter `->where('tenant_id', ...)`.
