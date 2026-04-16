<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\StoreProductKitItemRequest;
use App\Models\Product;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProductKitController extends Controller
{
    public function index(Product $product): JsonResponse
    {
        try {
            if (! $product->is_kit) {
                return ApiResponse::message('Este produto não é um kit', 422);
            }

            return ApiResponse::data($product->kitItems()->with('child')->paginate(min((int) request()->input('per_page', 25), 100)));
        } catch (\Exception $e) {
            Log::error('ProductKit index failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao listar itens do kit', 500);
        }
    }

    public function store(StoreProductKitItemRequest $request, Product $product): JsonResponse
    {
        if (! $product->is_kit) {
            return ApiResponse::message('Este produto não é um kit', 422);
        }

        $validated = $request->validated();

        if ((int) $validated['child_id'] === (int) $product->id) {
            return ApiResponse::message('Um kit não pode conter a si mesmo', 422);
        }

        try {
            $kitItem = DB::transaction(function () use ($product, $validated) {
                return $product->kitItems()->updateOrCreate(
                    ['child_id' => $validated['child_id']],
                    ['quantity' => $validated['quantity']]
                );
            });

            return ApiResponse::data($kitItem->load('child'), 201, ['message' => 'Componente adicionado ao kit']);
        } catch (\Exception $e) {
            Log::error('ProductKit store failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao adicionar componente ao kit', 500);
        }
    }

    public function destroy(Product $product, int $childId): JsonResponse
    {
        try {
            $product->kitItems()->where('child_id', $childId)->delete();

            return ApiResponse::message('Componente removido do kit');
        } catch (\Exception $e) {
            Log::error('ProductKit destroy failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao remover componente do kit', 500);
        }
    }
}
