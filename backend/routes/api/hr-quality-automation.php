<?php

/**
 * Routes: Fleet, HR, Qualidade, Automacao, Relatorios Perifericos
 * Extracted from api.php - Phase 10 Modularization
 * Original lines: 1864-2094
 */

use App\Http\Controllers\Api\V1\AutomationController;
use App\Http\Controllers\Api\V1\ESocialController;
use App\Http\Controllers\Api\V1\Fleet\FuelLogController;
use App\Http\Controllers\Api\V1\Fleet\VehicleAccidentController;
use App\Http\Controllers\Api\V1\FleetController;
use App\Http\Controllers\Api\V1\Hr\CltViolationController;
use App\Http\Controllers\Api\V1\Hr\DashboardController;
use App\Http\Controllers\Api\V1\Hr\FiscalAccessController;
use App\Http\Controllers\Api\V1\Hr\ReportsController;
use App\Http\Controllers\Api\V1\HRAdvancedController;
use App\Http\Controllers\Api\V1\HRController;
use App\Http\Controllers\Api\V1\Iam\UserController;
use App\Http\Controllers\Api\V1\PayrollController;
use App\Http\Controllers\Api\V1\PerformanceReviewController;
use App\Http\Controllers\Api\V1\PeripheralReportController;
use App\Http\Controllers\Api\V1\QualityController;
use App\Http\Controllers\Api\V1\RecruitmentController;
use App\Http\Controllers\Api\V1\RescissionController;
use Illuminate\Support\Facades\Route;

// â”€â”€â”€ FLEET (Frota) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
Route::prefix('fleet')->group(function () {
    Route::middleware('check.permission:fleet.vehicle.view')->group(function () {
        Route::get('vehicles', [FleetController::class, 'indexVehicles']);
        Route::get('vehicles/{vehicle}', [FleetController::class, 'showVehicle']);
        Route::get('vehicles/{vehicle}/inspections', [FleetController::class, 'indexInspections']);
        Route::get('dashboard', [FleetController::class, 'dashboardFleet']);

    });
    Route::middleware('check.permission:fleet.vehicle.create')->post('vehicles', [FleetController::class, 'storeVehicle']);
    Route::middleware('check.permission:fleet.vehicle.update')->put('vehicles/{vehicle}', [FleetController::class, 'updateVehicle']);
    Route::middleware('check.permission:fleet.vehicle.delete')->delete('vehicles/{vehicle}', [FleetController::class, 'destroyVehicle']);
    Route::middleware('check.permission:fleet.inspection.create')->post('vehicles/{vehicle}/inspections', [FleetController::class, 'storeInspection']);
    Route::middleware('check.permission:fleet.fine.view')->get('fines', [FleetController::class, 'indexFines']);
    Route::middleware('check.permission:fleet.fine.create')->post('fines', [FleetController::class, 'storeFine']);
    Route::middleware('check.permission:fleet.fine.update')->put('fines/{fine}', [FleetController::class, 'updateFine']);
    Route::middleware('check.permission:fleet.tool_inventory.view')->get('tools', [FleetController::class, 'indexTools']);
    Route::middleware('check.permission:fleet.tool_inventory.manage')->group(function () {
        Route::post('tools', [FleetController::class, 'storeTool']);
        Route::put('tools/{tool}', [FleetController::class, 'updateTool']);
        Route::delete('tools/{tool}', [FleetController::class, 'destroyTool']);
    });

    // Fuel logs and Accidents
    Route::middleware('check.permission:fleet.vehicle.view')->group(function () {
        Route::get('fuel-logs', [FuelLogController::class, 'index']);
        Route::get('fuel-logs/{log}', [FuelLogController::class, 'show']);
        Route::get('accidents', [VehicleAccidentController::class, 'index']);
        Route::get('accidents/{accident}', [VehicleAccidentController::class, 'show']);
    });
    Route::middleware('check.permission:fleet.vehicle.update')->group(function () {
        Route::post('fuel-logs', [FuelLogController::class, 'store']);
        Route::put('fuel-logs/{log}', [FuelLogController::class, 'update']);
        Route::delete('fuel-logs/{log}', [FuelLogController::class, 'destroy']);
        Route::post('accidents', [VehicleAccidentController::class, 'store']);
        Route::put('accidents/{accident}', [VehicleAccidentController::class, 'update']);
        Route::delete('accidents/{accident}', [VehicleAccidentController::class, 'destroy']);
    });
});

// â”€â”€â”€ HR (RH & Equipe) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
Route::prefix('hr')->group(function () {
    Route::middleware('check.permission:hr.schedule.view')->group(function () {
        Route::get('schedules', [HRController::class, 'indexSchedules']);
        Route::get('dashboard', [HRController::class, 'dashboard']);

    });
    Route::middleware('check.permission:hr.schedule.manage')->group(function () {
        Route::post('schedules', [HRController::class, 'storeSchedule']);
        Route::post('schedules/batch', [HRController::class, 'batchSchedule']);
    });
    Route::middleware('check.permission:hr.clock.manage')->group(function () {
        Route::post('clock/in', [HRController::class, 'clockIn']);
        Route::post('clock/out', [HRController::class, 'clockOut']);
    });
    Route::middleware('check.permission:hr.clock.view')->group(function () {
        Route::get('clock/my', [HRController::class, 'myClockHistory']);
        Route::get('clock/all', [HRController::class, 'allClockEntries']);
    });
    Route::middleware('check.permission:hr.training.view')->get('trainings', [HRController::class, 'indexTrainings']);
    Route::middleware('check.permission:hr.training.manage')->group(function () {
        Route::post('trainings', [HRController::class, 'storeTraining']);
        Route::put('trainings/{training}', [HRController::class, 'updateTraining']);
        Route::get('trainings/{training}', [HRController::class, 'showTraining']);
        Route::delete('trainings/{training}', [HRController::class, 'destroyTraining']);
    });
    Route::middleware('check.permission:hr.performance.view')->get('reviews', [PerformanceReviewController::class, 'indexReviews']);
    Route::middleware('check.permission:hr.performance.manage')->group(function () {
        Route::post('reviews', [PerformanceReviewController::class, 'storeReview']);
        Route::put('reviews/{review}', [PerformanceReviewController::class, 'updateReview']);
    });

    // â”€â”€â”€ ADVANCED: Ponto Digital Avançado (Wave 1) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    Route::middleware('check.permission:hr.clock.manage')->group(function () {
        Route::post('advanced/clock-in', [HRAdvancedController::class, 'advancedClockIn']);
        Route::post('advanced/clock-out', [HRAdvancedController::class, 'advancedClockOut']);
        Route::post('advanced/break-start', [HRAdvancedController::class, 'breakStart']);
        Route::post('advanced/break-end', [HRAdvancedController::class, 'breakEnd']);
    });
    Route::middleware('check.permission:hr.clock.view')->group(function () {
        Route::get('advanced/clock/status', [HRAdvancedController::class, 'currentClockStatus']);
        Route::get('advanced/clock/history', [HRAdvancedController::class, 'clockHistory']);
        Route::get('advanced/clock/pending', [HRAdvancedController::class, 'pendingClockEntries']);
        Route::get('clock/my', [HRAdvancedController::class, 'myClockEntries']);
        Route::get('clock/espelho', [HRAdvancedController::class, 'myEspelho']);
        Route::post('clock/espelho/confirm', [HRAdvancedController::class, 'confirmEspelho']);
    });
    Route::middleware('check.permission:hr.clock.approve')->group(function () {
        Route::post('advanced/clock/{id}/approve', [HRAdvancedController::class, 'approveClockEntry']);
        Route::post('advanced/clock/{id}/reject', [HRAdvancedController::class, 'rejectClockEntry']);
    });

    // â”€â”€â”€ ADVANCED: Geofences â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    Route::middleware('check.permission:hr.geofence.view')->get('geofences', [HRAdvancedController::class, 'indexGeofences']);
    Route::middleware('check.permission:hr.geofence.manage')->group(function () {
        Route::post('geofences', [HRAdvancedController::class, 'storeGeofence']);
        Route::put('geofences/{geofence}', [HRAdvancedController::class, 'updateGeofence']);
        Route::delete('geofences/{geofence}', [HRAdvancedController::class, 'destroyGeofence']);
    });

    // â”€â”€â”€ ADVANCED: Ajustes de Ponto â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    Route::middleware('check.permission:hr.adjustment.view')->get('adjustments', [HRAdvancedController::class, 'indexAdjustments']);
    Route::middleware('check.permission:hr.adjustment.create')->post('adjustments', [HRAdvancedController::class, 'storeAdjustment']);
    Route::middleware('check.permission:hr.adjustment.approve')->group(function () {
        Route::post('adjustments/{id}/approve', [HRAdvancedController::class, 'approveAdjustment']);
        Route::post('adjustments/{id}/reject', [HRAdvancedController::class, 'rejectAdjustment']);
    });

    // â”€â”€â”€ ADVANCED: Jornada & Banco de Horas (Wave 1) â”€â”€â”€â”€â”€â”€â”€â”€
    Route::middleware('check.permission:hr.journey.view')->group(function () {
        Route::get('journey-rules', [HRAdvancedController::class, 'indexJourneyRules']);
        Route::get('journey-entries', [HRAdvancedController::class, 'journeyEntries']);
        Route::get('hour-bank/balance', [HRAdvancedController::class, 'hourBankBalance']);
        Route::get('hour-bank/transactions', [HRAdvancedController::class, 'hourBankTransactions']);
    });
    Route::middleware('check.permission:hr.journey.manage')->group(function () {
        Route::post('journey-rules', [HRAdvancedController::class, 'storeJourneyRule']);
        Route::put('journey-rules/{rule}', [HRAdvancedController::class, 'updateJourneyRule']);
        Route::delete('journey-rules/{rule}', [HRAdvancedController::class, 'destroyJourneyRule']);
        Route::post('journey/calculate', [HRAdvancedController::class, 'calculateJourney']);
    });

    // â”€â”€â”€ ADVANCED: Feriados â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    Route::middleware('check.permission:hr.holiday.view')->get('holidays', [HRAdvancedController::class, 'indexHolidays']);
    Route::middleware('check.permission:hr.holiday.manage')->group(function () {
        Route::post('holidays', [HRAdvancedController::class, 'storeHoliday']);
        Route::put('holidays/{holiday}', [HRAdvancedController::class, 'updateHoliday']);
        Route::delete('holidays/{holiday}', [HRAdvancedController::class, 'destroyHoliday']);
        Route::post('holidays/import-national', [HRAdvancedController::class, 'importNationalHolidays']);
    });

    // â”€â”€â”€ ADVANCED: Férias & Afastamentos (Wave 2) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    Route::middleware('check.permission:hr.leave.view')->group(function () {
        Route::get('leaves', [HRAdvancedController::class, 'indexLeaves']);
        Route::get('vacation-balances', [HRAdvancedController::class, 'vacationBalances']);
    });
    Route::middleware('check.permission:hr.leave.view|hr.document.view|hr.onboarding.view|hr.benefits.view')
        ->get('users/options', [HRAdvancedController::class, 'userOptions']);
    Route::middleware('check.permission:hr.leave.create')->post('leaves', [HRAdvancedController::class, 'storeLeave']);
    Route::middleware('check.permission:hr.leave.approve')->group(function () {
        Route::post('leaves/{leave}/approve', [HRAdvancedController::class, 'approveLeave']);
        Route::post('leaves/{leave}/reject', [HRAdvancedController::class, 'rejectLeave']);
    });

    // â”€â”€â”€ ADVANCED: Documentos do Colaborador (Wave 2) â”€â”€â”€â”€â”€â”€â”€
    Route::middleware('check.permission:hr.document.view')->group(function () {
        Route::get('documents', [HRAdvancedController::class, 'indexDocuments']);
        Route::get('documents/expiring', [HRAdvancedController::class, 'expiringDocuments']);
    });
    Route::middleware('check.permission:hr.document.manage')->group(function () {
        Route::post('documents', [HRAdvancedController::class, 'storeDocument'])->middleware('throttle:tenant-uploads');
        Route::put('documents/{document}', [HRAdvancedController::class, 'updateDocument']);
        Route::delete('documents/{document}', [HRAdvancedController::class, 'destroyDocument']);
    });

    // â”€â”€â”€ ADVANCED: Onboarding (Wave 2) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    Route::middleware('check.permission:hr.onboarding.view')->group(function () {
        Route::get('onboarding/templates', [HRAdvancedController::class, 'indexTemplates']);
        Route::get('onboarding/checklists', [HRAdvancedController::class, 'indexChecklists']);
    });
    Route::middleware('check.permission:hr.onboarding.manage')->group(function () {
        Route::post('onboarding/templates', [HRAdvancedController::class, 'storeTemplate']);
        Route::put('onboarding/templates/{template}', [HRAdvancedController::class, 'updateTemplate']);
        Route::delete('onboarding/templates/{template}', [HRAdvancedController::class, 'destroyTemplate']);
        Route::post('onboarding/start', [HRAdvancedController::class, 'startOnboarding']);
        Route::put('onboarding/checklists/{checklist}', [HRAdvancedController::class, 'updateChecklist']);
        Route::delete('onboarding/checklists/{checklist}', [HRAdvancedController::class, 'destroyChecklist']);
        Route::post('onboarding/items/{itemId}/complete', [HRAdvancedController::class, 'completeChecklistItem']);
    });

    // â”€â”€â”€ ADVANCED: Dashboard Expandido â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    Route::middleware('check.permission:hr.dashboard.view')->get('advanced/dashboard', [HRAdvancedController::class, 'advancedDashboard']);

    // --- ADVANCED: Hash Chain Integrity (Portaria 671/2021) ---
    Route::middleware('check.permission:hr.clock.view')->get('ponto/verify-integrity', [HRAdvancedController::class, 'verifyIntegrity']);

    // --- ADVANCED: Comprovante, Espelho de Ponto, AFD Export (Portaria 671/2021) ---
    Route::middleware('check.permission:hr.clock.view')->group(function () {
        Route::get('ponto/comprovante/{id}', [HRAdvancedController::class, 'comprovante']);
        Route::get('ponto/espelho/{user_id}/{year}/{month}', [HRAdvancedController::class, 'espelhoPonto']);
        Route::get('ponto/afd/export', [HRAdvancedController::class, 'exportAFD']);
    });

    // ═══ DASHBOARD HR (Widgets + Team) ═══════════════════════
    Route::middleware('check.permission:hr.dashboard.view')->group(function () {
        Route::get('dashboard/widgets', [DashboardController::class, 'widgets']);
        Route::get('dashboard/team', [DashboardController::class, 'team']);
    });

    // ═══ RELATÓRIOS HR ═══════════════════════════════════════
    Route::middleware('check.permission:hr.reports.view')->group(function () {
        Route::get('reports/overtime-trend', [ReportsController::class, 'overtimeTrend']);
        Route::get('reports/hour-bank-forecast', [ReportsController::class, 'hourBankForecast']);
        Route::get('reports/tax-obligations', [ReportsController::class, 'taxObligations']);
        Route::get('reports/income-statement/{userId}/{year}', [ReportsController::class, 'incomeStatement']);
        Route::get('reports/labor-cost-by-project', [ReportsController::class, 'laborCostByProject']);
    });

    // ═══ FOLHA DE PAGAMENTO (Payroll) ═════════════════════════
    Route::middleware('check.permission:hr.payroll.view')->group(function () {
        Route::get('payroll', [PayrollController::class, 'index']);
        Route::get('payroll/{id}', [PayrollController::class, 'show']);
        Route::get('reports/payroll-cost', [PayrollController::class, 'payrollCostReport']);
    });
    Route::middleware('check.permission:hr.payroll.manage')->group(function () {
        Route::post('payroll', [PayrollController::class, 'store']);
        Route::post('payroll/{id}/calculate', [PayrollController::class, 'calculate']);
        Route::post('payroll/{id}/approve', [PayrollController::class, 'approve']);
        Route::post('payroll/{id}/mark-paid', [PayrollController::class, 'markAsPaid']);
        Route::post('payroll/{id}/generate-payslips', [PayrollController::class, 'generatePayslips']);
    });
    Route::get('my-payslips', [PayrollController::class, 'employeePayslips']);
    Route::get('payslips/{id}', [PayrollController::class, 'showPayslip']);
    Route::get('payslips/{id}/download', [PayrollController::class, 'downloadPayslip']);

    // ═══ RESCISÃO (Termination) ════════════════════════════════
    Route::middleware('check.permission:hr.payroll.view')->group(function () {
        Route::get('rescissions', [RescissionController::class, 'index']);
        Route::get('rescissions/{id}', [RescissionController::class, 'show']);
        Route::get('rescissions/{id}/trct', [RescissionController::class, 'generateTRCT']);
    });
    Route::middleware('check.permission:hr.payroll.manage')->group(function () {
        Route::post('rescissions', [RescissionController::class, 'store']);
        Route::post('rescissions/{id}/approve', [RescissionController::class, 'approve']);
        Route::post('rescissions/{id}/mark-paid', [RescissionController::class, 'markAsPaid']);
    });

    // ═══ RECRUTAMENTO (ATS Lite) ══════════════════════════════
    Route::middleware('check.permission:hr.recruitment.view')->group(function () {
        Route::get('job-postings', [RecruitmentController::class, 'index']);
        Route::get('job-postings/{jobPosting}', [RecruitmentController::class, 'show']);
    });
    Route::middleware('check.permission:hr.recruitment.manage')->group(function () {
        Route::post('job-postings', [RecruitmentController::class, 'store']);
        Route::put('job-postings/{jobPosting}', [RecruitmentController::class, 'update']);
        Route::delete('job-postings/{jobPosting}', [RecruitmentController::class, 'destroy']);
        Route::post('job-postings/{jobPosting}/candidates', [RecruitmentController::class, 'storeCandidate']);
        Route::put('job-postings/{jobPosting}/candidates/{candidate}', [RecruitmentController::class, 'updateCandidate']);
        Route::delete('job-postings/{jobPosting}/candidates/{candidate}', [RecruitmentController::class, 'destroyCandidate']);
    });

    // ═══ eSocial (Integração Governo Federal) ═══════════════════
    Route::middleware('check.permission:hr.esocial.view')->group(function () {
        Route::get('esocial/events', [ESocialController::class, 'index']);
        Route::get('esocial/events/{id}', [ESocialController::class, 'show']);
        Route::get('esocial/batches/{batchId}', [ESocialController::class, 'checkBatch']);
        Route::get('esocial/certificates', [ESocialController::class, 'certificates']);
        Route::get('esocial/dashboard', [ESocialController::class, 'dashboard']);
    });
    Route::middleware('check.permission:hr.esocial.manage')->group(function () {
        Route::post('esocial/events/generate', [ESocialController::class, 'generate']);
        Route::post('esocial/events/send-batch', [ESocialController::class, 'sendBatch']);
        Route::post('esocial/certificates', [ESocialController::class, 'storeCertificate']);
    });

    // ═══ Acesso Fiscal (Portaria 671/2021) ═══════════════════════
    Route::middleware('check.permission:hr.fiscal.access')->group(function () {
        Route::get('fiscal/afd', [FiscalAccessController::class, 'exportAfd']);
        Route::get('fiscal/aep/{userId}/{year}/{month}', [FiscalAccessController::class, 'exportAep']);
        Route::get('fiscal/integrity', [FiscalAccessController::class, 'verifyIntegrity']);
    });

    // ═══ Dashboard de Violações CLT (Portaria 671) ═══════════════
    Route::middleware('check.permission:hr.fiscal.access')->group(function () {
        Route::get('violations', [CltViolationController::class, 'index']);
        Route::get('violations/stats', [CltViolationController::class, 'stats']);
        Route::post('violations/{id}/resolve', [CltViolationController::class, 'resolve']);
    });

    // ═══ AUDIT TRAIL & COMPLIANCE (Portaria 671/2021 - Mega Auditoria) ═══
    Route::middleware('check.permission:hr.clock.view')->group(function () {
        Route::post('compliance/confirm-entry/{id}', [HRAdvancedController::class, 'confirmEntry']);
        Route::post('compliance/verify-integrity', [HRAdvancedController::class, 'verifyIntegrity']);
    });
    Route::middleware('check.permission:hr.clock.view')->prefix('audit-trail')->group(function () {
        Route::get('report', [HRAdvancedController::class, 'auditTrailReport']);
        Route::get('{entry_id}', [HRAdvancedController::class, 'auditTrailByEntry']);
    });
    Route::middleware('check.permission:hr.clock.view')->get('security/tampering-attempts', [HRAdvancedController::class, 'tamperingAttempts']);

    // ═══ TAX TABLES ADMIN ═══════════════════════════════════════
    Route::middleware('check.permission:hr.payroll.manage')->prefix('tax-tables')->group(function () {
        Route::get('/', [HRAdvancedController::class, 'indexTaxTables']);
        Route::post('/', [HRAdvancedController::class, 'storeTaxTable']);
        Route::put('{id}', [HRAdvancedController::class, 'updateTaxTable']);
    });

    // ═══ eSocial Extended (S-3000, S-1010, S-1000, Retry) ═══════
    Route::middleware('check.permission:hr.esocial.manage')->group(function () {
        Route::post('esocial/events/{id}/exclude', [ESocialController::class, 'excludeEvent']);
        Route::post('esocial/events/{id}/retry', [ESocialController::class, 'retryEvent']);
        Route::post('esocial/events/retry-all', [ESocialController::class, 'retryAll']);
        Route::post('esocial/rubric-table', [ESocialController::class, 'generateRubricTable']);
        Route::post('esocial/s1000', [ESocialController::class, 'generateS1000']);
    });

    // ═══ LEGACY ALIASES (backward compatibility) ═══════════════
    Route::middleware('check.permission:iam.user.view')->get('employees', [UserController::class, 'index']);
    Route::middleware('check.permission:iam.user.create')->post('employees', [UserController::class, 'store']);
    Route::middleware('check.permission:hr.clock.manage')->post('time-clock', [HRAdvancedController::class, 'advancedClockIn']);
    Route::middleware('check.permission:hr.clock.view')->get('time-clock', [HRController::class, 'allClockEntries']);
    Route::middleware('check.permission:hr.leave.view')->get('leave-requests', [HRAdvancedController::class, 'indexLeaves']);
    Route::middleware('check.permission:hr.leave.create')->post('leave-requests', [HRAdvancedController::class, 'storeLeave']);
    Route::middleware('check.permission:hr.leave.approve')->post('leave-requests/{leave}/approve', [HRAdvancedController::class, 'approveLeave']);
});

// â”€â”€â”€ QUALITY (Qualidade & SGQ) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
Route::prefix('quality')->group(function () {
    Route::middleware('check.permission:quality.procedure.view')->group(function () {
        Route::get('procedures', [QualityController::class, 'indexProcedures']);
        Route::get('procedures/{procedure}', [QualityController::class, 'showProcedure']);
    });
    Route::middleware('check.permission:quality.procedure.manage')->group(function () {
        Route::post('procedures', [QualityController::class, 'storeProcedure']);
        Route::put('procedures/{procedure}', [QualityController::class, 'updateProcedure']);
        Route::post('procedures/{procedure}/approve', [QualityController::class, 'approveProcedure']);
        Route::delete('procedures/{procedure}', [QualityController::class, 'destroyProcedure']);
    });
    Route::middleware('check.permission:quality.corrective_action.view')->get('corrective-actions', [QualityController::class, 'indexCorrectiveActions']);
    Route::middleware('check.permission:quality.corrective_action.manage')->group(function () {
        Route::post('corrective-actions', [QualityController::class, 'storeCorrectiveAction']);
        Route::put('corrective-actions/{action}', [QualityController::class, 'updateCorrectiveAction']);
        Route::delete('corrective-actions/{action}', [QualityController::class, 'destroyCorrectiveAction']);
    });
    Route::middleware('check.permission:quality.complaint.view')->get('complaints', [QualityController::class, 'indexComplaints']);
    Route::middleware('check.permission:quality.complaint.manage')->group(function () {
        Route::post('complaints', [QualityController::class, 'storeComplaint']);
        Route::put('complaints/{complaint}', [QualityController::class, 'updateComplaint']);
        Route::delete('complaints/{complaint}', [QualityController::class, 'destroyComplaint']);
    });
    Route::middleware('check.permission:customer.satisfaction.view')->get('surveys', [QualityController::class, 'indexSurveys']);
    Route::middleware('check.permission:customer.satisfaction.manage')->post('surveys', [QualityController::class, 'storeSurvey']);
    Route::middleware('check.permission:customer.nps.view')->get('nps', [QualityController::class, 'npsDashboard']);
    Route::middleware('check.permission:quality.dashboard.view')->get('dashboard', [QualityController::class, 'dashboard']);

});

// â”€â”€â”€ AUTOMATION (Automações & Webhooks) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
// ─── PERIPHERAL REPORTS ────────────────────────────────────
Route::prefix('reports/peripheral')->group(function () {
    Route::middleware('check.permission:hr.schedule.view')->get('timesheet', [PeripheralReportController::class, 'timesheetReport']);
    Route::middleware('check.permission:quality.dashboard.view')->get('quality-audit', [PeripheralReportController::class, 'qualityAuditReport']);
    Route::middleware('check.permission:fleet.vehicle.view')->get('fleet-costs', [PeripheralReportController::class, 'fleetCostReport']);
});

Route::prefix('automation')->group(function () {
    Route::middleware('check.permission:automation.rule.view')->group(function () {
        Route::get('rules', [AutomationController::class, 'indexRules']);
        Route::get('events', [AutomationController::class, 'availableEvents']);
        Route::get('actions', [AutomationController::class, 'availableActions']);
    });
    Route::middleware('check.permission:automation.rule.manage')->group(function () {
        Route::post('rules', [AutomationController::class, 'storeRule']);
        Route::put('rules/{rule}', [AutomationController::class, 'updateRule']);
        Route::patch('rules/{rule}/toggle', [AutomationController::class, 'toggleRule']);
        Route::delete('rules/{rule}', [AutomationController::class, 'destroyRule']);
    });
    Route::middleware('check.permission:automation.webhook.view')->group(function () {
        Route::get('webhooks', [AutomationController::class, 'indexWebhooks']);
        Route::get('webhooks/{webhook}/logs', [AutomationController::class, 'webhookLogs']);
    });
    Route::middleware('check.permission:automation.webhook.manage')->group(function () {
        Route::post('webhooks', [AutomationController::class, 'storeWebhook']);
        Route::put('webhooks/{webhook}', [AutomationController::class, 'updateWebhook']);
        Route::delete('webhooks/{webhook}', [AutomationController::class, 'destroyWebhook']);
    });
    Route::middleware('check.permission:reports.scheduled.view')->get('reports', [AutomationController::class, 'indexScheduledReports']);
    Route::middleware('check.permission:reports.scheduled.manage')->group(function () {
        Route::post('reports', [AutomationController::class, 'storeScheduledReport']);
        Route::put('reports/{report}', [AutomationController::class, 'updateScheduledReport']);
        Route::delete('reports/{report}', [AutomationController::class, 'destroyScheduledReport']);
    });
});
