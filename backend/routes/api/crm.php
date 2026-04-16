<?php

/**
 * Routes: CRM, CRM Features, CRM Field Management
 * Extracted from api.php - Phase 10 Modularization
 * Original lines: 1320-1658
 */

use App\Http\Controllers\Api\V1\Crm\CrmAlertController;
use App\Http\Controllers\Api\V1\Crm\CrmContractController;
use App\Http\Controllers\Api\V1\Crm\CrmEngagementController;
use App\Http\Controllers\Api\V1\Crm\CrmIntelligenceController;
use App\Http\Controllers\Api\V1\Crm\CrmProposalTrackingController;
use App\Http\Controllers\Api\V1\Crm\CrmSalesPipelineController;
use App\Http\Controllers\Api\V1\Crm\CrmSequenceController;
use App\Http\Controllers\Api\V1\Crm\CrmTerritoryGoalController;
use App\Http\Controllers\Api\V1\Crm\CrmWebFormController;
use App\Http\Controllers\Api\V1\CrmController;
use App\Http\Controllers\Api\V1\CrmFieldManagementController;
use App\Http\Controllers\Api\V1\CrmMessageController;
use App\Http\Middleware\EnsureTenantScope;
use Illuminate\Support\Facades\Route;

// CRM
Route::prefix('crm')->group(function () {
    Route::middleware('check.permission:crm.deal.view')->group(function () {
        Route::get('dashboard', [CrmController::class, 'dashboard']);
        Route::get('constants', [CrmController::class, 'constants']);
        Route::get('deals', [CrmController::class, 'dealsIndex']);
        Route::get('deals/{deal}', [CrmController::class, 'dealsShow']);
        Route::get('activities', [CrmController::class, 'activitiesIndex']);
        Route::get('customers/{customer}/360', [CrmController::class, 'customer360']);
        Route::get('customers/{customer}/360/pdf', [CrmController::class, 'export360']);
        Route::get('customer-360/{customer}', [CrmController::class, 'customer360']); // compat
        Route::get('customer-360/{customer}/pdf', [CrmController::class, 'export360']); // compat
    });
    Route::middleware('check.permission:crm.deal.create')->group(function () {
        Route::post('deals', [CrmController::class, 'dealsStore']);
        Route::post('activities', [CrmController::class, 'activitiesStore']);
    });
    Route::middleware('check.permission:crm.deal.update')->group(function () {
        Route::put('deals/{deal}', [CrmController::class, 'dealsUpdate']);
        Route::put('deals/{deal}/stage', [CrmController::class, 'dealsUpdateStage']);
        Route::post('deals/{deal}/move', [CrmController::class, 'dealsUpdateStage']);
        Route::put('deals/{deal}/won', [CrmController::class, 'dealsMarkWon']);
        Route::post('deals/{deal}/win', [CrmController::class, 'dealsMarkWon']);
        Route::put('deals/{deal}/lost', [CrmController::class, 'dealsMarkLost']);
        Route::post('deals/{deal}/lose', [CrmController::class, 'dealsMarkLost']);
        Route::post('deals/bulk-update', [CrmController::class, 'dealsBulkUpdate']);
        Route::put('activities/{activity}', [CrmController::class, 'activitiesUpdate']);
    });
    Route::middleware('check.permission:crm.deal.update|os.work_order.create')->post('deals/{deal}/convert-to-work-order', [CrmController::class, 'dealsConvertToWorkOrder']);
    Route::middleware('check.permission:crm.deal.update|quotes.quote.create')->post('deals/{deal}/convert-to-quote', [CrmController::class, 'dealsConvertToQuote']);
    Route::middleware('check.permission:crm.deal.delete')->group(function () {
        Route::delete('deals/{deal}', [CrmController::class, 'dealsDestroy']);
        Route::delete('activities/{activity}', [CrmController::class, 'activitiesDestroy']);
    });

    // Pipelines
    Route::middleware('check.permission:crm.pipeline.view')->get('pipelines', [CrmController::class, 'pipelinesIndex']);
    Route::middleware('check.permission:crm.pipeline.create')->post('pipelines', [CrmController::class, 'pipelinesStore']);
    Route::middleware('check.permission:crm.pipeline.update')->group(function () {
        Route::put('pipelines/{pipeline}', [CrmController::class, 'pipelinesUpdate']);
        Route::post('pipelines/{pipeline}/stages', [CrmController::class, 'stagesStore']);
        Route::put('stages/{stage}', [CrmController::class, 'stagesUpdate']);
        Route::delete('stages/{stage}', [CrmController::class, 'stagesDestroy']);
        Route::put('pipelines/{pipeline}/stages/reorder', [CrmController::class, 'stagesReorder']);
    });
    Route::middleware('check.permission:crm.pipeline.delete')->delete('pipelines/{pipeline}', [CrmController::class, 'pipelinesDestroy']);

    // Messages
    Route::middleware('check.permission:crm.message.view')->group(function () {
        Route::get('messages', [CrmMessageController::class, 'index']);
        Route::get('message-templates', [CrmMessageController::class, 'templates']);
    });
    Route::middleware('check.permission:crm.message.send')->group(function () {
        Route::post('messages/send', [CrmMessageController::class, 'send']);
        Route::post('message-templates', [CrmMessageController::class, 'storeTemplate']);
        Route::put('message-templates/{template}', [CrmMessageController::class, 'updateTemplate']);
        Route::delete('message-templates/{template}', [CrmMessageController::class, 'destroyTemplate']);
    });
});

// ─── CRM Features (22 domínios → 9 controllers dedicados) ─────
$sp = CrmSalesPipelineController::class;
$seq = CrmSequenceController::class;
$tg = CrmTerritoryGoalController::class;
$al = CrmAlertController::class;
$int = CrmIntelligenceController::class;
$wf = CrmWebFormController::class;
$pt = CrmProposalTrackingController::class;
$ct = CrmContractController::class;
$eng = CrmEngagementController::class;

Route::prefix('crm-features')->group(function () use ($sp, $seq, $tg, $al, $int, $wf, $pt, $ct, $eng) {
    Route::middleware('check.permission:crm.deal.view')->get('constants', [$eng, 'featuresConstants']);

    // Lead Scoring → CrmSalesPipelineController
    Route::middleware('check.permission:crm.scoring.view')->group(function () use ($sp) {
        Route::get('scoring/rules', [$sp, 'scoringRules']);
        Route::get('scoring/leaderboard', [$sp, 'leaderboard']);
    });
    Route::middleware('check.permission:crm.scoring.manage')->group(function () use ($sp) {
        Route::post('scoring/rules', [$sp, 'storeScoringRule']);
        Route::put('scoring/rules/{rule}', [$sp, 'updateScoringRule']);
        Route::delete('scoring/rules/{rule}', [$sp, 'destroyScoringRule']);
        Route::post('scoring/calculate', [$sp, 'calculateScores']);
    });

    // Sequences → CrmSequenceController
    Route::middleware('check.permission:crm.sequence.view')->group(function () use ($seq) {
        Route::get('sequences', [$seq, 'sequences']);
        Route::get('sequences/{sequence}', [$seq, 'showSequence']);
        Route::get('sequences/{sequence}/enrollments', [$seq, 'sequenceEnrollments']);
    });
    Route::middleware('check.permission:crm.sequence.manage')->group(function () use ($seq) {
        Route::post('sequences', [$seq, 'storeSequence']);
        Route::put('sequences/{sequence}', [$seq, 'updateSequence']);
        Route::delete('sequences/{sequence}', [$seq, 'destroySequence']);
        Route::post('sequences/enroll', [$seq, 'enrollInSequence']);
        Route::put('enrollments/{enrollment}/cancel', [$seq, 'unenrollFromSequence']);
    });

    // Forecasting → CrmSalesPipelineController
    Route::middleware('check.permission:crm.forecast.view')->group(function () use ($sp) {
        Route::get('forecast', [$sp, 'forecast']);
        Route::post('forecast/snapshot', [$sp, 'snapshotForecast']);
    });

    // Smart Alerts → CrmAlertController
    Route::middleware('check.permission:crm.deal.view')->group(function () use ($al) {
        Route::get('alerts', [$al, 'smartAlerts']);
        Route::put('alerts/{alert}/acknowledge', [$al, 'acknowledgeAlert']);
        Route::put('alerts/{alert}/resolve', [$al, 'resolveAlert']);
        Route::put('alerts/{alert}/dismiss', [$al, 'dismissAlert']);
        Route::post('alerts/generate', [$al, 'generateSmartAlerts']);
    });

    // Cross-sell / Up-sell → CrmIntelligenceController
    Route::middleware('check.permission:crm.deal.view')->group(function () use ($int) {
        Route::get('customers/{customer}/recommendations', [$int, 'crossSellRecommendations']);
    });

    // Loss Reasons → CrmIntelligenceController
    Route::middleware('check.permission:crm.deal.view')->group(function () use ($int) {
        Route::get('loss-reasons', [$int, 'lossReasons']);
        Route::get('loss-analytics', [$int, 'lossAnalytics']);
    });
    Route::middleware('check.permission:crm.deal.update')->group(function () use ($int) {
        Route::post('loss-reasons', [$int, 'storeLossReason']);
        Route::put('loss-reasons/{reason}', [$int, 'updateLossReason']);
    });

    // Territories → CrmTerritoryGoalController
    Route::middleware('check.permission:crm.territory.view')->group(function () use ($tg) {
        Route::get('territories', [$tg, 'territories']);
    });
    Route::middleware('check.permission:crm.territory.manage')->group(function () use ($tg) {
        Route::post('territories', [$tg, 'storeTerritory']);
        Route::put('territories/{territory}', [$tg, 'updateTerritory']);
        Route::delete('territories/{territory}', [$tg, 'destroyTerritory']);
    });

    // Sales Goals → CrmTerritoryGoalController
    Route::middleware('check.permission:crm.goal.view')->group(function () use ($tg) {
        Route::get('goals', [$tg, 'salesGoals']);
        Route::get('goals/dashboard', [$tg, 'goalsDashboard']);
    });
    Route::middleware('check.permission:crm.goal.manage')->group(function () use ($tg) {
        Route::post('goals', [$tg, 'storeSalesGoal']);
        Route::put('goals/{goal}', [$tg, 'updateSalesGoal']);
        Route::post('goals/recalculate', [$tg, 'recalculateGoals']);
    });

    // Pipeline Velocity → CrmSalesPipelineController
    Route::middleware('check.permission:crm.deal.view')->get('velocity', [$sp, 'pipelineVelocity']);

    // Contract Renewals → CrmContractController
    Route::middleware('check.permission:crm.renewal.view')->group(function () use ($ct) {
        Route::get('renewals', [$ct, 'contractRenewals']);
        Route::post('renewals/generate', [$ct, 'generateRenewals']);
    });
    Route::middleware('check.permission:crm.renewal.manage')->group(function () use ($ct) {
        Route::put('renewals/{renewal}', [$ct, 'updateRenewal']);
    });

    // Web Forms → CrmWebFormController
    Route::middleware('check.permission:crm.form.view')->group(function () use ($wf) {
        Route::get('web-forms', [$wf, 'webForms']);
        Route::get('web-forms/options', [$wf, 'webFormOptions']);
    });
    Route::middleware('check.permission:crm.form.manage')->group(function () use ($wf) {
        Route::post('web-forms', [$wf, 'storeWebForm']);
        Route::put('web-forms/{form}', [$wf, 'updateWebForm']);
        Route::delete('web-forms/{form}', [$wf, 'destroyWebForm']);
    });

    // Interactive Proposals → CrmProposalTrackingController
    Route::middleware('check.permission:crm.proposal.view')->group(function () use ($pt) {
        Route::get('proposals', [$pt, 'interactiveProposals']);
    });
    Route::middleware('check.permission:crm.proposal.manage')->group(function () use ($pt) {
        Route::post('proposals', [$pt, 'createInteractiveProposal']);
    });

    // Tracking Events → CrmProposalTrackingController
    Route::middleware('check.permission:crm.deal.view')->get('tracking', [$pt, 'trackingEvents']);
    Route::middleware('check.permission:crm.deal.view')->get('tracking/stats', [$pt, 'trackingStats']);

    // NPS Automation → CrmAlertController
    Route::middleware('check.permission:crm.deal.view')->get('nps/stats', [$al, 'npsAutomationConfig']);

    // Referrals → CrmEngagementController
    Route::middleware('check.permission:crm.referral.view')->group(function () use ($eng) {
        Route::get('referrals', [$eng, 'referrals']);
        Route::get('referrals/stats', [$eng, 'referralStats']);
        Route::get('referrals/options', [$eng, 'referralOptions']);
    });
    Route::middleware('check.permission:crm.referral.manage')->group(function () use ($eng) {
        Route::post('referrals', [$eng, 'storeReferral']);
        Route::put('referrals/{referral}', [$eng, 'updateReferral']);
        Route::delete('referrals/{referral}', [$eng, 'destroyReferral']);
    });

    // Calendar → CrmEngagementController
    Route::middleware('check.permission:crm.deal.view')->group(function () use ($eng) {
        Route::get('calendar', [$eng, 'calendarEvents']);
        Route::post('calendar', [$eng, 'storeCalendarEvent']);
        Route::put('calendar/{event}', [$eng, 'updateCalendarEvent']);
        Route::delete('calendar/{event}', [$eng, 'destroyCalendarEvent']);
    });

    // Cohort Analysis → CrmSalesPipelineController
    Route::middleware('check.permission:crm.forecast.view')->get('cohort', [$sp, 'cohortAnalysis']);

    // Revenue Intelligence → CrmIntelligenceController
    Route::middleware('check.permission:crm.forecast.view')->get('revenue-intelligence', [$int, 'revenueIntelligence']);

    // Competitive Matrix → CrmIntelligenceController
    Route::middleware('check.permission:crm.deal.view')->group(function () use ($int) {
        Route::get('competitors', [$int, 'competitiveMatrix']);
        Route::get('competitors/options', [$int, 'competitorOptions']);
    });
    Route::middleware('check.permission:crm.deal.update')->group(function () use ($int) {
        Route::post('competitors', [$int, 'storeDealCompetitor']);
        Route::put('competitors/{competitor}', [$int, 'updateDealCompetitor']);
    });
    Route::middleware('check.permission:crm.deal.delete')->delete('competitors/{competitor}', [$int, 'destroyDealCompetitor']);

    // CSV Import/Export → CrmEngagementController
    Route::middleware('check.permission:crm.deal.view')->get('deals/export-csv', [$eng, 'exportDealsCsv']);
    Route::middleware('check.permission:crm.deal.update')->post('deals/import-csv', [$eng, 'importDealsCsv']);

    // Calendar Activities Integration → CrmEngagementController
    Route::middleware('check.permission:crm.deal.view')->get('calendar/activities', [$eng, 'calendarActivities']);
});

// ─── CRM Field Management (20 novas funcionalidades de gestão) ─────
$fm = CrmFieldManagementController::class;
Route::prefix('crm-field')->group(function () use ($fm) {
    Route::middleware('check.permission:crm.deal.view')->get('constants', [$fm, 'constants']);

    // Visit Checkins
    Route::middleware('check.permission:crm.deal.view')->group(function () use ($fm) {
        Route::get('checkins', [$fm, 'checkinsIndex']);
        Route::post('checkins', [$fm, 'checkin']);
        Route::put('checkins/{checkin}/checkout', [$fm, 'checkout']);
    });

    // Visit Routes
    Route::middleware('check.permission:crm.deal.view')->group(function () use ($fm) {
        Route::get('routes', [$fm, 'routesIndex']);
        Route::post('routes', [$fm, 'routesStore']);
        Route::put('routes/{route}', [$fm, 'routesUpdate']);
    });

    // Visit Reports
    Route::middleware('check.permission:crm.deal.view')->group(function () use ($fm) {
        Route::get('reports', [$fm, 'reportsIndex']);
        Route::post('reports', [$fm, 'reportsStore']);
    });

    // Portfolio Map
    Route::middleware('check.permission:crm.deal.view')->get('portfolio-map', [$fm, 'portfolioMap']);

    // Forgotten Clients
    Route::middleware('check.permission:crm.deal.view')->get('forgotten-clients', [$fm, 'forgottenClients']);

    // Contact Policies
    Route::middleware('check.permission:crm.deal.view')->group(function () use ($fm) {
        Route::get('policies', [$fm, 'policiesIndex']);
        Route::post('policies', [$fm, 'policiesStore']);
        Route::put('policies/{policy}', [$fm, 'policiesUpdate']);
        Route::delete('policies/{policy}', [$fm, 'policiesDestroy']);
    });

    // Smart Agenda
    Route::middleware('check.permission:crm.deal.view')->get('smart-agenda', [$fm, 'smartAgenda']);

    // Quick Notes
    Route::middleware('check.permission:crm.deal.view')->group(function () use ($fm) {
        Route::get('quick-notes', [$fm, 'quickNotesIndex']);
        Route::post('quick-notes', [$fm, 'quickNotesStore']);
        Route::put('quick-notes/{note}', [$fm, 'quickNotesUpdate']);
        Route::delete('quick-notes/{note}', [$fm, 'quickNotesDestroy']);
    });

    // Commitments
    Route::middleware('check.permission:crm.deal.view')->group(function () use ($fm) {
        Route::get('commitments', [$fm, 'commitmentsIndex']);
        Route::post('commitments', [$fm, 'commitmentsStore']);
        Route::put('commitments/{commitment}', [$fm, 'commitmentsUpdate']);
    });

    // Negotiation History
    Route::middleware('check.permission:crm.deal.view')->get('customers/{customer}/negotiation-history', [$fm, 'negotiationHistory']);

    // Client Summary
    Route::middleware('check.permission:crm.deal.view')->get('customers/{customer}/summary', [$fm, 'clientSummary']);

    // RFM
    Route::middleware('check.permission:crm.deal.view')->group(function () use ($fm) {
        Route::get('rfm', [$fm, 'rfmIndex']);
        Route::post('rfm/recalculate', [$fm, 'rfmRecalculate']);
    });

    // Portfolio Coverage
    Route::middleware('check.permission:crm.deal.view')->get('coverage', [$fm, 'portfolioCoverage']);

    // Commercial Productivity
    Route::middleware('check.permission:crm.deal.view')->get('productivity', [$fm, 'commercialProductivity']);

    // Latent Opportunities
    Route::middleware('check.permission:crm.deal.view')->get('opportunities', [$fm, 'latentOpportunities']);

    // Important Dates
    Route::middleware('check.permission:crm.deal.view')->group(function () use ($fm) {
        Route::get('important-dates', [$fm, 'importantDatesIndex']);
        Route::post('important-dates', [$fm, 'importantDatesStore']);
        Route::put('important-dates/{date}', [$fm, 'importantDatesUpdate']);
        Route::delete('important-dates/{date}', [$fm, 'importantDatesDestroy']);
    });

    // Visit Surveys
    Route::middleware('check.permission:crm.deal.view')->group(function () use ($fm) {
        Route::get('surveys', [$fm, 'surveysIndex']);
        Route::post('surveys', [$fm, 'surveysSend']);
    });

    // Account Plans
    Route::middleware('check.permission:crm.deal.view')->group(function () use ($fm) {
        Route::get('account-plans', [$fm, 'accountPlansIndex']);
        Route::post('account-plans', [$fm, 'accountPlansStore']);
        Route::put('account-plans/{plan}', [$fm, 'accountPlansUpdate']);
        Route::put('account-plan-actions/{action}', [$fm, 'accountPlanActionsUpdate']);
    });

    // Gamification
    Route::middleware('check.permission:crm.deal.view')->group(function () use ($fm) {
        Route::get('gamification', [$fm, 'gamificationDashboard']);
        Route::post('gamification/recalculate', [$fm, 'gamificationRecalculate']);
    });
});

// Public: Visit Survey Answer (no auth)
Route::withoutMiddleware(['auth:sanctum', EnsureTenantScope::class])
    ->middleware('throttle:tenant-mutations')
    ->post('crm-field/surveys/{token}/answer', [$fm, 'surveysAnswer']);

// Public: Interactive Proposal (no auth for client view)
Route::prefix('proposals')
    ->withoutMiddleware(['auth:sanctum', EnsureTenantScope::class])
    ->middleware('throttle:tenant-bulk')
    ->group(function () use ($pt) {
        Route::get('{token}/view', [$pt, 'viewInteractiveProposal']);
        Route::post('{token}/respond', [$pt, 'respondToProposal']);
    });

// Public: Web Form submission (no auth)
Route::withoutMiddleware(['auth:sanctum', EnsureTenantScope::class])
    ->middleware('throttle:tenant-mutations')
    ->post('web-forms/{slug}/submit', [$wf, 'submitWebForm']);

// Tracking Pixel (no auth)
Route::withoutMiddleware(['auth:sanctum', EnsureTenantScope::class])
    ->middleware('throttle:tenant-tracking')
    ->get('crm-pixel/{trackingId}', [$pt, 'trackingPixel']);
