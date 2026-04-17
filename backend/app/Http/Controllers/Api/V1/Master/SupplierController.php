<?php

namespace App\Http\Controllers\Api\V1\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Supplier\StoreSupplierRequest;
use App\Http\Requests\Supplier\UpdateSupplierRequest;
use App\Http\Resources\SupplierResource;
use App\Models\AccountPayable;
use App\Models\Supplier;
use App\Support\ApiResponse;
use App\Support\SearchSanitizer;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SupplierController extends Controller
{
    use ResolvesCurrentTenant;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Supplier::class);
        $query = Supplier::query();

        if ($search = $request->get('search')) {
            $search = SearchSanitizer::escapeLike($search);
            $digitsOnlySearch = preg_replace('/\D+/', '', (string) $request->get('search'));
            $query->where(function ($q) use ($search, $digitsOnlySearch) {
                // `document` é encrypted (cast `encrypted`) — LIKE não funciona.
                // Wave 1B: busca exata via `document_hash` quando o termo é
                // CPF/CNPJ completo (11 ou 14 dígitos).
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('trade_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");

                if (is_string($digitsOnlySearch) && in_array(strlen($digitsOnlySearch), [11, 14], true)) {
                    $q->orWhere('document_hash', Supplier::hashSearchable('document', $digitsOnlySearch));
                }
            });
        }

        if ($request->has('type')) {
            $query->where('type', $request->get('type'));
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $suppliers = $query->orderBy('name')
            ->paginate(min((int) $request->get('per_page', 20), 100));

        return ApiResponse::paginated($suppliers, resourceClass: SupplierResource::class);
    }

    public function store(StoreSupplierRequest $request): JsonResponse
    {
        $this->authorize('create', Supplier::class);

        try {
            $validated = $request->validated();
            $supplier = DB::transaction(fn () => Supplier::create([
                ...$validated,
                'tenant_id' => $this->tenantId(),
            ]));

            return ApiResponse::data(new SupplierResource($supplier), 201);
        } catch (\Throwable $e) {
            Log::error('Supplier store failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao criar fornecedor.', 500);
        }
    }

    public function show(Supplier $supplier): JsonResponse
    {
        $this->authorize('view', $supplier);

        return ApiResponse::data(new SupplierResource($supplier));
    }

    public function update(UpdateSupplierRequest $request, Supplier $supplier): JsonResponse
    {
        $this->authorize('update', $supplier);
        $supplier->update($request->validated());

        return ApiResponse::data(new SupplierResource($supplier));
    }

    public function destroy(Supplier $supplier): JsonResponse
    {
        $this->authorize('delete', $supplier);
        $payablesCount = AccountPayable::where('supplier_id', $supplier->id)->count();

        if ($payablesCount > 0) {
            return ApiResponse::message(
                "Não é possivel excluir este fornecedor pois ele possui $payablesCount conta(s) a pagar vinculada(s).",
                409,
                ['dependencies' => ['accounts_payable' => $payablesCount]]
            );
        }

        try {
            DB::transaction(fn () => $supplier->delete());

            return ApiResponse::noContent();
        } catch (\Throwable $e) {
            Log::error('Supplier destroy failed', ['id' => $supplier->id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao excluir fornecedor.', 500);
        }
    }
}
