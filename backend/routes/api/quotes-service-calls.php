<?php

/**
 * Routes: Config, Orcamentos, Chamados, Caixa Tecnico, Contas, Transferencias
 * Extracted from api.php - Phase 10 Modularization
 * Original lines: 1049-1196
 */

use App\Http\Controllers\Api\V1\AdminTechnicianFundRequestController;
use App\Http\Controllers\Api\V1\Financial\BankAccountController;
use App\Http\Controllers\Api\V1\Financial\FundTransferController;
use App\Http\Controllers\Api\V1\QuoteController;
use App\Http\Controllers\Api\V1\ServiceCallController;
use App\Http\Controllers\Api\V1\ServiceCallTemplateController;
use App\Http\Controllers\Api\V1\SettingsController;
use App\Http\Controllers\Api\V1\TechnicianCashController;
use App\Http\Controllers\Api\V1\TechnicianExpenseController;
use Illuminate\Support\Facades\Route;

// Configurações + Auditoria
Route::middleware('check.permission:platform.settings.view')->get('settings', [SettingsController::class, 'index']);
Route::middleware('check.permission:platform.settings.manage')->put('settings', [SettingsController::class, 'update']);
Route::middleware('check.permission:platform.settings.manage')->post('settings/logo', [SettingsController::class, 'uploadLogo']);

// Orçamentos
Route::middleware('check.permission:quotes.quote.view')->group(function () {
    Route::get('quotes', [QuoteController::class, 'index']);
    Route::get('quotes-summary', [QuoteController::class, 'summary']);
    Route::get('quotes-export', [QuoteController::class, 'exportCsv']);
});
Route::middleware('check.permission:quotes.quote.update')->post('quotes/bulk-action', [QuoteController::class, 'bulkAction']);
Route::middleware('check.permission:quotes.quote.view')->group(function () {
    Route::get('quotes/{quote}', [QuoteController::class, 'show']);
    Route::get('quotes/{quote}/timeline', [QuoteController::class, 'timeline']);
});
Route::middleware('check.permission:quotes.quote.create')->group(function () {
    Route::post('quotes', [QuoteController::class, 'store']);
    Route::post('quotes/{quote}/duplicate', [QuoteController::class, 'duplicate']);
});
Route::middleware('check.permission:quotes.quote.update')->group(function () {
    Route::put('quotes/{quote}', [QuoteController::class, 'update']);
    Route::post('quotes/{quote}/equipments', [QuoteController::class, 'addEquipment']);
    Route::put('quote-equipments/{equipment}', [QuoteController::class, 'updateEquipment']);
    Route::delete('quotes/{quote}/equipments/{equipment}', [QuoteController::class, 'removeEquipment']);
    Route::post('quote-equipments/{equipment}/items', [QuoteController::class, 'addItem']);
    Route::put('quote-items/{item}', [QuoteController::class, 'updateItem']);
    Route::delete('quote-items/{item}', [QuoteController::class, 'removeItem']);
    Route::post('quotes/{quote}/photos', [QuoteController::class, 'addPhoto']);
    Route::delete('quote-photos/{photo}', [QuoteController::class, 'removePhoto']);
    Route::post('quotes/{quote}/reopen', [QuoteController::class, 'reopen']);
});
Route::middleware('check.permission:quotes.quote.approve')->group(function () {
    Route::post('quotes/{quote}/approve', [QuoteController::class, 'approve']);
    Route::post('quotes/{quote}/reject', [QuoteController::class, 'reject']);
});
// GAP-01: Internal approval (before sending to client)
Route::middleware('check.permission:quotes.quote.send')->post('quotes/{quote}/request-internal-approval', [QuoteController::class, 'requestInternalApproval']);
Route::middleware('check.permission:quotes.quote.internal_approve')->post('quotes/{quote}/internal-approve', [QuoteController::class, 'internalApprove']);
Route::middleware('check.permission:quotes.quote.send')->post('quotes/{quote}/send', [QuoteController::class, 'send']);
Route::middleware('check.permission:quotes.quote.convert')->post('quotes/{quote}/convert-to-os', [QuoteController::class, 'convertToWorkOrder']);
Route::middleware('check.permission:quotes.quote.convert')->post('quotes/{quote}/convert-to-chamado', [QuoteController::class, 'convertToServiceCall']);
Route::middleware('check.permission:quotes.quote.convert')->post('quotes/{quote}/approve-after-test', [QuoteController::class, 'approveAfterTest']);
Route::middleware('check.permission:quotes.quote.convert')->post('quotes/{quote}/renegotiate', [QuoteController::class, 'sendToRenegotiation']);
Route::middleware('check.permission:quotes.quote.convert')->post('quotes/{quote}/revert-renegotiation', [QuoteController::class, 'revertFromRenegotiation']);
Route::middleware('check.permission:quotes.quote.delete')->delete('quotes/{quote}', [QuoteController::class, 'destroy']);

// ── Melhorias Orçamentos (30 funcionalidades) ──
Route::middleware('check.permission:quotes.quote.view')->group(function () {
    Route::get('quotes-advanced-summary', [QuoteController::class, 'advancedSummary']);
    Route::get('quotes/{quote}/tags', [QuoteController::class, 'tags']);
    Route::get('quote-tags', [QuoteController::class, 'listTags']);
    Route::get('quote-templates', [QuoteController::class, 'listTemplates']);
    Route::post('quotes/compare', [QuoteController::class, 'compareQuotes']);
    Route::get('quotes/{quote}/revisions', [QuoteController::class, 'compareRevisions']);
    Route::get('quotes/{quote}/whatsapp', [QuoteController::class, 'whatsappLink']);
    Route::get('quotes/{quote}/installments', [QuoteController::class, 'installmentSimulation']);
});
Route::middleware('check.permission:quotes.quote.update')->group(function () {
    Route::post('quotes/{quote}/tags', [QuoteController::class, 'syncTags']);
});
Route::middleware('check.permission:quotes.quote.create')->group(function () {
    Route::post('quote-tags', [QuoteController::class, 'storeTag']);
    Route::post('quote-templates', [QuoteController::class, 'storeTemplate']);
    Route::put('quote-templates/{template}', [QuoteController::class, 'updateTemplate']);
    Route::post('quote-templates/{template}/create-quote', [QuoteController::class, 'createFromTemplate']);
});
Route::middleware('check.permission:quotes.quote.delete')->group(function () {
    Route::delete('quote-tags/{tag}', [QuoteController::class, 'destroyTag']);
    Route::delete('quote-templates/{template}', [QuoteController::class, 'destroyTemplate']);
});
Route::middleware('check.permission:quotes.quote.send')->post('quotes/{quote}/email', [QuoteController::class, 'sendEmail']);
Route::middleware('check.permission:quotes.quote.internal_approve')->post('quotes/{quote}/approve-level2', [QuoteController::class, 'approveLevel2']);
Route::middleware('check.permission:quotes.quote.approve')->post('quotes/{quote}/invoice', [QuoteController::class, 'markAsInvoiced']);

// Chamados Técnicos
Route::middleware('check.permission:service_calls.service_call.view')->group(function () {
    Route::get('service-calls', [ServiceCallController::class, 'index']);
    Route::get('service-calls-assignees', [ServiceCallController::class, 'assignees']);
    Route::get('service-calls-map', [ServiceCallController::class, 'mapData']);
    Route::get('service-calls/map-data', [ServiceCallController::class, 'mapData']); // compat
    Route::get('service-calls-agenda', [ServiceCallController::class, 'agenda']);
    Route::get('service-calls/agenda', [ServiceCallController::class, 'agenda']); // compat
    Route::get('service-calls-summary', [ServiceCallController::class, 'summary']);
    Route::get('service-calls-export', [ServiceCallController::class, 'exportCsv']);
    Route::get('service-calls-kpi', [ServiceCallController::class, 'dashboardKpi']);
    Route::get('service-calls/check-duplicate', [ServiceCallController::class, 'checkDuplicate']);
    Route::get('service-calls/{serviceCall}', [ServiceCallController::class, 'show']);
    Route::get('service-calls/{serviceCall}/comments', [ServiceCallController::class, 'comments']);
    Route::get('service-calls/{serviceCall}/audit-trail', [ServiceCallController::class, 'auditTrail']);
    // Templates
    Route::get('service-call-templates', [ServiceCallTemplateController::class, 'index']);
    Route::get('service-call-templates/active', [ServiceCallTemplateController::class, 'activeList']);
});
Route::middleware('check.permission:service_calls.service_call.create')->group(function () {
    Route::post('service-calls', [ServiceCallController::class, 'store']);
    Route::post('service-calls/{serviceCall}/comments', [ServiceCallController::class, 'addComment']);
    Route::post('service-calls/webhook', [ServiceCallController::class, 'webhookCreate']);
    // Templates
    Route::post('service-call-templates', [ServiceCallTemplateController::class, 'store']);
});
Route::middleware('check.permission:service_calls.service_call.update')->group(function () {
    Route::put('service-calls/{serviceCall}', [ServiceCallController::class, 'update']);
    Route::put('service-calls/{serviceCall}/status', [ServiceCallController::class, 'updateStatus']);
    Route::post('service-calls/{serviceCall}/convert-to-os', [ServiceCallController::class, 'convertToWorkOrder']);
    Route::post('service-calls/bulk-action', [ServiceCallController::class, 'bulkAction']);
    Route::post('service-calls/{serviceCall}/reschedule', [ServiceCallController::class, 'reschedule']);
    // Templates
    Route::put('service-call-templates/{serviceCallTemplate}', [ServiceCallTemplateController::class, 'update']);
});
Route::middleware('check.permission:service_calls.service_call.assign')->put('service-calls/{serviceCall}/assign', [ServiceCallController::class, 'assignTechnician']);
Route::middleware('check.permission:service_calls.service_call.delete')->group(function () {
    Route::delete('service-calls/{serviceCall}', [ServiceCallController::class, 'destroy']);
    Route::delete('service-call-templates/{serviceCallTemplate}', [ServiceCallTemplateController::class, 'destroy']);
});

// Caixa do Técnico - endpoints mobile (self-service, rotas literais antes das parametrizadas)
Route::middleware('check.permission:technicians.cashbox.view')->get('technician-cash/my-fund', [TechnicianCashController::class, 'myFund']);
Route::middleware('check.permission:technicians.cashbox.view')->get('technician-cash/my-transactions', [TechnicianCashController::class, 'myTransactions']);
Route::middleware('check.permission:technicians.cashbox.view')->get('technician-cash/my-requests', [TechnicianCashController::class, 'myRequests']);
Route::middleware('check.permission:technicians.cashbox.request_funds|technicians.cashbox.manage')->post('technician-cash/request-funds', [TechnicianCashController::class, 'requestFunds']);
Route::middleware('check.permission:technicians.cashbox.view')->get('technician-cash/my-expenses', [TechnicianExpenseController::class, 'index']);
Route::middleware('check.permission:technicians.cashbox.expense.create|technicians.cashbox.manage')->post('technician-cash/my-expenses', [TechnicianExpenseController::class, 'store']);
Route::middleware('check.permission:technicians.cashbox.expense.update|technicians.cashbox.manage')->put('technician-cash/my-expenses/{expense}', [TechnicianExpenseController::class, 'update']);
Route::middleware('check.permission:technicians.cashbox.expense.delete|technicians.cashbox.manage')->delete('technician-cash/my-expenses/{expense}', [TechnicianExpenseController::class, 'destroy']);

// Caixa do Técnico - admin
Route::middleware('check.permission:technicians.cashbox.view|technicians.cashbox.manage')->group(function () {
    Route::get('technician-cash', [TechnicianCashController::class, 'index']);
    Route::get('technician-cash-summary', [TechnicianCashController::class, 'summary']);
    Route::get('technician-cash/{userId}', [TechnicianCashController::class, 'show']);
});
Route::middleware('check.permission:technicians.cashbox.manage')->group(function () {
    Route::post('technician-cash/credit', [TechnicianCashController::class, 'addCredit']);
    Route::post('technician-cash/debit', [TechnicianCashController::class, 'addDebit']);
});

// Caixa do Técnico - solicitações (admin)
Route::middleware('check.permission:technicians.cashbox.view|technicians.cashbox.manage')->group(function () {
    Route::get('technician-fund-requests', [AdminTechnicianFundRequestController::class, 'index']);
});
Route::middleware('check.permission:technicians.cashbox.manage')->group(function () {
    Route::put('technician-fund-requests/{id}/status', [AdminTechnicianFundRequestController::class, 'updateStatus']);
});

// Contas Bancárias
Route::middleware('check.permission:financial.bank_account.view|financial.fund_transfer.create')->group(function () {
    Route::get('bank-accounts', [BankAccountController::class, 'index']);
    Route::get('bank-accounts/{bankAccount}', [BankAccountController::class, 'show']);
});
Route::middleware('check.permission:financial.bank_account.create')->post('bank-accounts', [BankAccountController::class, 'store']);
Route::middleware('check.permission:financial.bank_account.update')->put('bank-accounts/{bankAccount}', [BankAccountController::class, 'update']);
Route::middleware('check.permission:financial.bank_account.delete')->delete('bank-accounts/{bankAccount}', [BankAccountController::class, 'destroy']);

// Transferências para Técnicos
Route::middleware('check.permission:financial.fund_transfer.view')->group(function () {
    Route::get('fund-transfers', [FundTransferController::class, 'index']);
    Route::get('fund-transfers/summary', [FundTransferController::class, 'summary']);
    Route::get('fund-transfers/{fundTransfer}', [FundTransferController::class, 'show']);
});
Route::middleware('check.permission:financial.fund_transfer.create')->post('fund-transfers', [FundTransferController::class, 'store']);
Route::middleware('check.permission:financial.fund_transfer.cancel')->post('fund-transfers/{fundTransfer}/cancel', [FundTransferController::class, 'cancel']);
