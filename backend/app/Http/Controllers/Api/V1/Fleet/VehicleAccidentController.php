<?php

namespace App\Http\Controllers\Api\V1\Fleet;

use App\Http\Controllers\Controller;
use App\Http\Requests\Fleet\StoreVehicleAccidentRequest;
use App\Http\Requests\Fleet\UpdateVehicleAccidentRequest;
use App\Models\Fleet\VehicleAccident;
use App\Support\ApiResponse;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VehicleAccidentController extends Controller
{
    use ResolvesCurrentTenant;

    public function index(Request $request): JsonResponse
    {
        try {
            $query = VehicleAccident::where('tenant_id', $this->tenantId())
                ->with(['vehicle:id,plate,model', 'driver:id,name']);

            if ($request->filled('fleet_vehicle_id')) {
                $query->where('fleet_vehicle_id', $request->fleet_vehicle_id);
            }

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            return ApiResponse::paginated($query->latest('occurrence_date')->paginate(min((int) ($request->per_page ?? 15), 100)));
        } catch (\Exception $e) {
            Log::error('VehicleAccident index failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao listar acidentes', 500);
        }
    }

    public function store(StoreVehicleAccidentRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $validated = $request->validated();
            $validated['tenant_id'] = $this->tenantId();
            $validated['driver_id'] = $request->user()->id;

            $accident = VehicleAccident::create($validated);

            DB::commit();

            return ApiResponse::data($accident, 201, ['message' => 'Acidente registrado']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('VehicleAccident store failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao registrar acidente', 500);
        }
    }

    public function show(VehicleAccident $accident): JsonResponse
    {
        try {
            if ((int) $accident->tenant_id !== $this->tenantId()) {
                abort(404);
            }

            return ApiResponse::data($accident->load(['vehicle', 'driver']));
        } catch (\Exception $e) {
            Log::error('VehicleAccident show failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao buscar acidente', 500);
        }
    }

    public function update(UpdateVehicleAccidentRequest $request, VehicleAccident $accident): JsonResponse
    {
        try {
            DB::beginTransaction();

            if ((int) $accident->tenant_id !== $this->tenantId()) {
                abort(404);
            }

            $accident->update($request->validated());

            DB::commit();

            return ApiResponse::data($accident->fresh(), 200, ['message' => 'Acidente atualizado']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('VehicleAccident update failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao atualizar acidente', 500);
        }
    }

    public function destroy(VehicleAccident $accident): JsonResponse
    {
        try {
            if ((int) $accident->tenant_id !== $this->tenantId()) {
                abort(404);
            }
            $accident->delete();

            return ApiResponse::message('Acidente excluído');
        } catch (\Exception $e) {
            Log::error('VehicleAccident destroy failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao excluir acidente', 500);
        }
    }
}
