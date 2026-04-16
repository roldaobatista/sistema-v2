<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Advanced\IndexWorkOrderRatingRequest;
use App\Http\Requests\Advanced\SubmitWorkOrderRatingRequest;
use App\Models\WorkOrder;
use App\Models\WorkOrderRating;
use App\Support\ApiResponse;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WorkOrderRatingController extends Controller
{
    use ResolvesCurrentTenant;

    public function index(IndexWorkOrderRatingRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $query = WorkOrderRating::query()
            ->whereHas('workOrder', fn ($workOrderQuery) => $workOrderQuery->where('tenant_id', $this->tenantId()))
            ->with([
                'workOrder:id,number,os_number,customer_id',
                'customer:id,name',
            ]);

        return ApiResponse::paginated(
            $query->orderByDesc('created_at')
                ->paginate(min((int) ($validated['per_page'] ?? 20), 100))
        );
    }

    public function submitRating(SubmitWorkOrderRatingRequest $request, string $token): JsonResponse
    {
        // Not scoped to tenant because the client submits it via public token without auth headers usually.
        $workOrder = WorkOrder::where('rating_token', $token)->firstOrFail();

        $existingRating = WorkOrderRating::where('work_order_id', $workOrder->id)->first();
        if ($existingRating) {
            return ApiResponse::message('Avaliação já registrada.', 422);
        }

        $validated = $request->validated();

        try {
            DB::beginTransaction();
            $rating = WorkOrderRating::create([
                ...$validated,
                'work_order_id' => $workOrder->id,
                'customer_id' => $workOrder->customer_id,
                'channel' => 'link',
            ]);
            DB::commit();

            return ApiResponse::data($rating, 201, ['message' => 'Obrigado pela avaliação!']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('WorkOrderRating submit failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao registrar avaliação.', 500);
        }
    }
}
