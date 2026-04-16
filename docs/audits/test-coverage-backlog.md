# Test Coverage Backlog — Matriz de Priorização

**Gerado em:** 2026-04-10
**Fonte:** Varredura direta do codebase (find/grep) — supera a auditoria original que estava defasada
**Complementa:** `docs/audits/audit-tests-quality-2026-04-10.md` + `docs/plans/remediation-audit-2026-04-10.md` (Task 26)

## Resumo Executivo

| Categoria | Código | Com teste | **Sem teste** | % coberto |
|---|---:|---:|---:|---:|
| Controllers | 304 | 117 | **195** | 38% |
| Jobs | 35 | 0 | **35** | 0% |
| Policies | 67 | 4 | **63** | 6% |
| Listeners | 44 | 0* | **44** | 0%* |

\* Listeners podem ter cobertura indireta via testes feature que disparam eventos. Classificado como gap até confirmação.

**Discrepância com audit original:** a auditoria reportou 103 controllers sem teste; varredura real encontrou **195**. A diferença provavelmente se deve à auditoria contar apenas controllers "top-level" sem varrer subdiretórios (`Api/V1/Analytics/`, `Api/V1/Billing/`, etc.).

**Total de cobertura-alvo (este backlog):** 337 unidades sem teste.

---

## Critério de Tiers

| Tier | Risco | SLA | Definição |
|---|---|---|---|
| **1 — CRÍTICO** | Vazamento financeiro, quebra de compliance, sequestro de dados entre tenants, falha de auth | Imediato — toda PR touca = testes obrigatórios | Financeiro, Auth/Tenant, Fiscal, LGPD, Segurança |
| **2 — ALTO** | Quebra de operação core (OS, Calibração, CRM, Estoque, Contrato, Portal) | Próxima sprint | Core business operations |
| **3 — MÉDIO** | Relatórios, dashboards, admin, ferramentas auxiliares | Backlog contínuo | Observabilidade e support |

---

## TIER 1 — CRÍTICO (testes obrigatórios imediatos)

### 1.1 Auth / Tenant / IAM (14 controllers)
| Controller | Ação recomendada |
|---|---|
| `Api/V1/Auth/AuthController` | Login/logout/refresh, rate limit 429, cross-tenant |
| `Api/V1/Auth/PasswordResetController` | Token lifecycle, expiração, idempotência |
| `Api/V1/TwoFactorController` | TOTP verify, recovery codes, lockout |
| `Api/V1/TenantController` | Switch tenant, isolamento, permissão |
| `Api/V1/PermissionController` | CRUD permissions, escalation prevention |
| `Api/V1/RoleController` | CRUD roles, escalation prevention |
| `Api/V1/UserController` | CRUD users, cross-tenant, role assignment |
| `Api/V1/SettingsController` | Per-tenant config, permissão |
| `Api/V1/Billing/SaasSubscriptionController` | Plan change, cross-tenant, idempotência |
| `Api/V1/Billing/SaasPlanController` | Read plans, permissão admin |
| `Api/V1/PortalAuthController` | Portal login separado, isolamento cliente |
| `Api/V1/AuditLogController` | Query audit log, permissão, user enumeration |
| `Api/V1/SecurityController` | Security settings, 2FA enforcement |
| `Api/V1/BiometricConsentController` | LGPD consent, opt-in/out |

### 1.2 Financeiro Core (17 controllers)
| Controller | Ação recomendada |
|---|---|
| `Api/V1/AccountPayableController` | CRUD, paginação, cross-tenant, status transitions |
| `Api/V1/AccountingReportController` | Paginar `->get()` sem limit, permissão `export-reports` |
| `Api/V1/BankAccountController` | CRUD, isolamento |
| `Api/V1/BankReconciliationController` | Matching algorithm, edge cases |
| `Api/V1/CashFlowController` | Date range, agregação, permissão |
| `Api/V1/ChartOfAccountController` | CRUD árvore, validação de hierarquia |
| `Api/V1/ConsolidatedFinancialController` | Multi-branch roll-up |
| `Api/V1/ExpenseController` | CRUD, approval workflow, `created_by` não `user_id` |
| `Api/V1/PaymentController` | CRUD, reconciliação |
| `Api/V1/InvoiceController` | CRUD, status, cross-tenant |
| `Api/V1/FinancialExportController` | Export CSV com permissão, não `return true` |
| `Api/V1/FundTransferController` | Transferência entre contas, atomicidade |
| `Api/V1/DebtRenegotiationController` | Renegociação, anti-fraude |
| `Api/V1/CollectionRuleController` | Rules engine, CRUD |
| `Api/V1/ReconciliationRuleController` | Rules, CRUD |
| `Api/V1/RenegotiationController` | Flow completo |
| `Api/V1/PaymentMethodController` | CRUD, per-tenant |

### 1.3 Fiscal / NFe / NFSe (3 controllers + cascata)
| Controller | Ação recomendada |
|---|---|
| `Api/V1/FiscalController` | Emissão, cancelamento, idempotência |
| `Api/V1/FiscalPublicController` | Consulta pública, rate limit |
| `Api/V1/FiscalWebhookCallbackController` | HMAC validation, replay protection |

### 1.4 LGPD / Compliance (5 controllers)
| Controller | Ação recomendada |
|---|---|
| `Api/V1/LgpdConsentLogController` | Registro imutável, audit trail |
| `Api/V1/LgpdDataRequestController` | Solicitação de acesso/exclusão |
| `Api/V1/LgpdDpoConfigController` | Config DPO, per-tenant |
| `Api/V1/LgpdSecurityIncidentController` | Registro incidentes, obrigações ANPD |
| `Api/V1/LgpdDataTreatmentController` | Matriz de tratamento |

**Tier 1 Total: 39 controllers**

### 1.5 Jobs Tier 1 (Fiscal + ESocial) — 7 jobs
| Job | Ação recomendada |
|---|---|
| `EmitNFeJob` | Dispatch com tenant scope, retries, failure path |
| `EmitFiscalNoteJob` | Sefaz contingency, idempotência |
| `GenerateESocialEventsJob` | Batch generation, compliance |
| `ProcessESocialBatchJob` | Submission, retry exponencial |
| `RepairSeal/SubmitSealToPseiJob` | PSEI submission |
| `RepairSeal/RetryFailedPseiSubmissionsJob` | Retry logic |
| `RepairSeal/CheckSealDeadlinesJob` | Deadline alerts |

### 1.6 Policies Tier 1 (Financeiro + User) — 11 policies
`AccountPayablePolicy`, `AccountReceivablePolicy`, `BankAccountPolicy`, `ChartOfAccountPolicy`, `ExpensePolicy`, `FundTransferPolicy`, `DebtRenegotiationPolicy`, `InvoicePolicy`, `PaymentPolicy`, `FiscalNotePolicy`, `UserPolicy`

**Tier 1 GRAN TOTAL: 39 controllers + 7 jobs + 11 policies = 57 unidades**

---

## TIER 2 — ALTO (core operacional)

### 2.1 Ordem de Serviço (17 controllers)
`WorkOrderActionController`, `WorkOrderApprovalController`, `WorkOrderAttachmentController`, `WorkOrderChatController`, `WorkOrderChecklistResponseController`, `WorkOrderCommentController`, `WorkOrderDashboardController`, `WorkOrderDisplacementController`, `WorkOrderEquipmentController`, `WorkOrderExecutionController`, `WorkOrderFieldController`, `WorkOrderImportExportController`, `WorkOrderIntegrationController`, `WorkOrderRatingController`, `WorkOrderSignatureController`, `WorkOrderTemplateController`, `WorkOrderTimeLogController`

### 2.2 Calibração ISO 17025 (5 controllers)
`CalibrationControlChartController`, `StandardWeightController`, `StandardWeightWearController`, `WeightAssignmentController`, `WeightToolController`

### 2.3 Contratos (3 controllers)
`ContractController`, `ContractsAdvancedController`, `RecurringContractController`, `RescissionController`

### 2.4 Estoque / Inventário (10 controllers)
`BatchController`, `BatchExportController`, `InventoryController`, `InventoryPwaController`, `KardexController`, `ProductController`, `ProductKardexController`, `ProductKitController`, `StockController`, `StockAdvancedController`, `StockIntegrationController`, `StockIntelligenceController`, `StockLabelController`, `StockTransferController`, `UsedStockItemController`, `WarehouseController`, `WarehouseStockController`, `QrCodeInventoryController`, `PartsKitController`

### 2.5 CRM / Vendas (12 controllers)
`CrmAdvancedController`, `CrmAlertController`, `CrmContractController`, `CrmEngagementController`, `CrmFieldManagementController`, `CrmIntelligenceController`, `CrmMessageController`, `CrmProposalTrackingController`, `CrmSalesPipelineController`, `CrmSequenceController`, `CrmTerritoryGoalController`, `CrmWebFormController`

### 2.6 Comissões (6 controllers)
`CommissionCampaignController`, `CommissionController`, `CommissionDashboardController`, `CommissionDisputeController`, `CommissionGoalController`, `CommissionRuleController`, `RecurringCommissionController`

### 2.7 Portal Cliente (6 controllers)
`PortalController`, `PortalClienteController`, `PortalExecutiveDashboardController`, `PortalFinancialController`, `PortalGuestController`, `PortalTicketController`, `ClientPortalController`

### 2.8 RH / Journey (7 controllers)
`HrPortalController`, `JourneyApprovalController`, `JourneyBlockController`, `PayrollController`, `PayrollIntegrationController`, `PerformanceReviewController`, `JobPostingController`

### 2.9 Equipamentos (4 controllers)
`EquipmentHistoryController`, `EquipmentMaintenanceController`, `EquipmentModelController`, `ExpressWorkOrderController`, `TechQuickQuoteController`

### 2.10 Serviços (5 controllers)
`ServiceCallController`, `ServiceCallTemplateController`, `ServiceChecklistController`, `ServiceOpsController`, `ChecklistController`

**Tier 2 TOTAL: ~95 controllers**

### 2.11 Jobs Tier 2 (13 jobs)
Calibration: `DetectCalibrationFraudulentPatterns`, `Inmetro/ScrapeInstrumentDetailsJob`
Journey/CLT: `CalculateDailyJourney`, `ArchiveExpiredClockDataJob`, `CheckVacationDeadlines`, `ResolveClockLocationJob`, `MonthlyEspelhoDeliveryJob`
CRM/Notify: `GenerateCrmSmartAlerts`, `ProcessCrmSequences`, `QuoteExpirationAlertJob`, `QuoteFollowUpJob`, `StockMinimumAlertJob`
Documents: `CheckDocumentVersionExpiry`, `CheckExpiringDocuments`

### 2.12 Policies Tier 2 (~30 policies)
Todas as policies de CRM, Equipment, Calibration, Stock, Contract, WorkOrder, Commission e Service.

---

## TIER 3 — MÉDIO (relatórios, admin, observabilidade)

### 3.1 Analytics / Reports (15 controllers)
`Api/V1/Analytics/AIAnalyticsController`, `Api/V1/Analytics/AiAssistantController`, `Api/V1/Analytics/AnalyticsController`, `Api/V1/Analytics/AnalyticsDatasetController`, `Api/V1/Analytics/BiAnalyticsController`, `Api/V1/Analytics/DataExportJobController`, `Api/V1/Analytics/EmbeddedDashboardController`, `Api/V1/Analytics/ExpenseAnalyticsController`, `Api/V1/Analytics/FinancialAnalyticsController`, `Api/V1/Analytics/FleetAnalyticsController`, `Api/V1/Analytics/HRAnalyticsController`, `Api/V1/Analytics/PeopleAnalyticsController`, `Api/V1/Analytics/QualityAnalyticsController`, `Api/V1/Analytics/SalesAnalyticsController`, `ReportController`, `ReportsController`

### 3.2 Dashboards (5 controllers)
`DashboardController`, `OperationalDashboardController`, `SlaDashboardController`, `TvDashboardController`, `TvDashboardConfigController`

### 3.3 Admin / Admin Tools (12 controllers)
`AdminTechnicianFundRequestController`, `AutomationController`, `AuvoExportController`, `AuvoImportController`, `ImportController`, `XmlImportController`, `ExternalApiController`, `InfraIntegrationController`, `FeaturesController`, `NumberingSequenceController`, `SystemImprovementsController`, `LookupController`

### 3.4 Email / Comunicação (4 controllers)
`EmailActivityController`, `EmailLogController`, `EmailNoteController`, `EmailRuleController`

### 3.5 Diversos (~15 controllers)
`AgendaController`, `AgendaItemController`, `AlertController`, `CameraController`, `CatalogController`, `CustomerDocumentController`, `CustomerMergeController`, `DepartmentController`, `GoogleCalendarController`, `GpsTrackingController`, `InmetroController`, `InmetroAdvancedController`, `InmetroSealController`, `LabAdvancedController`, `ManagementReviewController`, `MetrologyQualityController`, `NotificationController`, `OrganizationController`, `PdfController`, `PeripheralReportController`, `PriceHistoryController`, `PriceTableController`, `PushSubscriptionController`, `QualityController`, `SatisfactionSurveyController`, `SearchController`, `SkillsController`, `TechnicianCashController`, `TechnicianCertificationController`, `TechnicianExpenseController`, `TollIntegrationController`, `ToolManagementController`, `ToolTrackingController`, `TravelExpenseController`, `UserFavoriteController`, `UserLocationController`, `VehiclePoolController`, `VehicleTireController`, `WarrantyTrackingController`, `WhatsappController`, `SlaPolicyController`, `FollowUpController`, `FleetAdvancedController`, `FuelingLogController`, `BranchController`, `CostCenterController`, `HealthCheckController`, `FiscalPublicController`

### 3.6 Jobs Tier 3 (15 jobs)
`DispatchWebhookJob`, `ImportJob`, `SyncEmailAccountJob`, `ClassifyEmailJob`, `SendQuoteEmailJob`, `SendScheduledEmails`, `CaptureTvDashboardKpis`, `RunDataExportJob`, `GenerateReportJob`, `SendScheduledReportJob`, `RunMonthlyDepreciation`, `FleetDocExpirationAlertJob`, `FleetMaintenanceAlertJob`, `CheckExpiringDocuments`, `Middleware/SetTenantContext`

### 3.7 Policies Tier 3 (~22 policies)
Policies restantes: `AgendaItem`, `Email`, `EmailTag`, `FuelingLog`, `NumberingSequence`, `PurchaseQuotation`, `Quote`, `Schedule`, `SlaPolicyPolicy`, `TechnicianCashFund`, `WarrantyTracking`, `WorkOrderTemplate`, `AssetRecord`, `Branch`, `Department`, `Position`, `Project`, `ReconciliationRule`, etc.

### 3.8 Listeners (44 total — todos sem teste direto)
Priorização interna dos listeners:
- **Críticos (P1):** `AutoEmitNFeOnInvoice`, `HandleWorkOrderInvoicing`, `HandlePaymentReceived`, `TriggerCertificateGeneration`, `ReleaseWorkOrderOnFiscalNoteAuthorized`, `CreateWarrantyTrackingOnWorkOrderInvoiced`
- **Altos (P2):** `HandleWorkOrderCompletion`, `HandleCalibrationExpiring`, `HandleContractRenewing`, `GenerateAccountPayableFromExpense`, `GenerateCorrectiveQuoteOnCalibrationFailure`, `Journey/OnTimeClockEvent`, `Journey/OnWorkOrderCheckin`, `HandleQuoteApproval`
- **Resto (P3):** demais listeners de notificação e agenda

---

## Padrão mínimo de teste por categoria

### Controller (CRUD simples) — 5 testes
```php
// 1. Happy path index (lista, cross-tenant, pagination)
// 2. Happy path store (cria, assertJsonStructure, DB persistence)
// 3. Validation 422 (campos obrigatórios, regras)
// 4. Cross-tenant 404 (recurso de outro tenant)
// 5. Permission 403 (usuário sem role)
```

### Controller (lógica customizada) — 8+ testes
Acima + edge cases, side effects, transitions de status, eager loading (N+1 guard).

### Job — 3 testes
```php
// 1. dispatches_with_tenant_scope (tenant_id correto)
// 2. handles_successfully (happy path end-to-end)
// 3. retries_on_failure (retry logic, $tries, backoff)
```

### Policy — 3 testes por método público
```php
// Para cada método (view, create, update, delete):
// 1. allows_for_authorized_user (permissão + same tenant)
// 2. denies_for_unauthorized_user (sem permissão)
// 3. denies_cross_tenant (tenant diferente retorna false)
```

### Listener — 2 testes
```php
// 1. listens_to_expected_event (Event::fake + assertDispatched)
// 2. executes_side_effect (dispara → verifica DB/queue)
```

---

## Estimativa de esforço

| Tier | Unidades | Testes estimados | Esforço |
|---|---:|---:|---|
| Tier 1 (core crítico) | 57 | ~350 testes | 3-5 sessões |
| Tier 2 (operacional) | ~140 | ~700 testes | 8-12 sessões |
| Tier 3 (observabilidade) | ~160 | ~500 testes | 6-10 sessões |
| **TOTAL** | **~357** | **~1550 testes** | **17-27 sessões** |

---

## Regras de execução (invioláveis)

1. **TDD first** — teste falhando → código → teste verde
2. **Commits atômicos** — máximo 5 arquivos por commit (guardrail de escopo)
3. **Gate escopado após cada lote** — `pest tests/<path>` verde antes do commit
4. **Gate completo apenas no fim de cada tier**
5. **Proibido:** `markTestSkipped`, `assertTrue(true)`, `->skip()`, `markTestIncomplete`
6. **Obrigatório em controllers:** `assertJsonStructure`, teste cross-tenant 404, teste 403 sem permissão
7. **Se teste expõe bug no código fonte → corrigir o código (Lei 2)**

---

## Próximos passos desta sessão

Esta sessão executará **Fase 2 Tier 1** — 10 controllers críticos em 2 lotes de 5:

**Lote 1 (alto impacto):**
1. `AuvoExportController` (já tem 1 teste — expandir)
2. `RouteOptimizationController` (já tem 1 teste — expandir)
3. `AccountingReportController` (0 testes, P1 paginação)
4. `Api/V1/Analytics/AnalyticsController` (SQL injection P0 + 0 testes)
5. `SaasSubscriptionController` (exists sem tenant P0 + 0 testes)

**Lote 2 (segurança + sistema):**
6. `BootstrapSecurityController` (0 testes)
7. `CommissionController` (0 testes)
8. `AuditPermissionsCommand` (0 testes)
9. `CheckExpiredQuotesController` (0 testes)
10. `RefreshAnalyticsDatasetsController` (0 testes)

Sessões futuras completarão Tier 1 restante (29 controllers), Tier 2 e Tier 3.

---

**Referências:**
- `docs/audits/audit-tests-quality-2026-04-10.md` — auditoria original (240 findings)
- `docs/audits/audit-security-2026-04-10.md` — achados de segurança ligados a testes
- `docs/plans/remediation-audit-2026-04-10.md` — plano macro de remediação
- `.agent/rules/test-policy.md` — política oficial de testes (definição de mascaramento)
- `backend/tests/README.md` — templates de teste
