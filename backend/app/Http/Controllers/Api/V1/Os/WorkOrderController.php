<?php

namespace App\Http\Controllers\Api\V1\Os;

use App\Http\Controllers\Controller;
use App\Http\Requests\WorkOrder\IndexWorkOrderRequest;
use App\Http\Requests\WorkOrder\StoreWorkOrderRequest;
use App\Http\Requests\WorkOrder\UpdateWorkOrderRequest;
use App\Http\Resources\WorkOrderResource;
use App\Models\WorkOrder;
use App\Services\WorkOrderService;
use App\Support\ApiResponse;
use App\Traits\ResolvesCurrentTenant;
use App\Traits\ScopesByRole;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class WorkOrderController extends Controller
{
    use ResolvesCurrentTenant;
    use ScopesByRole;

    public function __construct(
        private readonly WorkOrderService $service
    ) {}

    public function index(IndexWorkOrderRequest $request): JsonResponse
    {
        $this->authorize('viewAny', WorkOrder::class);

        $orders = $this->service->list(
            $request->validated(),
            $this->tenantId(),
            $this->shouldScopeByUser() ? auth()->id() : null
        );

        return ApiResponse::paginated(
            $orders,
            ['status_counts' => $orders->statusCounts ?? []],
            ['status_counts' => $orders->statusCounts ?? []],
            WorkOrderResource::class
        );
    }

    public function store(StoreWorkOrderRequest $request): JsonResponse
    {
        $this->authorize('create', WorkOrder::class);
        $tenantId = $this->tenantId();

        try {
            $result = $this->service->create($request->validated(), $request->user(), $tenantId);

            return ApiResponse::data(
                new WorkOrderResource($result['order']->load(['customer', 'equipment', 'assignee:id,name', 'seller:id,name', 'technicians', 'equipmentsList', 'items', 'statusHistory.user:id,name'])),
                201,
                ['warranty_warning' => $result['warranty_warning']]
            );
        } catch (ValidationException $e) {
            return ApiResponse::message(
                is_array($e->validator) ? $e->getMessage() : collect($e->errors())->first()[0] ?? $e->getMessage(),
                422,
                ['errors' => $e->errors()]
            );
        } catch (\Exception $e) {
            Log::error('Store OS error', ['err' => $e->getMessage()]);

            return ApiResponse::message('Erro ao criar OS', 500);
        }
    }

    public function show(WorkOrder $workOrder): JsonResponse
    {
        $this->authorize('view', $workOrder);
        if ($error = $this->ensureTenantOwnership($workOrder, 'OS')) {
            return $error;
        }
        $workOrder->load([
            'customer' => fn ($q) => $q->withTrashed()->with('contacts'),
            'equipment',
            'serviceCall',
            'assignee',
            'items.product',
            'items.service',
            'attachments.uploader',
            'statusHistory.user:id,name',
            'parent',
            'children',
            'branch',
            'creator:id,name',
            'seller',
            'driver:id,name',
            'quote',
            'technicians',
            'equipmentsList',
            'items',
            'attachments',
            'checklistResponses.item',
            'displacementStops',
            'calibrations',
            'invoices',
            'satisfactionSurvey',
            'chats.user:id,name',
            'dispatchAuthorizer:id,name',
        ]);

        return ApiResponse::data(new WorkOrderResource($workOrder));
    }

    public function update(UpdateWorkOrderRequest $request, WorkOrder $workOrder): JsonResponse
    {
        $this->authorize('update', $workOrder);
        if ($error = $this->ensureTenantOwnership($workOrder, 'OS')) {
            return $error;
        }

        if ($request->has('updated_at')) {
            $clientUpdatedAt = Carbon::parse($request->input('updated_at'));
            if ($workOrder->updated_at->gt($clientUpdatedAt)) {
                return ApiResponse::message(
                    'Esta OS foi modificada por outro usuário. Recarregue a página para ver as alterações mais recentes.',
                    409
                );
            }
        }

        try {
            $updatedOrder = $this->service->update($request->validated(), $workOrder, $request->user());

            return ApiResponse::data(
                new WorkOrderResource($updatedOrder->load(['customer', 'equipment', 'assignee:id,name', 'seller:id,name', 'technicians', 'equipmentsList', 'items', 'statusHistory.user:id,name']))
            );
        } catch (ValidationException $e) {
            return ApiResponse::message(
                is_array($e->validator) ? $e->getMessage() : collect($e->errors())->first()[0] ?? $e->getMessage(),
                422,
                ['errors' => $e->errors()]
            );
        } catch (\Exception $e) {
            Log::error('WorkOrder update failed', ['id' => $workOrder->id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao atualizar OS', 500);
        }
    }

    public function destroy(WorkOrder $workOrder): JsonResponse
    {
        $this->authorize('delete', $workOrder);
        if ($error = $this->ensureTenantOwnership($workOrder, 'OS')) {
            return $error;
        }

        try {
            $this->service->delete($workOrder);

            return ApiResponse::noContent();
        } catch (ValidationException $e) {
            $status = isset($e->errors()['conflicts']) ? 409 : 422;

            return ApiResponse::message(
                is_array($e->validator) ? $e->getMessage() : collect($e->errors())->first()[0] ?? $e->getMessage(),
                $status,
                ['errors' => $e->errors()]
            );
        } catch (\Exception $e) {
            return ApiResponse::message('Erro ao excluir OS', 500);
        }
    }
}
