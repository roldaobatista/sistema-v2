<?php

/**
 * Routes: Analise Financeira, Comercial, Kardex, Ferramentas, IA, 75 Features
 * Extracted from api.php - Phase 10 Modularization
 * Original lines: 2521-2711
 */

use App\Http\Controllers\Api\V1\AlertController;
use App\Http\Controllers\Api\V1\Analytics\AIAnalyticsController;
use App\Http\Controllers\Api\V1\Analytics\AiAssistantController;
use App\Http\Controllers\Api\V1\Analytics\FinancialAnalyticsController;
use App\Http\Controllers\Api\V1\Analytics\SalesAnalyticsController;
use App\Http\Controllers\Api\V1\FeaturesController;
use App\Http\Controllers\Api\V1\Financial\ConsolidatedFinancialController;
use App\Http\Controllers\Api\V1\ManagementReviewController;
use App\Http\Controllers\Api\V1\ProductKardexController;
use App\Http\Controllers\Api\V1\Quality\QualityAuditController;
use App\Http\Controllers\Api\V1\RenegotiationController;
use App\Http\Controllers\Api\V1\ToolTrackingController;
use App\Http\Controllers\Api\V1\WeightToolController;
use App\Http\Controllers\Api\V1\WhatsappController;
use App\Http\Controllers\Api\V1\WorkOrderFieldController;
use Illuminate\Support\Facades\Route;

// ═══ Análise Financeira ═══════════════════════════════════════
Route::prefix('financial')->group(function () {
    Route::middleware('check.permission:financeiro.view|finance.cashflow.view')->get('cash-flow-projection', [FinancialAnalyticsController::class, 'cashFlowProjection']);
    Route::middleware('check.permission:financeiro.view|finance.cashflow.view')->get('cash-flow-weekly', [FinancialAnalyticsController::class, 'cashFlowWeekly']);
    Route::middleware('check.permission:financeiro.view|finance.dre.view')->get('dre', [FinancialAnalyticsController::class, 'dre']);
    Route::middleware('check.permission:financeiro.view|finance.receivable.view')->get('aging-report', [FinancialAnalyticsController::class, 'agingReport']);
    Route::middleware('check.permission:financeiro.view|expenses.expense.view')->get('expense-allocation', [FinancialAnalyticsController::class, 'expenseAllocation']);
    Route::middleware('check.permission:financeiro.view|finance.payable.view')->get('batch-payment-approval', [FinancialAnalyticsController::class, 'batchPaymentApproval']);
    Route::middleware('check.permission:financeiro.view|finance.cashflow.view|finance.receivable.view|finance.payable.view')->get('consolidated', [ConsolidatedFinancialController::class, 'index']);
});
Route::middleware('check.permission:financeiro.approve|finance.payable.settle')
    ->post('financial/batch-payment-approval', [FinancialAnalyticsController::class, 'approveBatchPayment']);

// ═══ Análise Comercial ════════════════════════════════════════
Route::prefix('sales')->middleware('check.permission:comercial.view')->group(function () {
    Route::get('quote-rentability/{quote}', [SalesAnalyticsController::class, 'quoteRentability']);
    Route::get('follow-up-queue', [SalesAnalyticsController::class, 'followUpQueue']);
    Route::get('loss-reasons', [SalesAnalyticsController::class, 'lossReasons']);
    Route::get('client-segmentation', [SalesAnalyticsController::class, 'clientSegmentation']);
    Route::get('upsell-suggestions/{customer}', [SalesAnalyticsController::class, 'upsellSuggestions']);
    Route::get('discount-requests', [SalesAnalyticsController::class, 'discountRequests']);
});

// ═══ Kardex de Produto ═══════════════════════════════════════
Route::middleware('check.permission:estoque.view')->group(function () {
    Route::get('products/{product}/kardex-overview', [ProductKardexController::class, 'index']);
    Route::get('products/{product}/kardex-overview/summary', [ProductKardexController::class, 'monthlySummary']);
});

// ═══ Rastreamento de Ferramentas ═════════════════════════════
Route::prefix('tools')->middleware('check.permission:estoque.manage')->group(function () {
    Route::get('checkouts', [ToolTrackingController::class, 'index']);
    Route::post('checkout', [ToolTrackingController::class, 'checkout']);
    Route::post('checkin/{checkout}', [ToolTrackingController::class, 'checkin']);
    Route::get('overdue', [ToolTrackingController::class, 'overdue']);
});

// ═══ IA & Análise ═══════════════════════════════════════════════
Route::prefix('ai')->middleware('check.permission:ai.analytics.view')->group(function () {
    Route::get('predictive-maintenance', [AIAnalyticsController::class, 'predictiveMaintenance']);
    Route::get('expense-ocr-analysis', [AIAnalyticsController::class, 'expenseOcrAnalysis']);
    Route::get('triage-suggestions', [AIAnalyticsController::class, 'triageSuggestions']);
    Route::get('sentiment-analysis', [AIAnalyticsController::class, 'sentimentAnalysis']);
    Route::get('dynamic-pricing', [AIAnalyticsController::class, 'dynamicPricing']);
    Route::get('financial-anomalies', [AIAnalyticsController::class, 'financialAnomalies']);
    Route::get('voice-commands', [AIAnalyticsController::class, 'voiceCommandSuggestions']);
    Route::get('natural-language-report', [AIAnalyticsController::class, 'naturalLanguageReport']);
    Route::get('customer-clustering', [AIAnalyticsController::class, 'customerClustering']);
    Route::get('equipment-image-analysis', [AIAnalyticsController::class, 'equipmentImageAnalysis']);
    Route::middleware('check.permission:reports.analytics.view')->get('demand-forecast', [AIAnalyticsController::class, 'demandForecast']);
    Route::middleware('check.permission:os.work_order.view')->get('route-optimization', [AIAnalyticsController::class, 'aiRouteOptimization']);
    Route::middleware('check.permission:service_calls.service_call.view')->get('smart-ticket-labeling', [AIAnalyticsController::class, 'smartTicketLabeling']);
    Route::middleware('check.permission:reports.analytics.view')->get('churn-prediction', [AIAnalyticsController::class, 'churnPrediction']);
    Route::middleware('check.permission:os.work_order.view')->get('service-summary/{workOrderId}', [AIAnalyticsController::class, 'serviceSummary']);

    // AI Conversational Assistant (Tool Calling)
    Route::post('chat', [AiAssistantController::class, 'chat']);
    Route::get('tools', [AiAssistantController::class, 'tools']);
});

// ═══ 75 Features — Novas Funcionalidades ═══════════════════════
$fc = FeaturesController::class;
$ac = AlertController::class;
$wc = WhatsappController::class;
$rc = RenegotiationController::class;
$wtc = WeightToolController::class;
$wfc = WorkOrderFieldController::class;
$qac = QualityAuditController::class;

// --- Calibração (Certificado ISO 17025) ---
Route::prefix('calibration')->middleware('check.permission:equipamentos.calibration.view')->group(function () use ($fc) {
    Route::get('/', [$fc, 'listCalibrations']);
    Route::get('{calibration}/readings', [$fc, 'getCalibrationReadings']);
    Route::middleware('check.permission:equipamentos.calibration.create')->group(function () use ($fc) {
        Route::post('equipment/{equipment}/draft', [$fc, 'createCalibrationDraft']);
        Route::put('{calibration}/wizard', [$fc, 'updateCalibrationWizard']);
        Route::post('{calibration}/readings', [$fc, 'storeCalibrationReadings']);
        Route::post('{calibration}/excentricity', [$fc, 'storeExcentricityTest']);
        Route::post('{calibration}/weights', [$fc, 'syncCalibrationWeights']);
        Route::post('{calibration}/generate-certificate', [$fc, 'generateCertificate']);
        Route::post('{calibration}/send-certificate-email', [$fc, 'sendCertificateByEmail']);
        Route::post('{calibration}/repeatability', [$fc, 'storeRepeatabilityTest']);
    });
    Route::get('equipment/{equipment}/prefill', [$fc, 'prefillCalibration']);
    Route::get('equipment/{equipment}/suggest-points', [$fc, 'suggestMeasurementPoints']);
    Route::post('calculate-ema', [$fc, 'calculateEma']);
    Route::get('{calibration}/validate-iso17025', [$fc, 'validateCalibrationIso17025']);
    Route::post('procedure-config', [$fc, 'getProcedureConfig']);
    Route::get('gravity', [$fc, 'getGravity']);
    Route::get('{calibration}/validate-weights', [$fc, 'validateCalibrationWeights']);
    Route::post('uncertainty-budget', [$fc, 'calculateUncertaintyBudget']);
});

// --- Templates de Certificado ---
Route::prefix('certificate-templates')->middleware('check.permission:equipamentos.calibration.view')->group(function () use ($fc) {
    Route::get('/', [$fc, 'indexCertificateTemplates']);
    Route::middleware('check.permission:admin.settings.manage')->group(function () use ($fc) {
        Route::post('/', [$fc, 'storeCertificateTemplate']);
        Route::put('{template}', [$fc, 'updateCertificateTemplate']);
        Route::delete('{template}', [$fc, 'destroyCertificateTemplate']);
    });
});

// --- WhatsApp ---
Route::prefix('whatsapp')->middleware('check.permission:admin.settings.manage')->group(function () use ($wc) {
    Route::get('config', [$wc, 'getWhatsappConfig']);
    Route::post('config', [$wc, 'saveWhatsappConfig']);
    Route::post('test', [$wc, 'testWhatsapp']);
    Route::post('send', [$wc, 'sendWhatsapp']);
});

// --- Alertas do Sistema ---
Route::prefix('alerts')->group(function () use ($ac) {
    Route::middleware('check.permission:platform.dashboard.view')->group(function () use ($ac) {
        Route::get('/', [$ac, 'indexAlerts']);
        Route::get('export', [$ac, 'exportAlerts']);
        Route::get('summary', [$ac, 'alertSummary']);
        Route::post('{alert}/acknowledge', [$ac, 'acknowledgeAlert']);
        Route::post('{alert}/resolve', [$ac, 'resolveAlert']);
        Route::post('{alert}/dismiss', [$ac, 'dismissAlert']);
    });
    Route::middleware('check.permission:admin.settings.manage')->group(function () use ($ac) {
        Route::post('run-engine', [$ac, 'runAlertEngine']);
        Route::get('configs', [$ac, 'indexAlertConfigs']);
        Route::put('configs/{alertType}', [$ac, 'updateAlertConfig']);
    });
});

// --- Financeiro: Renegociação + Recibos + Régua de Cobrança ---
Route::prefix('renegotiations')->middleware('check.permission:financeiro.accounts_receivable.view')->group(function () use ($rc) {
    Route::get('/', [$rc, 'indexRenegotiations']);
    Route::middleware('check.permission:financeiro.accounts_receivable.create')->post('/', [$rc, 'storeRenegotiation']);
    Route::middleware('check.permission:financeiro.approve')->post('{renegotiation}/approve', [$rc, 'approveRenegotiation']);
    Route::middleware('check.permission:financeiro.approve')->post('{renegotiation}/reject', [$rc, 'rejectRenegotiation']);
});
Route::middleware('check.permission:financeiro.payment.create')->post('payments/{payment}/receipt', [$rc, 'generateReceipt']);
Route::middleware('check.permission:admin.settings.manage')->post('collection/run-engine', [$rc, 'runCollectionEngine']);
Route::middleware('check.permission:finance.receivable.view')->get('collection/summary', [$rc, 'collectionSummary']);
Route::middleware('check.permission:finance.receivable.view')->get('collection/actions', [$rc, 'collectionActions']);
Route::middleware('check.permission:admin.settings.manage')->post('collection/run', [$rc, 'runCollectionEngine']);

// --- Dashboard NPS ---
Route::middleware('check.permission:customer.nps.view')->get('dashboard-nps', [$wfc, 'dashboardNps']);

// --- WhatsApp Logs ---
Route::middleware('check.permission:whatsapp.log.view')->get('whatsapp/logs', [$wc, 'whatsappLogs']);

// --- Logística: Checkin/Checkout ---
Route::middleware('check.permission:os.work_order.change_status')->group(function () use ($wfc) {
    Route::post('work-orders/{workOrder}/checkin', [$wfc, 'checkinWorkOrder']);
    Route::post('work-orders/{workOrder}/checkout', [$wfc, 'checkoutWorkOrder']);
});
Route::middleware('check.permission:equipments.equipment.update')->post('equipments/{equipment}/generate-qr', [$wfc, 'generateEquipmentQr']);

// --- Qualidade: Auditorias internas (delega para QualityAuditController existente) ---
Route::prefix('quality-audits')->middleware('check.permission:quality.audit.view')->group(function () use ($qac) {
    Route::get('/', [$qac, 'index']);
    Route::get('{qualityAudit}', [$qac, 'show']);
    Route::middleware('check.permission:quality.audit.create')->post('/', [$qac, 'store']);
    Route::middleware('check.permission:quality.audit.update')->put('{qualityAudit}', [$qac, 'update']);
    Route::middleware('check.permission:quality.audit.update')->put('{qualityAudit}/items/{itemId}', [$qac, 'updateItem']);
});

// --- Qualidade: Documentos controlados ---
Route::prefix('iso-documents')->middleware('check.permission:quality.document.view')->group(function () use ($fc) {
    Route::get('/', [$fc, 'indexDocuments']);
    Route::get('{document}/download', [$fc, 'downloadDocument']);
    Route::middleware('check.permission:quality.document.create')->post('/', [$fc, 'storeDocument']);
    Route::middleware('check.permission:quality.document.update')->put('{document}', [$fc, 'updateDocument']);
    Route::middleware(['check.permission:quality.document.create', 'throttle:tenant-uploads'])->post('{document}/upload', [$fc, 'uploadDocumentFile']);
    Route::middleware('check.permission:quality.document.approve')->post('{document}/approve', [$fc, 'approveDocument']);
});

// --- Qualidade: Revisão pela direção ---
$mrc = ManagementReviewController::class;
Route::prefix('management-reviews')->middleware('check.permission:quality.management_review.view')->group(function () use ($mrc) {
    Route::get('/', [$mrc, 'index']);
    Route::get('dashboard', [$mrc, 'dashboard']);
    Route::get('{management_review}', [$mrc, 'show']);
    Route::middleware('check.permission:quality.management_review.create')->post('/', [$mrc, 'store']);
    Route::middleware('check.permission:quality.management_review.update')->put('{management_review}', [$mrc, 'update']);
    Route::middleware('check.permission:quality.management_review.update')->delete('{management_review}', [$mrc, 'destroy']);
    Route::middleware('check.permission:quality.management_review.create')->post('{management_review}/actions', [$mrc, 'storeAction']);
    Route::middleware('check.permission:quality.management_review.update')->put('actions/{action}', [$mrc, 'updateAction']);
});

// --- Ferramentas: Calibração ---
Route::prefix('tool-calibrations')->middleware('check.permission:estoque.manage')->group(function () use ($wtc) {
    Route::get('/', [$wtc, 'indexToolCalibrations']);
    Route::get('expiring', [$wtc, 'expiringToolCalibrations']);
    Route::post('/', [$wtc, 'storeToolCalibration']);
    Route::put('{calibration}', [$wtc, 'updateToolCalibration']);
    Route::delete('{calibration}', [$wtc, 'destroyToolCalibration']);
});
