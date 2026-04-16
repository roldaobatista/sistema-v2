<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Lookup\StoreLookupRequest;
use App\Http\Requests\Lookup\UpdateLookupRequest;
use App\Support\ApiResponse;
use App\Support\LookupValidationRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class LookupController extends Controller
{
    public function types(): JsonResponse
    {
        return response()->json(array_keys(LookupValidationRules::TYPE_MAP));
    }

    public function index(string $type): JsonResponse
    {
        $model = LookupValidationRules::resolveModel($type);
        if (! $model) {
            return ApiResponse::message('Tipo de cadastro inválido.', 404);
        }

        $items = $model::query()->ordered()->paginate(min((int) request()->input('per_page', 25), 100));

        return ApiResponse::data($items);
    }

    public function store(StoreLookupRequest $request, string $type): JsonResponse
    {
        $model = LookupValidationRules::resolveModel($type);
        if (! $model) {
            return ApiResponse::message('Tipo de cadastro inválido.', 404);
        }

        $validated = $request->validated();

        try {
            $item = $model::create($validated);

            return ApiResponse::data($item, 201);
        } catch (\Throwable $e) {
            Log::error("Lookup store error [{$type}]", ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao criar registro.', 500);
        }
    }

    public function update(UpdateLookupRequest $request, string $type, int $id): JsonResponse
    {
        $model = LookupValidationRules::resolveModel($type);
        if (! $model) {
            return ApiResponse::message('Tipo de cadastro inválido.', 404);
        }

        $item = $model::find($id);
        if (! $item) {
            return ApiResponse::message('Registro não encontrado.', 404);
        }

        $validated = $request->validated();

        try {
            $item->update($validated);

            return ApiResponse::data($item->fresh());
        } catch (\Throwable $e) {
            Log::error("Lookup update error [{$type}]", ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao atualizar registro.', 500);
        }
    }

    public function destroy(string $type, int $id): JsonResponse
    {
        $model = LookupValidationRules::resolveModel($type);
        if (! $model) {
            return ApiResponse::message('Tipo de cadastro inválido.', 404);
        }

        $item = $model::find($id);
        if (! $item) {
            return ApiResponse::message('Registro não encontrado.', 404);
        }

        try {
            $item->delete();

            return ApiResponse::message('Registro excluido com sucesso.');
        } catch (\Throwable $e) {
            Log::error("Lookup destroy error [{$type}]", ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao excluir registro.', 500);
        }
    }
}
