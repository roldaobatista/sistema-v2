<?php

use App\Http\Controllers\Api\V1\AgendaController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\BatchExportController;
use App\Http\Controllers\Api\V1\ContractController;
use App\Http\Controllers\Api\V1\DepartmentController;
use App\Http\Controllers\Api\V1\EmailLogController;
use App\Http\Controllers\Api\V1\Financial\AccountPayableController;
use App\Http\Controllers\Api\V1\Financial\AccountReceivableController;
use App\Http\Controllers\Api\V1\FiscalInvoiceController;
use App\Http\Controllers\Api\V1\Iam\UserController;
use App\Http\Controllers\Api\V1\ImportController;
use App\Http\Controllers\Api\V1\InventoryController;
use App\Http\Controllers\Api\V1\Master\CustomerController;
use App\Http\Controllers\Api\V1\Master\ProductController;
use App\Http\Controllers\Api\V1\Os\WorkOrderAttachmentController;
use App\Http\Controllers\Api\V1\Os\WorkOrderCommentController;
use App\Http\Controllers\Api\V1\Os\WorkOrderController;
use App\Http\Controllers\Api\V1\Os\WorkOrderIntegrationController;
use App\Http\Controllers\Api\V1\Os\WorkOrderItemController;
use App\Http\Controllers\Api\V1\QuoteController;
use App\Http\Controllers\Api\V1\SearchController;
use App\Http\Controllers\Api\V1\StockMovementController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Additional Routes — Controller-based only
|--------------------------------------------------------------------------
| NOTA: Todas as rotas herdam auth:sanctum + check.tenant do grupo pai em api.php.
| Cada rota DEVE ter check.permission adequado.
|
| ⚠️  NÃO adicionar closures inline aqui. Toda rota deve usar um controller.
|     Closures bypassam Form Requests, validação e lógica de negócio.
|
| Auditoria 17/03/2026: Removidas ~40 closures inline que duplicavam rotas
| de outros route files ou retornavam dados stub/hardcoded.
| Duplicatas removidas:
|   - PUT work-orders/{id}/status → work-orders.php (WorkOrderController::updateStatus)
|   - PUT service-calls/{id}/status → quotes-service-calls.php (ServiceCallController::updateStatus)
|   - GET/POST users → dashboard_iam.php (UserController)
|   - GET/PUT settings → quotes-service-calls.php (SettingsController)
|   - GET notifications/* → equipment-platform.php (NotificationController)
|   - GET commissions/* → financial.php (CommissionController)
|   - GET crm/deals, crm/pipelines → crm.php
|   - GET account-payables/receivables → financial.php
|   - GET contracts → work-orders.php
|   - GET calibrations → equipment-platform.php
|   - GET expenses/categories → financial.php
|   - GET reports/dre, reports/financial-summary → STUB removido (usar DashboardController::financialSummary)
|   - POST commissions/simulate → STUB removido (usar CommissionController::simulate)
|   - GET export/customers, customers/export, work-orders/export → STUB removido
|   -  GET/POST hr/employees, positions, time-clock, leave-requests → STUB removido (usar HRController)
|   - GET/POST surveys → STUB removido (usar QualityController)
|   - Fleet fuel/trips closures → STUB removido (usar FleetController)
|--------------------------------------------------------------------------
*/

// ── Stock Movements ──
Route::middleware('check.permission:estoque.movement.view')->get('stock-movements', [StockMovementController::class, 'index']);
Route::middleware('check.permission:estoque.movement.create')->post('stock-movements', [StockMovementController::class, 'store']);
Route::middleware('check.permission:estoque.movement.view')->get('stock-movements/{stockMovement}', [StockMovementController::class, 'show']);

// ── Agenda Items ──
Route::middleware('check.permission:agenda.item.view')->get('agenda', [AgendaController::class, 'index']);
Route::middleware('check.permission:agenda.item.view')->get('agenda/resumo', [AgendaController::class, 'resumo']);
Route::middleware('check.permission:agenda.item.view')->get('agenda/summary', [AgendaController::class, 'summary']);
Route::middleware('check.permission:agenda.item.view')->get('agenda/constants', [AgendaController::class, 'constants']);
Route::middleware('check.permission:agenda.item.view')->get('agenda/items', [AgendaController::class, 'index']);
Route::middleware('check.permission:agenda.create.task')->post('agenda', [AgendaController::class, 'store']);
Route::middleware('check.permission:agenda.close.self|agenda.close.any')->put('agenda/{agendaItem}/complete', [AgendaController::class, 'complete'])->whereNumber('agendaItem');
Route::middleware('check.permission:agenda.item.view')->get('agenda/{agendaItem}', [AgendaController::class, 'show'])->whereNumber('agendaItem');
Route::middleware('check.permission:agenda.close.self')->put('agenda/{agendaItem}', [AgendaController::class, 'update'])->whereNumber('agendaItem');
Route::middleware('check.permission:agenda.close.self')->delete('agenda/{agendaItem}', [AgendaController::class, 'destroy'])->whereNumber('agendaItem');
Route::middleware('check.permission:agenda.item.view')->get('agenda-items', [AgendaController::class, 'index']);
Route::middleware('check.permission:agenda.item.view')->get('agenda-items/summary', [AgendaController::class, 'summary']);
Route::middleware('check.permission:agenda.create.task')->post('agenda-items', [AgendaController::class, 'store']);
Route::middleware('check.permission:agenda.item.view')->get('agenda-items/{agendaItem}', [AgendaController::class, 'show'])->whereNumber('agendaItem');
Route::middleware('check.permission:agenda.item.view')->post('agenda-items/{agendaItem}/comments', [AgendaController::class, 'comment'])->whereNumber('agendaItem');
Route::middleware('check.permission:agenda.assign')->post('agenda-items/{agendaItem}/assign', [AgendaController::class, 'assign'])->whereNumber('agendaItem');
Route::middleware('check.permission:agenda.close.self')->put('agenda-items/{agendaItem}', [AgendaController::class, 'update'])->whereNumber('agendaItem');
Route::middleware('check.permission:agenda.close.self')->delete('agenda-items/{agendaItem}', [AgendaController::class, 'destroy'])->whereNumber('agendaItem');

// ── Fiscal Invoices ──
Route::middleware('check.permission:fiscal.note.view')->get('fiscal-invoices', [FiscalInvoiceController::class, 'index']);
Route::middleware('check.permission:fiscal.note.create')->post('fiscal-invoices', [FiscalInvoiceController::class, 'store']);
Route::middleware('check.permission:fiscal.note.view')->get('fiscal-invoices/{fiscalInvoice}', [FiscalInvoiceController::class, 'show']);
Route::middleware('check.permission:fiscal.note.create')->put('fiscal-invoices/{fiscalInvoice}', [FiscalInvoiceController::class, 'update']);
Route::middleware('check.permission:fiscal.note.cancel')->delete('fiscal-invoices/{fiscalInvoice}', [FiscalInvoiceController::class, 'destroy']);

// ── Email Logs ──
Route::middleware('check.permission:email.inbox.view')->get('email-logs', [EmailLogController::class, 'index']);
Route::middleware('check.permission:email.inbox.view')->get('email-logs/{id}', [EmailLogController::class, 'show']);

// ── Departments ──
Route::middleware('check.permission:hr.organization.view')->get('departments', [DepartmentController::class, 'index']);
Route::middleware('check.permission:hr.organization.manage')->post('departments', [DepartmentController::class, 'store']);
Route::middleware('check.permission:hr.organization.view')->get('departments/{department}', [DepartmentController::class, 'show']);
Route::middleware('check.permission:hr.organization.manage')->put('departments/{department}', [DepartmentController::class, 'update']);
Route::middleware('check.permission:hr.organization.manage')->delete('departments/{department}', [DepartmentController::class, 'destroy']);

// ── Imports ──
Route::middleware('check.permission:import.data.view')->get('imports', [ImportController::class, 'history']);

// ── Search (qualquer usuário autenticado pode buscar) ──
Route::get('search', [SearchController::class, 'search']);

// ── Auth user (dados próprios — sem permissão extra, herda auth:sanctum) ──
Route::get('auth/user', [AuthController::class, 'me']);

// ── Tenant switch (operação do próprio usuário — sem permissão extra) ──
Route::post('tenant/switch', [AuthController::class, 'switchTenant']);

// ── Aliases de rotas com permissão (redirecionam para controllers reais) ──
Route::middleware('check.permission:finance.payable.view')->get('account-payables', [AccountPayableController::class, 'index']);
Route::middleware('check.permission:finance.receivable.view')->get('account-receivables', [AccountReceivableController::class, 'index']);
Route::middleware('check.permission:contracts.contract.view')->get('contracts', [ContractController::class, 'index']);
Route::middleware('check.permission:contracts.contract.view')->get('contracts/{contract}', [ContractController::class, 'show'])->whereNumber('contract');
Route::middleware('check.permission:contracts.contract.create')->post('contracts', [ContractController::class, 'store']);
Route::middleware('check.permission:contracts.contract.update')->put('contracts/{contract}', [ContractController::class, 'update'])->whereNumber('contract');
Route::middleware('check.permission:cadastros.product.view')->get('inventory-items', [ProductController::class, 'index']);
Route::middleware('check.permission:cadastros.product.create')->post('inventory-items', [ProductController::class, 'store']);
Route::middleware('check.permission:cadastros.product.view')->get('inventory', [ProductController::class, 'index']);
Route::middleware('check.permission:cadastros.product.view')->get('inventory/categories', [ProductController::class, 'categories']);

// ── Inventory (blind count) aliases — /inventory/inventories and /stock/inventories paths ──
Route::middleware('check.permission:estoque.inventory.view')->get('inventory/inventories', [InventoryController::class, 'index']);
Route::middleware('check.permission:estoque.inventory.create')->post('inventory/inventories', [InventoryController::class, 'store']);

Route::middleware('check.permission:cadastros.customer.view')->get('exports/customers', [BatchExportController::class, 'exportCustomers']);
Route::middleware('check.permission:os.work_order.export|os.work_order.view')->get('exports/work-orders', [BatchExportController::class, 'exportWorkOrders']);
// ── Import field mappings legacy alias ──
Route::middleware('check.permission:import.data.view')
    ->get('import/fields/{entity}', [ImportController::class, 'fields']);

// ── Customer nested routes ──
Route::prefix('customers/{customer}')->middleware('check.permission:cadastros.customer.view')->group(function () {
    Route::get('addresses', [CustomerController::class, 'addresses']);
    Route::middleware('check.permission:cadastros.customer.update')->post('addresses', [CustomerController::class, 'storeAddress']);
    Route::get('contacts', [CustomerController::class, 'contacts']);
    Route::middleware('check.permission:cadastros.customer.update')->post('contacts', [CustomerController::class, 'storeContact']);
    Route::get('work-orders', [CustomerController::class, 'workOrders']);
    Route::get('equipments', [CustomerController::class, 'equipments']);
    Route::get('quotes', [CustomerController::class, 'quotes']);
});

// ── Work Order nested routes ──
Route::prefix('work-orders/{workOrder}')->middleware('check.permission:os.work_order.view')->group(function () {
    Route::get('items', [WorkOrderItemController::class, 'items']);
    Route::middleware('check.permission:os.work_order.update')->post('items', [WorkOrderItemController::class, 'storeItem']);
    Route::get('photos', [WorkOrderAttachmentController::class, 'photos']);
    Route::get('status-history', [WorkOrderIntegrationController::class, 'statusHistoryAlias']);
    Route::get('comments', [WorkOrderCommentController::class, 'comments']);
    Route::middleware('check.permission:os.work_order.update')->post('comments', [WorkOrderCommentController::class, 'storeComment']);
});

// ── Quote nested routes ──
Route::prefix('quotes/{quote}')->middleware('check.permission:quotes.quote.view')->group(function () {
    Route::get('items', [QuoteController::class, 'items']);
    Route::middleware('check.permission:quotes.quote.update')->post('items', [QuoteController::class, 'storeNestedItem']);
});
