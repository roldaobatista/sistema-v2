<?php

use App\Http\Controllers\Api\V1\SupplierPortal\SupplierPortalController;
use Illuminate\Support\Facades\Route;

// Supplier Portal Links do not use auth:sanctum because they are token-based
Route::prefix('supplier-portal')->group(function () {
    Route::middleware('throttle:tenant-reads')->group(function () {
        Route::get('quotations/{token}', [SupplierPortalController::class, 'showQuotation']);
        Route::post('quotations/{token}/answer', [SupplierPortalController::class, 'answerQuotation']);
    });
});

Route::prefix('portal/supplier')->group(function () {
    Route::middleware('throttle:tenant-reads')->group(function () {
        Route::get('quotations/{token}', [SupplierPortalController::class, 'showQuotation']);
        Route::post('quotations/{token}/answer', [SupplierPortalController::class, 'answerQuotation']);
    });
});
