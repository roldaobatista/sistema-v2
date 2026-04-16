<?php

use App\Http\Controllers\Api\V1\PseiSubmissionController;
use App\Http\Controllers\Api\V1\RepairSealAlertController;
use App\Http\Controllers\Api\V1\RepairSealBatchController;
use App\Http\Controllers\Api\V1\RepairSealController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Repair Seals — Selos de Reparo e Lacres
|--------------------------------------------------------------------------
| Controle completo de selos Inmetro e lacres: lotes, atribuição,
| uso em OS, integração PSEI, alertas de prazo, inventário por técnico.
*/

// ─── Selos ──────────────────────────────────────────────────────
Route::prefix('repair-seals')->group(function () {
    // Visualização (repair_seals.view)
    Route::middleware('check.permission:repair_seals.view')->group(function () {
        Route::get('/', [RepairSealController::class, 'index']);
        Route::get('/dashboard', [RepairSealController::class, 'dashboard']);
        Route::get('/overdue', [RepairSealController::class, 'overdue']);
        Route::get('/pending-psei', [RepairSealController::class, 'pendingPsei']);
        Route::get('/export', [RepairSealController::class, 'export']);
        Route::get('/{id}', [RepairSealController::class, 'show'])->whereNumber('id');
    });

    // Uso pelo técnico (repair_seals.use)
    Route::middleware('check.permission:repair_seals.use')->group(function () {
        Route::get('/my-inventory', [RepairSealController::class, 'myInventory']);
        Route::post('/use', [RepairSealController::class, 'registerUsage']);
        Route::post('/return', [RepairSealController::class, 'returnSeals']);
        Route::patch('/{id}/report-damage', [RepairSealController::class, 'reportDamage'])->whereNumber('id');
    });

    // Gestão (repair_seals.manage)
    Route::middleware('check.permission:repair_seals.manage')->group(function () {
        Route::get('/technician/{id}/inventory', [RepairSealController::class, 'technicianInventory'])->whereNumber('id');
        Route::post('/assign', [RepairSealController::class, 'assignToTechnician']);
        Route::post('/transfer', [RepairSealController::class, 'transfer']);
    });
});

// ─── Lotes ──────────────────────────────────────────────────────
Route::prefix('repair-seal-batches')->middleware('check.permission:repair_seals.manage')->group(function () {
    Route::get('/', [RepairSealBatchController::class, 'index']);
    Route::post('/', [RepairSealBatchController::class, 'store']);
    Route::get('/{id}', [RepairSealBatchController::class, 'show'])->whereNumber('id');
});

// ─── Alertas ────────────────────────────────────────────────────
Route::prefix('repair-seal-alerts')->group(function () {
    Route::middleware('check.permission:repair_seals.view')->group(function () {
        Route::get('/', [RepairSealAlertController::class, 'index']);
    });

    Route::middleware('check.permission:repair_seals.use')->group(function () {
        Route::get('/my-alerts', [RepairSealAlertController::class, 'myAlerts']);
        Route::patch('/{id}/acknowledge', [RepairSealAlertController::class, 'acknowledge'])->whereNumber('id');
    });
});

// ─── PSEI Submissions ───────────────────────────────────────────
Route::prefix('psei-submissions')->middleware('check.permission:repair_seals.manage')->group(function () {
    Route::get('/', [PseiSubmissionController::class, 'index']);
    Route::get('/{id}', [PseiSubmissionController::class, 'show'])->whereNumber('id');
    Route::post('/{sealId}/retry', [PseiSubmissionController::class, 'retry'])->whereNumber('sealId');
});
