<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Stock\StoreInventoryRequest;
use App\Http\Requests\Stock\UpdateInventoryItemRequest;
use App\Http\Resources\InventoryResource;
use App\Models\Inventory;
use App\Models\InventoryItem;
use App\Models\Notification;
use App\Models\Product;
use App\Models\SystemAlert;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Services\StockService;
use App\Support\ApiResponse;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;

class InventoryController extends Controller
{
    use ResolvesCurrentTenant;

    public function __construct(protected StockService $stockService) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Inventory::class);
        $tenantId = $this->tenantId();
        $query = Inventory::where('tenant_id', $tenantId)
            ->with(['warehouse', 'creator:id,name']);

        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->warehouse_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $results = $query->latest()->paginate(min($request->integer('per_page', 20), 100));

        return ApiResponse::paginated($results, resourceClass: InventoryResource::class);
    }

    /** Inicia uma nova sessão de inventário */
    public function store(StoreInventoryRequest $request): JsonResponse
    {
        $this->authorize('create', Inventory::class);
        $tenantId = $this->tenantId();
        $validated = $request->validated();
        $warehouse = Warehouse::where('tenant_id', $tenantId)
            ->whereKey($validated['warehouse_id'])
            ->firstOrFail();

        try {
            return DB::transaction(function () use ($validated, $tenantId, $warehouse) {
                // Check inside transaction to prevent concurrent inventory creation
                $exists = Inventory::where('tenant_id', $tenantId)
                    ->where('warehouse_id', $validated['warehouse_id'])
                    ->where('status', Inventory::STATUS_OPEN)
                    ->lockForUpdate()
                    ->exists();

                if ($exists) {
                    abort(422, 'Já existe um inventário aberto para este depósito.');
                }

                $inventory = Inventory::create([
                    'tenant_id' => $tenantId,
                    'warehouse_id' => $validated['warehouse_id'],
                    'reference' => $validated['reference'] ?? null,
                    'status' => Inventory::STATUS_OPEN,
                    'created_by' => Auth::id(),
                ]);

                $stocks = WarehouseStock::whereBelongsTo($warehouse, 'warehouse')
                    ->whereHas('product', fn ($q) => $q->where('tenant_id', $tenantId))
                    ->get();

                foreach ($stocks as $stock) {
                    $inventory->items()->create([
                        'tenant_id' => $tenantId,
                        'product_id' => $stock->product_id,
                        'batch_id' => $stock->batch_id,
                        'expected_quantity' => $stock->quantity,
                    ]);
                }

                $inventory->load('items.product');

                return ApiResponse::data(new InventoryResource($inventory), 201, ['message' => 'Inventario iniciado com sucesso']);
            });
        } catch (HttpException $e) {
            return ApiResponse::message($e->getMessage(), $e->getStatusCode());
        } catch (\Exception $e) {
            Log::error('Inventory store failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao iniciar inventario', 500);
        }
    }

    public function show(Inventory $inventory): JsonResponse
    {
        $this->authorize('view', $inventory);
        $inventory->load(['warehouse', 'items.product', 'items.batch', 'items.productSerial', 'creator:id,name']);

        if ($inventory->status === Inventory::STATUS_OPEN) {
            $inventory->items->each(function ($item) {
                $item->makeHidden(['expected_quantity', 'discrepancy']);
            });
        }

        return ApiResponse::data(new InventoryResource($inventory));
    }

    /** Registra a contagem de um item (Blind Count) */
    public function updateItem(UpdateInventoryItemRequest $request, Inventory $inventory, InventoryItem $item): JsonResponse
    {
        $this->authorize('update', $inventory);
        $validated = $request->validated();

        try {
            return DB::transaction(function () use ($inventory, $item, $validated) {
                // Lock inventory to prevent status change during count update
                $lockedInventory = Inventory::lockForUpdate()->find($inventory->id);
                if ($lockedInventory->status !== Inventory::STATUS_OPEN) {
                    abort(422, 'Este inventário já foi processado ou cancelado');
                }

                $item->update([
                    'counted_quantity' => $validated['counted_quantity'],
                    'notes' => $validated['notes'] ?? $item->notes,
                ]);

                return ApiResponse::data($item, 200, ['message' => 'Contagem registrada']);
            });
        } catch (HttpException $e) {
            return ApiResponse::message($e->getMessage(), $e->getStatusCode());
        }
    }

    /** Finaliza o inventário e processa os ajustes automáticos */
    public function complete(Inventory $inventory): JsonResponse
    {
        $this->authorize('update', $inventory);

        try {
            return DB::transaction(function () use ($inventory) {
                // Lock and verify status INSIDE transaction to prevent TOCTOU
                $inventory = Inventory::lockForUpdate()->findOrFail($inventory->id);

                if ($inventory->status !== Inventory::STATUS_OPEN) {
                    abort(422, 'Status inválido para finalização');
                }

                if ($inventory->items()->whereNull('counted_quantity')->exists()) {
                    abort(422, 'Existem itens sem contagem registrada');
                }

                // Eager load items and their products to avoid N+1
                $inventory->load('items.product');
                $discrepancyDetails = [];
                foreach ($inventory->items as $item) {
                    $discrepancy = $item->discrepancy;

                    if ($discrepancy != 0) {
                        $product = $item->product;
                        if (! $product instanceof Product) {
                            abort(422, "Produto não encontrado para o item de inventário #{$item->id}");
                        }

                        $adjustQty = $discrepancy;
                        $this->stockService->manualAdjustment(
                            product: $product,
                            qty: $adjustQty,
                            warehouseId: $inventory->warehouse_id,
                            batchId: $item->batch_id,
                            serialId: $item->product_serial_id ?? null,
                            notes: "Ajuste automático via Inventário #{$inventory->id} ({$inventory->reference})",
                            user: Auth::user()
                        );

                        $item->update(['adjustment_quantity' => $discrepancy]);
                        $discrepancyDetails[] = $product->name.': esperado '.$item->expected_quantity.', contado '.$item->counted_quantity;
                    }
                }

                $inventory->update([
                    'status' => Inventory::STATUS_COMPLETED,
                    'completed_at' => now(),
                ]);

                if (count($discrepancyDetails) > 0) {
                    $warehouse = $inventory->warehouse;
                    $title = 'Diferença no inventário - '.($warehouse ? $warehouse->name : '#'.$inventory->warehouse_id);
                    SystemAlert::create([
                        'tenant_id' => $inventory->tenant_id,
                        'alert_type' => 'inventory_discrepancy_critical',
                        'severity' => 'critical',
                        'title' => $title,
                        'message' => implode('; ', array_slice($discrepancyDetails, 0, 5)),
                        'status' => 'active',
                        'alertable_type' => Inventory::class,
                        'alertable_id' => $inventory->id,
                    ]);
                    $estoquistas = User::where('tenant_id', $inventory->tenant_id)->role('estoquista')->pluck('id');
                    foreach ($estoquistas as $uid) {
                        Notification::notify($inventory->tenant_id, $uid, 'inventory_discrepancy_critical', $title, [
                            'message' => 'Inventário finalizado com diferenças. Revise em Estoque > Inventários.',
                            'link' => '/estoque/inventarios/'.$inventory->id,
                            'data' => ['inventory_id' => $inventory->id],
                        ]);
                    }
                }

                $inventory->load('items');

                return ApiResponse::data(new InventoryResource($inventory), 200, ['message' => 'Inventario finalizado e ajustes aplicados']);
            });
        } catch (\Exception $e) {
            Log::error('Inventory completion failed', [
                'inventory_id' => $inventory->id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::message('Erro ao finalizar inventario. Tente novamente.', 500);
        }
    }

    public function cancel(Inventory $inventory): JsonResponse
    {
        $this->authorize('delete', $inventory);

        return DB::transaction(function () use ($inventory) {
            $locked = Inventory::lockForUpdate()->findOrFail($inventory->id);

            if ($locked->status === Inventory::STATUS_COMPLETED) {
                return ApiResponse::message('Não é possivel cancelar um inventario já finalizado', 422);
            }

            if ($locked->status === Inventory::STATUS_CANCELLED) {
                return ApiResponse::message('Este inventario já está cancelado', 422);
            }

            $locked->update(['status' => Inventory::STATUS_CANCELLED]);

            return ApiResponse::message('Inventario cancelado');
        });
    }
}
