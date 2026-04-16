<?php

namespace App\Http\Controllers\Api\V1\Fleet;

use App\Http\Controllers\Controller;
use App\Http\Requests\Fleet\FuelComparisonRequest;
use App\Http\Requests\Fleet\TripSimulationRequest;
use App\Services\Fleet\DriverScoringService;
use App\Services\Fleet\FleetDashboardService;
use App\Services\Fleet\FuelComparisonService;
use App\Support\ApiResponse;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class FleetAdvancedController extends Controller
{
    use ResolvesCurrentTenant;

    public function __construct(
        private FleetDashboardService $dashboardService,
        private FuelComparisonService $fuelComparisonService,
        private DriverScoringService $driverScoringService,
    ) {}

    public function dashboard(Request $request): JsonResponse
    {
        try {
            $data = $this->dashboardService->getAdvancedDashboard($this->tenantId());

            return ApiResponse::data($data);
        } catch (\Exception $e) {
            Log::error('FleetAdvanced dashboard failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao carregar dashboard da frota', 500);
        }
    }

    public function fuelComparison(FuelComparisonRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();

            $result = $this->fuelComparisonService->compare(
                $validated['gasoline_price'],
                $validated['ethanol_price'],
                $validated['diesel_price'] ?? null,
            );

            return ApiResponse::data($result);
        } catch (ValidationException $e) {
            return ApiResponse::message('Validação falhou', 422, ['errors' => $e->errors()]);
        } catch (\Exception $e) {
            Log::error('FleetAdvanced fuelComparison failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro na comparação de combustíveis', 500);
        }
    }

    public function tripSimulation(TripSimulationRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();

            $result = $this->fuelComparisonService->simulateTrip(
                $validated['distance_km'],
                $validated['avg_consumption'],
                $validated['fuel_price'],
            );

            return ApiResponse::data($result);
        } catch (ValidationException $e) {
            return ApiResponse::message('Validação falhou', 422, ['errors' => $e->errors()]);
        } catch (\Exception $e) {
            Log::error('FleetAdvanced tripSimulation failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro na simulação de viagem', 500);
        }
    }

    public function driverScore(Request $request, int $driverId): JsonResponse
    {
        try {
            $result = $this->driverScoringService->calculateScore($driverId, $this->tenantId());

            return ApiResponse::data($result);
        } catch (\Exception $e) {
            Log::error('FleetAdvanced driverScore failed', ['error' => $e->getMessage(), 'driverId' => $driverId]);

            return ApiResponse::message('Erro ao calcular score do motorista', 500);
        }
    }

    public function driverRanking(Request $request): JsonResponse
    {
        try {
            $ranking = $this->driverScoringService->getRanking($this->tenantId());

            return ApiResponse::data($ranking);
        } catch (\Exception $e) {
            Log::error('FleetAdvanced driverRanking failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao carregar ranking de motoristas', 500);
        }
    }
}
