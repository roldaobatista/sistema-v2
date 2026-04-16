<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\HR\StoreEmployeeBenefitRequest;
use App\Http\Requests\HR\UpdateEmployeeBenefitRequest;
use App\Models\EmployeeBenefit;
use App\Models\User;
use App\Support\ApiResponse;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class EmployeeBenefitController extends Controller
{
    use ResolvesCurrentTenant;

    private function userBelongsToTenant(int $userId, int $tenantId): bool
    {
        $belongsToTenant = User::query()
            ->where('id', $userId)
            ->where(function ($query) use ($tenantId) {
                $query
                    ->where('tenant_id', $tenantId)
                    ->orWhere('current_tenant_id', $tenantId);
            })
            ->exists();

        if (! $belongsToTenant && DB::getSchemaBuilder()->hasTable('user_tenants')) {
            $belongsToTenant = DB::table('user_tenants')
                ->where('tenant_id', $tenantId)
                ->where('user_id', $userId)
                ->exists();
        }

        return $belongsToTenant;
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $query = EmployeeBenefit::with('user')->where('tenant_id', $this->resolvedTenantId());

            if ($request->has('user_id')) {
                $query->where('user_id', $request->user_id);
            }

            if ($request->has('type')) {
                $query->where('type', $request->type);
            }

            return ApiResponse::paginated($query->paginate(20));
        } catch (\Exception $e) {
            Log::error('EmployeeBenefit index failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao listar benefícios', 500);
        }
    }

    public function store(StoreEmployeeBenefitRequest $request): JsonResponse
    {
        $tenantId = $this->resolvedTenantId();
        $validated = $request->validated();

        try {
            DB::beginTransaction();

            if (! $this->userBelongsToTenant((int) $validated['user_id'], $tenantId)) {
                DB::rollBack();

                return ApiResponse::message('Colaborador não pertence ao tenant atual.', 422);
            }

            $benefit = EmployeeBenefit::create(array_merge($validated, ['tenant_id' => $tenantId]));

            DB::commit();

            return ApiResponse::data($benefit, 201, ['message' => 'Benefício criado com sucesso']);
        } catch (ValidationException $e) {
            DB::rollBack();

            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('EmployeeBenefit store failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao criar benefício', 500);
        }
    }

    public function show($id): JsonResponse
    {
        try {
            $benefit = EmployeeBenefit::with('user')
                ->where('tenant_id', $this->resolvedTenantId())
                ->findOrFail($id);

            return ApiResponse::data($benefit);
        } catch (ModelNotFoundException $e) {
            return ApiResponse::message('Benefício não encontrado', 404);
        } catch (\Exception $e) {
            Log::error('EmployeeBenefit show failed', ['error' => $e->getMessage(), 'id' => $id]);

            return ApiResponse::message('Erro ao buscar benefício', 500);
        }
    }

    public function update(UpdateEmployeeBenefitRequest $request, $id): JsonResponse
    {
        $tenantId = $this->resolvedTenantId();
        $validated = $request->validated();

        try {
            DB::beginTransaction();

            $benefit = EmployeeBenefit::where('tenant_id', $tenantId)->findOrFail($id);

            if (isset($validated['user_id']) && ! $this->userBelongsToTenant((int) $validated['user_id'], $tenantId)) {
                DB::rollBack();

                return ApiResponse::message('Colaborador não pertence ao tenant atual.', 422);
            }

            $benefit->update($validated);

            DB::commit();

            return ApiResponse::data($benefit->fresh(), 200, ['message' => 'Benefício atualizado com sucesso']);
        } catch (ModelNotFoundException $e) {
            DB::rollBack();

            return ApiResponse::message('Benefício não encontrado', 404);
        } catch (ValidationException $e) {
            DB::rollBack();

            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('EmployeeBenefit update failed', ['error' => $e->getMessage(), 'id' => $id]);

            return ApiResponse::message('Erro ao atualizar benefício', 500);
        }
    }

    public function destroy($id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $benefit = EmployeeBenefit::where('tenant_id', $this->resolvedTenantId())->findOrFail($id);
            $benefit->delete();

            DB::commit();

            return ApiResponse::message('Benefício excluído com sucesso');
        } catch (ModelNotFoundException $e) {
            DB::rollBack();

            return ApiResponse::message('Benefício não encontrado', 404);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('EmployeeBenefit destroy failed', ['error' => $e->getMessage(), 'id' => $id]);

            return ApiResponse::message('Erro ao excluir benefício', 500);
        }
    }
}
