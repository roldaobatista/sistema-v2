<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\AnswerVisitSurveyRequest;
use App\Http\Requests\Crm\CheckinVisitRequest;
use App\Http\Requests\Crm\CheckoutVisitRequest;
use App\Http\Requests\Crm\SendVisitSurveyRequest;
use App\Http\Requests\Crm\StoreAccountPlanRequest;
use App\Http\Requests\Crm\StoreCommitmentRequest;
use App\Http\Requests\Crm\StoreContactPolicyRequest;
use App\Http\Requests\Crm\StoreImportantDateRequest;
use App\Http\Requests\Crm\StoreQuickNoteRequest;
use App\Http\Requests\Crm\StoreVisitReportRequest;
use App\Http\Requests\Crm\StoreVisitRouteRequest;
use App\Http\Requests\Crm\UpdateAccountPlanActionRequest;
use App\Http\Requests\Crm\UpdateAccountPlanRequest;
use App\Http\Requests\Crm\UpdateCommitmentRequest;
use App\Http\Requests\Crm\UpdateContactPolicyRequest;
use App\Http\Requests\Crm\UpdateImportantDateRequest;
use App\Http\Requests\Crm\UpdateQuickNoteRequest;
use App\Http\Requests\Crm\UpdateVisitRouteRequest;
use App\Models\AccountPlan;
use App\Models\AccountPlanAction;
use App\Models\Commitment;
use App\Models\ContactPolicy;
use App\Models\Customer;
use App\Models\CustomerRfmScore;
use App\Models\GamificationBadge;
use App\Models\ImportantDate;
use App\Models\QuickNote;
use App\Models\VisitCheckin;
use App\Models\VisitReport;
use App\Models\VisitRoute;
use App\Models\VisitSurvey;
use App\Services\CrmFieldManagementService;
use App\Support\ApiResponse;
use App\Traits\ScopesByRole;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CrmFieldManagementController extends Controller
{
    public function __construct(private CrmFieldManagementService $service) {}

    use ScopesByRole;

    private function tenantId(Request $request): int
    {
        $user = $request->user();

        return (int) ($user->current_tenant_id ?? $user->tenant_id);
    }

    // ═══════════════════════════════════════════════════════
    // 1. VISIT CHECKINS
    // ═══════════════════════════════════════════════════════

    public function checkinsIndex(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('crm.deal.view'), 403);
        $result = $this->service->checkinsIndex(method_exists($request, 'validated') ? $request->validated() : $request->all(), $request->user(), $this->tenantId($request));

        return ApiResponse::paginated($result);
    }

    public function checkin(CheckinVisitRequest $request): JsonResponse
    {
        $result = $this->service->checkin(method_exists($request, 'validated') ? $request->validated() : $request->all(), $request->user(), $this->tenantId($request));

        return ApiResponse::data($result, 201);
    }

    public function checkout(CheckoutVisitRequest $request, VisitCheckin $checkin): JsonResponse
    {
        $result = $this->service->checkout(method_exists($request, 'validated') ? $request->validated() : $request->all(), $checkin, $request->user(), $this->tenantId($request));

        return ApiResponse::data($result, 200);
    }

    // ═══════════════════════════════════════════════════════
    // 2. VISIT ROUTES
    // ═══════════════════════════════════════════════════════

    public function routesIndex(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('crm.deal.view'), 403);
        $result = $this->service->routesIndex(method_exists($request, 'validated') ? $request->validated() : $request->all(), $request->user(), $this->tenantId($request));

        return ApiResponse::paginated($result);
    }

    public function routesStore(StoreVisitRouteRequest $request): JsonResponse
    {
        $result = $this->service->routesStore(method_exists($request, 'validated') ? $request->validated() : $request->all(), $request->user(), $this->tenantId($request));

        return ApiResponse::data($result, 201);
    }

    public function routesUpdate(UpdateVisitRouteRequest $request, VisitRoute $route): JsonResponse
    {
        $result = $this->service->routesUpdate(method_exists($request, 'validated') ? $request->validated() : $request->all(), $route, $request->user(), $this->tenantId($request));

        return ApiResponse::data($result, 200);
    }

    // ═══════════════════════════════════════════════════════
    // 3. VISIT REPORTS
    // ═══════════════════════════════════════════════════════

    public function reportsIndex(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('crm.deal.view'), 403);
        $result = $this->service->reportsIndex(method_exists($request, 'validated') ? $request->validated() : $request->all(), $request->user(), $this->tenantId($request));

        return ApiResponse::paginated($result);
    }

    public function reportsStore(StoreVisitReportRequest $request): JsonResponse
    {
        $result = $this->service->reportsStore(method_exists($request, 'validated') ? $request->validated() : $request->all(), $request->user(), $this->tenantId($request));

        return ApiResponse::data($result, 201);
    }

    // ═══════════════════════════════════════════════════════
    // 4. PORTFOLIO MAP
    // ═══════════════════════════════════════════════════════

    public function portfolioMap(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('crm.deal.view'), 403);
        $result = $this->service->portfolioMap(method_exists($request, 'validated') ? $request->validated() : $request->all(), $request->user(), $this->tenantId($request));

        return ApiResponse::paginated($result);
    }

    // ═══════════════════════════════════════════════════════
    // 5. FORGOTTEN CLIENTS DASHBOARD
    // ═══════════════════════════════════════════════════════

    public function forgottenClients(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('crm.deal.view'), 403);
        $result = $this->service->forgottenClients(method_exists($request, 'validated') ? $request->validated() : $request->all(), $request->user(), $this->tenantId($request));

        return ApiResponse::paginated($result['paginated'], extra: $result['extra']);
    }

    // ═══════════════════════════════════════════════════════
    // 6. CONTACT POLICIES
    // ═══════════════════════════════════════════════════════

    public function policiesIndex(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('crm.deal.view'), 403);
        $result = $this->service->policiesIndex(method_exists($request, 'validated') ? $request->validated() : $request->all(), $request->user(), $this->tenantId($request));

        return ApiResponse::paginated($result);
    }

    public function policiesStore(StoreContactPolicyRequest $request): JsonResponse
    {
        $result = $this->service->policiesStore(method_exists($request, 'validated') ? $request->validated() : $request->all(), $request->user(), $this->tenantId($request));

        return ApiResponse::data($result, 201);
    }

    public function policiesUpdate(UpdateContactPolicyRequest $request, ContactPolicy $policy): JsonResponse
    {
        $result = $this->service->policiesUpdate(method_exists($request, 'validated') ? $request->validated() : $request->all(), $policy, $request->user(), $this->tenantId($request));

        return ApiResponse::data($result, 200);
    }

    public function policiesDestroy(Request $request, ContactPolicy $policy): JsonResponse
    {
        abort_unless($request->user()->can('crm.deal.delete'), 403);
        $result = $this->service->policiesDestroy(method_exists($request, 'validated') ? $request->validated() : $request->all(), $policy, $request->user(), $this->tenantId($request));

        return response()->json(null, 204);
    }

    // ═══════════════════════════════════════════════════════
    // 7. SMART AGENDA (Suggestions)
    // ═══════════════════════════════════════════════════════

    public function smartAgenda(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('crm.deal.view'), 403);
        $result = $this->service->smartAgenda(method_exists($request, 'validated') ? $request->validated() : $request->all(), $request->user(), $this->tenantId($request));

        return ApiResponse::data($result, 200);
    }

    // ═══════════════════════════════════════════════════════
    // 8. QUICK NOTES
    // ═══════════════════════════════════════════════════════

    public function quickNotesIndex(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('crm.deal.view'), 403);
        $result = $this->service->quickNotesIndex(method_exists($request, 'validated') ? $request->validated() : $request->all(), $request->user(), $this->tenantId($request));

        return ApiResponse::paginated($result);
    }

    public function quickNotesStore(StoreQuickNoteRequest $request): JsonResponse
    {
        $result = $this->service->quickNotesStore(method_exists($request, 'validated') ? $request->validated() : $request->all(), $request->user(), $this->tenantId($request));

        return ApiResponse::data($result, 201);
    }

    public function quickNotesUpdate(UpdateQuickNoteRequest $request, QuickNote $note): JsonResponse
    {
        $result = $this->service->quickNotesUpdate(method_exists($request, 'validated') ? $request->validated() : $request->all(), $note, $request->user(), $this->tenantId($request));

        return ApiResponse::data($result, 200);
    }

    public function quickNotesDestroy(Request $request, QuickNote $note): JsonResponse
    {
        abort_unless($request->user()->can('crm.deal.delete'), 403);
        $result = $this->service->quickNotesDestroy(method_exists($request, 'validated') ? $request->validated() : $request->all(), $note, $request->user(), $this->tenantId($request));

        return response()->json(null, 204);
    }

    // ═══════════════════════════════════════════════════════
    // 9. COMMITMENTS
    // ═══════════════════════════════════════════════════════

    public function commitmentsIndex(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('crm.deal.view'), 403);
        $result = $this->service->commitmentsIndex(method_exists($request, 'validated') ? $request->validated() : $request->all(), $request->user(), $this->tenantId($request));

        return ApiResponse::paginated($result);
    }

    public function commitmentsStore(StoreCommitmentRequest $request): JsonResponse
    {
        $result = $this->service->commitmentsStore(method_exists($request, 'validated') ? $request->validated() : $request->all(), $request->user(), $this->tenantId($request));

        return ApiResponse::data($result, 201);
    }

    public function commitmentsUpdate(UpdateCommitmentRequest $request, Commitment $commitment): JsonResponse
    {
        $result = $this->service->commitmentsUpdate(method_exists($request, 'validated') ? $request->validated() : $request->all(), $commitment, $request->user(), $this->tenantId($request));

        return ApiResponse::data($result, 200);
    }

    // ═══════════════════════════════════════════════════════
    // 10. NEGOTIATION HISTORY
    // ═══════════════════════════════════════════════════════

    public function negotiationHistory(Request $request, Customer $customer): JsonResponse
    {
        abort_unless($request->user()->can('crm.deal.view'), 403);
        $result = $this->service->negotiationHistory(method_exists($request, 'validated') ? $request->validated() : $request->all(), $customer, $request->user(), $this->tenantId($request));

        return ApiResponse::data($result, 200);
    }

    // ═══════════════════════════════════════════════════════
    // 11. CLIENT SUMMARY PDF DATA
    // ═══════════════════════════════════════════════════════

    public function clientSummary(Request $request, Customer $customer): JsonResponse
    {
        abort_unless($request->user()->can('crm.deal.view'), 403);
        $result = $this->service->clientSummary(method_exists($request, 'validated') ? $request->validated() : $request->all(), $customer, $request->user(), $this->tenantId($request));

        return ApiResponse::data($result, 200);
    }

    // ═══════════════════════════════════════════════════════
    // 12. RFM CLASSIFICATION
    // ═══════════════════════════════════════════════════════

    public function rfmIndex(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('crm.deal.view'), 403);
        $result = $this->service->rfmIndex(method_exists($request, 'validated') ? $request->validated() : $request->all(), $request->user(), $this->tenantId($request));

        return ApiResponse::data($result, 200);
    }

    public function rfmRecalculate(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('crm.deal.update'), 403);
        $result = $this->service->rfmRecalculate(method_exists($request, 'validated') ? $request->validated() : $request->all(), $request->user(), $this->tenantId($request));

        return ApiResponse::data($result, 200);
    }

    // ═══════════════════════════════════════════════════════
    // 13. PORTFOLIO COVERAGE
    // ═══════════════════════════════════════════════════════

    public function portfolioCoverage(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('crm.deal.view'), 403);
        $result = $this->service->portfolioCoverage(method_exists($request, 'validated') ? $request->validated() : $request->all(), $request->user(), $this->tenantId($request));

        return ApiResponse::data($result, 200);
    }

    // ═══════════════════════════════════════════════════════
    // 14. COMMERCIAL PRODUCTIVITY
    // ═══════════════════════════════════════════════════════

    public function commercialProductivity(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('crm.deal.view'), 403);
        $result = $this->service->commercialProductivity(method_exists($request, 'validated') ? $request->validated() : $request->all(), $request->user(), $this->tenantId($request));

        return ApiResponse::data($result, 200);
    }

    // ═══════════════════════════════════════════════════════
    // 15. LATENT OPPORTUNITIES
    // ═══════════════════════════════════════════════════════

    public function latentOpportunities(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('crm.deal.view'), 403);
        $result = $this->service->latentOpportunities(method_exists($request, 'validated') ? $request->validated() : $request->all(), $request->user(), $this->tenantId($request));

        return ApiResponse::data($result, 200);
    }

    // ═══════════════════════════════════════════════════════
    // 16. IMPORTANT DATES
    // ═══════════════════════════════════════════════════════

    public function importantDatesIndex(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('crm.deal.view'), 403);
        $result = $this->service->importantDatesIndex(method_exists($request, 'validated') ? $request->validated() : $request->all(), $request->user(), $this->tenantId($request));

        return ApiResponse::paginated($result);
    }

    public function importantDatesStore(StoreImportantDateRequest $request): JsonResponse
    {
        $result = $this->service->importantDatesStore(method_exists($request, 'validated') ? $request->validated() : $request->all(), $request->user(), $this->tenantId($request));

        return ApiResponse::data($result, 201);
    }

    public function importantDatesUpdate(UpdateImportantDateRequest $request, ImportantDate $date): JsonResponse
    {
        $result = $this->service->importantDatesUpdate(method_exists($request, 'validated') ? $request->validated() : $request->all(), $date, $request->user(), $this->tenantId($request));

        return ApiResponse::data($result, 200);
    }

    public function importantDatesDestroy(Request $request, ImportantDate $date): JsonResponse
    {
        abort_unless($request->user()->can('crm.deal.delete'), 403);
        $result = $this->service->importantDatesDestroy(method_exists($request, 'validated') ? $request->validated() : $request->all(), $date, $request->user(), $this->tenantId($request));

        return response()->json(null, 204);
    }

    // ═══════════════════════════════════════════════════════
    // 17. VISIT SURVEYS
    // ═══════════════════════════════════════════════════════

    public function surveysIndex(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('crm.deal.view'), 403);
        $result = $this->service->surveysIndex(method_exists($request, 'validated') ? $request->validated() : $request->all(), $request->user(), $this->tenantId($request));

        return ApiResponse::paginated($result);
    }

    public function surveysSend(SendVisitSurveyRequest $request): JsonResponse
    {
        $result = $this->service->surveysSend(method_exists($request, 'validated') ? $request->validated() : $request->all(), $request->user(), $this->tenantId($request));

        return ApiResponse::data($result, 201);
    }

    public function surveysAnswer(AnswerVisitSurveyRequest $request, string $token): JsonResponse
    {
        $result = $this->service->surveysAnswer(method_exists($request, 'validated') ? $request->validated() : $request->all(), $token, $request->user(), $this->tenantId($request));

        return ApiResponse::data($result, 200);
    }

    // ═══════════════════════════════════════════════════════
    // 18. ACCOUNT PLANS
    // ═══════════════════════════════════════════════════════

    public function accountPlansIndex(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('crm.deal.view'), 403);
        $result = $this->service->accountPlansIndex(method_exists($request, 'validated') ? $request->validated() : $request->all(), $request->user(), $this->tenantId($request));

        return ApiResponse::paginated($result);
    }

    public function accountPlansStore(StoreAccountPlanRequest $request): JsonResponse
    {
        $result = $this->service->accountPlansStore(method_exists($request, 'validated') ? $request->validated() : $request->all(), $request->user(), $this->tenantId($request));

        return ApiResponse::data($result, 201);
    }

    public function accountPlansUpdate(UpdateAccountPlanRequest $request, AccountPlan $plan): JsonResponse
    {
        $result = $this->service->accountPlansUpdate(method_exists($request, 'validated') ? $request->validated() : $request->all(), $plan, $request->user(), $this->tenantId($request));

        return ApiResponse::data($result, 200);
    }

    public function accountPlanActionsUpdate(UpdateAccountPlanActionRequest $request, AccountPlanAction $action): JsonResponse
    {
        $result = $this->service->accountPlanActionsUpdate(method_exists($request, 'validated') ? $request->validated() : $request->all(), $action, $request->user(), $this->tenantId($request));

        return ApiResponse::data($result, 200);
    }

    // ═══════════════════════════════════════════════════════
    // 19. GAMIFICATION
    // ═══════════════════════════════════════════════════════

    public function gamificationDashboard(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('crm.deal.view'), 403);
        $result = $this->service->gamificationDashboard(method_exists($request, 'validated') ? $request->validated() : $request->all(), $request->user(), $this->tenantId($request));

        return ApiResponse::data($result, 200);
    }

    public function gamificationRecalculate(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('crm.deal.update'), 403);
        $result = $this->service->gamificationRecalculate(method_exists($request, 'validated') ? $request->validated() : $request->all(), $request->user(), $this->tenantId($request));

        return ApiResponse::data($result, 200);
    }

    // ═══════════════════════════════════════════════════════
    // 20. CONSTANTS
    // ═══════════════════════════════════════════════════════

    public function constants(): JsonResponse
    {
        return ApiResponse::data([
            'visit_statuses' => VisitCheckin::STATUSES,
            'route_statuses' => VisitRoute::STATUSES,
            'report_sentiments' => VisitReport::SENTIMENTS,
            'report_visit_types' => VisitReport::VISIT_TYPES,
            'contact_policy_target_types' => ContactPolicy::TARGET_TYPES,
            'quick_note_channels' => QuickNote::CHANNELS,
            'quick_note_sentiments' => QuickNote::SENTIMENTS,
            'commitment_statuses' => Commitment::STATUSES,
            'commitment_responsible_types' => Commitment::RESPONSIBLE_TYPES,
            'commitment_priorities' => Commitment::PRIORITIES,
            'important_date_types' => ImportantDate::TYPES,
            'survey_statuses' => VisitSurvey::STATUSES,
            'account_plan_statuses' => AccountPlan::STATUSES,
            'account_plan_action_statuses' => AccountPlanAction::STATUSES,
            'rfm_segments' => CustomerRfmScore::SEGMENTS,
            'gamification_categories' => GamificationBadge::CATEGORIES,
        ]);
    }

    // ═══════════════════════════════════════════════════════
    // HELPERS
    // ═══════════════════════════════════════════════════════

    private function quintiles(array $values, bool $inverse = false): array
    {
        if (empty($values)) {
            return [0, 0, 0, 0, 0];
        }
        sort($values);
        $n = count($values);

        return [
            $values[(int) ($n * 0.2)] ?? 0,
            $values[(int) ($n * 0.4)] ?? 0,
            $values[(int) ($n * 0.6)] ?? 0,
            $values[(int) ($n * 0.8)] ?? 0,
            $values[$n - 1] ?? 0,
        ];
    }

    private function scoreInQuintile(float $value, array $quintiles, bool $inverse = false): int
    {
        if ($inverse) {
            if ($value <= $quintiles[0]) {
                return 5;
            }
            if ($value <= $quintiles[1]) {
                return 4;
            }
            if ($value <= $quintiles[2]) {
                return 3;
            }
            if ($value <= $quintiles[3]) {
                return 2;
            }

            return 1;
        }
        if ($value >= $quintiles[3]) {
            return 5;
        }
        if ($value >= $quintiles[2]) {
            return 4;
        }
        if ($value >= $quintiles[1]) {
            return 3;
        }
        if ($value >= $quintiles[0]) {
            return 2;
        }

        return 1;
    }
}
