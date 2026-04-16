<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\StockMovementType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Stock\StoreStockMovementRequest;
use App\Http\Resources\StockMovementResource;
use App\Models\Product;
use App\Models\StockMovement;
use App\Services\StockService;
use App\Support\ApiResponse;
use App\Support\SearchSanitizer;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class StockController extends Controller
{
    use ResolvesCurrentTenant;

    public function __construct(
        private readonly StockService $stockService,
    ) {}

    public function movements(Request $request): JsonResponse
    {
        $this->authorize('viewAny', StockMovement::class);
        $tenantId = $this->tenantId();

        $query = StockMovement::where('tenant_id', $tenantId)
            ->with(['product:id,name,code,unit', 'createdByUser:id,name', 'workOrder:id,number,os_number'])
            ->orderBy('created_at', 'desc');

        if ($request->filled('search')) {
            $safe = SearchSanitizer::contains($request->search);
            $query->whereHas('product', function ($q) use ($safe) {
                $q->where('name', 'like', $safe)
                    ->orWhere('code', 'like', $safe);
            });
        }

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        if ($request->filled('work_order_id')) {
            $query->where('work_order_id', $request->work_order_id);
        }

        $movements = $query->paginate(min($request->integer('per_page', 25), 100));

        return ApiResponse::paginated($movements, resourceClass: StockMovementResource::class);
    }

    public function summary(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId();
        $baseQuery = Product::where('tenant_id', $tenantId)->where('is_active', true);

        if ($request->filled('category_id')) {
            $baseQuery->where('category_id', $request->category_id);
        }

        // Compute aggregate stats in the DB — avoids loading thousands of rows into memory
        $stats = (clone $baseQuery)->selectRaw(
            'COUNT(*) as total_products,'.
            ' COALESCE(SUM(stock_qty * cost_price), 0) as total_value,'.
            ' SUM(CASE WHEN stock_min > 0 AND stock_qty > 0 AND stock_qty <= stock_min THEN 1 ELSE 0 END) as low_stock_count,'.
            ' SUM(CASE WHEN stock_qty <= 0 THEN 1 ELSE 0 END) as out_of_stock_count'
        )->first();

        $products = (clone $baseQuery)->select([
            'id', 'code', 'name', 'unit', 'cost_price', 'sell_price',
            'stock_qty', 'stock_min', 'category_id',
        ])
            ->with('category:id,name')
            ->orderBy('name')
            ->paginate(min($request->integer('per_page', 100), 500));

        return ApiResponse::data([
            'products' => $products,
            'stats' => [
                'total_products' => (int) ($stats?->getAttribute('total_products') ?? 0),
                'total_value' => round((float) ($stats?->getAttribute('total_value') ?? 0), 2),
                'low_stock_count' => (int) ($stats?->getAttribute('low_stock_count') ?? 0),
                'out_of_stock_count' => (int) ($stats?->getAttribute('out_of_stock_count') ?? 0),
            ],
        ]);
    }

    public function store(StoreStockMovementRequest $request): JsonResponse
    {
        $this->authorize('create', StockMovement::class);
        $tenantId = $this->tenantId();
        $validated = $request->validated();

        try {
            $product = Product::where('tenant_id', $tenantId)->findOrFail($validated['product_id']);
            $type = StockMovementType::from($validated['type']);

            $movement = match ($type) {
                StockMovementType::Entry => $this->stockService->manualEntry(
                    product: $product,
                    qty: $validated['quantity'],
                    warehouseId: $validated['warehouse_id'],
                    batchId: $validated['batch_id'] ?? null,
                    serialId: $validated['product_serial_id'] ?? null,
                    unitCost: $validated['unit_cost'] ?? $product->cost_price,
                    notes: $validated['notes'] ?? null,
                    user: $request->user(),
                ),
                StockMovementType::Exit => $this->stockService->manualExit(
                    product: $product,
                    qty: $validated['quantity'],
                    warehouseId: $validated['warehouse_id'],
                    batchId: $validated['batch_id'] ?? null,
                    serialId: $validated['product_serial_id'] ?? null,
                    notes: $validated['notes'] ?? null,
                    user: $request->user(),
                ),
                StockMovementType::Return => $this->stockService->manualReturn(
                    product: $product,
                    qty: $validated['quantity'],
                    warehouseId: $validated['warehouse_id'],
                    batchId: $validated['batch_id'] ?? null,
                    serialId: $validated['product_serial_id'] ?? null,
                    notes: $validated['notes'] ?? null,
                    user: $request->user(),
                ),
                StockMovementType::Reserve => $this->stockService->manualReserve(
                    product: $product,
                    qty: $validated['quantity'],
                    warehouseId: $validated['warehouse_id'],
                    batchId: $validated['batch_id'] ?? null,
                    serialId: $validated['product_serial_id'] ?? null,
                    notes: $validated['notes'] ?? null,
                    user: $request->user(),
                ),
                StockMovementType::Adjustment => $this->stockService->manualAdjustment(
                    product: $product,
                    qty: $validated['quantity'],
                    warehouseId: $validated['warehouse_id'],
                    batchId: $validated['batch_id'] ?? null,
                    serialId: $validated['product_serial_id'] ?? null,
                    notes: $validated['notes'] ?? null,
                    user: $request->user(),
                ),
                default => abort(422, 'Tipo de movimentação inválido para entrada manual.'),
            };

            $movement->load(['product:id,name,code,stock_qty', 'createdByUser:id,name']);

            return ApiResponse::data(new StockMovementResource($movement), 201, ['message' => 'Movimentacao registrada com sucesso.']);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('StockController::store failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao registrar movimentacao.', 500);
        }
    }

    public function lowStockAlerts(): JsonResponse
    {
        try {
            $tenantId = $this->tenantId();
            $products = Product::where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->where('stock_min', '>', 0)
                ->whereColumn('stock_qty', '<=', 'stock_min')
                ->select(['id', 'code', 'name', 'unit', 'stock_qty', 'stock_min', 'cost_price', 'category_id'])
                ->with('category:id,name')
                ->orderByRaw('(stock_min - stock_qty) DESC')
                ->get();

            return ApiResponse::data($products, 200, ['total' => $products->count()]);
        } catch (\Throwable $e) {
            Log::error('StockController::lowStockAlerts failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao buscar alertas de estoque baixo.', 500);
        }
    }
}
