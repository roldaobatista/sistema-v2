<?php

namespace App\Http\Controllers\Api\V1\Fleet;

use App\Http\Controllers\Controller;
use App\Http\Requests\Fleet\StoreVehiclePoolRequest;
use App\Http\Requests\Fleet\UpdateVehiclePoolStatusRequest;
use App\Models\Fleet\VehiclePoolRequest;
use App\Support\ApiResponse;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class VehiclePoolController extends Controller
{
    use ResolvesCurrentTenant;

    public function index(Request $request): JsonResponse
    {
        try {
            $query = VehiclePoolRequest::where('tenant_id', $this->tenantId())
                ->with(['user:id,name', 'vehicle:id,plate,model']);

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            if ($request->filled('user_id')) {
                $query->where('user_id', $request->user_id);
            }

            return ApiResponse::paginated($query->latest()->paginate(min((int) ($request->per_page ?? 15), 100)));
        } catch (\Exception $e) {
            Log::error('VehiclePool index failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao listar solicitações de veículo', 500);
        }
    }

    public function store(StoreVehiclePoolRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $validated = $request->validated();
            $validated['tenant_id'] = $this->tenantId();
            $validated['user_id'] = $request->user()->id;
            $validated['status'] = 'pending';

            $poolRequest = VehiclePoolRequest::create($validated);

            DB::commit();

            return ApiResponse::data($poolRequest, 201, ['message' => 'Solicitação criada']);
        } catch (ValidationException $e) {
            DB::rollBack();

            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('VehiclePool store failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao criar solicitação', 500);
        }
    }

    public function show(VehiclePoolRequest $requestModel): JsonResponse
    {
        try {
            abort_if((int) $requestModel->tenant_id !== $this->tenantId(), 404);

            return ApiResponse::data($requestModel->load(['user', 'vehicle']));
        } catch (\Exception $e) {
            Log::error('VehiclePool show failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao buscar solicitação', 500);
        }
    }

    public function updateStatus(UpdateVehiclePoolStatusRequest $request, $id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $poolRequest = VehiclePoolRequest::where('tenant_id', $this->tenantId())
                ->findOrFail($id);

            $validated = $request->validated();
            $poolRequest->update($validated);

            DB::commit();

            return ApiResponse::data($poolRequest->fresh(), 200, ['message' => 'Status atualizado']);
        } catch (ValidationException $e) {
            DB::rollBack();

            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('VehiclePool updateStatus failed', ['error' => $e->getMessage(), 'id' => $id]);

            return ApiResponse::message('Erro ao atualizar status', 500);
        }
    }

    public function destroy($id): JsonResponse
    {
        try {
            $poolRequest = VehiclePoolRequest::where('tenant_id', $this->tenantId())
                ->findOrFail($id);

            if ($poolRequest->status != 'pending') {
                return ApiResponse::message('Apenas solicitações pendentes podem ser excluídas', 422);
            }

            $poolRequest->delete();

            return ApiResponse::message('Solicitação excluída');
        } catch (ModelNotFoundException $e) {
            return ApiResponse::message('Solicitação não encontrada', 404);
        } catch (\Exception $e) {
            Log::error('VehiclePool destroy failed', ['error' => $e->getMessage(), 'id' => $id]);

            return ApiResponse::message('Erro ao excluir solicitação', 500);
        }
    }
}
