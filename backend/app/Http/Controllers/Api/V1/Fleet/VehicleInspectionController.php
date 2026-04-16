<?php

namespace App\Http\Controllers\Api\V1\Fleet;

use App\Http\Controllers\Controller;
use App\Http\Requests\Fleet\StoreVehicleInspectionRequest;
use App\Http\Requests\Fleet\UpdateVehicleInspectionRequest;
use App\Models\FleetVehicle;
use App\Models\VehicleInspection;
use App\Support\ApiResponse;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class VehicleInspectionController extends Controller
{
    use ResolvesCurrentTenant;

    public function index(Request $request): JsonResponse
    {
        try {
            $query = VehicleInspection::where('tenant_id', $this->tenantId())
                ->with(['vehicle:id,plate,model', 'inspector:id,name']);

            if ($request->filled('fleet_vehicle_id')) {
                $query->where('fleet_vehicle_id', $request->fleet_vehicle_id);
            }

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            return ApiResponse::paginated($query->latest('inspection_date')->paginate(min((int) ($request->per_page ?? 15), 100)));
        } catch (\Exception $e) {
            Log::error('VehicleInspection index failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao listar inspeções', 500);
        }
    }

    public function store(StoreVehicleInspectionRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $validated = $request->validated();
            $validated['tenant_id'] = $this->tenantId();
            $validated['inspector_id'] = $request->user()->id;

            $inspection = VehicleInspection::create($validated);

            FleetVehicle::where('tenant_id', $this->tenantId())
                ->where('id', $validated['fleet_vehicle_id'])
                ->update(['odometer_km' => $validated['odometer_km']]);

            DB::commit();

            return ApiResponse::data($inspection, 201, ['message' => 'Inspeção registrada']);
        } catch (ValidationException $e) {
            DB::rollBack();

            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('VehicleInspection store failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao registrar inspeção', 500);
        }
    }

    public function show(VehicleInspection $inspection): JsonResponse
    {
        try {
            if ((int) $inspection->tenant_id !== $this->tenantId()) {
                abort(404);
            }

            return ApiResponse::data($inspection->load(['vehicle', 'inspector']));
        } catch (\Exception $e) {
            Log::error('VehicleInspection show failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao buscar inspeção', 500);
        }
    }

    public function update(UpdateVehicleInspectionRequest $request, VehicleInspection $inspection): JsonResponse
    {
        try {
            DB::beginTransaction();

            abort_if((int) $inspection->tenant_id !== $this->tenantId(), 404);

            $validated = $request->validated();
            $inspection->update($validated);

            DB::commit();

            return ApiResponse::data($inspection->fresh(), 200, ['message' => 'Inspeção atualizada']);
        } catch (ValidationException $e) {
            DB::rollBack();

            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('VehicleInspection update failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao atualizar inspeção', 500);
        }
    }

    public function destroy(VehicleInspection $inspection): JsonResponse
    {
        try {
            if ((int) $inspection->tenant_id !== $this->tenantId()) {
                abort(404);
            }
            $inspection->delete();

            return ApiResponse::message('Inspeção excluída');
        } catch (\Exception $e) {
            Log::error('VehicleInspection destroy failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao excluir inspeção', 500);
        }
    }
}
