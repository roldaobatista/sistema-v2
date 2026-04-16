<?php

/**
 * Routes: Equipamentos, Pesos Padrao, Numeracao, Notificacoes, PDF, Perfil, Filiais, Tenants
 * Extracted from api.php - Phase 10 Modularization
 * Original lines: 1197-1318
 */

use App\Http\Controllers\Api\V1\BranchController;
use App\Http\Controllers\Api\V1\Calibration\AccreditationScopeController;
use App\Http\Controllers\Api\V1\Equipment\EquipmentMaintenanceController;
use App\Http\Controllers\Api\V1\EquipmentController;
use App\Http\Controllers\Api\V1\EquipmentModelController;
use App\Http\Controllers\Api\V1\Metrology\StandardWeightWearController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\NumberingSequenceController;
use App\Http\Controllers\Api\V1\PdfController;
use App\Http\Controllers\Api\V1\StandardWeightController;
use App\Http\Controllers\Api\V1\TenantController;
use App\Http\Controllers\Api\V1\TenantSettingsController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

// Equipamentos
Route::middleware('check.permission:equipments.equipment.view')->group(function () {
    Route::get('equipments', [EquipmentController::class, 'index']);
    Route::get('equipments/{equipment}', [EquipmentController::class, 'show']);
    Route::get('equipments-dashboard', [EquipmentController::class, 'dashboard']);
    Route::get('equipments-alerts', [EquipmentController::class, 'alerts']);
    Route::get('equipments-constants', [EquipmentController::class, 'constants']);
    Route::get('equipments/{equipment}/calibrations', [EquipmentController::class, 'calibrationHistory']);
    Route::get('equipments-export', [EquipmentController::class, 'exportCsv']);
    Route::get('equipment-documents/{document}/download', [EquipmentController::class, 'downloadDocument']);
});
Route::middleware('check.permission:equipments.equipment.create')->group(function () {
    Route::post('equipments', [EquipmentController::class, 'store']);
    Route::post('equipments/{equipment}/calibrations', [EquipmentController::class, 'addCalibration']);
    Route::post('equipments/{equipment}/maintenances', [EquipmentController::class, 'addMaintenance']);
    Route::post('equipments/{equipment}/documents', [EquipmentController::class, 'uploadDocument'])->middleware('throttle:tenant-uploads');
});
Route::middleware('check.permission:equipments.equipment.update')->put('equipments/{equipment}', [EquipmentController::class, 'update']);
Route::middleware('check.permission:equipments.equipment.delete')->group(function () {
    Route::delete('equipments/{equipment}', [EquipmentController::class, 'destroy']);
    Route::delete('equipment-documents/{document}', [EquipmentController::class, 'deleteDocument']);
});

// Escopos de Acreditação (RBC/Cgcre)
Route::middleware('check.permission:accreditation.scope.manage')->group(function () {
    Route::get('accreditation-scopes', [AccreditationScopeController::class, 'index']);
    Route::get('accreditation-scopes-active', [AccreditationScopeController::class, 'active']);
    Route::get('accreditation-scopes/{id}', [AccreditationScopeController::class, 'show']);
    Route::post('accreditation-scopes', [AccreditationScopeController::class, 'store']);
    Route::put('accreditation-scopes/{id}', [AccreditationScopeController::class, 'update']);
    Route::delete('accreditation-scopes/{id}', [AccreditationScopeController::class, 'destroy']);
});

// Manutenções de Equipamentos (CRUD dedicado)
Route::middleware('check.permission:equipments.equipment.view')->group(function () {
    Route::get('equipment-maintenances', [EquipmentMaintenanceController::class, 'index']);
    Route::get('equipment-maintenances/{equipmentMaintenance}', [EquipmentMaintenanceController::class, 'show']);
});
Route::middleware('check.permission:equipments.equipment.create')->post('equipment-maintenances', [EquipmentMaintenanceController::class, 'store']);
Route::middleware('check.permission:equipments.equipment.update')->put('equipment-maintenances/{equipmentMaintenance}', [EquipmentMaintenanceController::class, 'update']);
Route::middleware('check.permission:equipments.equipment.delete')->delete('equipment-maintenances/{equipmentMaintenance}', [EquipmentMaintenanceController::class, 'destroy']);

// Modelos de balança (equipment models / peças compatíveis)
Route::middleware('check.permission:equipments.equipment_model.view')->group(function () {
    Route::get('equipment-models', [EquipmentModelController::class, 'index']);
    Route::get('equipment-models/{equipmentModel}', [EquipmentModelController::class, 'show']);
});
Route::middleware('check.permission:equipments.equipment_model.create')->post('equipment-models', [EquipmentModelController::class, 'store']);
Route::middleware('check.permission:equipments.equipment_model.update')->group(function () {
    Route::put('equipment-models/{equipmentModel}', [EquipmentModelController::class, 'update']);
    Route::put('equipment-models/{equipmentModel}/products', [EquipmentModelController::class, 'syncProducts']);
});
Route::middleware('check.permission:equipments.equipment_model.delete')->delete('equipment-models/{equipmentModel}', [EquipmentModelController::class, 'destroy']);

// Pesos Padrão (Standard Weights)
Route::middleware('check.permission:equipments.standard_weight.view')->group(function () {
    Route::get('standard-weights', [StandardWeightController::class, 'index']);
    Route::get('standard-weights/expiring', [StandardWeightController::class, 'expiring']);
    Route::get('standard-weights/constants', [StandardWeightController::class, 'constants']);
    Route::get('standard-weights/export', [StandardWeightController::class, 'exportCsv']);
    Route::get('standard-weights/{standardWeight}', [StandardWeightController::class, 'show']);
});
Route::middleware('check.permission:equipments.standard_weight.view')
    ->post('standard-weights/{id}/predict-wear', [StandardWeightWearController::class, 'predict']);
Route::middleware('check.permission:equipments.standard_weight.create')->post('standard-weights', [StandardWeightController::class, 'store']);
Route::middleware('check.permission:equipments.standard_weight.update')->put('standard-weights/{standardWeight}', [StandardWeightController::class, 'update']);
Route::middleware('check.permission:equipments.standard_weight.delete')->delete('standard-weights/{standardWeight}', [StandardWeightController::class, 'destroy']);

// Numeração / Sequências
Route::middleware('check.permission:platform.settings.manage')->group(function () {
    Route::get('numbering-sequences', [NumberingSequenceController::class, 'index']);
    Route::put('numbering-sequences/{numberingSequence}', [NumberingSequenceController::class, 'update']);
    Route::get('numbering-sequences/{numberingSequence}/preview', [NumberingSequenceController::class, 'preview']);
});

// Notificaçõesções
Route::middleware('check.permission:notifications.notification.view')->group(function () {
    Route::get('notifications', [NotificationController::class, 'index']);
    Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount']);
});
Route::middleware('check.permission:notifications.notification.update')->group(function () {
    Route::put('notifications/{notification}/read', [NotificationController::class, 'markRead']);
    Route::put('notifications/read-all', [NotificationController::class, 'markAllRead']);
    Route::post('notifications/mark-read', [NotificationController::class, 'markAllRead']); // compat: cliente legado
    Route::post('notifications/mark-all-read', [NotificationController::class, 'markAllRead']);
    Route::delete('notifications/{notification}', [NotificationController::class, 'destroy']);
});

// PDF / Exports
// NOTA: work-orders/{work_order}/pdf já definida em work-orders.php (WorkOrderController::downloadPdf)
Route::middleware('check.permission:quotes.quote.view')->get('quotes/{quote}/pdf', [PdfController::class, 'quote']);
Route::middleware('check.permission:equipments.equipment.view')->get('equipments/{equipment}/calibrations/{calibration}/pdf', [PdfController::class, 'calibrationCertificate']);
// (Rota reports/{type}/export já registrada na seção de Relatórios acima â€” L477)

// Perfil do Usuário (com permissões/roles expandidos)
// FIX-B1: Usar FQCN para Api\V1\UserController (não confundir com Iam\UserController importado no topo)
Route::get('profile', [UserController::class, 'me']);
Route::put('profile', [UserController::class, 'updateProfile']);
Route::post('profile/change-password', [UserController::class, 'changePassword']);

// Filiais
Route::middleware('check.permission:platform.branch.view')->group(function () {
    Route::get('branches', [BranchController::class, 'index']);
    Route::get('branches/{branch}', [BranchController::class, 'show']);
});
Route::middleware('check.permission:platform.branch.create')->post('branches', [BranchController::class, 'store']);
Route::middleware('check.permission:platform.branch.update')->put('branches/{branch}', [BranchController::class, 'update']);
Route::middleware('check.permission:platform.branch.delete')->delete('branches/{branch}', [BranchController::class, 'destroy']);

// Tenant Management
Route::middleware('check.permission:platform.tenant.view')->group(function () {
    Route::get('tenants', [TenantController::class, 'index']);
    Route::get('tenants/{tenant}', [TenantController::class, 'show']);
    Route::get('tenants-stats', [TenantController::class, 'stats']);
    Route::get('tenants/{tenant}/roles', [TenantController::class, 'availableRoles']);
});
Route::middleware('check.permission:platform.tenant.create')->group(function () {
    Route::post('tenants', [TenantController::class, 'store']);
    Route::post('tenants/{tenant}/invite', [TenantController::class, 'invite']);
});
Route::middleware('check.permission:platform.tenant.update')->group(function () {
    Route::post('tenants/bulk-status', [TenantController::class, 'bulkStatus']);
    Route::put('tenants/{tenant}', [TenantController::class, 'update']);
    Route::post('tenants/{tenant}/logo', [TenantController::class, 'updateLogo']);
    Route::delete('tenants/{tenant}/users/{user}', [TenantController::class, 'removeUser']);
});
Route::middleware('check.permission:platform.tenant.delete')->delete('tenants/{tenant}', [TenantController::class, 'destroy']);

// Tenant Settings
Route::middleware('check.permission:platform.settings.view')->group(function () {
    Route::get('tenant-settings', [TenantSettingsController::class, 'index']);
    Route::get('tenant-settings/{key}', [TenantSettingsController::class, 'show']);
});
Route::middleware('check.permission:platform.settings.manage')->group(function () {
    Route::post('tenant-settings', [TenantSettingsController::class, 'upsert']);
    Route::delete('tenant-settings/{key}', [TenantSettingsController::class, 'destroy']);
});
