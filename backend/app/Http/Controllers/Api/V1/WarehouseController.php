<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Stock\StoreWarehouseRequest;
use App\Http\Requests\Stock\UpdateWarehouseRequest;
use App\Http\Resources\WarehouseResource;
use App\Models\Warehouse;
use App\Support\ApiResponse;
use App\Support\SearchSanitizer;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class WarehouseController extends Controller
{
    use ResolvesCurrentTenant;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Warehouse::class);
        $query = Warehouse::where('tenant_id', $this->tenantId())
            ->with(['user:id,name', 'vehicle:id,plate']);

        if ($request->filled('search')) {
            $search = SearchSanitizer::escapeLike($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->boolean('active_only', true)) {
            $query->where('is_active', true);
        }

        $warehouses = $query->orderBy('name')->paginate(min($request->integer('per_page', 50), 100));

        return ApiResponse::paginated($warehouses, resourceClass: WarehouseResource::class);
    }

    public function store(StoreWarehouseRequest $request): JsonResponse
    {
        $this->authorize('create', Warehouse::class);

        try {
            $tenantId = $this->tenantId();
            $validated = $request->validated();

            $warehouse = DB::transaction(fn () => Warehouse::create([...$validated, 'tenant_id' => $tenantId]));

            return ApiResponse::data(new WarehouseResource($warehouse), 201, ['message' => 'Armazem criado com sucesso.']);
        } catch (ValidationException $e) {
            return ApiResponse::message('Erro de validacao', 422, ['errors' => $e->errors()]);
        } catch (\Exception $e) {
            Log::error('WarehouseController::store failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao criar armazem.', 500);
        }
    }

    public function show(Warehouse $warehouse): JsonResponse
    {
        $this->authorize('view', $warehouse);
        if ($error = $this->ensureTenantOwnership($warehouse, 'Armazém')) {
            return $error;
        }

        return ApiResponse::data(new WarehouseResource($warehouse));
    }

    public function update(UpdateWarehouseRequest $request, Warehouse $warehouse): JsonResponse
    {
        $this->authorize('update', $warehouse);
        if ($error = $this->ensureTenantOwnership($warehouse, 'Armazém')) {
            return $error;
        }

        try {
            $validated = $request->validated();

            DB::transaction(fn () => $warehouse->update($validated));

            return ApiResponse::data(new WarehouseResource($warehouse->fresh()), 200, ['message' => 'Armazem atualizado com sucesso.']);
        } catch (ValidationException $e) {
            return ApiResponse::message('Erro de validacao', 422, ['errors' => $e->errors()]);
        } catch (\Exception $e) {
            Log::error('WarehouseController::update failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao atualizar armazem.', 500);
        }
    }

    public function destroy(Warehouse $warehouse): JsonResponse
    {
        $this->authorize('delete', $warehouse);
        if ($error = $this->ensureTenantOwnership($warehouse, 'Armazém')) {
            return $error;
        }

        try {
            if ($warehouse->stocks()->where('quantity', '>', 0)->exists()) {
                return ApiResponse::message('Não é possivel excluir um armazem que possui saldo de estoque.', 422);
            }

            DB::transaction(fn () => $warehouse->delete());

            return ApiResponse::message('Armazem excluido com sucesso.');
        } catch (\Exception $e) {
            Log::error('WarehouseController::destroy failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao excluir armazem.', 500);
        }
    }
}
