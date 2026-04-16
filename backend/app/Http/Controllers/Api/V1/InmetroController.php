<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inmetro\CalculateDistancesInmetroRequest;
use App\Http\Requests\Inmetro\EnrichBatchInmetroRequest;
use App\Http\Requests\Inmetro\ImportInmetroXmlRequest;
use App\Http\Requests\Inmetro\InmetroServiceInputRequest;
use App\Http\Requests\Inmetro\StoreInmetroCompetitorRequest;
use App\Http\Requests\Inmetro\StoreInmetroOwnerRequest;
use App\Http\Requests\Inmetro\SubmitPsieResultsRequest;
use App\Http\Requests\Inmetro\UpdateInmetroBaseConfigRequest;
use App\Http\Requests\Inmetro\UpdateInmetroConfigRequest;
use App\Http\Requests\Inmetro\UpdateInmetroLeadStatusRequest;
use App\Http\Requests\Inmetro\UpdateInmetroOwnerRequest;
use App\Services\InmetroService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;

class InmetroController extends Controller
{
    public function __construct(private InmetroService $service) {}

    /**
     * @return array<string, mixed>
     */
    private function serviceInput(FormRequest $request): array
    {
        return $request->validated();
    }

    /**
     * Dashboard with KPIs and summary.
     */
    public function dashboard(InmetroServiceInputRequest $request): JsonResponse
    {
        return $this->service->dashboard($this->serviceInput($request), $request->user(), $request->user()->current_tenant_id);
    }

    /**
     * List owners (prospects) with pagination and filters.
     */
    public function owners(InmetroServiceInputRequest $request): JsonResponse
    {
        return $this->service->owners($this->serviceInput($request), $request->user(), $request->user()->current_tenant_id);
    }

    /**
     * Show owner detail with locations, instruments, and history.
     */
    public function showOwner(InmetroServiceInputRequest $request, int $id): JsonResponse
    {
        return $this->service->showOwner($this->serviceInput($request), $id, $request->user(), $request->user()->current_tenant_id);
    }

    public function storeOwner(StoreInmetroOwnerRequest $request): JsonResponse
    {
        return $this->service->storeOwner($this->serviceInput($request), $request->user(), $request->user()->current_tenant_id);
    }

    /**
     * List instruments with filters (expiration, city, status).
     */
    public function instruments(InmetroServiceInputRequest $request): JsonResponse
    {
        return $this->service->instruments($this->serviceInput($request), $request->user(), $request->user()->current_tenant_id);
    }

    /**
     * List leads sorted by priority and expiration.
     */
    public function leads(InmetroServiceInputRequest $request): JsonResponse
    {
        return $this->service->leads($this->serviceInput($request), $request->user(), $request->user()->current_tenant_id);
    }

    /**
     * List competitors (authorized repair shops).
     */
    public function competitors(InmetroServiceInputRequest $request): JsonResponse
    {
        return $this->service->competitors($this->serviceInput($request), $request->user(), $request->user()->current_tenant_id);
    }

    public function storeCompetitor(StoreInmetroCompetitorRequest $request): JsonResponse
    {
        return $this->service->storeCompetitor($this->serviceInput($request), $request->user(), $request->user()->current_tenant_id);
    }

    /**
     * Import XML data from RBMLQ open data — multi-UF, multi-type.
     */
    public function importXml(ImportInmetroXmlRequest $request): JsonResponse
    {
        return $this->service->importXml($this->serviceInput($request), $request->user(), $request->user()->current_tenant_id);
    }

    /**
     * Get available instrument types for import.
     */
    public function instrumentTypes(InmetroServiceInputRequest $request): JsonResponse
    {
        return $this->service->instrumentTypes($request->user(), (int) $request->user()->current_tenant_id);
    }

    /**
     * Get available Brazilian UFs.
     */
    public function availableUfs(InmetroServiceInputRequest $request): JsonResponse
    {
        return $this->service->availableUfs($request->user(), (int) $request->user()->current_tenant_id);
    }

    /**
     * Get tenant INMETRO config.
     */
    public function getConfig(InmetroServiceInputRequest $request): JsonResponse
    {
        return $this->service->getConfig($this->serviceInput($request), $request->user(), $request->user()->current_tenant_id);
    }

    /**
     * Update tenant INMETRO config.
     */
    public function updateConfig(UpdateInmetroConfigRequest $request): JsonResponse
    {
        return $this->service->updateConfig($this->serviceInput($request), $request->user(), $request->user()->current_tenant_id);
    }

    /**
     * Initialize PSIE captcha session for manual scraping.
     */
    public function initPsieScrape(InmetroServiceInputRequest $request): JsonResponse
    {
        return $this->service->initPsieScrape($this->serviceInput($request), $request->user(), $request->user()->current_tenant_id);
    }

    /**
     * Submit PSIE scrape results after manual captcha resolution.
     */
    public function submitPsieResults(SubmitPsieResultsRequest $request): JsonResponse
    {
        return $this->service->submitPsieResults($this->serviceInput($request), $request->user(), $request->user()->current_tenant_id);
    }

    /**
     * Enrich a single owner's contact data.
     */
    public function enrichOwner(InmetroServiceInputRequest $request, int $ownerId): JsonResponse
    {
        return $this->service->enrichOwner($this->serviceInput($request), $ownerId, $request->user(), $request->user()->current_tenant_id);
    }

    /**
     * Batch enrich multiple owners.
     */
    public function enrichBatch(EnrichBatchInmetroRequest $request): JsonResponse
    {
        return $this->service->enrichBatch($this->serviceInput($request), $request->user(), $request->user()->current_tenant_id);
    }

    /**
     * Convert an INMETRO prospect into a CRM customer.
     */
    public function convertToCustomer(InmetroServiceInputRequest $request, int $ownerId): JsonResponse
    {
        return $this->service->convertToCustomer($this->serviceInput($request), $ownerId, $request->user(), $request->user()->current_tenant_id);
    }

    /**
     * Update owner lead status.
     */
    public function updateLeadStatus(UpdateInmetroLeadStatusRequest $request, int $ownerId): JsonResponse
    {
        return $this->service->updateLeadStatus($this->serviceInput($request), $ownerId, $request->user(), $request->user()->current_tenant_id);
    }

    /**
     * Get MT municipalities list from IBGE.
     */
    public function municipalities(InmetroServiceInputRequest $request): JsonResponse
    {
        return $this->service->municipalities(auth()->user(), $request->user()->current_tenant_id);
    }

    /**
     * Recalculate all owner priorities.
     */
    public function recalculatePriorities(InmetroServiceInputRequest $request): JsonResponse
    {
        return $this->service->recalculatePriorities($this->serviceInput($request), $request->user(), $request->user()->current_tenant_id);
    }

    /**
     * Get available cities with instrument counts.
     */
    public function cities(InmetroServiceInputRequest $request): JsonResponse
    {
        return $this->service->cities($this->serviceInput($request), $request->user(), $request->user()->current_tenant_id);
    }

    /**
     * Show instrument detail with history.
     */
    public function showInstrument(InmetroServiceInputRequest $request, int $id): JsonResponse
    {
        return $this->service->showInstrument($this->serviceInput($request), $id, $request->user(), $request->user()->current_tenant_id);
    }

    /**
     * Conversion statistics for the dashboard.
     */
    public function conversionStats(InmetroServiceInputRequest $request): JsonResponse
    {
        return $this->service->conversionStats($this->serviceInput($request), $request->user(), $request->user()->current_tenant_id);
    }

    /**
     * Update owner details.
     */
    public function update(UpdateInmetroOwnerRequest $request, int $id): JsonResponse
    {
        return $this->service->update($this->serviceInput($request), $id, $request->user(), $request->user()->current_tenant_id);
    }

    /**
     * Delete owner (and associated instruments/locations).
     */
    public function destroy(InmetroServiceInputRequest $request, int $id): JsonResponse
    {
        return $this->service->destroy($this->serviceInput($request), $id, $request->user(), $request->user()->current_tenant_id);
    }

    /**
     * Export leads as CSV.
     */
    public function exportLeadsCsv(InmetroServiceInputRequest $request)
    {
        return $this->service->exportLeadsCsv($this->serviceInput($request), $request->user(), $request->user()->current_tenant_id);
    }

    /**
     * Export instruments as CSV.
     */
    public function exportInstrumentsCsv(InmetroServiceInputRequest $request)
    {
        return $this->service->exportInstrumentsCsv($this->serviceInput($request), $request->user(), $request->user()->current_tenant_id);
    }

    /**
     * Cross-reference INMETRO owners with CRM customers by document.
     */
    public function crossReference(InmetroServiceInputRequest $request): JsonResponse
    {
        return $this->service->crossReference($this->serviceInput($request), $request->user(), $request->user()->current_tenant_id);
    }

    /**
     * Get INMETRO profile for a specific CRM customer.
     */
    public function customerInmetroProfile(InmetroServiceInputRequest $request, int $customerId): JsonResponse
    {
        return $this->service->customerInmetroProfile($this->serviceInput($request), $customerId, $request->user(), $request->user()->current_tenant_id);
    }

    /**
     * Get cross-reference summary stats.
     */
    public function crossReferenceStats(InmetroServiceInputRequest $request): JsonResponse
    {
        return $this->service->crossReferenceStats($this->serviceInput($request), $request->user(), $request->user()->current_tenant_id);
    }

    /**
     * Get map data: geolocated locations with instruments.
     */
    public function mapData(InmetroServiceInputRequest $request): JsonResponse
    {
        return $this->service->mapData($this->serviceInput($request), $request->user(), $request->user()->current_tenant_id);
    }

    /**
     * Geocode locations without coordinates.
     */
    public function geocodeLocations(InmetroServiceInputRequest $request): JsonResponse
    {
        return $this->service->geocodeLocations($this->serviceInput($request), $request->user(), $request->user()->current_tenant_id);
    }

    /**
     * Calculate distances from a base point.
     */
    public function calculateDistances(CalculateDistancesInmetroRequest $request): JsonResponse
    {
        return $this->service->calculateDistances($this->serviceInput($request), $request->user(), $request->user()->current_tenant_id);
    }

    // ─── Market Intelligence ───────────────────────────────────────

    /**
     * Market overview KPIs.
     */
    public function marketOverview(InmetroServiceInputRequest $request): JsonResponse
    {
        return $this->service->marketOverview($this->serviceInput($request), $request->user(), $request->user()->current_tenant_id);
    }

    /**
     * Competitor analysis.
     */
    public function competitorAnalysis(InmetroServiceInputRequest $request): JsonResponse
    {
        return $this->service->competitorAnalysis($this->serviceInput($request), $request->user(), $request->user()->current_tenant_id);
    }

    /**
     * Regional market analysis.
     */
    public function regionalAnalysis(InmetroServiceInputRequest $request): JsonResponse
    {
        return $this->service->regionalAnalysis($this->serviceInput($request), $request->user(), $request->user()->current_tenant_id);
    }

    /**
     * Brand and type analysis.
     */
    public function brandAnalysis(InmetroServiceInputRequest $request): JsonResponse
    {
        return $this->service->brandAnalysis($this->serviceInput($request), $request->user(), $request->user()->current_tenant_id);
    }

    /**
     * Expiration forecast (12 months).
     */
    public function expirationForecast(InmetroServiceInputRequest $request): JsonResponse
    {
        return $this->service->expirationForecast($this->serviceInput($request), $request->user(), $request->user()->current_tenant_id);
    }

    /**
     * Monthly trends (12-month tracking).
     */
    public function monthlyTrends(InmetroServiceInputRequest $request): JsonResponse
    {
        return $this->service->monthlyTrends($this->serviceInput($request), $request->user(), $request->user()->current_tenant_id);
    }

    /**
     * Revenue ranking (top 20 leads by estimated revenue).
     */
    public function revenueRanking(InmetroServiceInputRequest $request): JsonResponse
    {
        return $this->service->revenueRanking($this->serviceInput($request), $request->user(), $request->user()->current_tenant_id);
    }

    /**
     * Export leads as PDF report.
     */
    public function exportLeadsPdf(InmetroServiceInputRequest $request): JsonResponse
    {
        return $this->service->exportLeadsPdf($this->serviceInput($request), $request->user(), $request->user()->current_tenant_id);
    }

    /**
     * Get tenant base geolocation config.
     */
    public function getBaseConfig(InmetroServiceInputRequest $request): JsonResponse
    {
        return $this->service->getBaseConfig($this->serviceInput($request), $request->user(), $request->user()->current_tenant_id);
    }

    /**
     * Update tenant base geolocation config.
     */
    public function updateBaseConfig(UpdateInmetroBaseConfigRequest $request): JsonResponse
    {
        return $this->service->updateBaseConfig($this->serviceInput($request), $request->user(), $request->user()->current_tenant_id);
    }

    /**
     * Enrich owner data from dados.gov.br (enterprise CNPJ data).
     */
    public function enrichFromDadosGov(InmetroServiceInputRequest $request, int $ownerId): JsonResponse
    {
        return $this->service->enrichFromDadosGov($this->serviceInput($request), $ownerId, $request->user(), $request->user()->current_tenant_id);
    }

    /**
     * Get available datasets from dados.gov.br.
     */
    public function availableDatasets(InmetroServiceInputRequest $request): JsonResponse
    {
        return $this->service->availableDatasets(auth()->user(), $request->user()->current_tenant_id);
    }

    /**
     * Deep enrich an owner using ALL available sources.
     */
    public function deepEnrich(InmetroServiceInputRequest $request, int $ownerId): JsonResponse
    {
        return $this->service->deepEnrich($this->serviceInput($request), $ownerId, $request->user(), $request->user()->current_tenant_id);
    }

    /**
     * Search PSIE using authenticated session (no captcha needed if credentials are configured).
     */
    public function searchPsieAuth(InmetroServiceInputRequest $request): JsonResponse
    {
        return $this->service->searchPsieAuth($this->serviceInput($request), $request->user(), $request->user()->current_tenant_id);
    }

    /**
     * Generate WhatsApp link for a specific owner (for manual use from frontend).
     */
    public function generateWhatsappLink(InmetroServiceInputRequest $request, int $ownerId): JsonResponse
    {
        return $this->service->generateWhatsappLink($this->serviceInput($request), $ownerId, $request->user(), $request->user()->current_tenant_id);
    }
}
