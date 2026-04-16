<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Observability\ObservabilityDashboardRequest;
use App\Services\Observability\ObservabilityDashboardService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class ObservabilityDashboardController extends Controller
{
    public function __construct(
        private readonly ObservabilityDashboardService $dashboardService
    ) {}

    public function __invoke(ObservabilityDashboardRequest $request): JsonResponse
    {
        return ApiResponse::data($this->dashboardService->build());
    }
}
