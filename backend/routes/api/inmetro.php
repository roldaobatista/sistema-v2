<?php

/**
 * Routes: INMETRO (Inteligencia + Advanced)
 * Extracted from api.php - Phase 10 Modularization
 * Original lines: 915-1046
 */

use App\Http\Controllers\Api\V1\InmetroAdvancedController;
use App\Http\Controllers\Api\V1\InmetroController;
use Illuminate\Support\Facades\Route;

// Inteligência INMETRO
Route::middleware('check.permission:inmetro.intelligence.view')->group(function () {
    Route::get('inmetro/dashboard', [InmetroController::class, 'dashboard']);
    Route::get('inmetro/owners', [InmetroController::class, 'owners']);
    Route::get('inmetro/owners/{id}', [InmetroController::class, 'showOwner']);
    Route::get('inmetro/instruments', [InmetroController::class, 'instruments']);
    Route::get('inmetro/instruments/{id}', [InmetroController::class, 'showInstrument']);
    Route::get('inmetro/leads', [InmetroController::class, 'leads']);
    Route::get('inmetro/competitors', [InmetroController::class, 'competitors']);
    Route::get('inmetro/cities', [InmetroController::class, 'cities']);
    Route::middleware('check.permission:inmetro.view')->get('inmetro/municipalities', [InmetroController::class, 'municipalities']);
    Route::middleware('check.permission:inmetro.view')->get('inmetro/conversion-stats', [InmetroController::class, 'conversionStats']);
    Route::middleware('check.permission:inmetro.view')->get('inmetro/export/leads', [InmetroController::class, 'exportLeadsCsv']);
    Route::middleware('check.permission:inmetro.view')->get('inmetro/export/instruments', [InmetroController::class, 'exportInstrumentsCsv']);
    Route::middleware('check.permission:inmetro.view')->get('inmetro/instrument-types', [InmetroController::class, 'instrumentTypes']);
    Route::middleware('check.permission:inmetro.view')->get('inmetro/available-ufs', [InmetroController::class, 'availableUfs']);
    Route::middleware('check.permission:inmetro.view')->get('inmetro/config', [InmetroController::class, 'getConfig']);
    Route::middleware('check.permission:inmetro.view')->get('inmetro/cross-reference-stats', [InmetroController::class, 'crossReferenceStats']);
    Route::middleware('check.permission:inmetro.view')->get('inmetro/customer-profile/{customerId}', [InmetroController::class, 'customerInmetroProfile']);
    Route::middleware('check.permission:inmetro.view')->get('inmetro/map-data', [InmetroController::class, 'mapData']);
    Route::middleware('check.permission:inmetro.view')->get('inmetro/market-overview', [InmetroController::class, 'marketOverview']);
    Route::middleware('check.permission:inmetro.view')->get('inmetro/competitor-analysis', [InmetroController::class, 'competitorAnalysis']);
    Route::middleware('check.permission:inmetro.view')->get('inmetro/regional-analysis', [InmetroController::class, 'regionalAnalysis']);
    Route::middleware('check.permission:inmetro.view')->get('inmetro/brand-analysis', [InmetroController::class, 'brandAnalysis']);
    Route::middleware('check.permission:inmetro.view')->get('inmetro/expiration-forecast', [InmetroController::class, 'expirationForecast']);
    Route::middleware('check.permission:inmetro.view')->get('inmetro/monthly-trends', [InmetroController::class, 'monthlyTrends']);
    Route::middleware('check.permission:inmetro.view')->get('inmetro/revenue-ranking', [InmetroController::class, 'revenueRanking']);
    Route::middleware('check.permission:inmetro.view')->get('inmetro/export/leads-pdf', [InmetroController::class, 'exportLeadsPdf']);
    Route::middleware('check.permission:inmetro.view')->get('inmetro/base-config', [InmetroController::class, 'getBaseConfig']);
    Route::middleware('check.permission:inmetro.view')->get('inmetro/available-datasets', [InmetroController::class, 'availableDatasets']);
});
Route::middleware('check.permission:inmetro.intelligence.import')->group(function () {
    Route::post('inmetro/import/xml', [InmetroController::class, 'importXml']);
    Route::post('inmetro/import/psie-init', [InmetroController::class, 'initPsieScrape']);
    Route::post('inmetro/import/psie-results', [InmetroController::class, 'submitPsieResults']);
    Route::post('inmetro/import/psie-auth-search', [InmetroController::class, 'searchPsieAuth']);
    Route::put('inmetro/config', [InmetroController::class, 'updateConfig']);
    Route::post('inmetro/geocode', [InmetroController::class, 'geocodeLocations']);
    Route::post('inmetro/calculate-distances', [InmetroController::class, 'calculateDistances']);
    Route::put('inmetro/base-config', [InmetroController::class, 'updateBaseConfig']);
    Route::post('inmetro/whatsapp-link/{ownerId}', [InmetroController::class, 'generateWhatsappLink']);
});

Route::middleware('check.permission:inmetro.intelligence.enrich')->group(function () {
    Route::post('inmetro/enrich/{ownerId}', [InmetroController::class, 'enrichOwner']);
    Route::post('inmetro/enrich-batch', [InmetroController::class, 'enrichBatch']);
    Route::post('inmetro/enrich-dadosgov/{ownerId}', [InmetroController::class, 'enrichFromDadosGov']);
    Route::post('inmetro/deep-enrich/{ownerId}', [InmetroController::class, 'deepEnrich']);
});

Route::middleware('check.permission:inmetro.intelligence.convert')->group(function () {
    Route::post('inmetro/owners', [InmetroController::class, 'storeOwner']);
    Route::post('inmetro/competitors', [InmetroController::class, 'storeCompetitor']);
    Route::post('inmetro/convert/{ownerId}', [InmetroController::class, 'convertToCustomer']);
    Route::patch('inmetro/owners/{ownerId}/status', [InmetroController::class, 'updateLeadStatus']);
    Route::post('inmetro/recalculate-priorities', [InmetroController::class, 'recalculatePriorities']);
    Route::post('inmetro/cross-reference', [InmetroController::class, 'crossReference']);
    Route::put('inmetro/owners/{id}', [InmetroController::class, 'update']);
    Route::delete('inmetro/owners/{id}', [InmetroController::class, 'destroy']);
});

// â”€â”€â”€ INMETRO Advanced (50 Features) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
// Prospection & Lead Management (view-level)
Route::middleware('check.permission:inmetro.intelligence.view')->group(function () {
    Route::get('inmetro/advanced/contact-queue', [InmetroAdvancedController::class, 'getContactQueue']);
    Route::get('inmetro/advanced/follow-ups', [InmetroAdvancedController::class, 'followUps']);
    Route::get('inmetro/advanced/lead-score/{ownerId}', [InmetroAdvancedController::class, 'calculateLeadScore']);
    Route::get('inmetro/advanced/churn', [InmetroAdvancedController::class, 'detectChurn']);
    Route::get('inmetro/advanced/new-registrations', [InmetroAdvancedController::class, 'newRegistrations']);
    Route::get('inmetro/advanced/next-calibrations', [InmetroAdvancedController::class, 'suggestNextCalibrations']);
    Route::get('inmetro/advanced/segment-distribution', [InmetroAdvancedController::class, 'segmentDistribution']);
    Route::get('inmetro/advanced/reject-alerts', [InmetroAdvancedController::class, 'rejectAlerts']);
    Route::middleware('check.permission:inmetro.view')->get('inmetro/advanced/conversion-ranking', [InmetroAdvancedController::class, 'conversionRanking']);
    Route::middleware('check.permission:inmetro.view')->get('inmetro/advanced/interactions/{ownerId}', [InmetroAdvancedController::class, 'interactionHistory']);
    // Territorial Intelligence
    Route::middleware('check.permission:inmetro.view')->get('inmetro/advanced/map-layers', [InmetroAdvancedController::class, 'layeredMapData']);
    Route::middleware('check.permission:inmetro.view')->get('inmetro/advanced/competitor-zones', [InmetroAdvancedController::class, 'competitorZones']);
    Route::middleware('check.permission:inmetro.view')->get('inmetro/advanced/coverage-potential', [InmetroAdvancedController::class, 'coverageVsPotential']);
    Route::middleware('check.permission:inmetro.view')->get('inmetro/advanced/nearby-leads', [InmetroAdvancedController::class, 'nearbyLeads']);
    // Competitor Tracking
    Route::middleware('check.permission:inmetro.view')->get('inmetro/advanced/market-share-timeline', [InmetroAdvancedController::class, 'marketShareTimeline']);
    Route::middleware('check.permission:inmetro.view')->get('inmetro/advanced/competitor-movements', [InmetroAdvancedController::class, 'competitorMovements']);
    Route::middleware('check.permission:inmetro.view')->get('inmetro/advanced/pricing-estimate', [InmetroAdvancedController::class, 'estimatePricing']);
    Route::middleware('check.permission:inmetro.view')->get('inmetro/advanced/competitor-profile/{competitorId}', [InmetroAdvancedController::class, 'competitorProfile']);
    Route::middleware('check.permission:inmetro.view')->get('inmetro/advanced/win-loss', [InmetroAdvancedController::class, 'winLossAnalysis']);
    // Operational Bridge
    Route::middleware('check.permission:inmetro.view')->get('inmetro/advanced/suggest-equipments/{customerId}', [InmetroAdvancedController::class, 'suggestLinkedEquipments']);
    Route::middleware('check.permission:inmetro.view')->get('inmetro/advanced/prefill-certificate/{instrumentId}', [InmetroAdvancedController::class, 'prefillCertificate']);
    Route::middleware('check.permission:inmetro.view')->get('inmetro/advanced/instrument-timeline/{instrumentId}', [InmetroAdvancedController::class, 'instrumentTimeline']);
    Route::middleware('check.permission:inmetro.view')->get('inmetro/advanced/compare-calibrations/{instrumentId}', [InmetroAdvancedController::class, 'compareCalibrations']);
    // Reporting
    Route::middleware('check.permission:inmetro.view')->get('inmetro/advanced/executive-dashboard', [InmetroAdvancedController::class, 'executiveDashboard']);
    Route::middleware('check.permission:inmetro.view')->get('inmetro/advanced/revenue-forecast', [InmetroAdvancedController::class, 'revenueForecast']);
    Route::middleware('check.permission:inmetro.view')->get('inmetro/advanced/conversion-funnel', [InmetroAdvancedController::class, 'conversionFunnel']);
    Route::middleware('check.permission:inmetro.view')->get('inmetro/advanced/export-data', [InmetroAdvancedController::class, 'exportData']);
    Route::middleware('check.permission:inmetro.view')->get('inmetro/advanced/year-over-year', [InmetroAdvancedController::class, 'yearOverYear']);
    // Compliance
    Route::middleware('check.permission:inmetro.view')->get('inmetro/advanced/compliance-checklists', [InmetroAdvancedController::class, 'complianceChecklists']);
    Route::middleware('check.permission:inmetro.view')->get('inmetro/advanced/regulatory-traceability/{instrumentId}', [InmetroAdvancedController::class, 'regulatoryTraceability']);
    Route::middleware('check.permission:inmetro.view')->get('inmetro/advanced/corporate-groups', [InmetroAdvancedController::class, 'corporateGroups']);
    Route::middleware('check.permission:inmetro.view')->get('inmetro/advanced/compliance-instrument-types', [InmetroAdvancedController::class, 'instrumentTypes']);
    Route::middleware('check.permission:inmetro.view')->get('inmetro/advanced/anomalies', [InmetroAdvancedController::class, 'detectAnomalies']);
    Route::middleware('check.permission:inmetro.view')->get('inmetro/advanced/renewal-probability', [InmetroAdvancedController::class, 'renewalProbability']);
    // Webhooks & API
    Route::middleware('check.permission:inmetro.view')->get('inmetro/advanced/public-data', [InmetroAdvancedController::class, 'publicInstrumentData']);
    Route::middleware('check.permission:inmetro.view')->get('inmetro/advanced/webhooks', [InmetroAdvancedController::class, 'listWebhooks']);
    Route::middleware('check.permission:inmetro.view')->get('inmetro/advanced/webhook-events', [InmetroAdvancedController::class, 'availableWebhookEvents']);
});

// INMETRO Advanced â€” Write operations (manage permission)
Route::middleware('check.permission:inmetro.intelligence.convert')->group(function () {
    // Prospection actions
    Route::post('inmetro/advanced/generate-queue', [InmetroAdvancedController::class, 'generateDailyQueue']);
    Route::patch('inmetro/advanced/queue/{queueId}', [InmetroAdvancedController::class, 'markQueueItem']);
    Route::post('inmetro/advanced/recalculate-scores', [InmetroAdvancedController::class, 'recalculateAllScores']);
    Route::post('inmetro/advanced/classify-segments', [InmetroAdvancedController::class, 'classifySegments']);
    Route::post('inmetro/advanced/interactions', [InmetroAdvancedController::class, 'logInteraction']);
    // Territorial actions
    Route::post('inmetro/advanced/optimize-route', [InmetroAdvancedController::class, 'optimizeRoute']);
    Route::middleware('check.permission:inmetro.view')->post('inmetro/advanced/density-viability', [InmetroAdvancedController::class, 'densityViability']);
    // Competitor actions
    Route::middleware('check.permission:inmetro.view')->post('inmetro/advanced/snapshot-market-share', [InmetroAdvancedController::class, 'snapshotMarketShare']);
    Route::middleware('check.permission:inmetro.view')->post('inmetro/advanced/win-loss', [InmetroAdvancedController::class, 'recordWinLoss']);
    // Operational Bridge
    Route::middleware('check.permission:inmetro.view')->post('inmetro/advanced/link-instrument', [InmetroAdvancedController::class, 'linkInstrument']);
    // Compliance actions
    Route::middleware('check.permission:inmetro.view')->post('inmetro/advanced/compliance-checklists', [InmetroAdvancedController::class, 'createChecklist']);
    Route::middleware('check.permission:inmetro.view')->put('inmetro/advanced/compliance-checklists/{id}', [InmetroAdvancedController::class, 'updateChecklist']);
    Route::middleware('check.permission:inmetro.view')->post('inmetro/advanced/simulate-impact', [InmetroAdvancedController::class, 'simulateRegulatoryImpact']);
    // Webhooks CRUD
    Route::middleware('check.permission:inmetro.view')->post('inmetro/advanced/webhooks', [InmetroAdvancedController::class, 'createWebhook']);
    Route::middleware('check.permission:inmetro.view')->put('inmetro/advanced/webhooks/{id}', [InmetroAdvancedController::class, 'updateWebhook']);
    Route::middleware('check.permission:inmetro.view')->delete('inmetro/advanced/webhooks/{id}', [InmetroAdvancedController::class, 'deleteWebhook']);
});
