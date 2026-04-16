<?php

/**
 * Routes: Advanced Features, Recrutamento, Analytics, Financeiro Avancado
 * Extracted from api.php - Phase 10 Modularization
 * Original lines: 2185-2311
 */

use App\Http\Controllers\Api\V1\AccountingReportController;
use App\Http\Controllers\Api\V1\CostCenterController;
use App\Http\Controllers\Api\V1\CustomerDocumentController;
use App\Http\Controllers\Api\V1\EmployeeBenefitController;
use App\Http\Controllers\Api\V1\Financial\FinancialAdvancedController;
use App\Http\Controllers\Api\V1\FollowUpController;
use App\Http\Controllers\Api\V1\Integration\GoogleCalendarController;
use App\Http\Controllers\Api\V1\JobPostingController;
use App\Http\Controllers\Api\V1\OrganizationController;
use App\Http\Controllers\Api\V1\PerformanceReviewController;
use App\Http\Controllers\Api\V1\PriceTableController;
use App\Http\Controllers\Api\V1\SkillsController;
use App\Http\Controllers\Api\V1\WorkOrderRatingController;
use Illuminate\Support\Facades\Route;

// â”€â”€â”€ ADVANCED FEATURES â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
Route::prefix('advanced')->group(function () {
    // Follow-ups
    Route::middleware('check.permission:commercial.followup.view')->get('follow-ups', [FollowUpController::class, 'index']);
    Route::middleware('check.permission:commercial.followup.manage')->group(function () {
        Route::post('follow-ups', [FollowUpController::class, 'store']);
        Route::put('follow-ups/{followUp}', [FollowUpController::class, 'update']);
        Route::put('follow-ups/{followUp}/complete', [FollowUpController::class, 'complete']);
        Route::delete('follow-ups/{followUp}', [FollowUpController::class, 'destroy']);
    });
    // Price tables
    Route::middleware('check.permission:commercial.price_table.view')->group(function () {
        Route::get('price-tables', [PriceTableController::class, 'index']);
        Route::get('price-tables/{priceTable}', [PriceTableController::class, 'show']);
    });
    Route::middleware('check.permission:commercial.price_table.manage')->group(function () {
        Route::post('price-tables', [PriceTableController::class, 'store']);
        Route::put('price-tables/{priceTable}', [PriceTableController::class, 'update']);
        Route::delete('price-tables/{priceTable}', [PriceTableController::class, 'destroy']);
    });

    // Aliases compatíveis com frontend
    Route::middleware('check.permission:finance.cost_center.view')->get('cost-centers', [CostCenterController::class, 'index']);

    Route::middleware('check.permission:customer.document.view|cadastros.customer.view')->get('customer-documents', [CustomerDocumentController::class, 'indexGlobal']);
    Route::middleware('check.permission:customer.document.view|cadastros.customer.view')->get('customers/{customer}/documents', [CustomerDocumentController::class, 'index']);
    Route::middleware('check.permission:customer.document.manage|cadastros.customer.update')->post('customers/{customer}/documents', [CustomerDocumentController::class, 'store']);
    Route::middleware('check.permission:customer.document.manage|cadastros.customer.update')->delete('customer-documents/{document}', [CustomerDocumentController::class, 'destroy']);
    Route::middleware('check.permission:os.work_order.rating.view')->get('ratings', [WorkOrderRatingController::class, 'index']);
});

// compat
Route::middleware('check.permission:customer.document.view|cadastros.customer.view')->get('customers/{customer}/documents', [CustomerDocumentController::class, 'index']);
// compat
Route::middleware('check.permission:customer.document.manage|cadastros.customer.update')->post('customers/{customer}/documents', [CustomerDocumentController::class, 'store']);
// compat
Route::middleware('check.permission:customer.document.manage|cadastros.customer.update')->delete('customer-documents/{document}', [CustomerDocumentController::class, 'destroy']);

// Recrutamento (aliases legados sem conflitar com ATS Lite principal)
Route::middleware('check.permission:hr.recruitment.manage')->group(function () {
    Route::put('hr/candidates/{candidate}', [JobPostingController::class, 'updateCandidate']);
    Route::delete('hr/candidates/{candidate}', [JobPostingController::class, 'destroyCandidate']);
});

Route::middleware('check.permission:hr.recruitment.view')
    ->get('hr/job-postings/{jobPosting}/candidates', [JobPostingController::class, 'candidates']);

// Analytics
// (Moved to Analytics Bounded Context)

Route::middleware('check.permission:hr.reports.view')->group(function () {
    Route::get('hr/reports/accounting', [AccountingReportController::class, 'index']);
    Route::get('hr/reports/accounting/export', [AccountingReportController::class, 'export']);
});

// --- Wave 3: Organization ---
Route::middleware('check.permission:hr.organization.view')->group(function () {
    Route::get('hr/departments', [OrganizationController::class, 'indexDepartments']);
    Route::get('hr/positions', [OrganizationController::class, 'indexPositions']);
    Route::get('hr/org-chart', [OrganizationController::class, 'orgChart']);
});
Route::middleware('check.permission:hr.organization.manage')->group(function () {
    Route::post('hr/departments', [OrganizationController::class, 'storeDepartment']);
    Route::put('hr/departments/{department}', [OrganizationController::class, 'updateDepartment']);
    Route::delete('hr/departments/{department}', [OrganizationController::class, 'destroyDepartment']);

    Route::post('hr/positions', [OrganizationController::class, 'storePosition']);
    Route::put('hr/positions/{position}', [OrganizationController::class, 'updatePosition']);
    Route::delete('hr/positions/{position}', [OrganizationController::class, 'destroyPosition']);
});

// --- Wave 3: Skills Matrix ---
Route::middleware('check.permission:hr.skills.view')->group(function () {
    Route::apiResource('hr/skills', SkillsController::class)->only(['index', 'show']);
    Route::get('hr/skills-matrix', [SkillsController::class, 'matrix']);
});
Route::middleware('check.permission:hr.skills.manage')->group(function () {
    Route::apiResource('hr/skills', SkillsController::class)->only(['store', 'update', 'destroy']);
    Route::post('hr/skills/assess/{user}', [SkillsController::class, 'assessUser']);
});

// --- Wave 3: Performance & Feedback ---
Route::middleware('check.permission:hr.performance.view')->group(function () {
    Route::get('hr/performance-reviews', [PerformanceReviewController::class, 'indexReviews']);
    Route::get('hr/performance-reviews/{review}', [PerformanceReviewController::class, 'showReview']);
});
Route::middleware('check.permission:hr.performance.manage')->group(function () {
    Route::post('hr/performance-reviews', [PerformanceReviewController::class, 'storeReview']);
    Route::put('hr/performance-reviews/{review}', [PerformanceReviewController::class, 'updateReview']);
    Route::delete('hr/performance-reviews/{review}', [PerformanceReviewController::class, 'destroyReview']);
});

// Feedback routes with specific permissions
Route::middleware('check.permission:hr.feedback.view')->group(function () {
    Route::get('hr/continuous-feedback', [PerformanceReviewController::class, 'indexFeedback']);
});
Route::middleware('check.permission:hr.feedback.create')->group(function () {
    Route::post('hr/continuous-feedback', [PerformanceReviewController::class, 'storeFeedback']);
});

// --- Wave 4: Benefits ---
Route::middleware('check.permission:hr.benefits.view')->group(function () {
    Route::apiResource('hr/benefits', EmployeeBenefitController::class)->only(['index', 'show']);
});
Route::middleware('check.permission:hr.benefits.manage')->group(function () {
    Route::apiResource('hr/benefits', EmployeeBenefitController::class)->only(['store', 'update', 'destroy']);
});

// ═══ Financeiro Avançado ══════════════════════════════════════
Route::prefix('financial')->group(function () {
    Route::middleware('check.permission:financeiro.view|finance.payable.view')->get('supplier-contracts', [FinancialAdvancedController::class, 'supplierContracts']);
    Route::middleware('check.permission:financeiro.view|finance.payable.view')->get('checks', [FinancialAdvancedController::class, 'checks']);
    Route::middleware('check.permission:financeiro.view|finance.payable.view')->get('supplier-advances', [FinancialAdvancedController::class, 'supplierAdvances']);
    Route::middleware('check.permission:financeiro.view|expenses.expense.view')->get('expense-reimbursements', [FinancialAdvancedController::class, 'expenseReimbursements']);
    Route::middleware('check.permission:financeiro.approve|expenses.expense.approve')->post('expense-reimbursements', [FinancialAdvancedController::class, 'storeReimbursement']);
    Route::middleware('check.permission:financeiro.view|finance.receivable.view')->get('collection-rules', [FinancialAdvancedController::class, 'collectionRules']);
    // Receivables simulator (read-only calculation)
    Route::middleware('check.permission:financeiro.view|finance.receivable.view')->post('receivables-simulator', [FinancialAdvancedController::class, 'receivablesSimulator']);
    Route::middleware('check.permission:financeiro.view|finance.dre.view')->post('tax-calculation', [FinancialAdvancedController::class, 'taxCalculation']);
    Route::middleware('check.permission:financeiro.payment.create|finance.payable.create')->post('supplier-contracts', [FinancialAdvancedController::class, 'storeSupplierContract']);
    Route::middleware('check.permission:financeiro.payment.create|finance.payable.update')->put('supplier-contracts/{contract}', [FinancialAdvancedController::class, 'updateSupplierContract']);
    Route::middleware('check.permission:financeiro.payment.create|finance.payable.delete')->delete('supplier-contracts/{contract}', [FinancialAdvancedController::class, 'destroySupplierContract']);
    Route::middleware('check.permission:financeiro.payment.create|finance.payable.create')->post('checks', [FinancialAdvancedController::class, 'storeCheck']);
    Route::middleware('check.permission:financeiro.payment.create|finance.payable.update')->patch('checks/{check}/status', [FinancialAdvancedController::class, 'updateCheckStatus']);
    Route::middleware('check.permission:financeiro.payment.create|finance.payable.create')->post('supplier-advances', [FinancialAdvancedController::class, 'storeSupplierAdvance']);
});
Route::prefix('financial')->middleware('check.permission:financeiro.approve|expenses.expense.approve')->group(function () {
    Route::post('expense-reimbursements/{expense}/approve', [FinancialAdvancedController::class, 'approveReimbursement']);
});

// ═══ Google Calendar Integration ══════════════════════════════════════
Route::prefix('integrations/google-calendar')->group(function () {
    Route::middleware('check.permission:platform.settings.view')->group(function () {
        Route::get('status', [GoogleCalendarController::class, 'status']);
        Route::get('auth-url', [GoogleCalendarController::class, 'authUrl']);
    });

    Route::middleware('check.permission:platform.settings.manage')->group(function () {
        Route::post('callback', [GoogleCalendarController::class, 'callback']);
        Route::post('disconnect', [GoogleCalendarController::class, 'disconnect']);
        Route::post('sync', [GoogleCalendarController::class, 'sync']);
    });
});
