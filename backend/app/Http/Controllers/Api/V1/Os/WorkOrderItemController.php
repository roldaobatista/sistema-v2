<?php

namespace App\Http\Controllers\Api\V1\Os;

use App\Http\Controllers\Controller;
use App\Http\Requests\Os\StoreWorkOrderItemRequest;
use App\Http\Requests\Os\UpdateWorkOrderItemRequest;
use App\Models\WorkOrder;
use App\Models\WorkOrderItem;
use App\Services\WorkOrderService;
use App\Support\ApiResponse;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class WorkOrderItemController extends Controller
{
    use ResolvesCurrentTenant;

    public function __construct(private readonly WorkOrderService $service) {}

    public function items(WorkOrder $workOrder): JsonResponse
    {
        $this->authorize('view', $workOrder);
        if ($error = $this->ensureTenantOwnership($workOrder, 'OS')) {
            return $error;
        }

        return ApiResponse::paginated(
            $workOrder->items()
                ->where('tenant_id', $this->tenantId())
                ->orderBy('id')
                ->paginate(30)
        );
    }

    public function storeItem(StoreWorkOrderItemRequest $request, WorkOrder $workOrder): JsonResponse
    {
        $this->authorize('update', $workOrder);
        if ($error = $this->ensureTenantOwnership($workOrder, 'OS')) {
            return $error;
        }

        try {
            $item = $this->service->addItem($request->validated(), $workOrder, $this->tenantId());

            return ApiResponse::data($item, 201);
        } catch (ValidationException $e) {
            return ApiResponse::message(collect($e->errors())->first()[0] ?? $e->getMessage(), 422, ['errors' => $e->errors()]);
        } catch (\Exception $e) {
            Log::error('WorkOrder storeItem failed', ['wo_id' => $workOrder->id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao adicionar item', 500);
        }
    }

    public function updateItem(UpdateWorkOrderItemRequest $request, WorkOrder $workOrder, WorkOrderItem $item): JsonResponse
    {
        $this->authorize('update', $workOrder);
        if ($error = $this->ensureTenantOwnership($workOrder, 'OS')) {
            return $error;
        }

        try {
            $updatedItem = $this->service->updateItem($request->validated(), $workOrder, $item, $this->tenantId());

            return ApiResponse::data($updatedItem);
        } catch (ValidationException $e) {
            return ApiResponse::message(collect($e->errors())->first()[0] ?? $e->getMessage(), 422, ['errors' => $e->errors()]);
        } catch (\Exception $e) {
            if ($e->getCode() === 403) {
                return ApiResponse::message($e->getMessage(), 403);
            }
            Log::error('WorkOrder updateItem failed', ['item_id' => $item->id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao atualizar item', 500);
        }
    }

    public function destroyItem(WorkOrder $workOrder, WorkOrderItem $item): JsonResponse
    {
        $this->authorize('update', $workOrder);
        if ($error = $this->ensureTenantOwnership($workOrder, 'OS')) {
            return $error;
        }

        try {
            $this->service->deleteItem($workOrder, $item);

            return ApiResponse::noContent();
        } catch (\Exception $e) {
            if ($e->getCode() === 403) {
                return ApiResponse::message($e->getMessage(), 403);
            }
            Log::error('WorkOrder destroyItem failed', ['item_id' => $item->id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao remover item', 500);
        }
    }
}
