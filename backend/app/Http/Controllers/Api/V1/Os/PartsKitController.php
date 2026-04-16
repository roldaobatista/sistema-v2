<?php

namespace App\Http\Controllers\Api\V1\Os;

use App\Http\Controllers\Controller;
use App\Http\Requests\Os\StorePartsKitRequest;
use App\Http\Requests\Os\UpdatePartsKitRequest;
use App\Http\Resources\PartsKitResource;
use App\Models\PartsKit;
use App\Models\WorkOrder;
use App\Support\ApiResponse;
use App\Support\Decimal;
use App\Support\SearchSanitizer;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PartsKitController extends Controller
{
    use ResolvesCurrentTenant;

    /**
     * GET /parts-kits — list all kits with items count.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', PartsKit::class);
        $query = PartsKit::withCount('items')
            ->where('tenant_id', $this->tenantId());

        if ($request->filled('search')) {
            $safe = SearchSanitizer::contains($request->input('search'));
            $query->where('name', 'like', $safe);
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $perPage = min((int) $request->input('per_page', 20), 100);

        return ApiResponse::paginated($query->orderBy('name')->paginate($perPage), resourceClass: PartsKitResource::class);
    }

    /**
     * GET /parts-kits/{id} — single kit with items.
     */
    public function show(int $id): JsonResponse
    {
        $kit = PartsKit::with(['items.product', 'items.service'])
            ->where('tenant_id', $this->tenantId())
            ->findOrFail($id);
        $this->authorize('view', $kit);

        $total = $kit->items->sum(fn ($item) => $item->quantity * $item->unit_price);

        return ApiResponse::data([
            'data' => new PartsKitResource($kit),
            'total' => number_format((float) $total, 2, '.', ''),
        ]);
    }

    /**
     * POST /parts-kits — create a kit with items.
     */
    public function store(StorePartsKitRequest $request): JsonResponse
    {
        $this->authorize('create', PartsKit::class);
        $validated = $request->validated();

        DB::beginTransaction();

        try {
            $kit = PartsKit::create([
                'tenant_id' => $this->tenantId(),
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'is_active' => $validated['is_active'] ?? true,
            ]);

            foreach ($validated['items'] as $item) {
                $kit->items()->create($item);
            }

            DB::commit();

            return ApiResponse::data(new PartsKitResource($kit->load('items')), 201, ['message' => 'Kit criado com sucesso']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('PartsKit store failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao criar kit', 500);
        }
    }

    /**
     * PUT /parts-kits/{id} — update kit and replace items.
     */
    public function update(UpdatePartsKitRequest $request, int $id): JsonResponse
    {
        $kit = PartsKit::where('tenant_id', $this->tenantId())
            ->findOrFail($id);

        $validated = $request->validated();

        DB::beginTransaction();

        try {
            $kit->update([
                'name' => $validated['name'] ?? $kit->name,
                'description' => $validated['description'] ?? $kit->description,
                'is_active' => $validated['is_active'] ?? $kit->is_active,
            ]);

            if (isset($validated['items'])) {
                $kit->items()->delete();
                foreach ($validated['items'] as $item) {
                    $kit->items()->create($item);
                }
            }

            DB::commit();

            return ApiResponse::data(new PartsKitResource($kit->fresh()->load('items')), 200, ['message' => 'Kit atualizado com sucesso']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('PartsKit update failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao atualizar kit', 500);
        }
    }

    /**
     * DELETE /parts-kits/{id} — soft delete a kit and its items in one transaction.
     */
    public function destroy(int $id): JsonResponse
    {
        $kit = PartsKit::with('items')
            ->where('tenant_id', $this->tenantId())
            ->findOrFail($id);
        $this->authorize('delete', $kit);

        DB::transaction(function () use ($kit): void {
            $kit->items()->delete();
            $kit->delete();
        });

        return ApiResponse::message('Kit removido com sucesso');
    }

    /**
     * POST /work-orders/{work_order}/apply-kit/{parts_kit}
     * Applies all items from a kit to a work order.
     */
    public function applyToWorkOrder(int $workOrderId, int $partsKitId): JsonResponse
    {
        $tenantId = $this->tenantId();
        $kit = PartsKit::with('items')
            ->where('tenant_id', $tenantId)
            ->findOrFail($partsKitId);

        $workOrder = WorkOrder::where('tenant_id', $tenantId)
            ->findOrFail($workOrderId);

        DB::beginTransaction();

        try {
            foreach ($kit->items as $kitItem) {
                $workOrder->items()->create([
                    'tenant_id' => $tenantId,
                    'type' => $kitItem->type,
                    'reference_id' => $kitItem->reference_id,
                    'description' => $kitItem->description,
                    'quantity' => $kitItem->quantity,
                    'unit_price' => $kitItem->unit_price,
                    'discount' => 0,
                    'total' => bcmul(Decimal::string($kitItem->quantity), Decimal::string($kitItem->unit_price), 2),
                ]);
            }

            // Recalculate WO total (includes displacement and discount)
            $workOrder->recalculateTotal();

            DB::commit();

            return ApiResponse::data(
                $workOrder->fresh()->load('items'),
                200,
                ['message' => "Kit \"{$kit->name}\" aplicado com sucesso ({$kit->items->count()} itens)"]
            );
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('PartsKit apply failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao aplicar kit', 500);
        }
    }
}
