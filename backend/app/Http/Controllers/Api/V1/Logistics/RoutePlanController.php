<?php

namespace App\Http\Controllers\Api\V1\Logistics;

use App\Http\Controllers\Controller;
use App\Http\Requests\Advanced\IndexRoutePlanRequest;
use App\Http\Requests\Logistics\StoreLogisticsRoutePlanRequest;
use App\Models\RoutePlan;
use App\Support\ApiResponse;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;

class RoutePlanController extends Controller
{
    use ResolvesCurrentTenant;

    public function indexRoutePlans(IndexRoutePlanRequest $request): JsonResponse
    {
        $query = RoutePlan::query()
            ->where('tenant_id', $this->tenantId())
            ->with('technician:id,name');

        $validated = $request->validated();
        if (! empty($validated['technician_id'])) {
            $query->where('technician_id', (int) $validated['technician_id']);
        }

        if (! empty($validated['date'])) {
            $query->whereDate('plan_date', $validated['date']);
        }

        return ApiResponse::paginated(
            $query->orderByDesc('plan_date')
                ->paginate(min((int) ($validated['per_page'] ?? 20), 100))
        );
    }

    public function storeRoutePlan(StoreLogisticsRoutePlanRequest $request): JsonResponse
    {
        $plan = RoutePlan::create([
            ...$request->validated(),
            'tenant_id' => $this->tenantId(),
            'plan_date' => (string) $request->input('plan_date'),
        ]);

        return ApiResponse::data($plan, 201, ['message' => 'Rota planejada']);
    }
}
