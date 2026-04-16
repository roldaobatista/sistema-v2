<?php

use App\Http\Controllers\Api\V1\FinanceAdvancedController;
use App\Http\Controllers\Api\V1\Financial\FinancialExtraController;
use App\Http\Controllers\Api\V1\Financial\InstallmentPaymentController;
use App\Http\Controllers\Api\V1\InnovationController;
use App\Http\Controllers\Api\V1\Portal\PortalController;
use App\Http\Controllers\Api\V1\Portal\PortalExecutiveDashboardController;
use App\Http\Controllers\Api\V1\Portal\PortalFinancialController;
use App\Http\Controllers\Api\V1\Portal\PortalQuickQuoteApprovalController;
use App\Http\Controllers\Api\V1\Portal\SatisfactionSurveyController;

/**
 * Rotas: Financeiro Avançado (CNAB, projeção, regras de cobrança, parcelamento, inadimplência).
 * Carregado de dentro do grupo auth:sanctum + check.tenant em routes/api.php.
 */

// ── Financial Extra (boleto, payment gateway, portal overview) ──
Route::middleware('check.permission:finance.receivable.view')->get('financial-extra/portal-overview', [FinancialExtraController::class, 'financialPortalOverview']);
Route::middleware('check.permission:finance.receivable.create')->post('financial-extra/boleto', [FinancialExtraController::class, 'generateBoleto']);
Route::middleware('check.permission:finance.receivable.view')->get('financial-extra/payment-gateway-config', [FinancialExtraController::class, 'paymentGatewayConfig']);

// ── Innovation / Presentation KPIs ──
Route::middleware('check.permission:finance.receivable.view')->get('innovation/presentation', [InnovationController::class, 'presentationData']);

// ── Innovation / Theme & Referral & ROI & Easter Egg ──
Route::prefix('innovation')->middleware('check.permission:innovation.view')->group(function () {
    Route::get('theme-config', [InnovationController::class, 'themeConfig']);
    Route::put('theme-config', [InnovationController::class, 'updateThemeConfig']);
    Route::get('referral-program', [InnovationController::class, 'referralProgram']);
    Route::post('referral-code', [InnovationController::class, 'generateReferralCode']);
    Route::post('roi-calculator', [InnovationController::class, 'roiCalculator']);
    Route::get('easter-egg/{code}', [InnovationController::class, 'easterEgg']);
});

// ── Portal Executive Dashboard (per customer) ──
Route::middleware('check.permission:portal.client.view')->get('portal/dashboard/{customerId}', [PortalExecutiveDashboardController::class, 'show']);

// ── Portal Quick Quote Approve ──
Route::middleware('check.permission:quotes.quote.approve')->post('portal/quotes/{quoteId}/approve', [PortalQuickQuoteApprovalController::class, 'approve']);

// ── Portal Satisfaction Surveys (public — token-based, no auth required) ──
Route::withoutMiddleware(['auth:sanctum', 'check.tenant'])->group(function () {
    Route::get('portal/satisfaction-surveys/{survey}', [SatisfactionSurveyController::class, 'show']);
    Route::post('portal/satisfaction-surveys/{survey}/answer', [SatisfactionSurveyController::class, 'answer']);
});

// ── Portal Financial (customer invoices/receivables) ──
Route::middleware('check.permission:portal.client.view')->get('portal/financial/{customerId}', [PortalFinancialController::class, 'index']);
Route::middleware('check.permission:portal.client.view')->get('portal/knowledge-base', [PortalController::class, 'knowledgeBase']);
Route::middleware('check.permission:portal.client.view')->get('portal/nps', [PortalController::class, 'nps']);

// ── PSP: Boleto / PIX generation for installments ──
Route::prefix('financial/receivables')->group(function () {
    Route::middleware('check.permission:finance.receivable.create')
        ->post('{installment}/generate-boleto', [InstallmentPaymentController::class, 'generateBoleto']);
    Route::middleware('check.permission:finance.receivable.create')
        ->post('{installment}/generate-pix', [InstallmentPaymentController::class, 'generatePix']);
    Route::middleware('check.permission:finance.receivable.view')
        ->get('{installment}/payment-status', [InstallmentPaymentController::class, 'checkStatus']);
});

Route::prefix('finance-advanced')->group(function () {
    Route::middleware('check.permission:finance.receivable.create')->post('cnab/import', [FinanceAdvancedController::class, 'importCnab']);
    Route::middleware('check.permission:finance.cashflow.view')->get('cash-flow/projection', [FinanceAdvancedController::class, 'cashFlowProjection']);
    Route::middleware('check.permission:finance.receivable.view')->get('collection-rules', [FinanceAdvancedController::class, 'collectionRules']);
    Route::middleware('check.permission:finance.receivable.create')->post('collection-rules', [FinanceAdvancedController::class, 'storeCollectionRule']);
    Route::middleware('check.permission:finance.receivable.update')->put('collection-rules/{rule}', [FinanceAdvancedController::class, 'updateCollectionRule']);
    Route::middleware('check.permission:finance.receivable.delete')->delete('collection-rules/{rule}', [FinanceAdvancedController::class, 'deleteCollectionRule']);
    Route::middleware('check.permission:finance.receivable.update')->post('receivables/{receivable}/partial-payment', [FinanceAdvancedController::class, 'partialPayment']);
    Route::middleware('check.permission:finance.dre.view')->get('dre/cost-center', [FinanceAdvancedController::class, 'dreByCostCenter']);
    Route::middleware('check.permission:finance.receivable.view')->post('installment/simulate', [FinanceAdvancedController::class, 'simulateInstallment']);
    Route::middleware('check.permission:finance.receivable.create')->post('installment/create', [FinanceAdvancedController::class, 'createInstallment']);
    Route::middleware('check.permission:finance.receivable.view')->get('delinquency/dashboard', [FinanceAdvancedController::class, 'delinquencyDashboard']);
});
