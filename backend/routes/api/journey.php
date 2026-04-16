<?php

/**
 * Routes: Motor de Jornada Operacional
 * Prefix: api/v1 (applied by bootstrap/app.php)
 * Middleware: api, auth:sanctum, check.tenant (applied by bootstrap/app.php)
 */

use App\Http\Controllers\Api\V1\Journey\BiometricConsentController;
use App\Http\Controllers\Api\V1\Journey\JourneyApprovalController;
use App\Http\Controllers\Api\V1\Journey\JourneyBlockController;
use App\Http\Controllers\Api\V1\Journey\JourneyDayController;
use App\Http\Controllers\Api\V1\Journey\JourneyPolicyController;
use App\Http\Controllers\Api\V1\Journey\OfflineSyncController;
use App\Http\Controllers\Api\V1\Journey\PayrollIntegrationController;
use App\Http\Controllers\Api\V1\Journey\TechnicianCertificationController;
use App\Http\Controllers\Api\V1\Journey\TravelExpenseController;
use App\Http\Controllers\Api\V1\Journey\TravelRequestController;
use App\Models\JourneyEntry;
use App\Models\JourneyRule;

// Route model bindings: consolidação JourneyDay→JourneyEntry, JourneyPolicy→JourneyRule
Route::bind('journeyDay', fn ($id) => JourneyEntry::findOrFail($id));
Route::bind('journeyPolicy', fn ($id) => JourneyRule::findOrFail($id));

// Journey Days
Route::middleware('check.permission:hr.clock.view')->group(function () {
    Route::get('journey/days', [JourneyDayController::class, 'index']);
    Route::get('journey/days/{journeyDay}', [JourneyDayController::class, 'show']);
});

Route::middleware('check.permission:hr.clock.manage')->group(function () {
    Route::post('journey/days/{journeyDay}/reclassify', [JourneyDayController::class, 'reclassify']);
});

// Journey Blocks
Route::middleware('check.permission:hr.clock.manage')->group(function () {
    Route::post('journey/blocks/{journeyBlock}/adjust', [JourneyBlockController::class, 'adjust']);
});

// Journey Policies
Route::middleware('check.permission:hr.clock.view')->group(function () {
    Route::get('journey/policies', [JourneyPolicyController::class, 'index']);
    Route::get('journey/policies/{journeyPolicy}', [JourneyPolicyController::class, 'show']);
});

Route::middleware('check.permission:hr.clock.manage')->group(function () {
    Route::post('journey/policies', [JourneyPolicyController::class, 'store']);
    Route::put('journey/policies/{journeyPolicy}', [JourneyPolicyController::class, 'update']);
    Route::delete('journey/policies/{journeyPolicy}', [JourneyPolicyController::class, 'destroy']);
});

// Journey Approvals
Route::middleware('check.permission:hr.clock.view')->group(function () {
    Route::get('journey/approvals/{level}/pending', [JourneyApprovalController::class, 'pending']);
});

Route::middleware('check.permission:hr.clock.manage')->group(function () {
    Route::post('journey/days/{journeyDay}/submit-approval', [JourneyApprovalController::class, 'submit']);
    Route::post('journey/days/{journeyDay}/approve/{level}', [JourneyApprovalController::class, 'approve']);
    Route::post('journey/days/{journeyDay}/reject/{level}', [JourneyApprovalController::class, 'reject']);
});

// Travel Requests
Route::middleware('check.permission:hr.clock.view')->group(function () {
    Route::get('journey/travel-requests', [TravelRequestController::class, 'index']);
    Route::get('journey/travel-requests/{travelRequest}', [TravelRequestController::class, 'show']);
});

Route::middleware('check.permission:hr.clock.manage')->group(function () {
    Route::post('journey/travel-requests', [TravelRequestController::class, 'store']);
    Route::put('journey/travel-requests/{travelRequest}', [TravelRequestController::class, 'update']);
    Route::post('journey/travel-requests/{travelRequest}/approve', [TravelRequestController::class, 'approve']);
    Route::post('journey/travel-requests/{travelRequest}/cancel', [TravelRequestController::class, 'cancel']);
    Route::delete('journey/travel-requests/{travelRequest}', [TravelRequestController::class, 'destroy']);
});

// Travel Expenses
Route::middleware('check.permission:hr.clock.view')->group(function () {
    Route::post('journey/travel-requests/{travelRequest}/expense-report', [TravelExpenseController::class, 'submitReport']);
});

Route::middleware('check.permission:hr.clock.manage')->group(function () {
    Route::post('journey/travel-requests/{travelRequest}/expense-report/approve', [TravelExpenseController::class, 'approveReport']);
});

// Technician Certifications
Route::middleware('check.permission:hr.clock.view')->group(function () {
    Route::get('journey/certifications', [TechnicianCertificationController::class, 'index']);
    Route::get('journey/certifications/expiring', [TechnicianCertificationController::class, 'expiring']);
    Route::get('journey/certifications/{technicianCertification}', [TechnicianCertificationController::class, 'show']);
    Route::post('journey/certifications/check-eligibility', [TechnicianCertificationController::class, 'checkEligibility']);
});

Route::middleware('check.permission:hr.clock.manage')->group(function () {
    Route::post('journey/certifications', [TechnicianCertificationController::class, 'store']);
    Route::put('journey/certifications/{technicianCertification}', [TechnicianCertificationController::class, 'update']);
    Route::delete('journey/certifications/{technicianCertification}', [TechnicianCertificationController::class, 'destroy']);
});

// Biometric Consents (LGPD)
Route::middleware('check.permission:hr.clock.view')->group(function () {
    Route::get('journey/biometric-consents', [BiometricConsentController::class, 'index']);
    Route::post('journey/biometric-consents/check', [BiometricConsentController::class, 'check']);
});

Route::middleware('check.permission:hr.clock.manage')->group(function () {
    Route::post('journey/biometric-consents/grant', [BiometricConsentController::class, 'grant']);
    Route::post('journey/biometric-consents/revoke', [BiometricConsentController::class, 'revoke']);
});

// Offline Sync
Route::middleware('check.permission:hr.clock.view')->group(function () {
    Route::post('journey/sync', [OfflineSyncController::class, 'sync']);
});

// Payroll Integration & eSocial
Route::middleware('check.permission:hr.payroll.view')->group(function () {
    Route::get('journey/payroll/month-summary', [PayrollIntegrationController::class, 'monthSummary']);
    Route::get('journey/payroll/blocking-days', [PayrollIntegrationController::class, 'blockingDays']);
});

Route::middleware('check.permission:hr.payroll.manage')->group(function () {
    Route::post('journey/esocial/generate', [PayrollIntegrationController::class, 'generateESocial']);
});
