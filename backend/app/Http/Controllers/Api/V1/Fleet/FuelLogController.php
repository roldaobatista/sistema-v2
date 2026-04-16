<?php

namespace App\Http\Controllers\Api\V1\Fleet;

use App\Http\Controllers\Controller;
use App\Http\Requests\Fleet\StoreFuelLogRequest;
use App\Http\Requests\Fleet\UpdateFuelLogRequest;
use App\Models\Fleet\FuelLog;
use App\Models\FleetVehicle;
use App\Support\ApiResponse;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class FuelLogController extends Controller
{
    use ResolvesCurrentTenant;

    public function index(Request $request): JsonResponse
    {
        try {
            $query = FuelLog::where('tenant_id', $this->tenantId())
                ->with(['vehicle:id,plate,model', 'driver:id,name']);

            if ($request->filled('fleet_vehicle_id')) {
                $query->where('fleet_vehicle_id', $request->fleet_vehicle_id);
            }

            if ($request->filled('date_from')) {
                $query->whereDate('date', '>=', $request->date_from);
            }

            if ($request->filled('date_to')) {
                $query->whereDate('date', '<=', $request->date_to);
            }

            return ApiResponse::paginated($query->latest('date')->paginate(min((int) ($request->per_page ?? 15), 100)));
        } catch (\Exception $e) {
            Log::error('FuelLog index failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao listar abastecimentos', 500);
        }
    }

    public function store(StoreFuelLogRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $validated = $request->validated();
            $validated['tenant_id'] = $this->tenantId();
            $validated['driver_id'] = $request->user()->id;

            $tenantId = $this->tenantId();

            $previousLog = FuelLog::where('tenant_id', $tenantId)
                ->where('fleet_vehicle_id', $validated['fleet_vehicle_id'])
                ->where('odometer_km', '<', $validated['odometer_km'])
                ->latest('odometer_km')
                ->first();

            if ($previousLog) {
                $distance = $validated['odometer_km'] - $previousLog->odometer_km;
                $validated['consumption_km_l'] = $distance / $validated['liters'];
            }

            $log = FuelLog::create($validated);

            FleetVehicle::where('tenant_id', $tenantId)
                ->where('id', $validated['fleet_vehicle_id'])
                ->update(['odometer_km' => $validated['odometer_km']]);

            DB::commit();

            return ApiResponse::data($log, 201, ['message' => 'Abastecimento registrado']);
        } catch (ValidationException $e) {
            DB::rollBack();

            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('FuelLog store failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao registrar abastecimento', 500);
        }
    }

    public function show(FuelLog $log): JsonResponse
    {
        try {
            abort_if((int) $log->tenant_id !== $this->tenantId(), 404);

            return ApiResponse::data($log->load(['vehicle', 'driver']));
        } catch (\Exception $e) {
            Log::error('FuelLog show failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao buscar abastecimento', 500);
        }
    }

    public function update(UpdateFuelLogRequest $request, FuelLog $log): JsonResponse
    {
        try {
            DB::beginTransaction();

            abort_if((int) $log->tenant_id !== $this->tenantId(), 404);

            $validated = $request->validated();
            $log->update($validated);

            DB::commit();

            return ApiResponse::data($log->fresh(), 200, ['message' => 'Abastecimento atualizado']);
        } catch (ValidationException $e) {
            DB::rollBack();

            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('FuelLog update failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao atualizar abastecimento', 500);
        }
    }

    public function destroy(FuelLog $log): JsonResponse
    {
        try {
            abort_if((int) $log->tenant_id !== $this->tenantId(), 404);
            $log->delete();

            return ApiResponse::noContent();
        } catch (\Exception $e) {
            Log::error('FuelLog destroy failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao excluir abastecimento', 500);
        }
    }
}
