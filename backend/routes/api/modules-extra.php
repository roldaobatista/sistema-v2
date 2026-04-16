<?php

use App\Http\Controllers\Api\V1\Contracts\ContractAddendumController;
use App\Http\Controllers\Api\V1\Contracts\ContractMeasurementController;
use App\Http\Controllers\Api\V1\Helpdesk\EscalationRuleController;
use App\Http\Controllers\Api\V1\Helpdesk\SlaViolationController;
use App\Http\Controllers\Api\V1\Helpdesk\TicketCategoryController;
use Illuminate\Support\Facades\Route;

Route::prefix('helpdesk')->group(function () {
    Route::middleware('check.permission:helpdesk.ticket_category.view')->group(function () {
        Route::apiResource('ticket-categories', TicketCategoryController::class)->only(['index', 'show']);
        Route::apiResource('sla-violations', SlaViolationController::class)->only(['index', 'show']);
    });
    Route::middleware('check.permission:helpdesk.ticket_category.manage')->group(function () {
        Route::apiResource('ticket-categories', TicketCategoryController::class)->only(['store', 'update', 'destroy']);
    });
    Route::middleware('check.permission:helpdesk.escalation_rule.view')->group(function () {
        Route::apiResource('escalation-rules', EscalationRuleController::class)->only(['index', 'show']);
    });
    Route::middleware('check.permission:helpdesk.escalation_rule.manage')->group(function () {
        Route::apiResource('escalation-rules', EscalationRuleController::class)->only(['store', 'update', 'destroy']);
    });
});

Route::prefix('contracts')->group(function () {
    Route::middleware('check.permission:contracts.measurement.view')->group(function () {
        Route::apiResource('measurements', ContractMeasurementController::class)->only(['index', 'show']);
        Route::apiResource('addendums', ContractAddendumController::class)->only(['index', 'show']);
    });
    Route::middleware('check.permission:contracts.measurement.manage')->group(function () {
        Route::apiResource('measurements', ContractMeasurementController::class)->only(['store', 'update', 'destroy']);
        Route::apiResource('addendums', ContractAddendumController::class)->only(['store', 'update', 'destroy']);
    });
});

use App\Http\Controllers\Api\V1\IntegrationController;
use App\Http\Controllers\Api\V1\MobileController;
use App\Http\Controllers\Api\V1\Procurement\MaterialRequestController;
use App\Http\Controllers\Api\V1\Procurement\PurchaseQuotationController;
use App\Http\Controllers\Api\V1\Procurement\SupplierController;

Route::prefix('procurement')->group(function () {
    Route::middleware('check.permission:procurement.supplier.view')->group(function () {
        Route::apiResource('suppliers', SupplierController::class)->only(['index', 'show']);
    });
    Route::middleware('check.permission:procurement.supplier.manage')->group(function () {
        Route::apiResource('suppliers', SupplierController::class)->only(['store', 'update', 'destroy']);
    });

    Route::middleware('check.permission:procurement.material_request.view')->group(function () {
        Route::apiResource('material-requests', MaterialRequestController::class)->only(['index', 'show']);
    });
    Route::middleware('check.permission:procurement.material_request.manage')->group(function () {
        Route::apiResource('material-requests', MaterialRequestController::class)->only(['store', 'update', 'destroy']);
    });

    Route::middleware('check.permission:procurement.purchase_quotation.view')->group(function () {
        Route::apiResource('purchase-quotations', PurchaseQuotationController::class)->only(['index', 'show']);
    });
    Route::middleware('check.permission:procurement.purchase_quotation.manage')->group(function () {
        Route::apiResource('purchase-quotations', PurchaseQuotationController::class)->only(['store', 'update', 'destroy']);
    });
});

// ═══ MOBILE ═══════════════════════════════════════════════════════
$mob = MobileController::class;
Route::prefix('mobile')->group(function () use ($mob) {
    Route::middleware('check.permission:platform.dashboard.view')->group(function () use ($mob) {
        Route::get('preferences', [$mob, 'userPreferences']);
        Route::put('preferences', [$mob, 'updatePreferences']);
        Route::get('sync-queue', [$mob, 'syncQueue']);
        Route::post('sync-queue', [$mob, 'addToSyncQueue']);
        Route::get('notifications', [$mob, 'interactiveNotifications']);
        Route::post('notifications/{notificationId}/respond', [$mob, 'respondToNotification']);
        Route::get('barcode-lookup', [$mob, 'barcodeLookup']);
        Route::post('signatures', [$mob, 'storeSignature']);
        Route::get('print-jobs', [$mob, 'printJobs']);
        Route::post('print-jobs', [$mob, 'createPrintJob']);
        Route::post('voice-reports', [$mob, 'storeVoiceReport']);
        Route::get('biometric-config', [$mob, 'biometricConfig']);
        Route::put('biometric-config', [$mob, 'updateBiometricConfig']);
        Route::post('photo-annotations', [$mob, 'storePhotoAnnotation']);
        Route::post('thermal-readings', [$mob, 'storeThermalReading']);
        Route::get('kiosk-config', [$mob, 'kioskConfig']);
        Route::put('kiosk-config', [$mob, 'updateKioskConfig']);
        Route::get('offline-map-regions', [$mob, 'offlineMapRegions']);
    });
});

// ═══ INTEGRATIONS ═════════════════════════════════════════════════
$integ = IntegrationController::class;
Route::prefix('integrations')->group(function () use ($integ) {
    Route::middleware('check.permission:platform.settings.view')->group(function () use ($integ) {
        Route::get('webhooks', [$integ, 'webhooks']);
        Route::get('erp-sync/status', [$integ, 'erpSyncStatus']);
        Route::get('marketplace', [$integ, 'marketplace']);
        Route::get('sso-config', [$integ, 'ssoConfig']);
        Route::get('slack-teams-config', [$integ, 'slackTeamsConfig']);
        Route::get('marketing-config', [$integ, 'marketingIntegrationConfig']);
        Route::get('swagger', [$integ, 'swaggerDoc']);
        Route::get('email-plugin/manifest', [$integ, 'emailPluginManifest']);
    });
    Route::middleware('check.permission:platform.settings.manage')->group(function () use ($integ) {
        Route::post('webhooks', [$integ, 'storeWebhook']);
        Route::delete('webhooks/{id}', [$integ, 'deleteWebhook']);
        Route::post('erp-sync/trigger', [$integ, 'triggerErpSync']);
        Route::post('marketplace/request', [$integ, 'requestPartnerIntegration']);
        Route::put('sso-config', [$integ, 'updateSsoConfig']);
        Route::post('notification-channels', [$integ, 'storeNotificationChannel']);
        Route::post('shipping/calculate', [$integ, 'calculateShipping']);
        Route::put('marketing-config', [$integ, 'updateMarketingConfig']);
        Route::post('email-plugin/webhook', [$integ, 'emailPluginWebhook']);
        Route::post('power-bi/export', [$integ, 'powerBiDataExport']);
    });
});
