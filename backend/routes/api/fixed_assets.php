<?php

use App\Http\Controllers\Api\V1\FixedAssets\AssetRecordController;
use App\Http\Controllers\Api\V1\FixedAssets\DepreciationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'check.tenant'])->group(function () {
    Route::prefix('fixed-assets')->group(function () {
        Route::middleware('check.permission:fixed_assets.dashboard.view')->get('/dashboard', [AssetRecordController::class, 'dashboard']);
        Route::middleware('check.permission:fixed_assets.inventory.manage')->get('/inventories', [AssetRecordController::class, 'inventories']);
        Route::middleware('check.permission:fixed_assets.asset.view')->get('/movements', [AssetRecordController::class, 'movements']);
        Route::middleware('check.permission:fixed_assets.depreciation.run')->post('/run-depreciation', [DepreciationController::class, 'runMonthly']);
        Route::middleware('check.permission:fixed_assets.asset.view')->get('/', [AssetRecordController::class, 'index']);
        Route::middleware('check.permission:fixed_assets.asset.create')->post('/', [AssetRecordController::class, 'store']);
        Route::middleware('check.permission:fixed_assets.asset.view')->get('/{assetRecord}', [AssetRecordController::class, 'show']);
        Route::middleware('check.permission:fixed_assets.asset.update')->put('/{assetRecord}', [AssetRecordController::class, 'update']);
        Route::middleware('check.permission:fixed_assets.asset.update')->post('/{assetRecord}/movements', [AssetRecordController::class, 'storeMovement']);
        Route::middleware('check.permission:fixed_assets.inventory.manage')->post('/{assetRecord}/inventories', [AssetRecordController::class, 'storeInventory']);
        Route::middleware('check.permission:fixed_assets.asset.dispose')->post('/{assetRecord}/dispose', [AssetRecordController::class, 'dispose']);
        Route::middleware('check.permission:fixed_assets.asset.update')->post('/{assetRecord}/suspend', [AssetRecordController::class, 'suspend']);
        Route::middleware('check.permission:fixed_assets.asset.update')->post('/{assetRecord}/reactivate', [AssetRecordController::class, 'reactivate']);
        Route::middleware('check.permission:fixed_assets.depreciation.view')->get('/{assetRecord}/depreciation-logs', [DepreciationController::class, 'logs']);
    });
});
