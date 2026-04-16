<?php

namespace App\Http\Controllers\Api\V1\Logistics;

use App\Http\Controllers\Controller;
use App\Http\Requests\Logistics\DailyPlanRequest;
use App\Services\RoutingOptimizationService;
use App\Support\ApiResponse;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;

class RoutingController extends Controller
{
    use ResolvesCurrentTenant;

    public function __construct(
        private readonly RoutingOptimizationService $routingOptimizationService
    ) {}

    public function dailyPlan(DailyPlanRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $date = $validated['date'] ?? now()->toDateString();
        $user = $request->user();

        $optimizedPath = $this->routingOptimizationService->optimizeDailyPlan(
            $this->tenantId(),
            (int) $user->id,
            $date
        );

        return ApiResponse::data([
            'date' => $date,
            'technician_id' => (int) $user->id,
            'optimized_path' => $optimizedPath,
        ]);
    }
}
