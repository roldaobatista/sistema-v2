<?php

/**
 * Routes: Fiscal (NF-e, SEFAZ)
 * Extracted from api.php - Phase 10 Modularization
 * Original lines: 264-365
 */

use App\Http\Controllers\Api\V1\FiscalConfigController;
use App\Http\Controllers\Api\V1\FiscalController;
use App\Http\Controllers\Api\V1\FiscalExpandedController;
use App\Http\Controllers\Api\V1\FiscalReportController;
use Illuminate\Support\Facades\Route;

// Fiscal — NF-e / NFS-e
Route::middleware('check.permission:fiscal.note.view')->group(function () {
    Route::get('fiscal/notas', [FiscalController::class, 'index']);
    Route::get('fiscal/notas/{id}', [FiscalController::class, 'show']);
    Route::get('fiscal/status/{protocolo}', [FiscalController::class, 'consultarStatus']);
    Route::get('fiscal/notas/{id}/pdf', [FiscalController::class, 'downloadPdf']);
    Route::get('fiscal/notas/{id}/xml', [FiscalController::class, 'downloadXml']);
    Route::get('fiscal/notas/{id}/events', [FiscalController::class, 'events']);
    Route::get('fiscal/stats', [FiscalController::class, 'stats']);
    Route::get('fiscal/contingency/status', [FiscalController::class, 'contingencyStatus']);
});
Route::middleware('check.permission:fiscal.note.create')->group(function () {
    Route::post('fiscal/nfe', [FiscalController::class, 'emitirNFe']);
    Route::post('fiscal/nfse', [FiscalController::class, 'emitirNFSe']);
    Route::post('fiscal/nfe/from-work-order/{workOrderId}', [FiscalController::class, 'emitirNFeFromWorkOrder']);
    Route::post('fiscal/nfse/from-work-order/{workOrderId}', [FiscalController::class, 'emitirNFSeFromWorkOrder']);
    Route::post('fiscal/nfe/from-quote/{quoteId}', [FiscalController::class, 'emitirNFeFromQuote']);
    Route::post('fiscal/inutilizar', [FiscalController::class, 'inutilizar']);
});
Route::middleware('check.permission:fiscal.note.cancel')->post('fiscal/notas/{id}/cancelar', [FiscalController::class, 'cancelar']);
Route::middleware('check.permission:fiscal.note.create')->post('fiscal/notas/{id}/carta-correcao', [FiscalController::class, 'cartaCorrecao']);
Route::middleware('check.permission:fiscal.note.view')->post('fiscal/notas/{id}/email', [FiscalController::class, 'sendEmail']);
Route::middleware('check.permission:fiscal.note.create')->post('fiscal/contingency/retransmit', [FiscalController::class, 'retransmitContingency']);
Route::middleware('check.permission:fiscal.note.create')->post('fiscal/contingency/retransmit/{id}', [FiscalController::class, 'retransmitSingleNote']);

// Fiscal Config
Route::middleware('check.permission:platform.settings.view')->group(function () {
    Route::get('fiscal/config', [FiscalConfigController::class, 'show']);
    Route::get('fiscal/config/certificate/status', [FiscalConfigController::class, 'certificateStatus']);
    Route::get('fiscal/config/cfop-options', [FiscalConfigController::class, 'cfopOptions']);
    Route::get('fiscal/config/csosn-options', [FiscalConfigController::class, 'csosnOptions']);
    Route::get('fiscal/config/iss-exigibilidade-options', [FiscalConfigController::class, 'issExigibilidadeOptions']);
    Route::get('fiscal/config/lc116-options', [FiscalConfigController::class, 'lc116Options']);
});
Route::middleware('check.permission:platform.settings.manage')->group(function () {
    Route::put('fiscal/config', [FiscalConfigController::class, 'update']);
    Route::post('fiscal/config/certificate', [FiscalConfigController::class, 'uploadCertificate'])->middleware('throttle:tenant-uploads');
    Route::delete('fiscal/config/certificate', [FiscalConfigController::class, 'removeCertificate']);
});

// Fiscal Expanded — 30 New Features
// Reports (#1-5)
Route::middleware('check.permission:fiscal.note.view')->group(function () {
    Route::get('fiscal/reports/sped', [FiscalReportController::class, 'spedFiscal']);
    Route::get('fiscal/reports/tax-dashboard', [FiscalReportController::class, 'taxDashboard']);
    Route::get('fiscal/reports/export-accountant', [FiscalReportController::class, 'exportAccountant']);
    Route::get('fiscal/reports/ledger', [FiscalReportController::class, 'ledger']);
    Route::get('fiscal/reports/tax-forecast', [FiscalReportController::class, 'taxForecast']);
});

// Automation (#6-9)
Route::middleware('check.permission:fiscal.note.create')->group(function () {
    Route::post('fiscal/batch', [FiscalExpandedController::class, 'emitBatch']);
    Route::post('fiscal/schedule', [FiscalExpandedController::class, 'scheduleEmission']);
    Route::post('fiscal/notas/{id}/retry-email', [FiscalExpandedController::class, 'retryEmail']);
});

// Webhooks (#10)
Route::middleware('check.permission:platform.settings.manage')->group(function () {
    Route::get('fiscal/webhooks', [FiscalExpandedController::class, 'listWebhooks']);
    Route::post('fiscal/webhooks', [FiscalExpandedController::class, 'createWebhook']);
    Route::delete('fiscal/webhooks/{id}', [FiscalExpandedController::class, 'deleteWebhook']);
});

// Advanced NF-e (#11-15)
Route::middleware('check.permission:fiscal.note.create')->group(function () {
    Route::post('fiscal/notas/{id}/devolucao', [FiscalExpandedController::class, 'emitirDevolucao']);
    Route::post('fiscal/notas/{id}/complementar', [FiscalExpandedController::class, 'emitirComplementar']);
    Route::post('fiscal/remessa', [FiscalExpandedController::class, 'emitirRemessa']);
    Route::post('fiscal/notas/{id}/retorno', [FiscalExpandedController::class, 'emitirRetorno']);
    Route::post('fiscal/manifestacao', [FiscalExpandedController::class, 'manifestarDestinatario']);
    Route::post('fiscal/cte', [FiscalExpandedController::class, 'emitirCTe']);
});

// Compliance (#16-20)
Route::middleware('check.permission:fiscal.note.view')->group(function () {
    Route::get('fiscal/certificate-alert', [FiscalExpandedController::class, 'certificateAlert']);
    Route::get('fiscal/notas/{id}/audit', [FiscalExpandedController::class, 'auditLog']);
    Route::get('fiscal/audit-report', [FiscalExpandedController::class, 'auditReport']);
    Route::post('fiscal/validate-document', [FiscalExpandedController::class, 'validateDocument']);
    Route::get('fiscal/check-regime', [FiscalExpandedController::class, 'checkRegime']);
});

// Finance (#21-25)
Route::middleware('check.permission:fiscal.note.create')->group(function () {
    Route::post('fiscal/notas/{id}/reconcile', [FiscalExpandedController::class, 'reconcile']);
    Route::post('fiscal/notas/{id}/boleto', [FiscalExpandedController::class, 'generateBoleto']);
    Route::post('fiscal/notas/{id}/split-payment', [FiscalExpandedController::class, 'splitPayment']);
    Route::post('fiscal/retentions', [FiscalExpandedController::class, 'calculateRetentions']);
    Route::post('fiscal/payment-confirmed', [FiscalExpandedController::class, 'paymentConfirmed']);
});

// Templates & UX (#26-28)
Route::middleware('check.permission:fiscal.note.view')->group(function () {
    Route::get('fiscal/templates', [FiscalExpandedController::class, 'listTemplates']);
    Route::post('fiscal/templates', [FiscalExpandedController::class, 'saveTemplate']);
    Route::post('fiscal/notas/{id}/save-template', [FiscalExpandedController::class, 'saveTemplateFromNote']);
    Route::get('fiscal/templates/{id}/apply', [FiscalExpandedController::class, 'applyTemplate']);
    Route::delete('fiscal/templates/{id}', [FiscalExpandedController::class, 'deleteTemplate']);
    Route::get('fiscal/notas/{id}/duplicate', [FiscalExpandedController::class, 'duplicateNote']);
    Route::get('fiscal/search-key', [FiscalExpandedController::class, 'searchByAccessKey']);
});
