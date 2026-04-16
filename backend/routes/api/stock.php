<?php

/**
 * Routes: Estoque, Armazens, Conciliacao Bancaria
 * Extracted from api.php - Phase 10 Modularization
 * Original lines: 85-262
 */

use App\Http\Controllers\Api\V1\BankReconciliationController;
use App\Http\Controllers\Api\V1\BatchController;
use App\Http\Controllers\Api\V1\InventoryController;
use App\Http\Controllers\Api\V1\InventoryPwaController;
use App\Http\Controllers\Api\V1\KardexController;
use App\Http\Controllers\Api\V1\ProductKitController;
use App\Http\Controllers\Api\V1\ReconciliationRuleController;
use App\Http\Controllers\Api\V1\Stock\QrCodeInventoryController;
use App\Http\Controllers\Api\V1\StockAdvancedController;
use App\Http\Controllers\Api\V1\StockController;
use App\Http\Controllers\Api\V1\StockIntegrationController;
use App\Http\Controllers\Api\V1\StockIntelligenceController;
use App\Http\Controllers\Api\V1\StockLabelController;
use App\Http\Controllers\Api\V1\StockTransferController;
use App\Http\Controllers\Api\V1\UsedStockItemController;
use App\Http\Controllers\Api\V1\WarehouseController;
use App\Http\Controllers\Api\V1\WarehouseStockController;
use App\Http\Controllers\Api\V1\XmlImportController;
use Illuminate\Support\Facades\Route;

// Estoque
Route::middleware('check.permission:estoque.movement.view')->group(function () {
    Route::get('stock/movements', [StockController::class, 'movements']);
    Route::get('stock/summary', [StockController::class, 'summary']);
    Route::get('stock/low-alerts', [StockController::class, 'lowStockAlerts']); // primary
    Route::get('stock/low-stock-alerts', [StockController::class, 'lowStockAlerts']); // compat alias

    // Kits
    Route::get('products/{product}/kit', [ProductKitController::class, 'index']);
    Route::middleware('check.permission:cadastros.product.update')->post('products/{product}/kit', [ProductKitController::class, 'store']);
    Route::middleware('check.permission:cadastros.product.update')->delete('products/{product}/kit/{childId}', [ProductKitController::class, 'destroy']);

    // Inventário Cego
    Route::middleware('check.permission:estoque.inventory.view')->get('inventories', [InventoryController::class, 'index']);
    Route::middleware('check.permission:estoque.inventory.create')->post('inventories', [InventoryController::class, 'store']);
    Route::middleware('check.permission:estoque.inventory.view')->get('inventories/{inventory}', [InventoryController::class, 'show']);
    Route::middleware('check.permission:estoque.inventory.create')->put('inventories/{inventory}/items/{item}', [InventoryController::class, 'updateItem']);
    Route::middleware('check.permission:estoque.inventory.create')->post('inventories/{inventory}/complete', [InventoryController::class, 'complete']);
    Route::middleware('check.permission:estoque.inventory.create')->post('inventories/{inventory}/cancel', [InventoryController::class, 'cancel']);

    // Aliases de compatibilidade para frontend
    Route::middleware('check.permission:estoque.inventory.view')->get('stock/inventories', [InventoryController::class, 'index']);
    Route::middleware('check.permission:estoque.view')->get('stock/inventory-pwa/my-warehouses', [InventoryPwaController::class, 'myWarehouses']);
    Route::middleware('check.permission:estoque.view')->get('stock/inventory-pwa/warehouses/{warehouse}/products', [InventoryPwaController::class, 'warehouseProducts']);
    Route::middleware('check.permission:estoque.movement.create')->post('stock/inventory-pwa/submit-counts', [InventoryPwaController::class, 'submitCounts']);
    Route::middleware('check.permission:estoque.inventory.create')->post('stock/inventories', [InventoryController::class, 'store']);
    Route::middleware('check.permission:estoque.inventory.view')->get('stock/inventories/{inventory}', [InventoryController::class, 'show']);
    Route::middleware('check.permission:estoque.inventory.create')->put('stock/inventories/{inventory}/items/{item}', [InventoryController::class, 'updateItem']);
    Route::middleware('check.permission:estoque.inventory.create')->post('stock/inventories/{inventory}/complete', [InventoryController::class, 'complete']);
    Route::middleware('check.permission:estoque.inventory.create')->post('stock/inventories/{inventory}/cancel', [InventoryController::class, 'cancel']);
    Route::middleware('check.permission:estoque.warehouse.view')->get('stock/warehouses', [WarehouseController::class, 'index']);

    // Etiquetas de estoque
    Route::middleware('check.permission:estoque.label.print')->get('stock/labels/formats', [StockLabelController::class, 'formats']);
    Route::middleware('check.permission:estoque.label.print')->get('stock/labels/preview', [StockLabelController::class, 'preview']);
    Route::middleware('check.permission:estoque.label.print')->post('stock/labels/generate', [StockLabelController::class, 'generate']);

    // Kardex
    Route::middleware('check.permission:estoque.movement.view')->get('products/{product}/kardex', [KardexController::class, 'show']);
    Route::middleware('check.permission:estoque.movement.view')->get('stock/products/{product}/kardex', [KardexController::class, 'show']);

    // Inteligência de Estoque
    Route::middleware('check.permission:estoque.movement.view')->get('stock/intelligence/abc-curve', [StockIntelligenceController::class, 'abcCurve']);
    Route::middleware('check.permission:estoque.movement.view')->get('stock/intelligence/turnover', [StockIntelligenceController::class, 'turnover']);
    Route::middleware('check.permission:estoque.movement.view')->get('stock/intelligence/average-cost', [StockIntelligenceController::class, 'averageCost']);
    Route::middleware('check.permission:estoque.movement.view')->get('stock/intelligence/reorder-points', [StockIntelligenceController::class, 'reorderPoints']);
    Route::middleware('check.permission:estoque.movement.create')->post('stock/intelligence/reorder-points/auto-request', [StockIntelligenceController::class, 'autoRequest']);
    Route::middleware('check.permission:estoque.movement.view')->get('stock/intelligence/reservations', [StockIntelligenceController::class, 'reservations']);
    Route::middleware('check.permission:estoque.movement.view')->get('stock/intelligence/expiring-batches', [StockIntelligenceController::class, 'expiringBatches']);
    Route::middleware('check.permission:estoque.movement.view')->get('stock/intelligence/stale-products', [StockIntelligenceController::class, 'staleProducts']);

    // ═══ Peças Usadas (Used Stock Items) ═══
    Route::middleware('check.permission:estoque.used_stock.view')->get('stock/used-items', [UsedStockItemController::class, 'index']);
    Route::middleware('check.permission:estoque.used_stock.report')->post('stock/used-items/{usedStockItem}/report', [UsedStockItemController::class, 'report']);
    Route::middleware('check.permission:estoque.used_stock.confirm')->post('stock/used-items/{usedStockItem}/confirm-return', [UsedStockItemController::class, 'confirmReturn']);
    Route::middleware('check.permission:estoque.used_stock.confirm')->post('stock/used-items/{usedStockItem}/confirm-write-off', [UsedStockItemController::class, 'confirmWriteOff']);

    // ═══ Números de Série ═══
    Route::middleware('check.permission:estoque.movement.view')->get('stock/serial-numbers', [StockAdvancedController::class, 'serialNumbers']);
    Route::middleware('check.permission:estoque.movement.create')->post('stock/serial-numbers', [StockAdvancedController::class, 'storeSerialNumber']);

    // ═══ Stock Advanced Analytics ═══
    Route::middleware('check.permission:estoque.movement.view')->get('stock/advanced/slow-moving', [StockAdvancedController::class, 'slowMovingAnalysis']);
    Route::middleware('check.permission:estoque.movement.view')->get('stock/advanced/auto-reorder', [StockAdvancedController::class, 'autoReorder']);
    Route::middleware('check.permission:estoque.movement.view')->get('stock/advanced/suggest-transfers', [StockAdvancedController::class, 'suggestTransfers']);
});
Route::middleware('check.permission:estoque.movement.create')->group(function () {
    Route::post('stock/movements', [StockController::class, 'store']);
    Route::post('stock/import-xml', [XmlImportController::class, 'import']);
    Route::post('stock/scan-qr', [QrCodeInventoryController::class, 'scan']);
});
// ═══ Transferências de Estoque ═══
Route::middleware('check.permission:estoque.view')->group(function () {
    // compat alias: manter o contrato antigo em /stock/transfers com a mesma blindagem da superfície nova /stock-advanced/transfers
    Route::get('stock/transfers', [StockTransferController::class, 'index']);
    Route::get('stock/transfers/{transfer}', [StockTransferController::class, 'show']);
    Route::middleware('check.permission:estoque.transfer.create')->post('stock/transfers', [StockTransferController::class, 'store']);
    Route::middleware('check.permission:estoque.transfer.accept')->post('stock/transfers/{transfer}/accept', [StockTransferController::class, 'accept']);
    Route::middleware('check.permission:estoque.transfer.accept')->post('stock/transfers/{transfer}/reject', [StockTransferController::class, 'reject']);
});

// ═══ Cotação de Compras ═══
Route::middleware('check.permission:estoque.movement.view')->group(function () {
    Route::get('purchase-quotes', [StockIntegrationController::class, 'purchaseQuoteIndex']);
    Route::get('purchase-quotes/{purchaseQuote}', [StockIntegrationController::class, 'purchaseQuoteShow']);
});
Route::middleware('check.permission:estoque.movement.create')->group(function () {
    Route::post('purchase-quotes', [StockIntegrationController::class, 'purchaseQuoteStore']);
    Route::put('purchase-quotes/{purchaseQuote}', [StockIntegrationController::class, 'purchaseQuoteUpdate']);
    Route::delete('purchase-quotes/{purchaseQuote}', [StockIntegrationController::class, 'purchaseQuoteDestroy']);
});

// ═══ Solicitação de Material ═══
Route::middleware('check.permission:estoque.movement.view')->group(function () {
    Route::get('material-requests', [StockIntegrationController::class, 'materialRequestIndex']);
    Route::get('material-requests/{materialRequest}', [StockIntegrationController::class, 'materialRequestShow']);
});
Route::middleware('check.permission:estoque.movement.create')->group(function () {
    Route::post('material-requests', [StockIntegrationController::class, 'materialRequestStore']);
    Route::put('material-requests/{materialRequest}', [StockIntegrationController::class, 'materialRequestUpdate']);
    Route::delete('material-requests/{materialRequest}', [StockIntegrationController::class, 'materialRequestDestroy']);
});

// ═══ Tags RFID/QR ═══
Route::middleware('check.permission:estoque.movement.view')->group(function () {
    Route::get('asset-tags', [StockIntegrationController::class, 'assetTagIndex']);
    Route::get('asset-tags/{assetTag}', [StockIntegrationController::class, 'assetTagShow']);
});
Route::middleware('check.permission:estoque.movement.create')->group(function () {
    Route::post('asset-tags', [StockIntegrationController::class, 'assetTagStore']);
    Route::put('asset-tags/{assetTag}', [StockIntegrationController::class, 'assetTagUpdate']);
    Route::post('asset-tags/{assetTag}/scan', [StockIntegrationController::class, 'assetTagScan']);
});

// ═══ RMA (Devolução) ═══
Route::middleware('check.permission:estoque.movement.view')->group(function () {
    Route::get('rma', [StockIntegrationController::class, 'rmaIndex']);
    Route::get('rma/{rmaRequest}', [StockIntegrationController::class, 'rmaShow']);
});
Route::middleware('check.permission:estoque.movement.create')->group(function () {
    Route::post('rma', [StockIntegrationController::class, 'rmaStore']);
    Route::put('rma/{rmaRequest}', [StockIntegrationController::class, 'rmaUpdate']);
});

// ═══ Descarte Ecológico ═══
Route::middleware('check.permission:estoque.movement.view')->group(function () {
    Route::get('stock-disposals', [StockIntegrationController::class, 'disposalIndex']);
    Route::get('stock-disposals/{stockDisposal}', [StockIntegrationController::class, 'disposalShow']);
});
Route::middleware('check.permission:estoque.movement.create')->group(function () {
    Route::post('stock-disposals', [StockIntegrationController::class, 'disposalStore']);
    Route::put('stock-disposals/{stockDisposal}', [StockIntegrationController::class, 'disposalUpdate']);
});

// Armazéns e Saldos por Local
Route::middleware('check.permission:estoque.warehouse.view')->group(function () {
    Route::get('warehouses', [WarehouseController::class, 'index']);
    Route::get('warehouses/{warehouse}', [WarehouseController::class, 'show']);
    Route::get('batches', [BatchController::class, 'index']);
    Route::get('batches/{batch}', [BatchController::class, 'show']);
    Route::get('warehouse-stocks', [WarehouseStockController::class, 'index']);
    Route::get('warehouses/{warehouse}/stocks', [WarehouseStockController::class, 'byWarehouse']);
    Route::get('products/{product}/warehouse-stocks', [WarehouseStockController::class, 'byProduct']);
});
Route::middleware('check.permission:estoque.warehouse.create')->group(function () {
    Route::post('warehouses', [WarehouseController::class, 'store']);
    Route::put('warehouses/{warehouse}', [WarehouseController::class, 'update']);
    Route::delete('warehouses/{warehouse}', [WarehouseController::class, 'destroy']);
    Route::post('batches', [BatchController::class, 'store']);
    Route::put('batches/{batch}', [BatchController::class, 'update']);
    Route::delete('batches/{batch}', [BatchController::class, 'destroy']);
});

// Conciliação Bancária (expandido com Motor de Regras)
Route::middleware('check.permission:finance.receivable.view|finance.payable.view')->group(function () {
    Route::get('bank-reconciliation/summary', [BankReconciliationController::class, 'summary']);
    Route::get('bank-reconciliation/statements', [BankReconciliationController::class, 'statements']);
    Route::get('bank-reconciliation/statements/{statement}/entries', [BankReconciliationController::class, 'entries']);
    Route::get('bank-reconciliation/entries/{entry}/suggestions', [BankReconciliationController::class, 'suggestions']);
    Route::get('bank-reconciliation/entries/{entry}/history', [BankReconciliationController::class, 'entryHistory']);
    Route::get('bank-reconciliation/search-financials', [BankReconciliationController::class, 'searchFinancials']);
    Route::get('bank-reconciliation/statements/{statement}/export', [BankReconciliationController::class, 'exportStatement']);
    Route::get('bank-reconciliation/statements/{statement}/export-pdf', [BankReconciliationController::class, 'exportPdf']);
    Route::get('bank-reconciliation/dashboard', [BankReconciliationController::class, 'dashboardData']);
});
Route::middleware('check.permission:finance.receivable.create|finance.payable.create')->group(function () {
    Route::post('bank-reconciliation/import', [BankReconciliationController::class, 'import']);
    Route::post('bank-reconciliation/entries/{entry}/match', [BankReconciliationController::class, 'matchEntry']);
    Route::post('bank-reconciliation/entries/{entry}/ignore', [BankReconciliationController::class, 'ignoreEntry']);
    Route::post('bank-reconciliation/entries/{entry}/unmatch', [BankReconciliationController::class, 'unmatchEntry']);
    Route::post('bank-reconciliation/entries/{entry}/suggest-rule', [BankReconciliationController::class, 'suggestRule']);
    Route::post('bank-reconciliation/bulk-action', [BankReconciliationController::class, 'bulkAction']);
});
Route::middleware('check.permission:finance.receivable.delete|finance.payable.delete')->delete('bank-reconciliation/statements/{statement}', [BankReconciliationController::class, 'destroyStatement']);

// Regras de Conciliação Automática
Route::middleware('check.permission:finance.receivable.view|finance.payable.view')->group(function () {
    Route::get('reconciliation-rules', [ReconciliationRuleController::class, 'index']);
    Route::get('reconciliation-rules/{rule}', [ReconciliationRuleController::class, 'show']);
    Route::post('reconciliation-rules/test', [ReconciliationRuleController::class, 'testRule']);
});
Route::middleware('check.permission:finance.receivable.create|finance.payable.create')->group(function () {
    Route::post('reconciliation-rules', [ReconciliationRuleController::class, 'store']);
    Route::put('reconciliation-rules/{rule}', [ReconciliationRuleController::class, 'update']);
    Route::post('reconciliation-rules/{rule}/toggle', [ReconciliationRuleController::class, 'toggleActive']);
});
Route::middleware('check.permission:finance.receivable.delete|finance.payable.delete')->delete('reconciliation-rules/{rule}', [ReconciliationRuleController::class, 'destroy']);
