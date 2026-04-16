<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Observability\HealthStatusService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class HealthCheckController extends Controller
{
    public function __construct(
        private readonly HealthStatusService $healthStatusService
    ) {}

    public function __invoke(): JsonResponse
    {
        $payload = $this->healthStatusService->status();
        $status = $payload['status'] === 'healthy' ? 200 : 503;

        return ApiResponse::data($payload, $status);
    }
}
