<?php

namespace App\Http\Controllers\Api\V1\Operational;

use App\Events\WorkOrderStatusChanged;
use App\Http\Controllers\Controller;
use App\Http\Requests\Os\StoreExpressWorkOrderRequest;
use App\Http\Resources\WorkOrderResource;
use App\Models\Customer;
use App\Models\WorkOrder;
use App\Support\ApiResponse;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExpressWorkOrderController extends Controller
{
    use ResolvesCurrentTenant;

    public function store(StoreExpressWorkOrderRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $tenantId = $this->resolvedTenantId();

        try {
            return DB::transaction(function () use ($validated, $tenantId, $request) {
                $customerId = $validated['customer_id'] ?? null;
                if (! $customerId) {
                    $customer = Customer::create([
                        'tenant_id' => $tenantId,
                        'name' => $validated['customer_name'],
                        'is_active' => true,
                        'type' => 'PF',
                    ]);
                    $customerId = $customer->id;
                }

                $workOrder = WorkOrder::create([
                    'tenant_id' => $tenantId,
                    'customer_id' => $customerId,
                    'number' => WorkOrder::nextNumber($tenantId),
                    'description' => $validated['description'],
                    'priority' => $validated['priority'],
                    'status' => WorkOrder::STATUS_OPEN,
                    'origin_type' => 'manual',
                    'assigned_to' => $request->user()->id,
                    'created_by' => $request->user()->id,
                ]);

                $workOrder->statusHistory()->create([
                    'tenant_id' => $tenantId,
                    'user_id' => $request->user()->id,
                    'from_status' => null,
                    'to_status' => WorkOrder::STATUS_OPEN,
                    'notes' => 'OS Express criada',
                ]);

                // Broadcast para dashboard/kanban em real-time
                event(new WorkOrderStatusChanged($workOrder));

                return ApiResponse::data(
                    new WorkOrderResource($workOrder->load(['customer:id,name', 'assignee:id,name'])),
                    201,
                    ['message' => 'OS Express criada com sucesso']
                );
            });
        } catch (\Exception $e) {
            Log::error('OS Express failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao criar OS Express', 500);
        }
    }
}
