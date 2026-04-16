<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Financial\StoreReconciliationRuleRequest;
use App\Http\Requests\Financial\TestReconciliationRuleRequest;
use App\Http\Requests\Financial\UpdateReconciliationRuleRequest;
use App\Http\Resources\ReconciliationRuleResource;
use App\Models\BankStatementEntry;
use App\Models\ReconciliationRule;
use App\Support\ApiResponse;
use App\Support\SearchSanitizer;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReconciliationRuleController extends Controller
{
    use ResolvesCurrentTenant;

    public function index(Request $request): JsonResponse
    {
        try {
            $tenantId = $this->tenantId();

            $query = ReconciliationRule::where('tenant_id', $tenantId)
                ->with(['customer:id,name', 'supplier:id,name']);

            if ($request->filled('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            if ($request->filled('action')) {
                $query->where('action', $request->input('action'));
            }

            if ($request->filled('search')) {
                $safe = SearchSanitizer::contains($request->input('search'));
                $query->where(function ($q) use ($safe) {
                    $q->where('name', 'like', $safe)
                        ->orWhere('match_value', 'like', $safe);
                });
            }

            $rules = $query->orderBy('priority')->orderBy('name')->paginate(25);

            return ApiResponse::paginated($rules, resourceClass: ReconciliationRuleResource::class);
        } catch (\Throwable $e) {
            Log::error('ReconciliationRule index failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao listar regras.', 500);
        }
    }

    public function store(StoreReconciliationRuleRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            DB::beginTransaction();
            $tenantId = $this->tenantId();
            $validated['tenant_id'] = $tenantId;
            $validated['priority'] = $validated['priority'] ?? 50;
            $validated['is_active'] = $validated['is_active'] ?? true;

            $rule = ReconciliationRule::create($validated);
            DB::commit();

            return ApiResponse::data(
                new ReconciliationRuleResource($rule->load(['customer:id,name', 'supplier:id,name'])),
                201,
                ['success' => true, 'message' => 'Regra criada com sucesso']
            );
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('ReconciliationRule store failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao criar regra.', 500);
        }
    }

    public function show(Request $request, int $id): JsonResponse
    {
        try {
            $tenantId = $this->tenantId();
            $rule = ReconciliationRule::where('tenant_id', $tenantId)
                ->with(['customer:id,name', 'supplier:id,name'])
                ->findOrFail($id);

            return ApiResponse::data(new ReconciliationRuleResource($rule));
        } catch (\Throwable $e) {
            Log::error('ReconciliationRule show failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Regra não encontrada.', 404);
        }
    }

    public function update(UpdateReconciliationRuleRequest $request, int $id): JsonResponse
    {
        try {
            DB::beginTransaction();
            $tenantId = $this->tenantId();
            $rule = ReconciliationRule::where('tenant_id', $tenantId)->findOrFail($id);
            $rule->update($request->validated());
            DB::commit();

            return ApiResponse::data(
                new ReconciliationRuleResource($rule->load(['customer:id,name', 'supplier:id,name'])),
                200,
                ['message' => 'Regra atualizada']
            );
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('ReconciliationRule update failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao atualizar regra.', 500);
        }
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            DB::beginTransaction();
            $tenantId = $this->tenantId();
            $rule = ReconciliationRule::where('tenant_id', $tenantId)->findOrFail($id);
            $rule->delete();
            DB::commit();

            return ApiResponse::message('Regra excluída');
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('ReconciliationRule destroy failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao excluir regra.', 500);
        }
    }

    public function toggleActive(Request $request, int $id): JsonResponse
    {
        try {
            DB::beginTransaction();
            $tenantId = $this->tenantId();
            $rule = ReconciliationRule::where('tenant_id', $tenantId)->findOrFail($id);
            $rule->update(['is_active' => ! $rule->is_active]);
            DB::commit();

            $label = $rule->is_active ? 'ativada' : 'desativada';

            return ApiResponse::data(new ReconciliationRuleResource($rule), 200, ['message' => "Regra {$label}"]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('ReconciliationRule toggle failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao alternar regra.', 500);
        }
    }

    /**
     * Test a rule against existing pending entries without applying.
     */
    public function testRule(TestReconciliationRuleRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $tenantId = $this->tenantId();

            $tempRule = new ReconciliationRule($validated);

            $pending = BankStatementEntry::where('tenant_id', $tenantId)
                ->where('status', BankStatementEntry::STATUS_PENDING)
                ->limit(200)
                ->get();

            $matches = $pending->filter(fn ($entry) => $tempRule->matches($entry));

            return ApiResponse::data([
                'total_tested' => $pending->count(),
                'total_matched' => $matches->count(),
                'sample' => $matches->take(10)->map(fn ($e) => [
                    'id' => $e->id,
                    'date' => $e->date?->toDateString(),
                    'description' => $e->description,
                    'amount' => (float) $e->amount,
                    'type' => $e->type,
                ])->values(),
            ], 200, ['success' => true]);
        } catch (\Throwable $e) {
            Log::error('ReconciliationRule test failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao testar regra.', 500);
        }
    }
}
