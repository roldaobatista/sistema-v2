<?php

namespace App\Http\Controllers\Api\V1\Fleet;

use App\Http\Controllers\Controller;
use App\Http\Requests\Fleet\UpdateGpsPositionRequest;
use App\Models\FleetVehicle;
use App\Support\ApiResponse;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class GpsTrackingController extends Controller
{
    use ResolvesCurrentTenant;

    public function livePositions(Request $request): JsonResponse
    {
        try {
            $vehicles = FleetVehicle::where('tenant_id', $this->tenantId())
                ->whereNotNull('last_gps_lat')
                ->whereNotNull('last_gps_lng')
                ->select('id', 'plate', 'model', 'brand', 'status', 'last_gps_lat', 'last_gps_lng', 'last_gps_at', 'assigned_user_id')
                ->with('assignedUser:id,name')
                ->get();

            return ApiResponse::data($vehicles);
        } catch (\Exception $e) {
            Log::error('GpsTracking livePositions failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao carregar posições ao vivo', 500);
        }
    }

    public function updatePosition(UpdateGpsPositionRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $validated = $request->validated();

            $vehicle = FleetVehicle::where('id', $validated['fleet_vehicle_id'])
                ->where('tenant_id', $this->tenantId())
                ->firstOrFail();

            $vehicle->update([
                'last_gps_lat' => $validated['lat'],
                'last_gps_lng' => $validated['lng'],
                'last_gps_at' => now(),
            ]);

            DB::table('gps_tracking_history')->insert([
                'tenant_id' => $this->tenantId(),
                'fleet_vehicle_id' => $validated['fleet_vehicle_id'],
                'lat' => $validated['lat'],
                'lng' => $validated['lng'],
                'recorded_at' => now(),
                'created_at' => now(),
            ]);

            DB::commit();

            return ApiResponse::message('Posição atualizada');
        } catch (ValidationException $e) {
            DB::rollBack();

            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('GpsTracking updatePosition failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao atualizar posição', 500);
        }
    }

    public function history(Request $request, int $vehicleId): JsonResponse
    {
        try {
            $vehicle = FleetVehicle::where('id', $vehicleId)
                ->where('tenant_id', $this->tenantId())
                ->firstOrFail();

            $history = DB::table('gps_tracking_history')
                ->where('fleet_vehicle_id', $vehicleId)
                ->where('tenant_id', $this->tenantId())
                ->when($request->filled('date'), fn ($q) => $q->whereDate('recorded_at', $request->date))
                ->orderBy('recorded_at')
                ->limit(500)
                ->get(['lat', 'lng', 'recorded_at']);

            return ApiResponse::data($history, 200, ['vehicle' => $vehicle->only('id', 'plate', 'model')]);
        } catch (ModelNotFoundException $e) {
            return ApiResponse::message('Veículo não encontrado', 404);
        } catch (\Exception $e) {
            Log::error('GpsTracking history failed', ['error' => $e->getMessage(), 'vehicleId' => $vehicleId]);

            return ApiResponse::message('Erro ao buscar histórico GPS', 500);
        }
    }
}
