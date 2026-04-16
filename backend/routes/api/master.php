<?php

use App\Http\Controllers\Api\V1\BatchExportController;
use App\Http\Controllers\Api\V1\CatalogController;
use App\Http\Controllers\Api\V1\CrmController;
use App\Http\Controllers\Api\V1\Customer\CustomerMergeController;
use App\Http\Controllers\Api\V1\Master\CustomerController;
use App\Http\Controllers\Api\V1\Master\ProductController;
use App\Http\Controllers\Api\V1\Master\ServiceController;
use App\Http\Controllers\Api\V1\Master\SupplierController;
use App\Http\Controllers\Api\V1\PriceHistoryController;

/**
 * Rotas: Cadastros (clientes, produtos, serviços, fornecedores), Catálogo, Histórico de preços, Exportação em lote, CRM/BI.
 * Carregado de dentro do grupo auth:sanctum + check.tenant em routes/api.php.
 */

// Cadastros (namespace completo para route:cache em produção)
Route::middleware('check.permission:cadastros.customer.view')->get('customers', [CustomerController::class, 'index']);
Route::middleware('check.permission:cadastros.customer.view')->get('customers/duplicates', [CustomerMergeController::class, 'searchDuplicates']);
Route::middleware('check.permission:cadastros.customer.update')->get('customers/search-duplicates', [CustomerMergeController::class, 'searchDuplicates']);
Route::middleware('check.permission:cadastros.customer.update')->post('customers/merge', [CustomerMergeController::class, 'merge']);
Route::middleware('check.permission:cadastros.customer.view')->get('customers/options', [CustomerController::class, 'options']);
Route::middleware('check.permission:cadastros.customer.view')->get('customers/export', [BatchExportController::class, 'exportCustomers']);
Route::middleware('check.permission:cadastros.customer.view')->get('customers/{customer}/stats', [CustomerController::class, 'stats']);
Route::middleware('check.permission:cadastros.customer.view')->get('customers/{customer}', [CustomerController::class, 'show']);
Route::middleware('check.permission:cadastros.customer.view')->get('customers/{customer}/documents', [CustomerController::class, 'documents']);
Route::middleware('check.permission:cadastros.customer.update')->post('customers/{customer}/documents', [CustomerController::class, 'storeDocument']);
Route::middleware('check.permission:cadastros.customer.create')->post('customers', [CustomerController::class, 'store']);
Route::middleware('check.permission:cadastros.customer.update')->put('customers/{customer}', [CustomerController::class, 'update']);
Route::middleware('check.permission:cadastros.customer.delete')->delete('customers/{customer}', [CustomerController::class, 'destroy']);

Route::middleware('check.permission:cadastros.product.view')->group(function () {
    Route::get('products', [ProductController::class, 'index']);
    Route::get('products/{product}', [ProductController::class, 'show']);
    Route::get('product-categories', [ProductController::class, 'categories']);
});
Route::middleware('check.permission:cadastros.product.create')->group(function () {
    Route::post('products', [ProductController::class, 'store']);
    Route::post('product-categories', [ProductController::class, 'storeCategory']);
});
Route::middleware('check.permission:cadastros.product.update')->group(function () {
    Route::put('products/{product}', [ProductController::class, 'update']);
    Route::put('product-categories/{category}', [ProductController::class, 'updateCategory']);
});
Route::middleware('check.permission:cadastros.product.delete')->group(function () {
    Route::delete('products/{product}', [ProductController::class, 'destroy']);
    Route::delete('product-categories/{category}', [ProductController::class, 'destroyCategory']);
});

Route::middleware('check.permission:cadastros.service.view')->group(function () {
    Route::get('services', [ServiceController::class, 'index']);
    Route::get('services/{service}', [ServiceController::class, 'show']);
    Route::get('service-categories', [ServiceController::class, 'categories']);
});
Route::middleware('check.permission:cadastros.service.create')->group(function () {
    Route::post('services', [ServiceController::class, 'store']);
    Route::post('service-categories', [ServiceController::class, 'storeCategory']);
});
Route::middleware('check.permission:cadastros.service.update')->group(function () {
    Route::put('services/{service}', [ServiceController::class, 'update']);
    Route::put('service-categories/{category}', [ServiceController::class, 'updateCategory']);
});
Route::middleware('check.permission:cadastros.service.delete')->group(function () {
    Route::delete('services/{service}', [ServiceController::class, 'destroy']);
    Route::delete('service-categories/{category}', [ServiceController::class, 'destroyCategory']);
});

// Fornecedores
Route::middleware('check.permission:cadastros.supplier.view')->group(function () {
    Route::get('suppliers', [SupplierController::class, 'index']);
    Route::get('suppliers/{supplier}', [SupplierController::class, 'show']);
});
Route::middleware('check.permission:cadastros.supplier.create')->post('suppliers', [SupplierController::class, 'store']);
Route::middleware('check.permission:cadastros.supplier.update')->put('suppliers/{supplier}', [SupplierController::class, 'update']);
Route::middleware('check.permission:cadastros.supplier.delete')->delete('suppliers/{supplier}', [SupplierController::class, 'destroy']);

// Catálogo de Serviços (editável, link compartilhável)
Route::middleware('check.permission:catalog.view')->group(function () {
    Route::get('catalogs', [CatalogController::class, 'index']);
    Route::get('catalogs/{catalog}', [CatalogController::class, 'show']);
    Route::get('catalogs/{catalog}/items', [CatalogController::class, 'items']);
});
Route::middleware('check.permission:catalog.manage')->group(function () {
    Route::post('catalogs', [CatalogController::class, 'store']);
    Route::put('catalogs/{catalog}', [CatalogController::class, 'update']);
    Route::delete('catalogs/{catalog}', [CatalogController::class, 'destroy']);
    Route::post('catalogs/{catalog}/items', [CatalogController::class, 'storeItem']);
    Route::put('catalogs/{catalog}/items/{item}', [CatalogController::class, 'updateItem']);
    Route::delete('catalogs/{catalog}/items/{item}', [CatalogController::class, 'destroyItem']);
    Route::post('catalogs/{catalog}/items/{item}/image', [CatalogController::class, 'uploadImage'])->middleware('throttle:tenant-uploads');
    Route::post('catalogs/{catalog}/reorder', [CatalogController::class, 'reorderItems']);
});

// Histórico de Preços
Route::middleware('check.permission:cadastros.product.view')->group(function () {
    Route::get('price-history', [PriceHistoryController::class, 'index']);
    Route::get('products/{product}/price-history', [PriceHistoryController::class, 'forProduct']);
    Route::get('services/{service}/price-history', [PriceHistoryController::class, 'forService']);
    Route::get('customers/{customer}/item-prices', [PriceHistoryController::class, 'customerItemPrices']);
});

// Exportação em Lote
Route::middleware('check.permission:cadastros.customer.view')->group(function () {
    Route::get('batch-export/entities', [BatchExportController::class, 'entities']);
    Route::post('batch-export/csv', [BatchExportController::class, 'exportCsv']);
    Route::post('batch-export/print', [BatchExportController::class, 'batchPrint']);
    Route::get('export/customers', [BatchExportController::class, 'exportCustomers']);
});

// CRM & BI
Route::middleware('check.permission:reports.crm_report.view')->group(function () {
    Route::get('crm/customer-360/{customer}', [CrmController::class, 'customer360']);
    Route::get('crm/customer-360/{id}/pdf', [CrmController::class, 'export360']);
    Route::get('crm/dashboard', [CrmController::class, 'dashboard']);
});
