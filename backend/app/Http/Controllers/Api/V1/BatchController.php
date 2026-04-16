<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Stock\StoreBatchRequest;
use App\Http\Requests\Stock\UpdateBatchRequest;
use App\Http\Resources\BatchResource;
use App\Models\Batch;
use App\Support\ApiResponse;
use App\Support\SearchSanitizer;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BatchController extends Controller
{
    use ResolvesCurrentTenant;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Batch::class);

        $tenantId = $this->tenantId();
        $query = Batch::where('tenant_id', $tenantId)
            ->with('product');

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        if ($request->filled('search')) {
            $query->where('code', 'like', SearchSanitizer::contains($request->search));
        }

        if ($request->boolean('active_only', true)) {
            $query->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>=', now());
            });
        }

        $batches = $query->latest()->paginate(min($request->integer('per_page', 50), 100));

        return ApiResponse::paginated($batches, resourceClass: BatchResource::class);
    }

    public function store(StoreBatchRequest $request): JsonResponse
    {
        $this->authorize('create', Batch::class);

        $tenantId = $this->tenantId();
        $validated = $request->validated();
        $payload = [
            'tenant_id' => $tenantId,
            'product_id' => $validated['product_id'],
            'code' => $validated['batch_number'],
            'expires_at' => $validated['expires_at'] ?? null,
            'cost_price' => isset($validated['initial_quantity']) ? (float) $validated['initial_quantity'] : 0,
        ];

        try {
            DB::beginTransaction();
            $batch = Batch::create($payload);
            DB::commit();

            $batch->load('product');

            return ApiResponse::data(new BatchResource($batch), 201, ['message' => 'Lote criado com sucesso']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Batch creation failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao criar lote', 500);
        }
    }

    public function show(Batch $batch): JsonResponse
    {
        $this->authorize('view', $batch);
        $batch->load(['product', 'stocks.warehouse']);

        return ApiResponse::data(new BatchResource($batch));
    }

    public function update(UpdateBatchRequest $request, Batch $batch): JsonResponse
    {
        $this->authorize('update', $batch);

        $validated = $request->validated();
        $payload = [
            'code' => $validated['batch_number'],
            'expires_at' => $validated['expires_at'] ?? null,
        ];

        try {
            $batch->update($payload);
            $batch->load('product');

            return ApiResponse::data(new BatchResource($batch), 200, ['message' => 'Lote atualizado com sucesso']);
        } catch (\Exception $e) {
            Log::error('Batch update failed', ['id' => $batch->id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao atualizar lote', 500);
        }
    }

    public function destroy(Batch $batch): JsonResponse
    {
        $this->authorize('delete', $batch);

        if ($batch->stocks()->where('quantity', '>', 0)->exists()) {
            return ApiResponse::message('Não é possivel excluir um lote com estoque ativo', 422);
        }

        try {
            $batch->delete();

            return ApiResponse::noContent();
        } catch (\Exception $e) {
            Log::error('Batch deletion failed', ['id' => $batch->id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao excluir lote', 500);
        }
    }
}
