<?php

/**
 * Routes: Financeiro, Frota, Relatorios, Dashboard, Auvo
 * Extracted from api.php - Phase 10 Modularization
 * Original lines: 561-913
 */

use App\Http\Controllers\Api\V1\AuvoExportController;
use App\Http\Controllers\Api\V1\AuvoImportController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\Financial\AccountPayableCategoryController;
use App\Http\Controllers\Api\V1\Financial\AccountPayableController;
use App\Http\Controllers\Api\V1\Financial\AccountReceivableController;
use App\Http\Controllers\Api\V1\Financial\CommissionCampaignController;
use App\Http\Controllers\Api\V1\Financial\CommissionController;
use App\Http\Controllers\Api\V1\Financial\CommissionDashboardController;
use App\Http\Controllers\Api\V1\Financial\CommissionDisputeController;
use App\Http\Controllers\Api\V1\Financial\CommissionGoalController;
use App\Http\Controllers\Api\V1\Financial\CommissionRuleController;
use App\Http\Controllers\Api\V1\Financial\ExpenseController;
use App\Http\Controllers\Api\V1\Financial\FinancialExportController;
use App\Http\Controllers\Api\V1\Financial\FinancialLookupController;
use App\Http\Controllers\Api\V1\Financial\FuelingLogController;
use App\Http\Controllers\Api\V1\Financial\PaymentController;
use App\Http\Controllers\Api\V1\Financial\RecurringCommissionController;
use App\Http\Controllers\Api\V1\Fleet\FleetAdvancedController;
use App\Http\Controllers\Api\V1\Fleet\FuelLogController;
use App\Http\Controllers\Api\V1\Fleet\GpsTrackingController;
use App\Http\Controllers\Api\V1\Fleet\TollIntegrationController;
use App\Http\Controllers\Api\V1\Fleet\VehicleAccidentController;
use App\Http\Controllers\Api\V1\Fleet\VehicleInspectionController;
use App\Http\Controllers\Api\V1\Fleet\VehicleInsuranceController;
use App\Http\Controllers\Api\V1\Fleet\VehiclePoolController;
use App\Http\Controllers\Api\V1\Fleet\VehicleTireController;
use App\Http\Controllers\Api\V1\ImportController;
use App\Http\Controllers\Api\V1\InvoiceController;
use App\Http\Controllers\Api\V1\PaymentMethodController;
use App\Http\Controllers\Api\V1\QuoteController;
use App\Http\Controllers\Api\V1\ReportController;
use Illuminate\Support\Facades\Route;

// Financeiro â,? Contas a Receber
Route::middleware('check.permission:finance.receivable.view')->group(function () {
    Route::get('accounts-receivable', [AccountReceivableController::class, 'index']);
    Route::get('accounts-receivable/{account_receivable}', [AccountReceivableController::class, 'show']);
    Route::get('accounts-receivable-summary', [AccountReceivableController::class, 'summary']);
});
Route::middleware('check.permission:finance.receivable.create')->group(function () {
    Route::post('accounts-receivable', [AccountReceivableController::class, 'store']);
    Route::post('accounts-receivable/generate-from-os', [AccountReceivableController::class, 'generateFromWorkOrder']);
    Route::post('accounts-receivable/installments', [AccountReceivableController::class, 'generateInstallments']);
});
Route::middleware('check.permission:finance.receivable.settle')->post('accounts-receivable/{account_receivable}/pay', [AccountReceivableController::class, 'pay']);
Route::middleware('check.permission:finance.receivable.update')->put('accounts-receivable/{account_receivable}', [AccountReceivableController::class, 'update']);
Route::middleware('check.permission:finance.receivable.delete')->delete('accounts-receivable/{account_receivable}', [AccountReceivableController::class, 'destroy']);

// Financeiro â,? Contas a Pagar
Route::middleware('check.permission:finance.payable.view')->group(function () {
    Route::get('accounts-payable', [AccountPayableController::class, 'index']);
    Route::get('accounts-payable/{account_payable}', [AccountPayableController::class, 'show']);
    Route::get('accounts-payable-summary', [AccountPayableController::class, 'summary']);
    Route::get('accounts-payable-export', [AccountPayableController::class, 'export']);
});
Route::middleware('check.permission:finance.payable.create')->post('accounts-payable', [AccountPayableController::class, 'store']);
Route::middleware('check.permission:finance.payable.settle')->post('accounts-payable/{account_payable}/pay', [AccountPayableController::class, 'pay']);
Route::middleware('check.permission:finance.payable.update')->put('accounts-payable/{account_payable}', [AccountPayableController::class, 'update']);
Route::middleware('check.permission:finance.payable.delete')->delete('accounts-payable/{account_payable}', [AccountPayableController::class, 'destroy']);
Route::middleware('check.permission:finance.payable.create|finance.payable.update')->get('financial/lookups/suppliers', [FinancialLookupController::class, 'suppliers']);
Route::middleware('check.permission:finance.receivable.create|finance.receivable.update')->get('financial/lookups/customers', [FinancialLookupController::class, 'customers']);
Route::middleware('check.permission:finance.receivable.create|expenses.expense.create|expenses.expense.update')->get('financial/lookups/work-orders', [FinancialLookupController::class, 'workOrders']);
Route::middleware('check.permission:finance.payable.create|finance.payable.update|finance.payable.settle|finance.receivable.create|finance.receivable.update|finance.receivable.settle|financial.fund_transfer.create')->get('financial/lookups/payment-methods', [FinancialLookupController::class, 'paymentMethods']);
Route::middleware('check.permission:finance.receivable.create|finance.payable.create|financial.fund_transfer.create')->get('financial/lookups/bank-accounts', [FinancialLookupController::class, 'bankAccounts']);
Route::middleware('check.permission:finance.payable.create|finance.payable.update|financeiro.payment.create')->get('financial/lookups/supplier-contract-payment-frequencies', [FinancialLookupController::class, 'supplierContractPaymentFrequencies']);

// Exportação Financeira (#27) ?" ambos os tipos via ?type=receivable|payable
Route::middleware('check.permission:finance.receivable.view|finance.payable.view')->group(function () {
    Route::get('financial/export/ofx', [FinancialExportController::class, 'ofx']);
    Route::get('financial/export/csv', [FinancialExportController::class, 'csv']);
});

// Comissões
Route::middleware('check.permission:commissions.rule.view')->group(function () {
    Route::get('commission-rules', [CommissionRuleController::class, 'rules']);
    Route::get('commission-rules/{commission_rule}', [CommissionRuleController::class, 'showRule']);
    Route::get('commission-users', [CommissionRuleController::class, 'users']);
    Route::get('commission-calculation-types', [CommissionRuleController::class, 'calculationTypes']);
});

Route::middleware('check.permission:commissions.event.view')->group(function () {
    Route::get('commission-events', [CommissionController::class, 'events']);
    Route::get('commission-summary', [CommissionController::class, 'summary']);
});

Route::middleware('check.permission:commissions.settlement.view')->group(function () {
    Route::get('commission-settlements', [CommissionController::class, 'settlements']);
});
Route::middleware('check.permission:commissions.rule.create')->group(function () {
    Route::post('commission-rules', [CommissionRuleController::class, 'storeRule']);
    Route::post('commission-events/generate', [CommissionController::class, 'generateForWorkOrder']);
    Route::post('commission-events/batch-generate', [CommissionController::class, 'batchGenerateForWorkOrders']);
    Route::post('commission-simulate', [CommissionController::class, 'simulate']);
});
Route::middleware('check.permission:commissions.rule.update')->group(function () {
    Route::put('commission-rules/{commission_rule}', [CommissionRuleController::class, 'updateRule']);
});
Route::middleware('check.permission:commissions.event.update')->group(function () {
    Route::put('commission-events/{commission_event}/status', [CommissionController::class, 'updateEventStatus']);
});
Route::middleware('check.permission:commissions.rule.delete')->delete('commission-rules/{commission_rule}', [CommissionRuleController::class, 'destroyRule']);
Route::middleware('check.permission:commissions.settlement.create')->group(function () {
    Route::post('commission-settlements/close', [CommissionController::class, 'closeSettlement']);
});
Route::middleware('check.permission:commissions.settlement.update')->group(function () {
    Route::post('commission-settlements/{commission_settlement}/pay', [CommissionController::class, 'paySettlement']);
    Route::post('commission-settlements/{commission_settlement}/reopen', [CommissionController::class, 'reopenSettlement']);
});
// GAP-25: Settlement approval workflow (Nayara closes â†’ Roldão approves)
Route::middleware('check.permission:commissions.settlement.approve')->group(function () {
    Route::post('commission-settlements/{commission_settlement}/approve', [CommissionController::class, 'approveSettlement']);
    Route::post('commission-settlements/{commission_settlement}/reject', [CommissionController::class, 'rejectSettlement']);
});
// Batch, Splits, Exports
Route::middleware('check.permission:commissions.event.update')->group(function () {
    Route::post('commission-events/batch-status', [CommissionController::class, 'batchUpdateStatus']);
    Route::post('commission-events/{commission_event}/splits', [CommissionController::class, 'splitEvent']);
});
Route::middleware('check.permission:commissions.event.view')->group(function () {
    Route::get('commission-events/export', [CommissionController::class, 'exportEvents']);
    Route::get('commission-events/{commission_event}/splits', [CommissionController::class, 'eventSplits']);
});

Route::middleware('check.permission:commissions.settlement.view')->group(function () {
    Route::get('commission-settlements/balance-summary', [CommissionController::class, 'balanceSummary']);
    Route::get('commission-settlements/export', [CommissionController::class, 'exportSettlements']);
    Route::get('commission-statement/pdf', [CommissionController::class, 'downloadStatement']);
});
// Dashboard Analítico
Route::middleware('check.permission:commissions.rule.view')->group(function () {
    Route::get('commission-dashboard/overview', [CommissionDashboardController::class, 'overview']);
    Route::get('commission-dashboard/ranking', [CommissionDashboardController::class, 'ranking']);
    Route::get('commission-dashboard/evolution', [CommissionDashboardController::class, 'evolution']);
    Route::get('commission-dashboard/by-rule', [CommissionDashboardController::class, 'byRule']);
    Route::get('commission-dashboard/by-role', [CommissionDashboardController::class, 'byRole']);
});
// Contestações / Disputas
Route::middleware('check.permission:commissions.dispute.view')->group(function () {
    Route::get('commission-disputes', [CommissionDisputeController::class, 'index']);
});
Route::middleware('check.permission:commissions.dispute.create')->group(function () {
    Route::post('commission-disputes', [CommissionDisputeController::class, 'store']);
});
Route::middleware('check.permission:commissions.dispute.resolve')->group(function () {
    Route::match(['post', 'put'], 'commission-disputes/{dispute}/resolve', [CommissionDisputeController::class, 'resolve']);
});
Route::middleware('check.permission:commissions.dispute.view')->group(function () {
    Route::get('commission-disputes/{dispute}', [CommissionDisputeController::class, 'show']);
});
Route::middleware('check.permission:commissions.dispute.delete')->group(function () {
    Route::delete('commission-disputes/{dispute}', [CommissionDisputeController::class, 'destroy']);
});
// Metas de Vendas
Route::middleware('check.permission:commissions.goal.view')->group(function () {
    Route::get('commission-goals', [CommissionGoalController::class, 'index']);
});
Route::middleware('check.permission:commissions.goal.create')->group(function () {
    Route::post('commission-goals', [CommissionGoalController::class, 'store']);
    Route::post('commission-goals/{goal}/refresh', [CommissionGoalController::class, 'refreshAchievement']);
});
Route::middleware('check.permission:commissions.goal.update')->put('commission-goals/{goal}', [CommissionGoalController::class, 'update']);
Route::middleware('check.permission:commissions.goal.delete')->delete('commission-goals/{goal}', [CommissionGoalController::class, 'destroy']);
// Campanhas / Aceleradores
Route::middleware('check.permission:commissions.campaign.view')->group(function () {
    Route::get('commission-campaigns', [CommissionCampaignController::class, 'index']);
});
Route::middleware('check.permission:commissions.campaign.create')->post('commission-campaigns', [CommissionCampaignController::class, 'store']);
Route::middleware('check.permission:commissions.campaign.update')->put('commission-campaigns/{campaign}', [CommissionCampaignController::class, 'update']);
Route::middleware('check.permission:commissions.campaign.delete')->delete('commission-campaigns/{campaign}', [CommissionCampaignController::class, 'destroy']);
// Comissões Recorrentes
Route::middleware('check.permission:commissions.recurring.view')->group(function () {
    Route::get('recurring-commissions', [RecurringCommissionController::class, 'index']);
});
Route::middleware('check.permission:commissions.recurring.create')->group(function () {
    Route::post('recurring-commissions', [RecurringCommissionController::class, 'store']);
    Route::post('recurring-commissions/process-monthly', [RecurringCommissionController::class, 'processMonthly']);
    Route::post('recurring-commissions/process', [RecurringCommissionController::class, 'processMonthly']); // compat alias
});
Route::middleware('check.permission:commissions.recurring.update')->put('recurring-commissions/{id}/status', [RecurringCommissionController::class, 'updateStatus']);
Route::middleware('check.permission:commissions.recurring.delete')->delete('recurring-commissions/{id}', [RecurringCommissionController::class, 'destroy']);
// Comissões pessoais do técnico/vendedor (dados próprios — sem permissão extra)
Route::get('my/commission-events', [CommissionController::class, 'myEvents']);
Route::get('my/commission-settlements', [CommissionController::class, 'mySettlements']);
Route::get('my/commission-statements/download', [CommissionController::class, 'myStatementDownload']);
Route::get('my/commission-summary', [CommissionController::class, 'mySummary']);
Route::get('my/commission-disputes', [CommissionDisputeController::class, 'myIndex']);

// Despesas
Route::middleware('check.permission:expenses.expense.view')->group(function () {
    Route::get('expenses', [ExpenseController::class, 'index']);
    Route::get('expenses/{expense}', [ExpenseController::class, 'show']);
    Route::get('expenses/{expense}/history', [ExpenseController::class, 'history']);
    Route::get('expense-categories', [ExpenseController::class, 'categories']);
    Route::get('expense-summary', [ExpenseController::class, 'summary']);
});
Route::middleware('check.permission:expenses.expense.create')->group(function () {
    Route::post('expenses', [ExpenseController::class, 'store']);
    Route::post('expenses/{expense}/duplicate', [ExpenseController::class, 'duplicate']);
    Route::post('expense-categories', [ExpenseController::class, 'storeCategory']);
});
Route::middleware('check.permission:expenses.expense.update')->group(function () {
    Route::put('expenses/{expense}', [ExpenseController::class, 'update']);
    Route::put('expense-categories/{category}', [ExpenseController::class, 'updateCategory']);
    Route::put('expense-categories/batch-limits', [ExpenseController::class, 'batchUpdateLimits']);
});
Route::middleware('check.permission:expenses.expense.approve')->put('expenses/{expense}/status', [ExpenseController::class, 'updateStatus']);
Route::middleware('check.permission:expenses.expense.approve')->post('expenses/{expense}/approve', [ExpenseController::class, 'updateStatus']);
Route::middleware('check.permission:expenses.expense.approve')->post('expenses/batch-status', [ExpenseController::class, 'batchUpdateStatus']);
// GAP-20: Expense review step (conferência before approval)
Route::middleware('check.permission:expenses.expense.review')->post('expenses/{expense}/review', [ExpenseController::class, 'review']);
Route::middleware('check.permission:expenses.expense.view')->get('expenses-export', [ExpenseController::class, 'export']);

Route::middleware('check.permission:expenses.expense.delete')->group(function () {
    Route::delete('expenses/{expense}', [ExpenseController::class, 'destroy']);
    Route::delete('expense-categories/{category}', [ExpenseController::class, 'destroyCategory']);
});

// GAP-09: Fueling Logs (motorista)
Route::middleware('check.permission:expenses.fueling_log.view')->group(function () {
    Route::get('fueling-logs', [FuelingLogController::class, 'index']);
    Route::get('fueling-logs/{fuelingLog}', [FuelingLogController::class, 'show']);
});
Route::middleware('check.permission:expenses.fueling_log.create')->post('fueling-logs', [FuelingLogController::class, 'store']);
Route::middleware('check.permission:expenses.fueling_log.update')->put('fueling-logs/{fuelingLog}', [FuelingLogController::class, 'update']);
Route::middleware('check.permission:expenses.fueling_log.approve')->post('fueling-logs/{fuelingLog}/approve', [FuelingLogController::class, 'approve']);
Route::middleware('check.permission:expenses.fueling_log.update')->post('fueling-logs/{fuelingLog}/resubmit', [FuelingLogController::class, 'resubmit']);
Route::middleware('check.permission:expenses.fueling_log.delete')->delete('fueling-logs/{fuelingLog}', [FuelingLogController::class, 'destroy']);

// --- Módulo Frota Avançado ---
Route::middleware('check.permission:fleet.management')->group(function () {
    // Gestão de Pneus
    Route::get('fleet/tires', [VehicleTireController::class, 'index']);
    Route::post('fleet/tires', [VehicleTireController::class, 'store']);
    Route::get('fleet/tires/{tire}', [VehicleTireController::class, 'show']);
    Route::put('fleet/tires/{tire}', [VehicleTireController::class, 'update']);
    Route::delete('fleet/tires/{tire}', [VehicleTireController::class, 'destroy']);

    // Gestão de Abastecimento Avançado (API unificada)
    Route::middleware('check.permission:fleet.view')->get('fleet/fuel-logs', [FuelLogController::class, 'index']);
    Route::middleware('check.permission:fleet.management')->post('fleet/fuel-logs', [FuelLogController::class, 'store']);
    Route::middleware('check.permission:fleet.view')->get('fleet/fuel-logs/{log}', [FuelLogController::class, 'show']);
    Route::middleware('check.permission:fleet.management')->put('fleet/fuel-logs/{log}', [FuelLogController::class, 'update']);
    Route::middleware('check.permission:fleet.management')->delete('fleet/fuel-logs/{log}', [FuelLogController::class, 'destroy']);

    // Pool de Veículos
    Route::middleware('check.permission:fleet.view')->get('fleet/pool-requests', [VehiclePoolController::class, 'index']);
    Route::middleware('check.permission:fleet.management')->post('fleet/pool-requests', [VehiclePoolController::class, 'store']);
    Route::middleware('check.permission:fleet.view')->get('fleet/pool-requests/{request}', [VehiclePoolController::class, 'show']);
    Route::middleware('check.permission:fleet.management')->patch('fleet/pool-requests/{request}/status', [VehiclePoolController::class, 'updateStatus']);
    Route::middleware('check.permission:fleet.management')->delete('fleet/pool-requests/{request}', [VehiclePoolController::class, 'destroy']);

    // Gestão de Acidentes
    Route::middleware('check.permission:fleet.view')->get('fleet/accidents', [VehicleAccidentController::class, 'index']);
    Route::middleware('check.permission:fleet.management')->post('fleet/accidents', [VehicleAccidentController::class, 'store']);
    Route::middleware('check.permission:fleet.view')->get('fleet/accidents/{accident}', [VehicleAccidentController::class, 'show']);
    Route::middleware('check.permission:fleet.management')->put('fleet/accidents/{accident}', [VehicleAccidentController::class, 'update']);
    Route::middleware('check.permission:fleet.management')->delete('fleet/accidents/{accident}', [VehicleAccidentController::class, 'destroy']);

    // Inspeções / Checklists Móveis
    Route::middleware('check.permission:fleet.view')->get('fleet/inspections', [VehicleInspectionController::class, 'index']);
    Route::middleware('check.permission:fleet.inspection.create|fleet.management')->post('fleet/inspections', [VehicleInspectionController::class, 'store']);
    Route::middleware('check.permission:fleet.view')->get('fleet/inspections/{inspection}', [VehicleInspectionController::class, 'show']);
    Route::middleware('check.permission:fleet.management')->put('fleet/inspections/{inspection}', [VehicleInspectionController::class, 'update']);
    Route::middleware('check.permission:fleet.management')->delete('fleet/inspections/{inspection}', [VehicleInspectionController::class, 'destroy']);

    // Seguros de Frota
    Route::middleware('check.permission:fleet.view')->get('fleet/insurances', [VehicleInsuranceController::class, 'index']);
    Route::middleware('check.permission:fleet.management')->post('fleet/insurances', [VehicleInsuranceController::class, 'store']);
    Route::middleware('check.permission:fleet.view')->get('fleet/insurances/alerts', [VehicleInsuranceController::class, 'alerts']);
    Route::middleware('check.permission:fleet.view')->get('fleet/insurances/{insurance}', [VehicleInsuranceController::class, 'show']);
    Route::middleware('check.permission:fleet.management')->put('fleet/insurances/{insurance}', [VehicleInsuranceController::class, 'update']);
    Route::middleware('check.permission:fleet.management')->delete('fleet/insurances/{insurance}', [VehicleInsuranceController::class, 'destroy']);

    // Dashboard Avançado & Ferramentas
    Route::middleware('check.permission:fleet.view')->get('fleet/dashboard', [FleetAdvancedController::class, 'dashboard']);
    Route::middleware('check.permission:fleet.view')->post('fleet/fuel-comparison', [FleetAdvancedController::class, 'fuelComparison']);
    Route::middleware('check.permission:fleet.view')->post('fleet/trip-simulation', [FleetAdvancedController::class, 'tripSimulation']);
    Route::middleware('check.permission:fleet.view')->get('fleet/driver-score/{driverId}', [FleetAdvancedController::class, 'driverScore']);
    Route::middleware('check.permission:fleet.view')->get('fleet/driver-ranking', [FleetAdvancedController::class, 'driverRanking']);

    // GPS em Tempo Real
    Route::middleware('check.permission:fleet.view')->get('fleet/gps/live', [GpsTrackingController::class, 'livePositions']);
    Route::middleware('check.permission:fleet.view')->post('fleet/gps/update', [GpsTrackingController::class, 'updatePosition']);
    Route::middleware('check.permission:fleet.view')->get('fleet/gps/history/{vehicleId}', [GpsTrackingController::class, 'history']);

    // Integração de Pedágio
    Route::middleware('check.permission:fleet.view')->get('fleet/tolls', [TollIntegrationController::class, 'index']);
    Route::middleware('check.permission:fleet.management')->post('fleet/tolls', [TollIntegrationController::class, 'store']);
    Route::middleware('check.permission:fleet.view')->get('fleet/tolls/summary', [TollIntegrationController::class, 'summary']);
    Route::middleware('check.permission:fleet.management')->delete('fleet/tolls/{id}', [TollIntegrationController::class, 'destroy']);
});

// Pagamentos
Route::middleware('check.permission:finance.receivable.view|finance.payable.view')->group(function () {
    Route::get('payments', [PaymentController::class, 'index']);
    Route::get('payments-summary', [PaymentController::class, 'summary']);
});
Route::middleware('check.permission:finance.receivable.settle|finance.payable.settle')->delete('payments/{payment}', [PaymentController::class, 'destroy']);

// Faturamento / NF
Route::middleware('check.permission:finance.receivable.view')->group(function () {
    Route::get('invoices', [InvoiceController::class, 'index']);
    Route::get('invoices/metadata', [InvoiceController::class, 'metadata']);
    Route::get('invoices/{invoice}', [InvoiceController::class, 'show']);
});
Route::middleware('check.permission:finance.receivable.create')->post('invoices/batch', [InvoiceController::class, 'storeBatch']);
Route::middleware('check.permission:finance.receivable.create')->post('invoices', [InvoiceController::class, 'store']);
Route::middleware('check.permission:finance.receivable.update')->put('invoices/{invoice}', [InvoiceController::class, 'update']);
Route::middleware('check.permission:finance.receivable.delete')->delete('invoices/{invoice}', [InvoiceController::class, 'destroy']);

// Categorias de Contas a Pagar (editáveis)
Route::middleware('check.permission:finance.payable.view')->get('account-payable-categories', [AccountPayableCategoryController::class, 'index']);
Route::middleware('check.permission:finance.payable.view')->get('accounts-payable-categories', [AccountPayableCategoryController::class, 'index']); // compat
Route::middleware('check.permission:finance.payable.create')->post('account-payable-categories', [AccountPayableCategoryController::class, 'store']);
Route::middleware('check.permission:finance.payable.create')->post('accounts-payable-categories', [AccountPayableCategoryController::class, 'store']); // compat
Route::middleware('check.permission:finance.payable.update')->put('account-payable-categories/{category}', [AccountPayableCategoryController::class, 'update']);
Route::middleware('check.permission:finance.payable.update')->put('accounts-payable-categories/{category}', [AccountPayableCategoryController::class, 'update']); // compat
Route::middleware('check.permission:finance.payable.delete')->delete('account-payable-categories/{category}', [AccountPayableCategoryController::class, 'destroy']);
Route::middleware('check.permission:finance.payable.delete')->delete('accounts-payable-categories/{category}', [AccountPayableCategoryController::class, 'destroy']); // compat

// Formas de Pagamento
Route::middleware('check.permission:finance.payable.view|finance.receivable.view|financial.fund_transfer.create')->get('payment-methods', [PaymentMethodController::class, 'index']);
Route::middleware('check.permission:finance.payable.create')->post('payment-methods', [PaymentMethodController::class, 'store']);
Route::middleware('check.permission:finance.payable.update')->put('payment-methods/{paymentMethod}', [PaymentMethodController::class, 'update']);
Route::middleware('check.permission:finance.payable.delete')->delete('payment-methods/{paymentMethod}', [PaymentMethodController::class, 'destroy']);

// Relatórios
Route::middleware('check.permission:reports.os_report.view')->get('reports/work-orders', [ReportController::class, 'workOrders']);
Route::middleware('check.permission:reports.productivity_report.view')->get('reports/productivity', [ReportController::class, 'productivity']);
Route::middleware('check.permission:reports.financial_report.view')->get('/reports/financial', [ReportController::class, 'financial']);
Route::middleware('check.permission:expenses.expense.view')->get('/reports/expenses', [ReportController::class, 'expenses']);
Route::middleware('check.permission:reports.commission_report.view')->get('/reports/commissions', [ReportController::class, 'commissions']);
Route::middleware('check.permission:reports.margin_report.view')->get('/reports/profitability', [ReportController::class, 'profitability']);
Route::middleware('check.permission:reports.quotes_report.view')->get('/reports/quotes', [ReportController::class, 'quotes']);
Route::middleware('check.permission:reports.service_calls_report.view')->get('/reports/service-calls', [ReportController::class, 'serviceCalls']);
Route::middleware('check.permission:reports.technician_cash_report.view')->get('/reports/technician-cash', [ReportController::class, 'technicianCash']);
Route::middleware('check.permission:reports.crm_report.view')->get('/reports/crm', [ReportController::class, 'crm']);

// New Reports
Route::middleware('check.permission:reports.equipments_report.view')->get('/reports/equipments', [ReportController::class, 'equipments']);
Route::middleware('check.permission:reports.suppliers_report.view')->get('/reports/suppliers', [ReportController::class, 'suppliers']);
Route::middleware('check.permission:reports.stock_report.view')->get('/reports/stock', [ReportController::class, 'stock']);
Route::middleware('check.permission:reports.customers_report.view')->get('/reports/customers', [ReportController::class, 'customers']);

// Export Routes
Route::middleware('check.report.export')->get('/reports/{type}/export', [ReportController::class, 'export']);

// ═══ Alias Routes (API consistency) ═══
Route::middleware('check.permission:platform.dashboard.view')->get('dashboard', [DashboardController::class, 'stats']);
Route::middleware('check.permission:finance.cashflow.view')->get('financial/summary', [DashboardController::class, 'financialSummary']);
Route::middleware('check.permission:reports.os_report.view')->get('reports/os', [ReportController::class, 'workOrders']);
Route::middleware('check.permission:quotes.quote.view')->get('quotes/summary', [QuoteController::class, 'summary']);
Route::middleware('check.permission:commissions.event.view')->get('commissions', [CommissionController::class, 'events']);
Route::middleware('check.permission:commissions.rule.view')->get('commissions/rules', [CommissionRuleController::class, 'rules']);
Route::middleware('check.permission:commissions.event.view')->get('commissions/events', [CommissionController::class, 'events']);
Route::middleware('check.permission:commissions.settlement.view')->get('commissions/settlements', [CommissionController::class, 'settlements']);
Route::middleware('check.permission:commissions.rule.create')->post('commissions/simulate', [CommissionController::class, 'simulate']);

// Analytics Hub (Fase 2 — Unified Analytics)
Route::middleware('check.permission:reports.analytics.view')->group(function () {});

// Importação
Route::middleware('check.permission:import.data.view')->group(function () {
    Route::get('import/fields/{entity}', [ImportController::class, 'fields']);
    Route::get('import/history', [ImportController::class, 'history']);
    Route::get('import/templates', [ImportController::class, 'templates']);
    Route::get('import/sample/{entity}', [ImportController::class, 'downloadSample']);
    Route::get('import/export/{entity}', [ImportController::class, 'exportData']);
    Route::get('import/{id}/errors', [ImportController::class, 'exportErrors']);
    Route::get('import/{id}', [ImportController::class, 'show']);
    Route::get('import/{id}/progress', [ImportController::class, 'progress']);
    Route::get('import-stats', [ImportController::class, 'stats']);
    Route::get('import-entity-counts', [ImportController::class, 'entityCounts']);
});
Route::middleware('check.permission:import.data.execute')->group(function () {
    Route::post('import/upload', [ImportController::class, 'upload'])->middleware('throttle:tenant-uploads');
    Route::post('import/preview', [ImportController::class, 'preview']);
    Route::post('import/execute', [ImportController::class, 'execute']);
    Route::post('import/templates', [ImportController::class, 'saveTemplate']);
});
Route::middleware('check.permission:import.data.delete')->group(function () {
    Route::delete('import/templates/{id}', [ImportController::class, 'deleteTemplate']);
    Route::post('import/{id}/rollback', [ImportController::class, 'rollback']);
    Route::delete('import/{id}', [ImportController::class, 'destroy']);
});

// Integração Auvo API v2
Route::middleware('check.permission:auvo.import.view')->group(function () {
    Route::get('auvo/status', [AuvoImportController::class, 'testConnection']);
    Route::get('auvo/sync-status', [AuvoImportController::class, 'syncStatus']);
    Route::get('auvo/preview/{entity}', [AuvoImportController::class, 'preview']);
    Route::get('auvo/history', [AuvoImportController::class, 'history']);
    Route::get('auvo/mappings', [AuvoImportController::class, 'mappings']);
    Route::get('auvo/config', [AuvoImportController::class, 'getConfig']);
});
Route::middleware('check.permission:auvo.import.execute')->group(function () {
    Route::post('auvo/import/{entity}', [AuvoImportController::class, 'import']);
    Route::post('auvo/import-all', [AuvoImportController::class, 'importAll']);
    Route::put('auvo/config', [AuvoImportController::class, 'config']);
});
Route::middleware('check.permission:auvo.import.delete')->group(function () {
    Route::post('auvo/rollback/{id}', [AuvoImportController::class, 'rollback']);
    Route::delete('auvo/history/{id}', [AuvoImportController::class, 'destroy']);
});
Route::middleware('check.permission:auvo.export.execute')->group(function () {
    Route::post('auvo/export/customer/{customer}', [AuvoExportController::class, 'exportCustomer']);
    Route::post('auvo/export/product/{product}', [AuvoExportController::class, 'exportProduct']);
    Route::post('auvo/export/service/{service}', [AuvoExportController::class, 'exportServiceEntity']);
    Route::post('auvo/export/quote/{quote}', [AuvoExportController::class, 'exportQuote']);
});
