<?php

use App\Http\Controllers\Api\V1\Analytics\AIAnalyticsController;
use App\Http\Controllers\Api\V1\Analytics\AiAssistantController;
use App\Http\Controllers\Api\V1\Analytics\AnalyticsController;
use App\Http\Controllers\Api\V1\Analytics\AnalyticsDatasetController;
use App\Http\Controllers\Api\V1\Analytics\BiAnalyticsController;
use App\Http\Controllers\Api\V1\Analytics\DataExportJobController;
use App\Http\Controllers\Api\V1\Analytics\EmbeddedDashboardController;
use App\Http\Controllers\Api\V1\Analytics\ExpenseAnalyticsController;
use App\Http\Controllers\Api\V1\Analytics\FinancialAnalyticsController;
use App\Http\Controllers\Api\V1\Analytics\FleetAnalyticsController;
use App\Http\Controllers\Api\V1\Analytics\HRAnalyticsController;
use App\Http\Controllers\Api\V1\Analytics\PeopleAnalyticsController;
use App\Http\Controllers\Api\V1\Analytics\QualityAnalyticsController;
use App\Http\Controllers\Api\V1\Analytics\SalesAnalyticsController;
use App\Http\Controllers\Api\V1\Financial\ConsolidatedFinancialController;
use Illuminate\Support\Facades\Route;

// ═ ═ ═  Financial Analytics ═ ═ ═ ═ ═ ═ ═ ═ ═ ═ ═ ═ ═ ═ ═ ═ ═ ═ ═
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

// ═ ═ ═  Sales Analytics ═ ═ ═ ═ ═ ═ ═ ═ ═ ═ ═ ═ ═ ═ ═ ═ ═ ═ ═ ═ ═
Route::prefix('sales')->middleware('check.permission:comercial.view')->group(function () {
    Route::get('quote-rentability/{quote}', [SalesAnalyticsController::class, 'quoteRentability']);
    Route::get('follow-up-queue', [SalesAnalyticsController::class, 'followUpQueue']);
    Route::get('loss-reasons', [SalesAnalyticsController::class, 'lossReasons']);
    Route::get('client-segmentation', [SalesAnalyticsController::class, 'clientSegmentation']);
    Route::get('upsell-suggestions/{customer}', [SalesAnalyticsController::class, 'upsellSuggestions']);
    Route::get('discount-requests', [SalesAnalyticsController::class, 'discountRequests']);
});

// ═ ═ ═  IA & Análise ═ ═ ═ ═ ═ ═ ═ ═ ═ ═ ═ ═ ═ ═ ═ ═ ═ ═ ═ ═ ═ ═ ═
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

    // AI Conversational Assistant
    Route::post('chat', [AiAssistantController::class, 'chat']);
    Route::get('tools', [AiAssistantController::class, 'tools']);
});

// ═ ═ ═  Geral / Overview Analytics ═ ═ ═ ═ ═ ═ ═ ═ ═ ═ ═ ═ ═ ═ ═ ═
Route::prefix('analytics')->group(function () {
    Route::get('overview', [AnalyticsController::class, 'executiveSummary']);
    Route::get('executive-summary', [AnalyticsController::class, 'executiveSummary']);
    Route::get('trends', [AnalyticsController::class, 'trends']);
    Route::get('forecast', [AnalyticsController::class, 'forecast']);
    Route::get('anomalies', [AnalyticsController::class, 'anomalies']);
    Route::get('nl-query', [AnalyticsController::class, 'nlQuery']);

    Route::get('datasets', [AnalyticsDatasetController::class, 'index'])
        ->middleware('check.permission:analytics.dataset.view');
    Route::post('datasets', [AnalyticsDatasetController::class, 'store'])
        ->middleware('check.permission:analytics.dataset.manage');
    Route::get('datasets/{dataset}', [AnalyticsDatasetController::class, 'show'])
        ->middleware('check.permission:analytics.dataset.view');
    Route::put('datasets/{dataset}', [AnalyticsDatasetController::class, 'update'])
        ->middleware('check.permission:analytics.dataset.manage');
    Route::delete('datasets/{dataset}', [AnalyticsDatasetController::class, 'destroy'])
        ->middleware('check.permission:analytics.dataset.manage');
    Route::post('datasets/{dataset}/preview', [AnalyticsDatasetController::class, 'preview'])
        ->middleware('check.permission:analytics.dataset.view');

    Route::get('export-jobs', [DataExportJobController::class, 'index'])
        ->middleware('check.permission:analytics.export.view');
    Route::post('export-jobs', [DataExportJobController::class, 'store'])
        ->middleware('check.permission:analytics.export.create');
    Route::post('export-jobs/{job}/retry', [DataExportJobController::class, 'retry'])
        ->middleware('check.permission:analytics.export.create');
    Route::post('export-jobs/{job}/cancel', [DataExportJobController::class, 'cancel'])
        ->middleware('check.permission:analytics.export.create');
    Route::get('export-jobs/{job}/download', [DataExportJobController::class, 'download'])
        ->middleware('check.permission:analytics.export.download');

    Route::get('dashboards', [EmbeddedDashboardController::class, 'index'])
        ->middleware('check.permission:analytics.dashboard.view');
    Route::post('dashboards', [EmbeddedDashboardController::class, 'store'])
        ->middleware('check.permission:analytics.dashboard.manage');
    Route::get('dashboards/{dashboard}', [EmbeddedDashboardController::class, 'show'])
        ->middleware('check.permission:analytics.dashboard.view');
    Route::put('dashboards/{dashboard}', [EmbeddedDashboardController::class, 'update'])
        ->middleware('check.permission:analytics.dashboard.manage');
    Route::delete('dashboards/{dashboard}', [EmbeddedDashboardController::class, 'destroy'])
        ->middleware('check.permission:analytics.dashboard.manage');
});

// ═ ═ ═  BI Analytics (KPIs, Reports, Comparison) ═ ═ ═ ═ ═ ═ ═ ═ ═
Route::middleware('check.permission:reports.analytics.view')->group(function () {
    Route::prefix('bi-analytics')->group(function () {
        Route::get('kpis/realtime', [BiAnalyticsController::class, 'realtimeKpis']);
        Route::get('profitability', [BiAnalyticsController::class, 'profitabilityByOS']);
        Route::get('anomalies', [BiAnalyticsController::class, 'anomalyDetection']);
        Route::get('exports/scheduled', [BiAnalyticsController::class, 'scheduledExports']);
        Route::post('exports/scheduled', [BiAnalyticsController::class, 'createScheduledExport']);
        Route::delete('exports/scheduled/{id}', [BiAnalyticsController::class, 'deleteScheduledExport']);
        Route::get('comparison', [BiAnalyticsController::class, 'periodComparison']);
    });

    // Aliases legados mantidos para clientes que ainda chamam sem o prefixo do bounded context.
    Route::get('kpis/realtime', [BiAnalyticsController::class, 'realtimeKpis']);
    Route::get('profitability', [BiAnalyticsController::class, 'profitabilityByOS']);
    Route::get('anomalies', [BiAnalyticsController::class, 'anomalyDetection']);
    Route::get('exports/scheduled', [BiAnalyticsController::class, 'scheduledExports']);
    Route::post('exports/scheduled', [BiAnalyticsController::class, 'createScheduledExport']);
    Route::delete('exports/scheduled/{id}', [BiAnalyticsController::class, 'deleteScheduledExport']);
    Route::get('comparison', [BiAnalyticsController::class, 'periodComparison']);
});

// ═ ═ ═  People Analytics ═ ═ ═ ═ ═ ═ ═ ═ ═ ═ ═ ═ ═ ═ ═ ═ ═ ═ ═ ═ ═
Route::get('hr/analytics/dashboard', [PeopleAnalyticsController::class, 'dashboard']);

// ═ ═ ═  Outras Análises Consolidadas ═ ═ ═ ═ ═ ═ ═ ═ ═ ═ ═ ═ ═ ═
Route::middleware('check.permission:fleet.vehicle.view')->get('fleet/analytics', [FleetAnalyticsController::class, 'analyticsFleet']);
Route::middleware('check.permission:hr.schedule.view')->get('hr/analytics', [HRAnalyticsController::class, 'analyticsHr']);
Route::middleware('check.permission:quality.dashboard.view')->get('quality/analytics', [QualityAnalyticsController::class, 'analyticsQuality']);
Route::middleware('check.permission:quality.dashboard.view')->get('analytics/quality/analytics', [QualityAnalyticsController::class, 'analyticsQuality']);
Route::middleware('check.permission:expenses.expense.view')->get('expense-analytics', [ExpenseAnalyticsController::class, 'analytics']);
