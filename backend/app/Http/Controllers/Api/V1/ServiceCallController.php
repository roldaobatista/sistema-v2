<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ServiceCall\AssignServiceCallTechnicianRequest;
use App\Http\Requests\ServiceCall\BulkServiceCallActionRequest;
use App\Http\Requests\ServiceCall\CheckDuplicateServiceCallsRequest;
use App\Http\Requests\ServiceCall\IndexServiceCallsRequest;
use App\Http\Requests\ServiceCall\RescheduleServiceCallRequest;
use App\Http\Requests\ServiceCall\StoreServiceCallCommentRequest;
use App\Http\Requests\ServiceCall\StoreServiceCallRequest;
use App\Http\Requests\ServiceCall\UpdateServiceCallRequest;
use App\Http\Requests\ServiceCall\UpdateServiceCallStatusRequest;
use App\Http\Requests\ServiceCall\WebhookCreateServiceCallRequest;
use App\Models\ServiceCall;
use App\Services\ServiceCallService;
use App\Traits\ResolvesCurrentTenant;
use App\Traits\ScopesByRole;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceCallController extends Controller
{
    use ResolvesCurrentTenant, ScopesByRole;

    public function __construct(private ServiceCallService $service) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ServiceCall::class);

        return $this->service->index(method_exists($request, 'validated') ? $request->validated() : $request->all(), $request->user(), $this->tenantId(), $this->shouldScopeByUser());
    }

    public function store(StoreServiceCallRequest $request): JsonResponse
    {
        $this->authorize('create', ServiceCall::class);

        return $this->service->store(method_exists($request, 'validated') ? $request->validated() : $request->all(), $request->user(), $this->tenantId());
    }

    public function show(ServiceCall $serviceCall): JsonResponse
    {
        $this->authorize('view', $serviceCall);

        return $this->service->show($serviceCall, auth()->user(), $this->tenantId());
    }

    public function update(UpdateServiceCallRequest $request, ServiceCall $serviceCall): JsonResponse
    {
        $this->authorize('update', $serviceCall);

        return $this->service->update(method_exists($request, 'validated') ? $request->validated() : $request->all(), $serviceCall, $request->user(), $this->tenantId());
    }

    public function destroy(ServiceCall $serviceCall): JsonResponse
    {
        $this->authorize('delete', $serviceCall);

        return $this->service->destroy($serviceCall, auth()->user(), $this->tenantId());
    }

    // ── Ações de Negócio ──

    public function updateStatus(UpdateServiceCallStatusRequest $request, ServiceCall $serviceCall): JsonResponse
    {
        $this->authorize('update', $serviceCall);

        return $this->service->updateStatus(method_exists($request, 'validated') ? $request->validated() : $request->all(), $serviceCall, $request->user(), $this->tenantId());
    }

    public function assignTechnician(AssignServiceCallTechnicianRequest $request, ServiceCall $serviceCall): JsonResponse
    {
        $this->authorize('update', $serviceCall);

        return $this->service->assignTechnician(method_exists($request, 'validated') ? $request->validated() : $request->all(), $serviceCall, $request->user(), $this->tenantId());
    }

    public function convertToWorkOrder(ServiceCall $serviceCall): JsonResponse
    {
        $this->authorize('update', $serviceCall);

        return $this->service->convertToWorkOrder($serviceCall, auth()->user(), $this->tenantId());
    }

    // ── Comentários Internos ──

    public function comments(ServiceCall $serviceCall): JsonResponse
    {
        $this->authorize('view', $serviceCall);

        return $this->service->comments($serviceCall, auth()->user(), $this->tenantId());
    }

    public function addComment(StoreServiceCallCommentRequest $request, ServiceCall $serviceCall): JsonResponse
    {
        $this->authorize('view', $serviceCall);

        return $this->service->addComment(method_exists($request, 'validated') ? $request->validated() : $request->all(), $serviceCall, $request->user(), $this->tenantId());
    }

    // ── Exportação CSV ──

    public function exportCsv(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ServiceCall::class);

        return $this->service->exportCsv(method_exists($request, 'validated') ? $request->validated() : $request->all(), $request->user(), $this->tenantId());
    }

    // ── Mapa ──

    public function mapData(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ServiceCall::class);

        return $this->service->mapData(method_exists($request, 'validated') ? $request->validated() : $request->all(), $request->user(), $this->tenantId());
    }

    // ── Agenda por técnico ──

    public function agenda(IndexServiceCallsRequest $request): JsonResponse
    {
        $this->authorize('viewAny', ServiceCall::class);

        return $this->service->agenda(method_exists($request, 'validated') ? $request->validated() : $request->all(), $request->user(), $this->tenantId());
    }

    public function auditTrail(ServiceCall $serviceCall): JsonResponse
    {
        $this->authorize('view', $serviceCall);

        return $this->service->auditTrail($serviceCall, auth()->user(), $this->tenantId());
    }

    public function summary(): JsonResponse
    {
        $this->authorize('viewAny', ServiceCall::class);

        return $this->service->summary(auth()->user(), $this->tenantId());
    }

    // ── Dashboard KPI ──

    public function dashboardKpi(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ServiceCall::class);

        return $this->service->dashboardKpi(method_exists($request, 'validated') ? $request->validated() : $request->all(), $request->user(), $this->tenantId());
    }

    // ── Bulk Action ──

    public function bulkAction(BulkServiceCallActionRequest $request): JsonResponse
    {
        $this->authorize('viewAny', ServiceCall::class);

        return $this->service->bulkAction(method_exists($request, 'validated') ? $request->validated() : $request->all(), $request->user(), $this->tenantId());
    }

    // ── Reschedule ──

    public function reschedule(RescheduleServiceCallRequest $request, ServiceCall $serviceCall): JsonResponse
    {
        $this->authorize('update', $serviceCall);

        return $this->service->reschedule(method_exists($request, 'validated') ? $request->validated() : $request->all(), $serviceCall, $request->user(), $this->tenantId());
    }

    // ── Check Duplicate ──

    public function checkDuplicate(CheckDuplicateServiceCallsRequest $request): JsonResponse
    {
        $this->authorize('viewAny', ServiceCall::class);

        return $this->service->checkDuplicate(method_exists($request, 'validated') ? $request->validated() : $request->all(), $request->user(), $this->tenantId());
    }

    // ── Webhook (external creation) ──

    public function webhookCreate(WebhookCreateServiceCallRequest $request): JsonResponse
    {
        return $this->service->webhookCreate(method_exists($request, 'validated') ? $request->validated() : $request->all(), $request->user(), $this->tenantId());
    }

    public function assignees(): JsonResponse
    {
        return $this->service->assignees(auth()->user(), $this->tenantId());
    }
}
