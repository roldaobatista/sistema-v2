<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Integration\CircuitBreaker;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Health-check and management endpoint for all integration circuit breakers.
 */
class IntegrationHealthController extends Controller
{
    /**
     * List status of all registered circuit breakers.
     */
    public function index(Request $request): JsonResponse
    {
        if ($request->user() && ! $request->user()->can('integration.health.view')) {
            return ApiResponse::message('Acesso negado.', 403);
        }

        // Get statuses from registry
        $statuses = CircuitBreaker::statusAll();

        // Also check well-known services that may not be in registry yet
        $knownServices = ['esocial_api', 'fiscal_nuvemfiscal', 'fiscal_focusnfe', 'asaas_api'];

        foreach ($knownServices as $service) {
            if (! isset($statuses[$service])) {
                $cb = CircuitBreaker::for($service);
                $statuses[$service] = [
                    'service' => $service,
                    'state' => $cb->getState(),
                    'failure_count' => $cb->getFailureCount(),
                ];
            }
        }

        return ApiResponse::data([
            'services' => array_values($statuses),
            'total' => count($statuses),
            'healthy' => collect($statuses)->where('state', 'closed')->count(),
            'degraded' => collect($statuses)->where('state', 'half_open')->count(),
            'unhealthy' => collect($statuses)->where('state', 'open')->count(),
        ]);
    }

    /**
     * Show status of a specific circuit breaker.
     */
    public function show(Request $request, string $service): JsonResponse
    {
        if ($request->user() && ! $request->user()->can('integration.health.view')) {
            return ApiResponse::message('Acesso negado.', 403);
        }

        $cb = CircuitBreaker::for($service);

        return ApiResponse::data([
            'service' => $service,
            'state' => $cb->getState(),
            'failure_count' => $cb->getFailureCount(),
        ]);
    }

    /**
     * Force reset a circuit breaker to CLOSED state.
     */
    public function reset(Request $request, string $service): JsonResponse
    {
        if ($request->user() && ! $request->user()->can('integration.health.reset')) {
            return ApiResponse::message('Acesso negado.', 403);
        }

        $cb = CircuitBreaker::for($service);
        $previousState = $cb->getState();
        $cb->reset();

        return ApiResponse::data([
            'service' => $service,
            'previous_state' => $previousState,
            'current_state' => 'closed',
            'message' => "Circuit breaker '{$service}' resetado com sucesso.",
        ]);
    }
}
