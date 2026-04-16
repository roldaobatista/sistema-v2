<?php

/**
 * Routes: Lookups, Lotes 1-10, WO Templates, Quality Audits, Debt, Receipts, Work Schedules, Tool Mgmt
 * Extracted from api.php - Phase 10 Modularization
 * Original lines: 2713-3021
 */

use App\Http\Controllers\Api\V1\Calibration\LinearityTestController;
use App\Http\Controllers\Api\V1\CalibrationControlChartController;
use App\Http\Controllers\Api\V1\ClientPortalController;
use App\Http\Controllers\Api\V1\CollectionRuleController;
use App\Http\Controllers\Api\V1\ContractsAdvancedController;
use App\Http\Controllers\Api\V1\CostCenterController;
use App\Http\Controllers\Api\V1\CrmAdvancedController;
use App\Http\Controllers\Api\V1\Equipment\EquipmentHistoryController;
use App\Http\Controllers\Api\V1\Equipment\WeightAssignmentController;
use App\Http\Controllers\Api\V1\Financial\DebtRenegotiationController;
use App\Http\Controllers\Api\V1\Financial\PaymentReceiptController;
use App\Http\Controllers\Api\V1\Hr\WorkScheduleController;
use App\Http\Controllers\Api\V1\HRAdvancedController;
use App\Http\Controllers\Api\V1\HrPeopleController;
use App\Http\Controllers\Api\V1\InfraIntegrationController;
use App\Http\Controllers\Api\V1\Logistics\DispatchController;
use App\Http\Controllers\Api\V1\LookupController;
use App\Http\Controllers\Api\V1\MetrologyQualityController;
use App\Http\Controllers\Api\V1\Os\WorkOrderTemplateController;
use App\Http\Controllers\Api\V1\Quality\DocumentVersionController;
use App\Http\Controllers\Api\V1\Quality\NonConformityController;
use App\Http\Controllers\Api\V1\Quality\QualityAuditController;
use App\Http\Controllers\Api\V1\Security\TwoFactorController;
use App\Http\Controllers\Api\V1\SecurityController;
use App\Http\Controllers\Api\V1\ServiceOpsController;
use App\Http\Controllers\Api\V1\Stock\ToolManagementController;
use App\Http\Controllers\Api\V1\StockAdvancedController;
use App\Http\Controllers\Api\V1\StockTransferController;
use Illuminate\Support\Facades\Route;

// --- Cadastros Auxiliares (Lookups) ---
Route::prefix('lookups')->group(function () {
    Route::middleware('check.permission:lookups.view')->get('types', [LookupController::class, 'types']);
    Route::middleware('check.permission:lookups.view')->get('{type}', [LookupController::class, 'index']);
    Route::middleware('check.permission:lookups.create')->post('{type}', [LookupController::class, 'store']);
    Route::middleware('check.permission:lookups.update')->put('{type}/{id}', [LookupController::class, 'update']);
    Route::middleware('check.permission:lookups.delete')->delete('{type}/{id}', [LookupController::class, 'destroy']);
});

// ═══════════════════════════════════════════════════════════════════
// LOTE 1: Atendimento & OS — Service Ops (#1-#8B)
// ═══════════════════════════════════════════════════════════════════
Route::prefix('operational/service-ops')->group(function () {
    // #1 SLA
    Route::middleware('check.permission:os.work_order.view')->get('sla/dashboard', [ServiceOpsController::class, 'slaDashboard']);
    Route::middleware('check.permission:os.work_order.update')->post('sla/run-checks', [ServiceOpsController::class, 'runSlaChecks']);
    Route::middleware('check.permission:os.work_order.view')->get('sla/status/{workOrder}', [ServiceOpsController::class, 'slaStatus']);
    // #2B Bulk create
    Route::middleware('check.permission:os.work_order.create')->post('work-orders/bulk-create', [ServiceOpsController::class, 'bulkCreateWorkOrders']);
    // #3 Auto assignment aliases (promovidos para Logistics, mantidos aqui por compatibilidade)
    Route::middleware('check.permission:os.work_order.view')->get('auto-assign/rules', [DispatchController::class, 'autoAssignRules']);
    Route::middleware('check.permission:os.work_order.create')->post('auto-assign/rules', [DispatchController::class, 'storeAutoAssignRule']);
    Route::middleware('check.permission:os.work_order.update')->put('auto-assign/rules/{rule}', [DispatchController::class, 'updateAutoAssignRule']);
    Route::middleware('check.permission:os.work_order.delete')->delete('auto-assign/rules/{rule}', [DispatchController::class, 'deleteAutoAssignRule']);
    Route::middleware('check.permission:os.work_order.update')->post('auto-assign/work-orders/{workOrder}', [DispatchController::class, 'triggerAutoAssign']);

});

// LOTE 2: Financeiro Avançado — rotas em routes/api/finance-advanced.php
require base_path('routes/api/finance-advanced.php');

// ═══════════════════════════════════════════════════════════════════
// LOTE 3: Estoque & Compras (#16B-#21B)
// ═══════════════════════════════════════════════════════════════════
Route::prefix('stock-advanced')->group(function () {
    // ═══ Transferências de Estoque (alias de /stock/transfers com mesma blindagem) ═══
    Route::middleware('check.permission:estoque.view')->group(function () {
        Route::get('transfers', [StockTransferController::class, 'index']);
        Route::get('transfers/{transfer}', [StockTransferController::class, 'show']);
        Route::middleware('check.permission:estoque.transfer.create')->post('transfers', [StockTransferController::class, 'store']);
        Route::middleware('check.permission:estoque.transfer.accept')->post('transfers/{transfer}/accept', [StockTransferController::class, 'accept']);
        Route::middleware('check.permission:estoque.transfer.accept')->post('transfers/{transfer}/reject', [StockTransferController::class, 'reject']);
    });
    Route::middleware('check.permission:estoque.movement.view')->get('auto-reorder/suggestions', [StockAdvancedController::class, 'autoReorder']);
    Route::middleware('check.permission:estoque.movement.create')->post('auto-reorder/create', [StockAdvancedController::class, 'createAutoReorderPO']);
    Route::middleware('check.permission:estoque.movement.create')->post('work-orders/{workOrder}/auto-deduct', [StockAdvancedController::class, 'autoDeductFromWO']);
    Route::middleware('check.permission:estoque.movement.create')->post('inventory/start-count', [StockAdvancedController::class, 'startCyclicCount']);
    Route::middleware('check.permission:estoque.movement.create')->post('inventory/{count}/submit', [StockAdvancedController::class, 'submitCount']);
    Route::middleware('check.permission:estoque.movement.view')->get('warranty-tracking', [StockAdvancedController::class, 'warrantyTracking']);
    Route::middleware('check.permission:estoque.movement.view')->get('warranty/lookup', [StockAdvancedController::class, 'warrantyLookup']);
    Route::middleware('check.permission:estoque.movement.view')->post('quotes/compare', [StockAdvancedController::class, 'comparePurchaseQuotes']);
    Route::middleware('check.permission:estoque.movement.view')->get('slow-moving', [StockAdvancedController::class, 'slowMovingAnalysis']);
});

// ═══════════════════════════════════════════════════════════════════
// LOTE 4: CRM & Vendas (#22-#27)
// ═══════════════════════════════════════════════════════════════════
Route::prefix('crm-advanced')->group(function () {
    Route::middleware('check.permission:crm.deal.view')->get('funnel-automations', [CrmAdvancedController::class, 'funnelAutomations']);
    Route::middleware('check.permission:crm.deal.create')->post('funnel-automations', [CrmAdvancedController::class, 'storeFunnelAutomation']);
    Route::middleware('check.permission:crm.deal.update')->put('funnel-automations/{id}', [CrmAdvancedController::class, 'updateFunnelAutomation']);
    Route::middleware('check.permission:crm.deal.delete')->delete('funnel-automations/{id}', [CrmAdvancedController::class, 'deleteFunnelAutomation']);
    Route::middleware('check.permission:crm.scoring.view')->post('lead-scoring/recalculate', [CrmAdvancedController::class, 'recalculateLeadScores']);
    Route::middleware('check.permission:crm.deal.update')->post('quotes/{quote}/send-for-signature', [CrmAdvancedController::class, 'sendQuoteForSignature']);
    Route::middleware('check.permission:crm.forecast.view')->get('forecast', [CrmAdvancedController::class, 'salesForecast']);
    Route::middleware('check.permission:crm.deal.view')->get('leads/duplicates', [CrmAdvancedController::class, 'findDuplicateLeads']);
    Route::middleware('check.permission:crm.deal.update')->post('leads/merge', [CrmAdvancedController::class, 'mergeLeads']);
    Route::middleware('check.permission:crm.pipeline.view')->get('pipelines', [CrmAdvancedController::class, 'multiProductPipelines']);
    Route::middleware('check.permission:crm.pipeline.create')->post('pipelines', [CrmAdvancedController::class, 'createPipeline']);
});

// ═══════════════════════════════════════════════════════════════════
// LOTE 5: BI & Analytics (#28-#32)
// ═══════════════════════════════════════════════════════════════════
Route::prefix('bi-analytics')->group(function () {});

// ═══════════════════════════════════════════════════════════════════
// LOTE 6: RH & Pessoas (#33-#37)
// ═══════════════════════════════════════════════════════════════════
Route::prefix('hr-advanced')->group(function () {
    Route::middleware('check.permission:hr.epi.view')->get('epi', [HRAdvancedController::class, 'epiList']);
    Route::middleware('check.permission:hr.schedule.view')->get('hour-bank', [HrPeopleController::class, 'hourBankSummary']);
    Route::middleware('check.permission:hr.schedule.view')->get('on-call', [HrPeopleController::class, 'onCallSchedule']);
    Route::middleware('check.permission:hr.schedule.manage')->post('on-call', [HrPeopleController::class, 'storeOnCallSchedule']);
    Route::middleware('check.permission:hr.performance.view')->get('performance-reviews', [HrPeopleController::class, 'performanceReviews']);
    Route::middleware('check.permission:hr.performance.view')->post('performance-reviews', [HrPeopleController::class, 'storePerformanceReview']);
    Route::middleware('check.permission:hr.onboarding.view')->get('onboarding/templates', [HrPeopleController::class, 'onboardingTemplates']);
    Route::middleware('check.permission:hr.onboarding.view')->post('onboarding/templates', [HrPeopleController::class, 'storeOnboardingTemplate']);
    Route::middleware('check.permission:hr.onboarding.view')->post('onboarding/start', [HrPeopleController::class, 'startOnboarding']);
    Route::middleware('check.permission:hr.schedule.view')->get('training/courses', [HrPeopleController::class, 'trainingCourses']);
    Route::middleware('check.permission:hr.schedule.manage')->post('training/courses', [HrPeopleController::class, 'storeTrainingCourse']);
    Route::middleware('check.permission:hr.schedule.manage')->post('training/enroll', [HrPeopleController::class, 'enrollUser']);
    Route::middleware('check.permission:hr.schedule.manage')->post('training/{enrollment}/complete', [HrPeopleController::class, 'completeTraining']);
});

// ═══════════════════════════════════════════════════════════════════
// LOTE 7: Contratos & Recorrência (#38-#41)
// ═══════════════════════════════════════════════════════════════════
Route::prefix('contracts-advanced')->group(function () {
    Route::middleware('check.permission:contracts.contract.view')->get('adjustments/pending', [ContractsAdvancedController::class, 'pendingAdjustments']);
    Route::middleware('check.permission:contracts.contract.update')->post('{contract}/adjust', [ContractsAdvancedController::class, 'applyAdjustment']);
    Route::middleware('check.permission:contracts.contract.view')->get('churn-risk', [ContractsAdvancedController::class, 'churnRisk']);
    Route::middleware('check.permission:contracts.contract.view')->get('{contract}/addendums', [ContractsAdvancedController::class, 'contractAddendums']);
    Route::middleware('check.permission:contracts.contract.create')->post('{contract}/addendums', [ContractsAdvancedController::class, 'createAddendum']);
    Route::middleware('check.permission:contracts.contract.update')->post('addendums/{addendum}/approve', [ContractsAdvancedController::class, 'approveAddendum']);
    Route::middleware('check.permission:contracts.contract.view')->get('{contract}/measurements', [ContractsAdvancedController::class, 'contractMeasurements']);
    Route::middleware('check.permission:contracts.contract.create')->post('{contract}/measurements', [ContractsAdvancedController::class, 'storeMeasurement']);
});

// ═══════════════════════════════════════════════════════════════════
// LOTE 8: Portal do Cliente (#42-#44)
// ═══════════════════════════════════════════════════════════════════
Route::prefix('client-portal')->group(function () {
    Route::middleware('check.permission:portal.client.create')->post('service-calls', [ClientPortalController::class, 'createServiceCallFromPortal']);
    Route::middleware('check.permission:portal.client.view')->get('work-orders/track', [ClientPortalController::class, 'trackWorkOrders']);
    Route::middleware('check.permission:portal.client.view')->get('service-calls/track', [ClientPortalController::class, 'trackServiceCalls']);
    Route::middleware('check.permission:portal.client.view')->get('calibration-certificates', [ClientPortalController::class, 'calibrationCertificates']);
    Route::middleware('check.permission:portal.client.view')->get('calibration-certificates/{certificate}/download', [ClientPortalController::class, 'downloadCertificate']);
});

// ═══════════════════════════════════════════════════════════════════
// LOTE 9: Metrologia & Qualidade (#45-#48)
// ═══════════════════════════════════════════════════════════════════
Route::prefix('metrology')->group(function () {
    Route::middleware('check.permission:calibration.reading.view')->get('non-conformances', [MetrologyQualityController::class, 'nonConformances']);
    Route::middleware('check.permission:calibration.reading.create')->post('non-conformances', [MetrologyQualityController::class, 'storeNonConformance']);
    Route::middleware('check.permission:calibration.reading.create')->put('non-conformances/{id}', [MetrologyQualityController::class, 'updateNonConformance']);
    Route::middleware('check.permission:calibration.reading.view')->post('certificates/{certificate}/qr', [MetrologyQualityController::class, 'generateCertificateQR']);
    Route::middleware('check.permission:calibration.reading.view')->get('uncertainties', [MetrologyQualityController::class, 'measurementUncertainty']);
    Route::middleware('check.permission:calibration.reading.create')->post('uncertainties', [MetrologyQualityController::class, 'storeMeasurementUncertainty']);
    Route::middleware('check.permission:calibration.reading.view')->get('calibration-schedule', [MetrologyQualityController::class, 'calibrationSchedule']);
    Route::middleware('check.permission:calibration.reading.create')->post('calibration-schedule/recall', [MetrologyQualityController::class, 'triggerRecall']);

    // Control Charts (SPC) - 2.14
    Route::middleware('check.permission:equipments.equipment.view')->get('control-charts/{equipment_id}', [CalibrationControlChartController::class, 'show']);

    // QA Alerts (Anti-Fraud) - 2.13
    Route::middleware('check.permission:calibration.reading.view')->get('qa-alerts', [MetrologyQualityController::class, 'qaAlerts']);

    // Linearity Tests (ISO 17025 / Portaria 157)
    Route::middleware('check.permission:calibration.reading.view')->get('calibration/{calibration}/linearity', [LinearityTestController::class, 'index']);
    Route::middleware('check.permission:calibration.reading.create')->post('calibration/{calibration}/linearity', [LinearityTestController::class, 'store']);
    Route::middleware('check.permission:calibration.reading.create')->delete('calibration/{calibration}/linearity', [LinearityTestController::class, 'destroyAll']);
});

// ═══════════════════════════════════════════════════════════════════
// LOTE 10: Infraestrutura & Integrações (#49-#50)
// ═══════════════════════════════════════════════════════════════════
Route::prefix('infra')->group(function () {
    Route::middleware('check.permission:platform.settings.view')->get('webhooks', [InfraIntegrationController::class, 'webhookConfigs']);
    Route::middleware('check.permission:platform.settings.manage')->post('webhooks', [InfraIntegrationController::class, 'storeWebhook']);
    Route::middleware('check.permission:platform.settings.manage')->put('webhooks/{id}', [InfraIntegrationController::class, 'updateWebhook']);
    Route::middleware('check.permission:platform.settings.manage')->delete('webhooks/{id}', [InfraIntegrationController::class, 'deleteWebhook']);
    Route::middleware('check.permission:platform.settings.manage')->post('webhooks/{id}/test', [InfraIntegrationController::class, 'testWebhook']);
    Route::middleware('check.permission:platform.settings.view')->get('webhooks/{id}/logs', [InfraIntegrationController::class, 'webhookLogs']);
    Route::middleware('check.permission:platform.settings.view')->get('api-keys', [InfraIntegrationController::class, 'apiKeys']);
    Route::middleware('check.permission:platform.settings.manage')->post('api-keys', [InfraIntegrationController::class, 'createApiKey']);
    Route::middleware('check.permission:platform.settings.manage')->delete('api-keys/{id}/revoke', [InfraIntegrationController::class, 'revokeApiKey']);
    Route::middleware('check.permission:platform.settings.view')->get('swagger', [InfraIntegrationController::class, 'swaggerSpec']);
});

// ═══════════════════════════════════════════════════════════════════
// End of Lotes 1-10
// ═══════════════════════════════════════════════════════════════════

// ═══ Recursos Avançados — rotas extras que NÃO existem em advanced-features.php ═══
// (Follow-ups, Price Tables, Customer Documents, Ratings e Route Plans GET/Cost Centers GET
//  já estão definidos em advanced-features.php com permissões mais específicas)

// Cost Center CRUD (manage) — não existe em advanced-features.php
Route::middleware('check.permission:finance.cost_center.manage')->group(function () {
    Route::post('advanced/cost-centers', [CostCenterController::class, 'store']);
    Route::put('advanced/cost-centers/{costCenter}', [CostCenterController::class, 'update']);
    Route::delete('advanced/cost-centers/{costCenter}', [CostCenterController::class, 'destroy']);
});

// Collection Rules — não existe em advanced-features.php
Route::middleware('check.permission:finance.receivable.view')->get('advanced/collection-rules', [CollectionRuleController::class, 'index']);
Route::middleware('check.permission:finance.receivable.manage')->group(function () {
    Route::post('advanced/collection-rules', [CollectionRuleController::class, 'store']);
    Route::put('advanced/collection-rules/{rule}', [CollectionRuleController::class, 'update']);
});

// ═══ Work Order Approvals ═══

// ═══ Work Order Templates ═══
Route::middleware('check.permission:os.work_order.view')->get('work-order-templates', [WorkOrderTemplateController::class, 'index']);
Route::middleware('check.permission:os.work_order.view')->get('work-order-templates/{id}', [WorkOrderTemplateController::class, 'show']);
Route::middleware('check.permission:os.work_order.create')->post('work-order-templates', [WorkOrderTemplateController::class, 'store']);
Route::middleware('check.permission:os.work_order.update')->put('work-order-templates/{id}', [WorkOrderTemplateController::class, 'update']);
Route::middleware('check.permission:os.work_order.delete')->delete('work-order-templates/{id}', [WorkOrderTemplateController::class, 'destroy']);

// ═══ Equipment History ═══
Route::middleware('check.permission:equipments.equipment.view')->get('equipments/{equipment}/history', [EquipmentHistoryController::class, 'history']);
Route::middleware('check.permission:equipments.equipment.view')->get('equipments/{equipment}/work-orders', [EquipmentHistoryController::class, 'workOrders']);

// ═══ Quality Audits ═══
Route::middleware('check.permission:quality.audit.view')->group(function () {
    Route::get('quality-audits', [QualityAuditController::class, 'index']);
    Route::get('quality-audits/{qualityAudit}', [QualityAuditController::class, 'show']);
});
Route::middleware('check.permission:quality.audit.create')->post('quality-audits', [QualityAuditController::class, 'store']);
Route::middleware('check.permission:quality.audit.update')->group(function () {
    Route::put('quality-audits/{qualityAudit}', [QualityAuditController::class, 'update']);
    Route::put('quality-audits/{qualityAudit}/items/{itemId}', [QualityAuditController::class, 'updateItem']);
});
Route::middleware('check.permission:quality.audit.delete')->delete('quality-audits/{qualityAudit}', [QualityAuditController::class, 'destroy']);
// Ações Corretivas / Fechamento de NC
Route::middleware('check.permission:quality.audit.view')->get('quality-audits/{qualityAudit}/corrective-actions', [QualityAuditController::class, 'indexCorrectiveActions']);
Route::middleware('check.permission:quality.audit.update')->group(function () {
    Route::post('quality-audits/{qualityAudit}/corrective-actions', [QualityAuditController::class, 'storeCorrectiveAction']);
    Route::patch('quality-audits/{qualityAudit}/items/{itemId}/close', [QualityAuditController::class, 'closeItem']);
});

// ═══ Non-Conformities (RNC ISO-9001) ═══
Route::middleware('check.permission:quality.nc.view')->group(function () {
    Route::get('non-conformities', [NonConformityController::class, 'index']);
    Route::get('non-conformities/{nonConformity}', [NonConformityController::class, 'show']);
});
Route::middleware('check.permission:quality.nc.create')->post('non-conformities', [NonConformityController::class, 'store']);
Route::middleware('check.permission:quality.nc.update')->put('non-conformities/{nonConformity}', [NonConformityController::class, 'update']);
Route::middleware('check.permission:quality.nc.delete')->delete('non-conformities/{nonConformity}', [NonConformityController::class, 'destroy']);

// ═══ Document Versions ═══
Route::middleware('check.permission:quality.document.view')->group(function () {
    Route::get('document-versions', [DocumentVersionController::class, 'index']);
    Route::get('document-versions/{id}', [DocumentVersionController::class, 'show']);
});
Route::middleware('check.permission:quality.document.create')->post('document-versions', [DocumentVersionController::class, 'store']);
Route::middleware('check.permission:quality.document.update')->put('document-versions/{id}', [DocumentVersionController::class, 'update']);
Route::middleware('check.permission:quality.document.delete')->delete('document-versions/{id}', [DocumentVersionController::class, 'destroy']);

// ═══ Debt Renegotiation ═══
Route::middleware('check.permission:finance.receivable.view')->group(function () {
    Route::get('debt-renegotiations', [DebtRenegotiationController::class, 'index']);
    Route::get('debt-renegotiations/{debtRenegotiation}', [DebtRenegotiationController::class, 'show']);
});
Route::middleware('check.permission:finance.receivable.create')->post('debt-renegotiations', [DebtRenegotiationController::class, 'store']);
Route::middleware('check.permission:finance.receivable.update')->group(function () {
    Route::post('debt-renegotiations/{debtRenegotiation}/approve', [DebtRenegotiationController::class, 'approve']);
    Route::post('debt-renegotiations/{debtRenegotiation}/cancel', [DebtRenegotiationController::class, 'cancel']);
});

// ═══ Payment Receipts ═══
Route::middleware('check.permission:finance.receivable.view')->group(function () {
    Route::get('payment-receipts', [PaymentReceiptController::class, 'index']);
    Route::get('payment-receipts/{payment}', [PaymentReceiptController::class, 'show']);
    Route::get('payment-receipts/{payment}/pdf', [PaymentReceiptController::class, 'downloadPdf']);
});

// ═══ 2FA / Security — DESATIVADO POR DECISÃO DO PROPRIETÁRIO ═══
Route::prefix('security')->group(function () {
    Route::middleware('check.permission:platform.settings.view')
        ->get('2fa/status', [TwoFactorController::class, 'status']);
    // Password policy & Watermark
    Route::middleware('check.permission:platform.settings.view')
        ->get('password-policy', [SecurityController::class, 'passwordPolicy']);
    Route::middleware('check.permission:platform.settings.manage')
        ->put('password-policy', [SecurityController::class, 'updatePasswordPolicy']);
    Route::middleware('check.permission:platform.settings.view')
        ->get('watermark', [SecurityController::class, 'watermarkConfig']);
    Route::middleware('check.permission:platform.settings.manage')
        ->put('watermark', [SecurityController::class, 'updateWatermarkConfig']);
});

// ═══ Work Schedules ═══
Route::middleware('check.permission:rh.work_schedule.view')->group(function () {
    Route::get('work-schedules', [WorkScheduleController::class, 'index']);
    Route::get('work-schedules/{workSchedule}', [WorkScheduleController::class, 'show']);
});
Route::middleware('check.permission:rh.work_schedule.create')->post('work-schedules', [WorkScheduleController::class, 'store']);
Route::middleware('check.permission:rh.work_schedule.update')->put('work-schedules/{workSchedule}', [WorkScheduleController::class, 'update']);
Route::middleware('check.permission:rh.work_schedule.delete')->delete('work-schedules/{workSchedule}', [WorkScheduleController::class, 'destroy']);

// ═══ Tool Management ═══
Route::middleware('check.permission:estoque.movement.view')->group(function () {
    Route::get('tool-inventories', [ToolManagementController::class, 'inventoryIndex']);
    Route::get('tool-calibrations', [ToolManagementController::class, 'calibrationIndex']);
});
Route::middleware('check.permission:estoque.movement.create')->group(function () {
    Route::post('tool-inventories', [ToolManagementController::class, 'inventoryStore']);
    Route::put('tool-inventories/{id}', [ToolManagementController::class, 'inventoryUpdate']);
    Route::delete('tool-inventories/{id}', [ToolManagementController::class, 'inventoryDestroy']);
    Route::post('tool-calibrations', [ToolManagementController::class, 'calibrationStore']);
    Route::put('tool-calibrations/{id}', [ToolManagementController::class, 'calibrationUpdate']);
    Route::delete('tool-calibrations/{id}', [ToolManagementController::class, 'calibrationDestroy']);
});

// ═══ Weight Assignments ═══
Route::middleware('check.permission:equipments.standard_weight.view')->group(function () {
    Route::get('weight-assignments', [WeightAssignmentController::class, 'index']);
});
Route::middleware('check.permission:equipments.standard_weight.update')->group(function () {
    Route::post('weight-assignments', [WeightAssignmentController::class, 'store']);
    Route::put('weight-assignments/{id}', [WeightAssignmentController::class, 'update']);
    Route::delete('weight-assignments/{id}', [WeightAssignmentController::class, 'destroy']);
});

// ═══ HR Break Routes (Tech Time Clock) ═══
Route::middleware('check.permission:rh.clock.manage')->group(function () {
    Route::post('hr/advanced/break-start', [HRAdvancedController::class, 'breakStart']);
    Route::post('hr/advanced/break-end', [HRAdvancedController::class, 'breakEnd']);
});
