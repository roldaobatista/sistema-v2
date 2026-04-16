<?php

namespace App\Http\Controllers\Api\V1\Financial;

use App\Http\Controllers\Controller;
use App\Http\Requests\Financial\StoreAccountPayableCategoryRequest;
use App\Http\Requests\Financial\UpdateAccountPayableCategoryRequest;
use App\Models\AccountPayableCategory;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AccountPayableCategoryController extends Controller
{
    private function tenantId(Request $request): int
    {
        $user = $request->user();

        return (int) ($user->current_tenant_id ?? $user->tenant_id);
    }

    public function index(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        $categories = AccountPayableCategory::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('name')
            ->paginate(min((int) $request->input('per_page', 25), 100));

        return ApiResponse::paginated($categories);
    }

    public function store(StoreAccountPayableCategoryRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $tenantId = $this->tenantId($request);

        try {
            $category = DB::transaction(function () use ($validated, $tenantId) {
                return AccountPayableCategory::create([
                    ...$validated,
                    'tenant_id' => $tenantId,
                    'is_active' => true,
                ]);
            });

            return ApiResponse::data($category, 201);
        } catch (\Throwable $e) {
            Log::error('AP Category store failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao criar categoria', 500);
        }
    }

    public function update(UpdateAccountPayableCategoryRequest $request, AccountPayableCategory $category): JsonResponse
    {
        if ($category->tenant_id !== $this->tenantId($request)) {
            return ApiResponse::message('Acesso negado', 403);
        }

        $validated = $request->validated();
        $category->update($validated);

        return ApiResponse::data($category->fresh());
    }

    public function destroy(Request $request, AccountPayableCategory $category): JsonResponse
    {
        if ($category->tenant_id !== $this->tenantId($request)) {
            return ApiResponse::message('Acesso negado', 403);
        }

        if ($category->accountsPayable()->exists()) {
            return ApiResponse::message('Não é possível excluir categoria com contas a pagar vinculadas', 409);
        }

        try {
            DB::transaction(fn () => $category->delete());

            return ApiResponse::noContent();
        } catch (\Throwable $e) {
            Log::error('AP Category destroy failed', ['id' => $category->id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao excluir categoria', 500);
        }
    }
}
