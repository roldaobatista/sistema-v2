<?php

/**
 * Routes: LGPD (Proteção de Dados)
 * RF-11.1 a RF-11.9
 */

use App\Http\Controllers\Api\V1\Lgpd\LgpdConsentLogController;
use App\Http\Controllers\Api\V1\Lgpd\LgpdDataRequestController;
use App\Http\Controllers\Api\V1\Lgpd\LgpdDataTreatmentController;
use App\Http\Controllers\Api\V1\Lgpd\LgpdDpoConfigController;
use App\Http\Controllers\Api\V1\Lgpd\LgpdSecurityIncidentController;
use Illuminate\Support\Facades\Route;

// RF-11.1: Base legal por tipo de tratamento
Route::middleware('check.permission:lgpd.treatment.view')->group(function () {
    Route::get('lgpd/treatments', [LgpdDataTreatmentController::class, 'index']);
    Route::get('lgpd/treatments/{id}', [LgpdDataTreatmentController::class, 'show']);
});
Route::middleware('check.permission:lgpd.treatment.create')->group(function () {
    Route::post('lgpd/treatments', [LgpdDataTreatmentController::class, 'store']);
    Route::put('lgpd/treatments/{id}', [LgpdDataTreatmentController::class, 'update']);
});
Route::middleware('check.permission:lgpd.treatment.delete')
    ->delete('lgpd/treatments/{id}', [LgpdDataTreatmentController::class, 'destroy']);

// RF-11.5: Log de consentimento
Route::middleware('check.permission:lgpd.consent.view')->group(function () {
    Route::get('lgpd/consents', [LgpdConsentLogController::class, 'index']);
    Route::get('lgpd/consents/{id}', [LgpdConsentLogController::class, 'show']);
});
Route::middleware('check.permission:lgpd.consent.create')
    ->post('lgpd/consents', [LgpdConsentLogController::class, 'store']);
Route::middleware('check.permission:lgpd.consent.revoke')
    ->post('lgpd/consents/{id}/revoke', [LgpdConsentLogController::class, 'revoke']);

// RF-11.2 + RF-11.3 + RF-11.4: Solicitações do titular
Route::middleware('check.permission:lgpd.request.view')->group(function () {
    Route::get('lgpd/requests', [LgpdDataRequestController::class, 'index']);
    Route::get('lgpd/requests/overdue', [LgpdDataRequestController::class, 'overdue']);
    Route::get('lgpd/requests/{id}', [LgpdDataRequestController::class, 'show']);
});
Route::middleware('check.permission:lgpd.request.create')
    ->post('lgpd/requests', [LgpdDataRequestController::class, 'store']);
Route::middleware('check.permission:lgpd.request.respond')
    ->post('lgpd/requests/{id}/respond', [LgpdDataRequestController::class, 'respond']);

// RF-11.7: DPO por tenant
Route::middleware('check.permission:lgpd.dpo.view')
    ->get('lgpd/dpo', [LgpdDpoConfigController::class, 'show']);
Route::middleware('check.permission:lgpd.dpo.manage')
    ->put('lgpd/dpo', [LgpdDpoConfigController::class, 'upsert']);

// RF-11.8: Incidentes de segurança
Route::middleware('check.permission:lgpd.incident.view')->group(function () {
    Route::get('lgpd/incidents', [LgpdSecurityIncidentController::class, 'index']);
    Route::get('lgpd/incidents/{id}', [LgpdSecurityIncidentController::class, 'show']);
    Route::get('lgpd/incidents/{id}/anpd-report', [LgpdSecurityIncidentController::class, 'generateAnpdReport']);
});
Route::middleware('check.permission:lgpd.incident.create')
    ->post('lgpd/incidents', [LgpdSecurityIncidentController::class, 'store']);
Route::middleware('check.permission:lgpd.incident.update')
    ->put('lgpd/incidents/{id}', [LgpdSecurityIncidentController::class, 'update']);
