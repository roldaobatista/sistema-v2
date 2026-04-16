<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inmetro\CreateChecklistInmetroRequest;
use App\Http\Requests\Inmetro\CreateWebhookInmetroRequest;
use App\Http\Requests\Inmetro\DensityViabilityInmetroRequest;
use App\Http\Requests\Inmetro\LinkInstrumentInmetroRequest;
use App\Http\Requests\Inmetro\LogInteractionInmetroRequest;
use App\Http\Requests\Inmetro\MarkQueueItemRequest;
use App\Http\Requests\Inmetro\NearbyLeadsInmetroRequest;
use App\Http\Requests\Inmetro\OptimizeRouteInmetroRequest;
use App\Http\Requests\Inmetro\RecordWinLossInmetroRequest;
use App\Http\Requests\Inmetro\SimulateRegulatoryImpactInmetroRequest;
use App\Http\Requests\Inmetro\UpdateChecklistInmetroRequest;
use App\Http\Requests\Inmetro\UpdateWebhookInmetroRequest;
use App\Models\InmetroOwner;
use App\Services\InmetroCompetitorTrackingService;
use App\Services\InmetroComplianceService;
use App\Services\InmetroOperationalBridgeService;
use App\Services\InmetroProspectionService;
use App\Services\InmetroReportingService;
use App\Services\InmetroTerritorialService;
use App\Services\InmetroWebhookService;
use App\Support\ApiResponse;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class InmetroAdvancedController extends Controller
{
    use ResolvesCurrentTenant;

    public function __construct(
        private InmetroProspectionService $prospection,
        private InmetroTerritorialService $territorial,
        private InmetroCompetitorTrackingService $competitorTracking,
        private InmetroOperationalBridgeService $operational,
        private InmetroReportingService $reporting,
        private InmetroComplianceService $compliance,
        private InmetroWebhookService $webhooks,
    ) {}

    // ════════════════════════════════════════════
    // PROSPECTION & LEADS
    // ════════════════════════════════════════════

    public function generateDailyQueue(Request $request): JsonResponse
    {
        try {
            $result = $this->prospection->generateDailyQueue(
                $this->tenantId(),
                $request->input('assigned_to'),
                $request->input('max_items', 20),
            );

            return ApiResponse::data($result);
        } catch (\Exception $e) {
            Log::error('Failed to generate queue: '.$e->getMessage(), ['exception' => $e]);

            return ApiResponse::message('Falha ao gerar fila', 500);
        }
    }

    public function getContactQueue(Request $request): JsonResponse
    {
        $result = $this->prospection->getContactQueue(
            $this->tenantId(),
            $request->input('date'),
        );

        return ApiResponse::data($result);
    }

    public function markQueueItem(MarkQueueItemRequest $request, int $queueId): JsonResponse
    {
        try {
            $item = $this->prospection->markQueueItem($queueId, $request->validated('status'), $this->tenantId());

            return ApiResponse::data($item, 200, ['message' => 'Queue item updated']);
        } catch (\Exception $e) {
            Log::error('Failed to update queue item: '.$e->getMessage(), ['exception' => $e]);

            return ApiResponse::message('Falha ao atualizar item da fila', 500);
        }
    }

    public function followUps(Request $request): JsonResponse
    {
        $result = $this->prospection->scheduleFollowUps($this->tenantId());

        return ApiResponse::data($result);
    }

    public function calculateLeadScore(Request $request, int $ownerId): JsonResponse
    {
        try {
            $owner = InmetroOwner::where('tenant_id', $this->tenantId())->findOrFail($ownerId);
            $score = $this->prospection->calculateLeadScore($owner, $this->tenantId());

            return ApiResponse::data($score, 200, ['message' => 'Score calculated']);
        } catch (\Exception $e) {
            Log::error('Failed to calculate score: '.$e->getMessage(), ['exception' => $e]);

            return ApiResponse::message('Falha ao calcular pontuação', 500);
        }
    }

    public function recalculateAllScores(Request $request): JsonResponse
    {
        try {
            $count = $this->prospection->recalculateAllScores($this->tenantId());

            return ApiResponse::message("Scores recalculated for {$count} owners");
        } catch (\Exception $e) {
            Log::error('Failed to recalculate scores: '.$e->getMessage(), ['exception' => $e]);

            return ApiResponse::message('Falha ao recalcular pontuações', 500);
        }
    }

    public function detectChurn(Request $request): JsonResponse
    {
        $result = $this->prospection->detectChurnedCustomers(
            $this->tenantId(),
            $request->input('inactive_months', 6),
        );

        return ApiResponse::data($result);
    }

    public function newRegistrations(Request $request): JsonResponse
    {
        $result = $this->prospection->detectNewRegistrations(
            $this->tenantId(),
            $request->input('since_days', 7),
        );

        return ApiResponse::data($result);
    }

    public function suggestNextCalibrations(Request $request): JsonResponse
    {
        $result = $this->prospection->suggestNextCalibrations(
            $this->tenantId(),
            $request->input('days', 90),
        );

        return ApiResponse::data($result);
    }

    public function classifySegments(Request $request): JsonResponse
    {
        try {
            $count = $this->prospection->classifySegments($this->tenantId());

            return ApiResponse::message("{$count} owners classified");
        } catch (\Exception $e) {
            Log::error('Failed to classify segments: '.$e->getMessage(), ['exception' => $e]);

            return ApiResponse::message('Falha ao classificar segmentos', 500);
        }
    }

    public function segmentDistribution(Request $request): JsonResponse
    {
        $result = $this->prospection->getSegmentDistribution($this->tenantId());

        return ApiResponse::data($result);
    }

    public function rejectAlerts(Request $request): JsonResponse
    {
        $result = $this->prospection->getRejectAlerts($this->tenantId());

        return ApiResponse::data($result);
    }

    public function conversionRanking(Request $request): JsonResponse
    {
        $result = $this->prospection->getConversionRanking(
            $this->tenantId(),
            $request->input('period'),
        );

        return ApiResponse::data($result);
    }

    public function logInteraction(LogInteractionInmetroRequest $request): JsonResponse
    {
        try {
            $interaction = $this->prospection->logInteraction(
                $request->validated(),
                $this->tenantId(),
                $request->user()->id,
            );

            return ApiResponse::data($interaction, 201, ['message' => 'Interaction logged']);
        } catch (\Exception $e) {
            Log::error('Failed to log interaction: '.$e->getMessage(), ['exception' => $e]);

            return ApiResponse::message('Falha ao registrar interação', 500);
        }
    }

    public function interactionHistory(Request $request, int $ownerId): JsonResponse
    {
        $result = $this->prospection->getInteractionHistory($ownerId, $this->tenantId());

        return ApiResponse::data($result);
    }

    // ════════════════════════════════════════════
    // TERRITORIAL INTELLIGENCE
    // ════════════════════════════════════════════

    public function layeredMapData(Request $request): JsonResponse
    {
        $layers = $request->input('layers', ['instruments', 'competitors', 'leads']);
        $result = $this->territorial->getLayeredMapData($this->tenantId(), $layers);

        return ApiResponse::data($result);
    }

    public function optimizeRoute(OptimizeRouteInmetroRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $result = $this->territorial->optimizeRoute(
            $this->tenantId(),
            (float) $validated['base_lat'],
            (float) $validated['base_lng'],
            $validated['owner_ids'],
        );

        return ApiResponse::data($result);
    }

    public function competitorZones(Request $request): JsonResponse
    {
        $result = $this->territorial->getCompetitorZones($this->tenantId());

        return ApiResponse::data($result);
    }

    public function coverageVsPotential(Request $request): JsonResponse
    {
        $result = $this->territorial->getCoverageVsPotential($this->tenantId());

        return ApiResponse::data($result);
    }

    public function densityViability(DensityViabilityInmetroRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $result = $this->territorial->getDensityViability(
            $this->tenantId(),
            (float) $validated['base_lat'],
            (float) $validated['base_lng'],
            (float) $request->input('cost_per_km', 1.30),
        );

        return ApiResponse::data($result);
    }

    public function nearbyLeads(NearbyLeadsInmetroRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $result = $this->territorial->getNearbyLeads(
            $this->tenantId(),
            (float) $validated['lat'],
            (float) $validated['lng'],
            (float) $request->input('radius_km', 50),
        );

        return ApiResponse::data($result);
    }

    // ════════════════════════════════════════════
    // COMPETITOR TRACKING
    // ════════════════════════════════════════════

    public function snapshotMarketShare(Request $request): JsonResponse
    {
        try {
            $snapshot = $this->competitorTracking->snapshotMarketShare($this->tenantId());

            return ApiResponse::data($snapshot, 200, ['message' => 'Market share snapshot created']);
        } catch (\Exception $e) {
            Log::error('Failed to create snapshot: '.$e->getMessage(), ['exception' => $e]);

            return ApiResponse::message('Falha ao criar snapshot', 500);
        }
    }

    public function marketShareTimeline(Request $request): JsonResponse
    {
        $result = $this->competitorTracking->getMarketShareTimeline(
            $this->tenantId(),
            $request->input('months', 12),
        );

        return ApiResponse::data($result);
    }

    public function competitorMovements(Request $request): JsonResponse
    {
        $result = $this->competitorTracking->detectCompetitorMovements($this->tenantId());

        return ApiResponse::data($result);
    }

    public function estimatePricing(Request $request): JsonResponse
    {
        $result = $this->competitorTracking->estimatePricing($this->tenantId());

        return ApiResponse::data($result);
    }

    public function competitorProfile(Request $request, int $competitorId): JsonResponse
    {
        try {
            $result = $this->competitorTracking->getCompetitorProfile($this->tenantId(), $competitorId);

            return ApiResponse::data($result);
        } catch (\Exception $e) {
            return ApiResponse::message('Competitor not found', 404);
        }
    }

    public function recordWinLoss(RecordWinLossInmetroRequest $request): JsonResponse
    {
        $data = $request->validated();
        if (! isset($data['outcome_date'])) {
            $data['outcome_date'] = now()->toDateString();
        }

        try {
            $record = $this->competitorTracking->recordWinLoss($data, $this->tenantId());

            return ApiResponse::data($record, 201, ['message' => 'Win/Loss recorded']);
        } catch (\Throwable $e) {
            Log::error('Failed to record win/loss: '.$e->getMessage(), ['exception' => $e]);

            return ApiResponse::message('Falha ao registrar ganho/perda', 500);
        }
    }

    public function winLossAnalysis(Request $request): JsonResponse
    {
        $result = $this->competitorTracking->getWinLossAnalysis(
            $this->tenantId(),
            $request->input('period'),
        );

        return ApiResponse::data($result);
    }

    // ════════════════════════════════════════════
    // OPERATIONAL BRIDGE (OS + CERTIFICATES)
    // ════════════════════════════════════════════

    public function suggestLinkedEquipments(Request $request, int $customerId): JsonResponse
    {
        $result = $this->operational->suggestLinkedEquipments($this->tenantId(), $customerId);

        return ApiResponse::data($result);
    }

    public function linkInstrument(LinkInstrumentInmetroRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $result = $this->operational->linkInstrumentToEquipment(
                $validated['instrument_id'],
                $validated['equipment_id'],
            );

            return ApiResponse::data($result, 200, ['message' => 'Instrument linked']);
        } catch (\Exception $e) {
            Log::error('Failed to link instrument: '.$e->getMessage(), ['exception' => $e]);

            return ApiResponse::message('Falha ao vincular instrumento', 500);
        }
    }

    public function prefillCertificate(Request $request, int $instrumentId): JsonResponse
    {
        try {
            $result = $this->operational->prefillCertificateData($instrumentId);

            return ApiResponse::data($result);
        } catch (\Exception $e) {
            return ApiResponse::message('Instrument not found', 404);
        }
    }

    public function instrumentTimeline(Request $request, int $instrumentId): JsonResponse
    {
        try {
            $result = $this->operational->getInstrumentTimeline($instrumentId, $this->tenantId());

            return ApiResponse::data($result);
        } catch (\Exception $e) {
            return ApiResponse::message('Instrument not found', 404);
        }
    }

    public function compareCalibrations(Request $request, int $instrumentId): JsonResponse
    {
        try {
            $result = $this->operational->compareCalibrationResults($instrumentId, $this->tenantId());

            return ApiResponse::data($result);
        } catch (\Exception $e) {
            return ApiResponse::message('Instrument not found', 404);
        }
    }

    // ════════════════════════════════════════════
    // REPORTING & DASHBOARDS
    // ════════════════════════════════════════════

    public function executiveDashboard(Request $request): JsonResponse
    {
        $result = $this->reporting->getExecutiveDashboard($this->tenantId());

        return ApiResponse::data($result);
    }

    public function revenueForecast(Request $request): JsonResponse
    {
        $result = $this->reporting->getRevenueForecast(
            $this->tenantId(),
            $request->input('months', 6),
        );

        return ApiResponse::data($result);
    }

    public function conversionFunnel(Request $request): JsonResponse
    {
        $result = $this->reporting->getConversionFunnel($this->tenantId());

        return ApiResponse::data($result);
    }

    public function exportData(Request $request): JsonResponse
    {
        $result = $this->reporting->getExportData($this->tenantId());

        return ApiResponse::data($result);
    }

    public function yearOverYear(Request $request): JsonResponse
    {
        $result = $this->reporting->getYearOverYear($this->tenantId());

        return ApiResponse::data($result);
    }

    // ════════════════════════════════════════════
    // COMPLIANCE & REGULATORY
    // ════════════════════════════════════════════

    public function complianceChecklists(Request $request): JsonResponse
    {
        $result = $this->compliance->getChecklists(
            $this->tenantId(),
            $request->input('instrument_type'),
        );

        return ApiResponse::data($result);
    }

    public function createChecklist(CreateChecklistInmetroRequest $request): JsonResponse
    {
        $data = $request->validated();

        try {
            $checklist = $this->compliance->createChecklist($data, $this->tenantId());

            return ApiResponse::data($checklist, 201, ['message' => 'Checklist created']);
        } catch (\Exception $e) {
            Log::error('Failed to create checklist: '.$e->getMessage(), ['exception' => $e]);

            return ApiResponse::message('Falha ao criar checklist', 500);
        }
    }

    public function updateChecklist(UpdateChecklistInmetroRequest $request, int $id): JsonResponse
    {
        $data = $request->validated();

        try {
            $checklist = $this->compliance->updateChecklist($id, $data, $this->tenantId());

            return ApiResponse::data($checklist, 200, ['message' => 'Checklist updated']);
        } catch (\Exception $e) {
            Log::error('Failed to update checklist: '.$e->getMessage(), ['exception' => $e]);

            return ApiResponse::message('Falha ao atualizar checklist', 500);
        }
    }

    public function regulatoryTraceability(Request $request, int $instrumentId): JsonResponse
    {
        try {
            $result = $this->compliance->getRegulatoryTraceability($instrumentId, $this->tenantId());

            return ApiResponse::data($result);
        } catch (\Exception $e) {
            return ApiResponse::message('Instrument not found', 404);
        }
    }

    public function simulateRegulatoryImpact(SimulateRegulatoryImpactInmetroRequest $request): JsonResponse
    {
        $data = $request->validated();
        $result = $this->compliance->simulateRegulatoryImpact($this->tenantId(), $data);

        return ApiResponse::data($result);
    }

    public function corporateGroups(Request $request): JsonResponse
    {
        $result = $this->compliance->detectCorporateGroups($this->tenantId());

        return ApiResponse::data($result);
    }

    public function instrumentTypes(Request $request): JsonResponse
    {
        $result = $this->compliance->getInstrumentTypes($this->tenantId());

        return ApiResponse::data($result);
    }

    public function detectAnomalies(Request $request): JsonResponse
    {
        $result = $this->compliance->detectAnomalies($this->tenantId());

        return ApiResponse::data($result);
    }

    public function renewalProbability(Request $request): JsonResponse
    {
        $result = $this->compliance->getRenewalProbability($this->tenantId());

        return ApiResponse::data($result);
    }

    // ════════════════════════════════════════════
    // WEBHOOKS & PUBLIC API
    // ════════════════════════════════════════════

    public function publicInstrumentData(Request $request): JsonResponse
    {
        $result = $this->webhooks->getPublicInstrumentData(
            $this->tenantId(),
            $request->input('city'),
        );

        return ApiResponse::data($result);
    }

    public function listWebhooks(Request $request): JsonResponse
    {
        $result = $this->webhooks->listWebhooks($this->tenantId());

        return ApiResponse::data($result);
    }

    public function createWebhook(CreateWebhookInmetroRequest $request): JsonResponse
    {
        $data = $request->validated();

        try {
            $webhook = $this->webhooks->createWebhook($data, $this->tenantId());

            return ApiResponse::data($webhook, 201, ['message' => 'Webhook created']);
        } catch (\Exception $e) {
            Log::error('Failed to create webhook: '.$e->getMessage(), ['exception' => $e]);

            return ApiResponse::message('Falha ao criar webhook', 500);
        }
    }

    public function updateWebhook(UpdateWebhookInmetroRequest $request, int $id): JsonResponse
    {
        $data = $request->validated();

        try {
            $webhook = $this->webhooks->updateWebhook($id, $data, $this->tenantId());

            return ApiResponse::data($webhook, 200, ['message' => 'Webhook updated']);
        } catch (\Exception $e) {
            Log::error('Failed to update webhook: '.$e->getMessage(), ['exception' => $e]);

            return ApiResponse::message('Falha ao atualizar webhook', 500);
        }
    }

    public function deleteWebhook(Request $request, int $id): JsonResponse
    {
        try {
            $this->webhooks->deleteWebhook($id, $this->tenantId());

            return ApiResponse::message('Webhook deleted');
        } catch (\Exception $e) {
            Log::error('Failed to delete webhook: '.$e->getMessage(), ['exception' => $e]);

            return ApiResponse::message('Falha ao excluir webhook', 500);
        }
    }

    public function availableWebhookEvents(): JsonResponse
    {
        return ApiResponse::data($this->webhooks->getAvailableEvents());
    }
}
