<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\BulkUpdateCrmDealsRequest;
use App\Http\Requests\Crm\CrmDashboardRequest;
use App\Http\Requests\Crm\Customer360Request;
use App\Http\Requests\Crm\DealsConvertToQuoteRequest;
use App\Http\Requests\Crm\DealsConvertToWorkOrderRequest;
use App\Http\Requests\Crm\DealsMarkLostRequest;
use App\Http\Requests\Crm\DealsMarkWonRequest;
use App\Http\Requests\Crm\DealsUpdateStageRequest;
use App\Http\Requests\Crm\IndexCrmActivityRequest;
use App\Http\Requests\Crm\IndexCrmDealRequest;
use App\Http\Requests\Crm\StagesReorderRequest;
use App\Http\Requests\Crm\StoreCrmActivityRequest;
use App\Http\Requests\Crm\StoreCrmDealRequest;
use App\Http\Requests\Crm\StoreCrmPipelineRequest;
use App\Http\Requests\Crm\StoreCrmStageRequest;
use App\Http\Requests\Crm\UpdateCrmActivityRequest;
use App\Http\Requests\Crm\UpdateCrmDealRequest;
use App\Http\Requests\Crm\UpdateCrmPipelineRequest;
use App\Http\Requests\Crm\UpdateCrmStageRequest;
use App\Models\CrmActivity;
use App\Models\CrmDeal;
use App\Models\CrmPipeline;
use App\Models\CrmPipelineStage;
use App\Models\Customer;
use App\Services\CrmService;
use App\Traits\ResolvesCurrentTenant;
use App\Traits\ScopesByRole;
use Illuminate\Http\JsonResponse;

class CrmController extends Controller
{
    use ResolvesCurrentTenant, ScopesByRole;

    public function __construct(private CrmService $service) {}

    // ─── Dashboard ──────────────────────────────────────

    public function dashboard(CrmDashboardRequest $request): JsonResponse
    {
        return $this->service->dashboard($request->validated(), $request->user(), $this->tenantId());
    }

    // ─── Pipelines ──────────────────────────────────────

    public function pipelinesIndex(): JsonResponse
    {
        $this->authorize('viewAny', CrmPipeline::class);

        return $this->service->pipelinesIndex(auth()->user(), $this->tenantId());
    }

    public function pipelinesStore(StoreCrmPipelineRequest $request): JsonResponse
    {
        $this->authorize('create', CrmPipeline::class);

        return $this->service->pipelinesStore($request->validated(), $request->user(), $this->tenantId());
    }

    public function pipelinesUpdate(UpdateCrmPipelineRequest $request, CrmPipeline $pipeline): JsonResponse
    {
        $this->authorize('update', $pipeline);

        return $this->service->pipelinesUpdate($request->validated(), $pipeline, $request->user(), $this->tenantId());
    }

    public function pipelinesDestroy(CrmPipeline $pipeline): JsonResponse
    {
        $this->authorize('delete', $pipeline);

        return $this->service->pipelinesDestroy($pipeline, auth()->user(), $this->tenantId());
    }

    // ─── Pipeline Stages ──────────────────────────────────

    public function stagesStore(StoreCrmStageRequest $request, CrmPipeline $pipeline): JsonResponse
    {
        return $this->service->stagesStore($request->validated(), $pipeline, $request->user(), $this->tenantId());
    }

    public function stagesUpdate(UpdateCrmStageRequest $request, CrmPipelineStage $stage): JsonResponse
    {
        return $this->service->stagesUpdate($request->validated(), $stage, $request->user(), $this->tenantId());
    }

    public function stagesDestroy(CrmPipelineStage $stage): JsonResponse
    {
        return $this->service->stagesDestroy($stage, auth()->user(), $this->tenantId());
    }

    public function stagesReorder(StagesReorderRequest $request, CrmPipeline $pipeline): JsonResponse
    {
        return $this->service->stagesReorder($request->validated(), $pipeline, $request->user(), $this->tenantId());
    }

    // ─── Deals ──────────────────────────────────────────

    public function dealsIndex(IndexCrmDealRequest $request): JsonResponse
    {
        $this->authorize('viewAny', CrmDeal::class);

        return $this->service->dealsIndex($request->validated(), $request->user(), $this->tenantId(), $this->shouldScopeByUser());
    }

    public function dealsStore(StoreCrmDealRequest $request): JsonResponse
    {
        $this->authorize('create', CrmDeal::class);

        return $this->service->dealsStore($request->validated(), $request->user(), $this->tenantId());
    }

    public function dealsShow(CrmDeal $deal): JsonResponse
    {
        $this->authorize('view', $deal);
        if ($deny = $this->ensureTenantOwnership($deal, 'Deal')) {
            return $deny;
        }

        return $this->service->dealsShow($deal, auth()->user(), $this->tenantId());
    }

    public function dealsUpdate(UpdateCrmDealRequest $request, CrmDeal $deal): JsonResponse
    {
        $this->authorize('update', $deal);
        if ($deny = $this->ensureTenantOwnership($deal, 'Deal')) {
            return $deny;
        }

        return $this->service->dealsUpdate($request->validated(), $deal, $request->user(), $this->tenantId());
    }

    public function dealsUpdateStage(DealsUpdateStageRequest $request, CrmDeal $deal): JsonResponse
    {
        $this->authorize('update', $deal);
        if ($deny = $this->ensureTenantOwnership($deal, 'Deal')) {
            return $deny;
        }

        return $this->service->dealsUpdateStage($request->validated(), $deal, $request->user(), $this->tenantId());
    }

    public function dealsMarkWon(DealsMarkWonRequest $request, CrmDeal $deal): JsonResponse
    {
        $this->authorize('update', $deal);
        if ($deny = $this->ensureTenantOwnership($deal, 'Deal')) {
            return $deny;
        }

        return $this->service->dealsMarkWon($request->validated(), $deal, $request->user(), $this->tenantId());
    }

    public function dealsMarkLost(DealsMarkLostRequest $request, CrmDeal $deal): JsonResponse
    {
        $this->authorize('update', $deal);
        if ($deny = $this->ensureTenantOwnership($deal, 'Deal')) {
            return $deny;
        }

        return $this->service->dealsMarkLost($request->validated(), $deal, $request->user(), $this->tenantId());
    }

    /**
     * Cria uma OS a partir do negócio (cliente, valor e título do deal) e vincula ao deal.
     */
    public function dealsConvertToWorkOrder(DealsConvertToWorkOrderRequest $request, CrmDeal $deal): JsonResponse
    {
        $this->authorize('update', $deal);

        return $this->service->dealsConvertToWorkOrder($request->validated(), $deal, $request->user(), $this->tenantId());
    }

    public function dealsConvertToQuote(DealsConvertToQuoteRequest $request, CrmDeal $deal): JsonResponse
    {
        $this->authorize('update', $deal);

        return $this->service->dealsConvertToQuote($request->validated(), $deal, $request->user(), $this->tenantId());
    }

    public function dealsDestroy(CrmDeal $deal): JsonResponse
    {
        $this->authorize('delete', $deal);
        if ($deny = $this->ensureTenantOwnership($deal, 'Deal')) {
            return $deny;
        }

        return $this->service->dealsDestroy($deal, auth()->user(), $this->tenantId());
    }

    /**
     * Bulk update multiple deals at once.
     * Supports: move_stage, mark_won, mark_lost, delete.
     */
    public function dealsBulkUpdate(BulkUpdateCrmDealsRequest $request): JsonResponse
    {
        abort_unless($request->user()->can('crm.deal.update'), 403);

        return $this->service->dealsBulkUpdate($request->validated(), $request->user(), $this->tenantId());
    }

    // ─── Activities ─────────────────────────────────────

    public function activitiesIndex(IndexCrmActivityRequest $request): JsonResponse
    {
        $this->authorize('viewAny', CrmActivity::class);

        return $this->service->activitiesIndex($request->validated(), $request->user(), $this->tenantId());
    }

    public function activitiesStore(StoreCrmActivityRequest $request): JsonResponse
    {
        $this->authorize('create', CrmActivity::class);

        return $this->service->activitiesStore($request->validated(), $request->user(), $this->tenantId());
    }

    public function activitiesUpdate(UpdateCrmActivityRequest $request, CrmActivity $activity): JsonResponse
    {
        $this->authorize('update', $activity);

        return $this->service->activitiesUpdate($request->validated(), $activity, $request->user(), $this->tenantId());
    }

    public function activitiesDestroy(CrmActivity $activity): JsonResponse
    {
        $this->authorize('delete', $activity);

        return $this->service->activitiesDestroy($activity, auth()->user(), $this->tenantId());
    }

    // ─── Customer 360 ───────────────────────────────────

    public function customer360(Customer360Request $request, Customer $customer): JsonResponse
    {
        $this->authorize('view', $customer);

        return $this->service->customer360($request->validated(), $customer, $request->user(), $this->tenantId());
    }

    public function export360(Customer360Request $request, $id)
    {
        abort_unless($request->user()->can('crm.deal.view'), 403);

        return $this->service->export360($request->validated(), $id, $request->user(), $this->tenantId());
    }

    // ─── Constants ──────────────────────────────────────

    public function constants(): JsonResponse
    {
        return $this->service->constants(auth()->user(), $this->tenantId());
    }
}
