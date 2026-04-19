# Re-auditoria Camada 1 — qa-expert
Data: 2026-04-17

## Metodologia

Auditoria independente de cobertura e qualidade dos testes que cobrem (ou deveriam cobrir) a Camada 1 (Fundação — Schema + Migrations). Varredura mecânica sobre `backend/tests/**`, `backend/app/Models/**`, `backend/app/Http/Requests/**`, `backend/database/migrations/**`, `backend/database/factories/**`, `backend/phpunit.xml`, `backend/tests/Pest.php`. Suite Pest executada no final.

## Sumário
- Total: 13 findings
- S1: 3 | S2: 5 | S3: 4 | S4: 1
- Suite status: `./vendor/bin/pest --parallel --processes=16 --no-coverage` → **9752 passed / 0 failed / 28148 assertions / 242.19s / 16 processes / exit 0**

## Inventário mecânico

| Dimensão | Valor |
|---|---|
| Arquivos de teste totais | 913 (suffix `Test.php`) |
| Feature | 712 | Unit | 173 | Arch | 1 | Critical | 19 | Smoke | 3 | Performance | 5 | E2E | **0** |
| Test cases (`it(`/`test(`) contados | 1 527 (sub-estimativa; Pest reporta 9 752 cases efetivos — maioria datasets/higher-order) |
| Total assertions executadas | 28 148 (≈ 2,88 assertions/case efetivo) |
| Controllers em `app/Http/Controllers/Api` | 305 |
| Arquivos `*ControllerTest.php` | 269 |
| Controllers sob `tests/Feature/Api` com cross-tenant explícito (outro tenant + 404/403) | **143 / 269 (53,1%)** |
| FormRequests totais | 881 |
| FormRequests com `authorize(): return true;` literal e sem lógica | **0** (todos os 6 `return true` flagrados têm comentário justificando — endpoints públicos) |
| Arquivos de teste que tocam FormRequests (Unit/Http/Requests ou menção "FormRequest") | 7 |
| Models totais em `app/Models` | 429 |
| Factories em `database/factories` | 198 |
| Models sem Factory correspondente | **~231** (60 samples listados abaixo) |
| Models com cast `'encrypted'` | 17 |
| Models com `$hidden` | 17 |
| Ocorrências `getRawOriginal(` em testes | 6 — todas em `AuvoImportQuotationsTest.php` e apenas para `status` (nenhuma para campo cifrado) |
| Ocorrências `assertJsonMissing` | 73 |
| Ocorrências `rand(` / `random_int(` / `mt_rand(` em testes | 13 (não-determinísticos, sem `srand`) |
| `Carbon::now()` / `now()` em testes | 2 179 (alto risco de dependência temporal sem `setTestNow`) |
| `assertTrue(true)` | **0** |
| `markTestIncomplete` / `markTestSkipped` | **0** |
| `->skip(` inline | **0** |
| `sleep(` | **0** |
| `E2E` suite (Playwright/integração ponta-a-ponta backend) | **0 arquivos** |
| `tests/Arch` (architecture tests Pest) | **1 arquivo apenas** (`ArchTest.php`) |

Comando principal:
```
cd backend && ./vendor/bin/pest --parallel --processes=16 --no-coverage
# Tests:    9752 passed (28148 assertions)
# Duration: 242.19s
# Parallel: 16 processes
# Exit: 0
```

## Seções verificadas (sem problema)

- **Anti-patterns bloqueadores CLAUDE.md:** zero `assertTrue(true)`, zero `markTestIncomplete`, zero `markTestSkipped`, zero `->skip(`, zero `sleep(`. Suite limpa nesses eixos (verificado em `backend/tests/**`).
- **`FormRequest::authorize()` com `return true` sem justificativa:** 0 ocorrências. Os 6 `return true` presentes em `app/Http/Requests/Auth/*`, `app/Http/Requests/Portal/*` estão explicitamente marcados como endpoint público (login, reset password, portal guest). Conforme.
- **Anti-patterns de response:** `assertJsonStructure()` presente em 73+ assertions `assertJsonMissing`. Disponibilidade generalizada do padrão "structure-first".
- **Environment de teste:** `phpunit.xml` força SQLite in-memory, `BCRYPT_ROUNDS=4`, `CACHE_STORE=array`, `QUEUE_CONNECTION=sync`, `PULSE_ENABLED=false`, `TELESCOPE_ENABLED=false`, `OTEL_SDK_DISABLED=true`. Config determinística conforme.
- **Bootstrap Pest:** `tests/Pest.php` vincula `TestCase` a Feature/Unit, `SmokeTestCase` a Smoke, `CriticalTestCase` a Critical, `PerformanceTestCase` a Performance. Separação de base por testsuite OK.
- **Suite convergência:** 9 752 cases passando, 28 148 assertions, 242s paralelo — dentro do orçamento CLAUDE.md (<5 min) e sem flakiness na execução.
- **Critical/TenantIsolation:** existem 19 arquivos `tests/Critical/TenantIsolation/*` (CustomerIsolation, WorkOrderIsolation, FinancialIsolation, EquipmentIsolation, StockIsolation, CrmIsolation, HrIsolation, EagerLoadLeak, CascadeDelete, ReportIsolation, RawQueryIsolation). Representa a cobertura explícita S1 de isolamento.
- **Observadores de modelo (`app/Observers/*`):** cobertos em `tests/Unit/Listeners/ModelObserversTest.php` e `WorkOrderObserverTest.php`.

---

## Findings

### QA-RA-01 [S1] — Ausência de teste de `getRawOriginal` para 17 campos com cast `encrypted`
- **Arquivos:** `backend/app/Models/User.php:135-143`, `Customer.php:196`, `Supplier.php:54`, `EmailAccount.php:29`, `ESocialCertificate.php`, `TwoFactorAuth.php:32`, `WhatsappConfig.php:26`, `Webhook.php:33`, `SsoConfig.php:29`, `PaymentGatewayConfig.php:48-49`, `MarketingIntegration.php:45`, `ClientPortalUser.php:64-65`, `EmployeeDependent.php:66`, `FiscalWebhook.php:33`, `InmetroBaseConfig.php:57`, `InmetroWebhook.php:28` + busca em `backend/tests/**`.
- **Descrição:** O checklist pede explicitamente "Testes de encryption: `getRawOriginal('campo') !== valor_plain`". A suite tem 6 ocorrências de `getRawOriginal()`, **todas em `tests/Feature/AuvoImportQuotationsTest.php` e todas para a coluna `status`** — nenhuma valida que `cpf`, `document`, `client_secret`, `api_key`, `api_secret`, `secret` (TOTP), `imap_password`, `psie_password`, `two_factor_secret`, `google_calendar_token`, `backup_codes` estejam cifrados no banco. Ou seja: o cast `'encrypted'` existe no Model, mas nenhum teste garante que o valor persistido seja ciphertext. Se alguém remover ou degradar o cast, a suite continua 100% verde.
- **Evidência:** 
  - `grep -rn "getRawOriginal(" tests/` → 6 hits, todos em `AuvoImportQuotationsTest.php` linhas 94, 177-180, 208 (status, não encryption).
  - `grep -rln "'encrypted'" app/Models/` → 17 Models com cast `encrypted`.
  - Zero arquivos `*EncryptionTest.php` / `*PiiTest.php` específicos.
- **Recomendação:** Criar `tests/Unit/Models/Encryption/PiiEncryptionTest.php` com um teste por coluna cifrada: criar registro com valor plain, `->save()`, recarregar, `expect($model->getRawOriginal('cpf'))->not->toBe($plainCpf)` e `expect($model->cpf)->toBe($plainCpf)`. Datasets para os 17 models/campos. Também testar que `document_hash` (SEC-021) é determinístico para o mesmo input (Customer/Supplier).

### QA-RA-02 [S1] — Cobertura cross-tenant ausente em 126 dos 269 controllers testados (46,9%)
- **Arquivos:** 126 `*ControllerTest.php` não combinam simultaneamente `(Tenant::factory()|otherTenant|anotherTenant|secondTenant)` com `(assertNotFound|assertStatus(404)|assertForbidden|403)`. Amostra concreta:
  - `tests/Feature/Api/V1/AgendaControllerTest.php`
  - `tests/Feature/Api/V1/CashFlowControllerTest.php`
  - `tests/Feature/Api/V1/AutomationControllerTest.php`
  - `tests/Feature/Api/V1/AuvoImportControllerTest.php`
  - `tests/Feature/Api/V1/BatchControllerTest.php`
  - `tests/Feature/Api/V1/ChecklistControllerTest.php`
  - `tests/Feature/Api/V1/ClientPortalControllerTest.php`
  - `tests/Feature/Api/V1/CollectionRuleControllerTest.php`
  - `tests/Feature/Api/V1/CostCenterControllerTest.php`
  - `tests/Feature/Api/V1/Crm/CrmAlertControllerTest.php` (+ 7 outros `Crm/*`)
  - `tests/Feature/Api/V1/Auth/PasswordResetControllerTest.php`
  - `tests/Feature/Api/V1/Analytics/EmbeddedDashboardControllerTest.php`
  - `tests/Feature/Api/V1/AccountingReportControllerTest.php`
  - `tests/Feature/Api/V1/AiAssistantControllerTest.php`
  - `tests/Feature/Api/CrmControllerTest.php`, `FinancialControllerTest.php`, `HrFleetQualityControllerTest.php`, `QuoteControllerTest.php`, `EquipmentControllerTest.php`
- **Descrição:** CLAUDE.md torna "Teste cross-tenant é OBRIGATÓRIO" para endpoints de tabela `tenant_id`. 46,9% dos controllers testados não executam o cenário "recurso de outro tenant → 404/403". Risco S1 porque a Camada 1 inteira depende de `tenant_id` — se o controller vazar scope, nenhum teste detecta.
- **Evidência:** Varredura shell — de 269 `*ControllerTest.php`, apenas 143 combinam criação de segundo tenant + assert de 404/403 na mesma suite.
- **Recomendação:** Gerar backlog por controller (os 126 listados). Para cada, adicionar no mínimo: `it('retorna 404 para recurso de outro tenant')` padronizado como no template do `qa-expert.md`. Considerar gerar test skeleton via script + dataset.

### QA-RA-03 [S1] — Models encrypted `ESocialCertificate`, `TvDashboardConfig`, `WebhookConfig` no `$hidden` mas sem teste de vazamento no response
- **Arquivos:** `app/Models/ESocialCertificate.php:42`, `app/Models/TvDashboardConfig.php`, `app/Models/WebhookConfig.php`, `app/Models/Tenant.php` (campo `$hidden` presente).
- **Descrição:** 17 models têm `$hidden` mas não há teste explícito que serialize o model via `toArray()` ou resposta HTTP e assegure que o campo sensível **não** aparece. `assertJsonMissing` existe 73 vezes na suite mas predominantemente valida ausência de registros de outro tenant em listagens (cross-tenant), não ausência de campo sensível. Ou seja: se alguém remover o `$hidden` por engano, o test fica verde.
- **Evidência:** `grep -rn "toArray\(\)" tests/ | grep -iE "password|secret|api_key|cpf|client_secret|backup_code|imap"` → zero hits úteis. `assertJsonMissing('client_secret'|'api_key'|'secret'|'imap_password')` → zero ocorrências no bruto.
- **Recomendação:** Criar `tests/Unit/Models/Hidden/HiddenFieldsTest.php` com teste por model (17 targets), fazendo `$model->toArray()` e `expect(...)->not->toHaveKey('secret')` etc., + teste HTTP correspondente em Feature (`getJson('/api/webhooks/{id}')->assertJsonMissing(['secret' => ...])`).

### QA-RA-04 [S2] — Factory gap: ~231 Models sem Factory (≈54%)
- **Arquivos:** `app/Models/*.php` (429 arquivos) vs `database/factories/*.php` (198 arquivos). Amostras de Models sem Factory: `AccessRestriction`, `AccountPayablePayment`, `AccountPlan`, `AccountPlanAction`, `Admission`, `AgendaAttachment`, `AgendaItemComment`, `AgendaItemHistory`, `AgendaItemWatcher`, `AgendaNotificationPreference`, `AgendaRule`, `AgendaSubtask`, `AgendaTimeEntry`, `AssetTag`, `AssetTagScan`, `AutomationRule`, `AuvoIdMapping`, `BusinessHour`, `CalibrationDecisionLog`, `Camera`, `CertificateTemplate`, `ClientPortalUser`, `CollectionAction`, `CollectionActionLog`, `CollectionRule`, `Commitment`, `CompetitorInstrumentRepair`, `ContactPolicy`, `ContinuousFeedback`, `CorrectiveAction`, `CostCenter`, `CrmCalendarEvent`, `CrmContractRenewal`, `CrmDealCompetitor`, `CrmDealStageHistory`, `CrmForecastSnapshot`, `CrmFunnelAutomation`, `CrmInteractiveProposal`, `CrmLeadScore`, `CrmLeadScoringRule`, `CrmLossReason`, `CrmReferral`, `CrmSalesGoal`, `CrmSequenceStep`, `CrmSmartAlert`, `CrmTerritory`, `CrmTerritoryMember`, `CrmTrackingEvent`, `CrmWebForm`, `CrmWebFormSubmission`, `CustomerContact`, `CustomerDocument`, `CustomerLocation`, `CustomerRfmScore`, `DebtRenegotiation`, `DebtRenegotiationItem`, `DocumentVersion`, `EcologicalDisposal`, `Email`, `EmailAccount`, … (60 amostras extraídas por varredura shell).
- **Descrição:** CLAUDE.md §Testes: "Factory coverage: todo Model de produção tem Factory". Com 231 models sem factory, ~54% dos domínios não podem ser instanciados facilmente em testes novos. Isso explica parte da QA-RA-02: escrever cross-tenant fica proibitivo sem Factory. Alguns são pivots M2M (talvez aceitável), mas `ClientPortalUser` (com cast encrypted), `DocumentVersion`, `Camera`, `CollectionRule`, `CostCenter`, `CustomerLocation` são entidades de domínio claro.
- **Evidência:** `ls database/factories/ | wc -l` → 198. `find app/Models -maxdepth 1 -name '*.php' | wc -l` → 429. Varredura confirmou ausência do arquivo `Factory.php` correspondente.
- **Recomendação:** Priorizar Factory para os 17 models com encryption (necessário para QA-RA-01) e para qualquer model cujo controller esteja na lista QA-RA-02. Aceitar dívida explícita para pivots (`*_user_role_tenant`, `model_has_roles`).

### QA-RA-05 [S2] — Suite `tests/E2E` declarada no `phpunit.xml` mas vazia (0 arquivos)
- **Arquivo:** `backend/phpunit.xml:23-25` declara `<testsuite name="E2E"><directory suffix="Test.php">./tests/E2E</directory></testsuite>`. `find tests/E2E -name '*Test.php'` → 0 arquivos.
- **Descrição:** Config declara suite E2E, pipeline de testsuite existe, mas zero arquivos. Ausência de testes ponta-a-ponta backend (jornada multi-controller com dados reais). Para uma fundação (Camada 1), pelo menos um teste E2E por domínio crítico (Work Order → Quote → Invoice; Calibration emission) seria esperado.
- **Evidência:** `find tests/E2E -name '*.php' -type f` vazio; `phpunit.xml` linhas 23-25 listam o testsuite.
- **Recomendação:** Ou remover o testsuite vazio do `phpunit.xml` (evitar falso sinal), ou popular com pelo menos 3 jornadas críticas: OS→Orçamento→Fatura, Calibração→Certificado, Despesa→Aprovação→Pagamento.

### QA-RA-06 [S2] — `tests/Arch` possui apenas 1 arquivo (`ArchTest.php`)
- **Arquivo:** `backend/tests/Arch/ArchTest.php` (único).
- **Descrição:** Pest Architecture Tests são o mecanismo idiomático para travar convenções da Camada 1 (Models sempre com `BelongsToTenant`, Controllers sempre com `->paginate()`, FormRequests sempre com `authorize()` não-trivial, Migrations sem `dropColumn` sobre colunas em uso, etc.). Apenas 1 arquivo arquitetural para uma codebase de 429 models e 305 controllers é sub-cobertura.
- **Evidência:** `find tests/Arch -name '*.php'` → 1 arquivo. `tests/Arch/ArchTest.php` testa estrutura de workflows CI (Health Check, Rollback) — não regras de domínio.
- **Recomendação:** Criar `tests/Arch/Domain/ModelsArchTest.php` (todo Model em `app/Models` com factory OU pivot declarado), `ControllersArchTest.php` (controllers de index → paginam), `FormRequestsArchTest.php` (authorize retorna true só em *Public* classes), `MigrationsArchTest.php` (migration nova não altera coluna de migration mergeada sem guard).

### QA-RA-07 [S2] — FormRequests: 881 classes, 7 arquivos de teste
- **Arquivos:** `app/Http/Requests/**` (881 arquivos) vs `tests/Unit/Http/Requests/**` + buscas por "FormRequest"/"Request extends" em tests (7 arquivos referenciam).
- **Descrição:** O checklist pede "Testes de FormRequest `authorize()`/`rules()`". Com 881 FormRequests e ~7 arquivos de teste específicos, >99% das regras de validação nunca são testadas em isolamento. A cobertura existe indiretamente via Feature test (422 em CRUD), mas isso não valida regras complexas como `exists:table,id` com scoping de tenant, `unique:table,column,NULL,id,tenant_id,X`, ou `sometimes|array|max:N`.
- **Evidência:** `find app/Http/Requests -name '*.php' | wc -l` → 881. `grep -rlE "FormRequest|Request extends" tests/Unit/Http/Requests tests/Feature` → 7.
- **Recomendação:** Testes datasets-driven por FormRequest: `it('invalida quando X')` com 10-15 cenários por Request crítico. Prioridade: Customer/Supplier (PII), PaymentGatewayConfig, ESocialCertificate, Expense, WorkOrder, FiscalInvoice.

### QA-RA-08 [S2] — `Carbon::now()` / `now()` em testes: 2 179 ocorrências (flakiness temporal latente)
- **Arquivo:** `backend/tests/**`.
- **Descrição:** 2 179 chamadas a `now()`/`Carbon::now()` em testes. Sem `Carbon::setTestNow(...)` acoplado, qualquer teste com lógica de "vencimento em N dias", "mais de 30 dias", "> hoje" depende do relógio real. Um teste que passa à meia-noite pode falhar à meia-noite e um segundo. A suite é paralela com 16 processos — janelas temporais reais podem divergir entre workers.
- **Evidência:** `grep -rnE "Carbon::now\(\)|\bnow\(\)" tests/ | wc -l` → 2 179. Amostragem mostra uso direto sem freeze.
- **Recomendação:** Padronizar `beforeEach(fn() => Carbon::setTestNow('2026-04-17 12:00:00'))` em TestCase ou via trait. Auditar top 50 arquivos com uso mais denso.

### QA-RA-09 [S3] — `rand()`/`random_int()` não-determinísticos em 13 testes (flakiness de dados e colisão)
- **Arquivos:** 
  - `tests/Feature/Api/CoreCrudControllerTest.php:211` `'serial_number' => 'SN-'.rand(100000, 999999)`
  - `tests/Feature/Api/V1/Portal/PortalTicketControllerTest.php:55` `random_int(1, 999999)`
  - `tests/Feature/Api/V1/Quality/NonConformityControllerTest.php:41`
  - `tests/Feature/Api/V1/Quality/QualityAuditControllerTest.php:43`
  - `tests/Feature/CashFlowTest.php:122,129,256,263`
  - `tests/Feature/Flows/SupportTicketFlowTest.php:56`
  - `tests/Feature/Jobs/CheckDocumentVersionExpiryTest.php:31`
  - `tests/Feature/QuoteInvoicingFinancialTest.php:64`
  - `tests/Feature/ReconciliationExportTest.php:51,52`
- **Descrição:** Uso de `rand()` para gerar identificadores semi-únicos em testes. Com 16 workers paralelos e range pequeno (ex: `rand(1000, 9999)` em `CashFlowTest`) há probabilidade não-zero de colisão de UNIQUE constraint em runs paralelos. Também usado em `rand(-500, 500)` para `amount` em ReconciliationExport — valores de teste não-reproduzíveis dificultam triagem de falhas.
- **Evidência:** Listagem exaustiva via grep.
- **Recomendação:** Substituir por `fake()->unique()->regexify(...)` ou contador de sequência explícito. Para valores numéricos, usar fixtures estáveis.

### QA-RA-10 [S3] — `assertJsonStructure()` ausente em uma fatia significativa dos ControllerTest
- **Arquivos:** amostra de tests que NÃO usam `assertJsonStructure()` (scan direto):
  - `tests/Feature/Api/V1/Analytics/AIAnalyticsControllerTest.php`
  - `tests/Feature/Api/V1/Analytics/AnalyticsControllerTest.php`
  - `tests/Feature/Api/V1/Analytics/BiAnalyticsControllerTest.php`
  - `tests/Feature/Api/V1/Analytics/DataExportJobControllerTest.php`
  - `tests/Feature/Api/V1/Analytics/ExpenseAnalyticsControllerTest.php`
  - `tests/Feature/Api/V1/Analytics/FinancialAnalyticsControllerTest.php`
  - `tests/Feature/Api/V1/Analytics/HRAnalyticsControllerTest.php`
  - `tests/Feature/Api/V1/Analytics/PeopleAnalyticsControllerTest.php`
  - `tests/Feature/Api/V1/Analytics/SalesAnalyticsControllerTest.php`
  - `tests/Feature/Api/V1/Auth/PasswordResetControllerTest.php`
  - `tests/Feature/Api/V1/AuvoImportControllerTest.php`
  - `tests/Feature/Api/V1/BankReconciliationControllerTest.php`
  - `tests/Feature/Api/V1/CashFlowControllerTest.php`
  - `tests/Feature/Api/V1/ChecklistControllerTest.php`
  - `tests/Feature/Api/V1/CollectionRuleControllerTest.php`
  - `tests/Feature/Api/V1/CrmAdvancedControllerTest.php`
  - `tests/Feature/Api/V1/CrmMessageControllerTest.php`
  - `tests/Feature/Api/V1/Customer/CustomerMergeControllerTest.php`
  - `tests/Feature/Api/V1/Financial/ChartOfAccountControllerTest.php`
  - `tests/Feature/Api/V1/Financial/DebtRenegotiationControllerTest.php`
  - `tests/Feature/Api/V1/Financial/FinancialAdvancedControllerTest.php`
  - `tests/Feature/Api/V1/Financial/FinancialExportControllerTest.php`
  - `tests/Feature/Api/V1/Fiscal/FiscalInvoiceControllerTest.php`
  - (+ mais — scan parcial limitado a 20 samples)
- **Descrição:** CLAUDE.md: "`assertJsonStructure()` é obrigatório — não só status code". Observação: alguns desses arquivos podem ser wrappers de testes mais antigos — mas o scan confirma a ausência literal do método nesses arquivos.
- **Evidência:** Varredura `for f in ...; do grep -q assertJsonStructure "$f" || echo NO_STRUCT; done`.
- **Recomendação:** Para cada arquivo listado, garantir que haja ao menos um `assertJsonStructure(['data' => [...]])` por endpoint listado/mostrado.

### QA-RA-11 [S3] — Testes de `FormRequest` não validam mensagens/rules críticas de `exists:table,id,tenant_id`
- **Arquivos:** `app/Http/Requests/**`.
- **Descrição:** CLAUDE.md §Padrões obrigatórios: "Relationship validada por `exists:table,id` deve considerar `tenant_id`". Não há arquitetural test nem suite dedicada que valide essa regra em todos os 881 FormRequests. O SEC-014/DATA-xxx dessa auditoria anterior levantou o risco — mas não há teste automático que bloqueie regressão. Se alguém adicionar `exists:users,id` sem scoping, nenhum teste Pest Arch captura.
- **Evidência:** Não há `tests/Arch/*.php` com `arch('rules')->expect(...)->toContain('tenant_id')`.
- **Recomendação:** Criar `tests/Arch/FormRequestsArchTest.php` parseando `rules()` via reflexão/static parsing e falhando se algum `exists:` não contiver `NULL,id,tenant_id,...`.

### QA-RA-12 [S3] — `tests/Critical` com apenas 19 arquivos — nenhum para encryption, soft-delete, $hidden
- **Arquivos:** `tests/Critical/*` — subdiretórios `TenantIsolation/` (13 arquivos), `Performance/`, etc. Total 19. Não há `tests/Critical/Encryption/`, `tests/Critical/PiiLeakage/`, `tests/Critical/SoftDelete/`.
- **Descrição:** O padrão `CriticalTestCase` existe (em `tests/Critical/`) e é declarado em `phpunit.xml` como testsuite dedicada. Atualmente só cobre isolamento de tenant. Não há suíte Critical para propriedades invariantes como "campo encrypted nunca vaza em to_array/JSON/log" ou "soft-delete respeitado em scope global".
- **Evidência:** `ls tests/Critical/` → `TenantIsolation/` e 1-2 arquivos soltos.
- **Recomendação:** Criar `tests/Critical/Encryption/PiiNeverLeaksTest.php`, `tests/Critical/SoftDelete/GlobalScopeTest.php`. Fazer parte do testsuite `Critical` rodada em CI como gate separado.

### QA-RA-13 [S4] — Testsuite "Default" em `phpunit.xml` mistura Unit+Feature+Smoke+Arch — pirâmide não separável
- **Arquivo:** `backend/phpunit.xml:4-9`.
- **Descrição:** `defaultTestSuite="Default"` inclui `Unit`, `Feature`, `Smoke`, `Arch` em um único agrupamento. CLAUDE.md exige pirâmide de escalada (específico → grupo → testsuite → full). A definição atual faz o comando padrão rodar tudo junto, dificultando o fluxo "rodar Unit primeiro, depois Feature". Para fluxo local isso é subótimo — o engenheiro tem de lembrar `--testsuite=Unit` explicitamente.
- **Evidência:** `phpunit.xml` testsuites block.
- **Recomendação:** Remover o `defaultTestSuite="Default"` ou mudar default para `Unit` (rápido). Documentar em `tests/README.md` os comandos de escalada.

---

## Proibições confirmadas

- Não li `docs/handoffs/`, `docs/audits/*` pré-existente (exceto o output desta auditoria que vou gravar), `docs/plans/`.
- Não rodei `git log`, `git diff`, `git show`, `git blame` para inferir o que foi alterado.
- Rodei a suite Pest para evidência concreta (exit 0, 9752 passed, 242s).
- Todas as afirmações têm arquivo:linha ou contagem mecânica verificada.
