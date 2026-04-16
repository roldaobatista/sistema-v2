<?php

namespace App\Http\Controllers\Api\Stock;

use App\Http\Controllers\Controller;
use App\Http\Requests\Stock\StoreWarehouseRequest;
use App\Http\Requests\Stock\UpdateWarehouseRequest;
use App\Models\Warehouse;
use App\Support\ApiResponse;
use App\Support\SearchSanitizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WarehouseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Warehouse::query()
                ->when($request->filled('search'), fn ($q) => $q->where('name', 'like', SearchSanitizer::contains($request->search)))
                ->orderBy('name');

            return ApiResponse::paginated($query->paginate(min((int) $request->input('per_page', 20), 100)));
        } catch (\Exception $e) {
            Log::error('Warehouse index failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao listar armazéns.', 500);
        }
    }

    public function store(StoreWarehouseRequest $request): JsonResponse
    {
        try {
            $warehouse = DB::transaction(fn () => Warehouse::create($request->validated()));

            return ApiResponse::data($warehouse, 201, ['message' => 'Armazém criado com sucesso']);
        } catch (\Exception $e) {
            Log::error('Warehouse store failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao criar armazém.', 500);
        }
    }

    public function show(Warehouse $warehouse): JsonResponse
    {
        return ApiResponse::data($warehouse);
    }

    public function update(UpdateWarehouseRequest $request, Warehouse $warehouse): JsonResponse
    {
        try {
            DB::transaction(fn () => $warehouse->update($request->validated()));

            return ApiResponse::data($warehouse->fresh(), 200, ['message' => 'Armazém atualizado com sucesso']);
        } catch (\Exception $e) {
            Log::error('Warehouse update failed', ['error' => $e->getMessage(), 'id' => $warehouse->id]);

            return ApiResponse::message('Erro ao atualizar armazém.', 500);
        }
    }

    public function destroy(Warehouse $warehouse): JsonResponse
    {
        try {
            DB::transaction(fn () => $warehouse->delete());

            return ApiResponse::message('Armazém excluído com sucesso.');
        } catch (\Exception $e) {
            Log::error('Warehouse destroy failed', ['error' => $e->getMessage(), 'id' => $warehouse->id]);

            return ApiResponse::message('Erro ao excluir armazém.', 500);
        }
    }
}
