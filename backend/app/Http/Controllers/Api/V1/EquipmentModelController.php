<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Equipment\StoreEquipmentModelRequest;
use App\Http\Requests\Equipment\SyncEquipmentModelProductsRequest;
use App\Http\Requests\Equipment\UpdateEquipmentModelRequest;
use App\Http\Resources\EquipmentModelResource;
use App\Models\EquipmentModel;
use App\Models\Product;
use App\Support\ApiResponse;
use App\Support\SearchSanitizer;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EquipmentModelController extends Controller
{
    use ResolvesCurrentTenant;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', EquipmentModel::class);
        $query = EquipmentModel::query()
            ->withCount('products');

        if ($search = $request->get('search')) {
            $search = SearchSanitizer::escapeLike($search);
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('brand', 'like', "%{$search}%");
            });
        }
        if ($category = $request->get('category')) {
            $query->where('category', $category);
        }

        $list = $query->orderBy('name')->paginate(min((int) $request->get('per_page', 25), 100));

        return ApiResponse::paginated($list, resourceClass: EquipmentModelResource::class);
    }

    public function show(Request $request, EquipmentModel $equipmentModel): JsonResponse
    {
        $this->authorize('view', $equipmentModel);
        $equipmentModel->load('products:id,name,code');

        return ApiResponse::data(new EquipmentModelResource($equipmentModel));
    }

    public function store(StoreEquipmentModelRequest $request): JsonResponse
    {
        $this->authorize('create', EquipmentModel::class);
        $validated = $request->validated();
        $validated['tenant_id'] = $this->tenantId();

        try {
            $model = DB::transaction(fn () => EquipmentModel::create($validated));

            return ApiResponse::data(['equipment_model' => new EquipmentModelResource($model)], 201);
        } catch (\Throwable $e) {
            Log::error('EquipmentModel store failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao criar modelo de equipamento', 500);
        }
    }

    public function update(UpdateEquipmentModelRequest $request, EquipmentModel $equipmentModel): JsonResponse
    {
        $this->authorize('update', $equipmentModel);
        $validated = $request->validated();

        try {
            DB::transaction(fn () => $equipmentModel->update($validated));

            return ApiResponse::data(['equipment_model' => new EquipmentModelResource($equipmentModel->fresh())]);
        } catch (\Throwable $e) {
            Log::error('EquipmentModel update failed', ['id' => $equipmentModel->id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao atualizar modelo de equipamento', 500);
        }
    }

    public function destroy(Request $request, EquipmentModel $equipmentModel): JsonResponse
    {
        $this->authorize('delete', $equipmentModel);
        $count = $equipmentModel->equipments()->count();
        if ($count > 0) {
            return ApiResponse::message("Não é possivel excluir: {$count} equipamento(s) vinculado(s) a este modelo.", 422);
        }

        try {
            DB::transaction(fn () => $equipmentModel->delete());

            return ApiResponse::message('Modelo de equipamento excluido com sucesso');
        } catch (\Throwable $e) {
            Log::error('EquipmentModel destroy failed', ['id' => $equipmentModel->id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao excluir modelo de equipamento', 500);
        }
    }

    public function syncProducts(SyncEquipmentModelProductsRequest $request, EquipmentModel $equipmentModel): JsonResponse
    {
        $this->authorize('update', $equipmentModel);
        $tenantId = $this->tenantId();
        $validated = $request->validated();
        $productIds = collect($validated['product_ids'])->unique()->values()->all();
        $allowed = Product::where('tenant_id', $tenantId)->whereIn('id', $productIds)->pluck('id')->all();
        $equipmentModel->products()->sync($allowed);
        $equipmentModel->load('products:id,name,code');

        return ApiResponse::data(['equipment_model' => new EquipmentModelResource($equipmentModel)]);
    }
}
