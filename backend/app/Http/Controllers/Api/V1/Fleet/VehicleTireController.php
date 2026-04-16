<?php

namespace App\Http\Controllers\Api\V1\Fleet;

use App\Http\Controllers\Controller;
use App\Http\Requests\Fleet\StoreVehicleTireRequest;
use App\Http\Requests\Fleet\UpdateVehicleTireRequest;
use App\Models\Fleet\VehicleTire;
use App\Support\ApiResponse;
use App\Support\SearchSanitizer;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class VehicleTireController extends Controller
{
    use ResolvesCurrentTenant;

    public function index(Request $request): JsonResponse
    {
        try {
            $query = VehicleTire::where('tenant_id', $this->tenantId())
                ->with('vehicle:id,plate,model');

            if ($request->filled('fleet_vehicle_id')) {
                $query->where('fleet_vehicle_id', $request->fleet_vehicle_id);
            }

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            if ($request->filled('search')) {
                $safe = SearchSanitizer::contains($request->search);
                $query->where(function ($q) use ($safe) {
                    $q->where('serial_number', 'like', $safe)
                        ->orWhere('brand', 'like', $safe)
                        ->orWhere('model', 'like', $safe);
                });
            }

            return ApiResponse::paginated($query->paginate(min((int) ($request->per_page ?? 15), 100)));
        } catch (\Exception $e) {
            Log::error('VehicleTire index failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao listar pneus', 500);
        }
    }

    public function store(StoreVehicleTireRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $validated = $request->validated();
            $validated['tenant_id'] = $this->tenantId();

            $tire = VehicleTire::create($validated);

            DB::commit();

            return ApiResponse::data($tire, 201, ['message' => 'Pneu registrado']);
        } catch (ValidationException $e) {
            DB::rollBack();

            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('VehicleTire store failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao registrar pneu', 500);
        }
    }

    public function show(VehicleTire $tire): JsonResponse
    {
        try {
            if ((int) $tire->tenant_id !== $this->tenantId()) {
                abort(404);
            }

            return ApiResponse::data($tire->load('vehicle'));
        } catch (\Exception $e) {
            Log::error('VehicleTire show failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao buscar pneu', 500);
        }
    }

    public function update(UpdateVehicleTireRequest $request, VehicleTire $tire): JsonResponse
    {
        try {
            DB::beginTransaction();

            abort_if((int) $tire->tenant_id !== $this->tenantId(), 404);

            $validated = $request->validated();
            $tire->update($validated);

            DB::commit();

            return ApiResponse::data($tire->fresh(), 200, ['message' => 'Pneu atualizado']);
        } catch (ValidationException $e) {
            DB::rollBack();

            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('VehicleTire update failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao atualizar pneu', 500);
        }
    }

    public function destroy(VehicleTire $tire): JsonResponse
    {
        try {
            if ((int) $tire->tenant_id !== $this->tenantId()) {
                abort(404);
            }
            $tire->delete();

            return ApiResponse::message('Pneu excluído');
        } catch (\Exception $e) {
            Log::error('VehicleTire destroy failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao excluir pneu', 500);
        }
    }
}
