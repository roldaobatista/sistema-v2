<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Advanced\IndexCostCenterRequest;
use App\Http\Requests\Advanced\StoreCostCenterRequest;
use App\Http\Requests\Advanced\UpdateCostCenterRequest;
use App\Models\CostCenter;
use App\Support\ApiResponse;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CostCenterController extends Controller
{
    use ResolvesCurrentTenant;

    public function index(IndexCostCenterRequest $request): JsonResponse
    {
        return ApiResponse::paginated(
            CostCenter::where('tenant_id', $this->tenantId())
                ->with('children')
                ->whereNull('parent_id')
                ->orderBy('code')
                ->paginate($request->input('per_page', 15))
        );
    }

    public function store(StoreCostCenterRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            DB::beginTransaction();
            $validated['tenant_id'] = $this->tenantId();
            $center = CostCenter::create($validated);
            DB::commit();

            return ApiResponse::data($center, 201, ['message' => 'Centro de custo criado']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('CostCenter create failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao criar.', 500);
        }
    }

    public function update(UpdateCostCenterRequest $request, CostCenter $costCenter): JsonResponse
    {
        if ((int) $costCenter->tenant_id !== $this->tenantId()) {
            return ApiResponse::message('Centro de custo não encontrado.', 404);
        }

        $validated = $request->validated();

        try {
            DB::beginTransaction();
            $costCenter->update($validated);
            DB::commit();

            return ApiResponse::data($costCenter->fresh(), 200, ['message' => 'Centro de custo atualizado']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('CostCenter update failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao atualizar.', 500);
        }
    }

    public function destroy(CostCenter $costCenter): JsonResponse
    {
        if ((int) $costCenter->tenant_id !== $this->tenantId()) {
            return ApiResponse::message('Centro de custo não encontrado.', 404);
        }

        if ($costCenter->children()->exists()) {
            return ApiResponse::message('Não é possível remover um centro de custo com filhos.', 409);
        }

        try {
            DB::beginTransaction();
            $costCenter->delete();
            DB::commit();

            return ApiResponse::message('Centro de custo removido.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('CostCenter destroy failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao remover.', 500);
        }
    }
}
