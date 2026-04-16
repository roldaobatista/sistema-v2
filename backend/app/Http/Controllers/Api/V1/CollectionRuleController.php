<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Advanced\IndexCollectionRuleRequest;
use App\Http\Requests\Advanced\StoreCollectionRuleAdvancedRequest;
use App\Http\Requests\Advanced\UpdateCollectionRuleAdvancedRequest;
use App\Models\CollectionRule;
use App\Support\ApiResponse;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CollectionRuleController extends Controller
{
    use ResolvesCurrentTenant;

    public function index(IndexCollectionRuleRequest $request): JsonResponse
    {
        $validated = $request->validated();

        return ApiResponse::paginated(
            CollectionRule::where('tenant_id', $this->tenantId())
                ->orderBy('name')
                ->paginate(min((int) ($validated['per_page'] ?? 20), 100))
        );
    }

    public function store(StoreCollectionRuleAdvancedRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            DB::beginTransaction();
            $validated['tenant_id'] = $this->tenantId();
            $rule = CollectionRule::create($validated);
            DB::commit();

            return ApiResponse::data($rule, 201, ['message' => 'Régua de cobrança criada']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('CollectionRule create failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao criar.', 500);
        }
    }

    public function update(UpdateCollectionRuleAdvancedRequest $request, CollectionRule $rule): JsonResponse
    {
        if ((int) $rule->tenant_id !== $this->tenantId()) {
            return ApiResponse::message('Régua não encontrada.', 404);
        }

        $validated = $request->validated();

        try {
            DB::beginTransaction();
            $rule->update($validated);
            DB::commit();

            return ApiResponse::data($rule->fresh(), 200, ['message' => 'Régua atualizada']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('CollectionRule update failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao atualizar.', 500);
        }
    }
}
