<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Stock\ComparePurchaseQuotesRequest;
use App\Http\Requests\Stock\StartInventoryCountRequest;
use App\Http\Requests\Stock\StoreProductSerialRequest;
use App\Http\Requests\Stock\StorePurchaseOrderRequest;
use App\Http\Requests\Stock\SubmitCountRequest;
use App\Http\Requests\Stock\WarrantyLookupRequest;
use App\Models\Product;
use App\Models\ProductSerial;
use App\Models\PurchaseQuotation;
use App\Models\WarrantyTracking;
use App\Models\WorkOrder;
use App\Services\StockService;
use App\Support\ApiResponse;
use App\Support\SearchSanitizer;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StockAdvancedController extends Controller
{
    use ResolvesCurrentTenant;

    public function __construct(private StockService $stockService) {}

    // ─── Serial Numbers ─────────────────────────────────────────

    public function serialNumbers(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId();

        $query = ProductSerial::where('tenant_id', $tenantId)
            ->with('product:id,name,code', 'warehouse:id,name');

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->input('product_id'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('search')) {
            $query->where('serial_number', 'like', SearchSanitizer::contains($request->input('search')));
        }

        $serials = $query->orderByDesc('created_at')->paginate(
            min((int) $request->input('per_page', 20), 100)
        );

        return ApiResponse::paginated($serials);
    }

    public function storeSerialNumber(StoreProductSerialRequest $request): JsonResponse
    {
        $tenantId = $this->tenantId();
        $validated = $request->validated();
        $exists = ProductSerial::where('tenant_id', $tenantId)
            ->where('serial_number', $validated['serial_number'])
            ->exists();

        if ($exists) {
            return ApiResponse::message('Número de série já cadastrado.', 422);
        }

        try {
            $serial = ProductSerial::create([
                'tenant_id' => $tenantId,
                'product_id' => $validated['product_id'],
                'warehouse_id' => $validated['warehouse_id'] ?? null,
                'serial_number' => $validated['serial_number'],
                'status' => $validated['status'] ?? 'available',
            ]);

            return ApiResponse::data($serial->load('product:id,name,code', 'warehouse:id,name'), 201, ['message' => 'Número de série cadastrado com sucesso.']);
        } catch (\Throwable $e) {
            Log::error('storeSerialNumber failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao cadastrar número de série.', 500);
        }
    }

    // ─── Sugestões de Transferência entre Armazéns ─────────────

    public function suggestTransfers(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId();

        $warehouseStocks = DB::table('warehouse_stocks')
            ->join('warehouses', 'warehouse_stocks.warehouse_id', '=', 'warehouses.id')
            ->join('products', 'warehouse_stocks.product_id', '=', 'products.id')
            ->where('warehouses.tenant_id', $tenantId)
            ->where('warehouses.is_active', true)
            ->where('products.is_active', true)
            ->whereNotNull('products.min_repo_point')
            ->select(
                'warehouse_stocks.warehouse_id',
                'warehouse_stocks.product_id',
                'warehouse_stocks.quantity',
                'warehouses.name as warehouse_name',
                'products.name as product_name',
                'products.code as product_code',
                'products.min_repo_point',
                'products.max_stock',
            )
            ->get();

        $grouped = $warehouseStocks->groupBy('product_id');
        $suggestions = [];

        foreach ($grouped as $productId => $stocks) {
            if ($stocks->count() < 2) {
                continue;
            }

            $deficit = $stocks->filter(fn ($s) => $s->quantity <= $s->min_repo_point);
            $surplus = $stocks->filter(fn ($s) => $s->quantity > ($s->max_stock ?? $s->min_repo_point * 2) * 0.8);

            foreach ($deficit as $low) {
                $needed = ($low->min_repo_point * 1.5) - $low->quantity;
                if ($needed <= 0) {
                    continue;
                }

                foreach ($surplus as $high) {
                    $available = $high->quantity - $high->min_repo_point;
                    if ($available <= 0) {
                        continue;
                    }

                    $transferQty = min($needed, $available);
                    $suggestions[] = [
                        'product_id' => $productId,
                        'product_name' => $low->product_name,
                        'product_code' => $low->product_code,
                        'from_warehouse_id' => $high->warehouse_id,
                        'from_warehouse_name' => $high->warehouse_name,
                        'from_current_qty' => $high->quantity,
                        'to_warehouse_id' => $low->warehouse_id,
                        'to_warehouse_name' => $low->warehouse_name,
                        'to_current_qty' => $low->quantity,
                        'suggested_quantity' => round($transferQty, 2),
                        'reason' => "Estoque abaixo do ponto de reposição ({$low->min_repo_point})",
                    ];

                    $needed -= $transferQty;
                    if ($needed <= 0) {
                        break;
                    }
                }
            }
        }

        usort($suggestions, fn ($a, $b) => $b['suggested_quantity'] <=> $a['suggested_quantity']);

        return ApiResponse::data([
            'total_suggestions' => count($suggestions),
            'suggestions' => array_slice($suggestions, 0, 50),
        ]);
    }

    // ─── #16B Compra Automática por Reorder Point ───────────────

    public function autoReorder(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId();

        $belowReorder = DB::table('products')
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->whereNotNull('min_repo_point')
            ->whereRaw('stock_qty <= min_repo_point')
            ->get();

        $productIds = $belowReorder->pluck('id');
        $pendingProductIds = $productIds->isNotEmpty()
            ? DB::table('purchase_quotation_items')
                ->join('purchase_quotations', 'purchase_quotation_items.purchase_quotation_id', '=', 'purchase_quotations.id')
                ->where('purchase_quotations.tenant_id', $tenantId)
                ->where('purchase_quotations.status', 'pending')
                ->whereIn('purchase_quotation_items.product_id', $productIds)
                ->pluck('purchase_quotation_items.product_id')
                ->unique()
            : collect();

        $orders = [];
        foreach ($belowReorder as $product) {
            $orderQty = ($product->max_stock ?? ($product->min_repo_point * 2)) - $product->stock_qty;
            if ($orderQty <= 0) {
                continue;
            }
            if ($pendingProductIds->contains($product->id)) {
                continue;
            }

            $orders[] = [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'stock_qty' => $product->stock_qty,
                'min_repo_point' => $product->min_repo_point,
                'suggested_quantity' => $orderQty,
                'preferred_supplier_id' => $product->default_supplier_id ?? null,
            ];
        }

        return ApiResponse::data([
            'products_below_reorder' => count($orders),
            'suggestions' => $orders,
        ]);
    }

    public function createAutoReorderPO(StorePurchaseOrderRequest $request): JsonResponse
    {
        $tenantId = $this->tenantId();
        $request->validated();
        $grouped = collect($request->input('items'))->groupBy('supplier_id');
        $created = [];

        // Pre-load all referenced products to avoid N+1
        $allProductIds = collect($request->input('items'))->pluck('product_id')->unique();
        $productsMap = Product::where('tenant_id', $tenantId)->whereIn('id', $allProductIds)->pluck('cost_price', 'id');

        try {
            $created = DB::transaction(function () use ($tenantId, $request, $grouped, $productsMap) {
                $created = [];
                foreach ($grouped as $supplierId => $items) {
                    $pq = PurchaseQuotation::create([
                        'tenant_id' => $tenantId,
                        'supplier_id' => $supplierId,
                        'status' => 'pending',
                        'notes' => 'auto_reorder',
                        'created_by' => $request->user()->id,
                    ]);

                    $total = 0;
                    foreach ($items as $item) {
                        $unitPrice = $productsMap->get($item['product_id'], 0) ?? 0;
                        $itemTotal = round((float) $item['quantity'] * (float) $unitPrice, 2);
                        DB::table('purchase_quotation_items')->insert([
                            'tenant_id' => $tenantId,
                            'purchase_quotation_id' => $pq->id,
                            'product_id' => $item['product_id'],
                            'quantity' => $item['quantity'],
                            'unit_price' => $unitPrice,
                            'total' => $itemTotal,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                        $total += $unitPrice * $item['quantity'];
                    }

                    $pq->update(['total' => $total, 'total_amount' => $total]);
                    $created[] = $pq->id;
                }

                return $created;
            });
        } catch (\Throwable $e) {
            Log::error('StockAdvanced createAutoReorderPO failed', ['exception' => $e]);

            return ApiResponse::message('Erro interno do servidor.', 500);
        }

        return ApiResponse::data([
            'purchase_quotation_ids' => $created,
        ], 201, ['message' => count($created).' purchase quotations created']);
    }

    // ─── #17 Baixa Automática de Estoque na OS ─────────────────

    public function autoDeductFromWO(Request $request, WorkOrder $workOrder): JsonResponse
    {
        $tenantId = $this->tenantId();

        if ((int) $workOrder->tenant_id !== $tenantId) {
            abort(404);
        }

        $parts = DB::table('work_order_parts')
            ->where('work_order_id', $workOrder->id)
            ->where('deducted', false)
            ->get();

        if ($parts->isEmpty()) {
            return ApiResponse::message('Nenhuma peça para deduzir.');
        }

        $deducted = [];
        $errors = [];

        $productIds = $parts->pluck('product_id')->unique();
        $productsById = Product::whereIn('id', $productIds)->get()->keyBy('id');

        DB::beginTransaction();

        try {
            foreach ($parts as $part) {
                try {
                    $product = $productsById->get($part->product_id);
                    if (! $product) {
                        $errors[] = "Product #{$part->product_id}: não encontrado";
                        continue;
                    }
                    $this->stockService->deduct(
                        product: $product,
                        qty: $part->quantity,
                        workOrder: $workOrder,
                        warehouseId: $part->warehouse_id ?? $workOrder->warehouse_id,
                    );

                    DB::table('work_order_parts')
                        ->where('id', $part->id)
                        ->update(['deducted' => true, 'deducted_at' => now()]);

                    $deducted[] = $part->product_id;
                } catch (\Throwable $e) {
                    $errors[] = "Product #{$part->product_id}: {$e->getMessage()}";
                }
            }

            if (! empty($errors)) {
                DB::rollBack();

                return ApiResponse::message('Erro ao processar deduções. Operação cancelada.', 422, ['errors' => $errors]);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('autoDeductFromWO transaction failed', ['wo_id' => $workOrder->id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao deduzir peças da OS.', 500);
        }

        return ApiResponse::data([
            'deducted' => count($deducted),
            'errors' => $errors,
        ]);
    }

    // ─── #18 Inventário Cíclico com QR Code ────────────────────

    public function startCyclicCount(StartInventoryCountRequest $request): JsonResponse
    {
        $tenantId = $this->tenantId();
        $request->validated();
        $query = Product::where('tenant_id', $tenantId)->where('is_active', true);

        if ($request->filled('product_ids')) {
            $query->whereIn('id', $request->input('product_ids'));
        }
        if ($request->filled('category')) {
            $query->where('category_id', $request->input('category'));
        }

        $products = $query->get(['id', 'name', 'code', 'stock_qty']);

        try {
            $result = DB::transaction(function () use ($tenantId, $request, $products) {
                $countSession = DB::table('inventory_counts')->insertGetId([
                    'tenant_id' => $tenantId,
                    'warehouse_id' => $request->input('warehouse_id'),
                    'status' => 'in_progress',
                    'started_by' => $request->user()->id,
                    'items_count' => $products->count(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                foreach ($products as $product) {
                    DB::table('inventory_count_items')->insert([
                        'inventory_count_id' => $countSession,
                        'product_id' => $product->id,
                        'system_quantity' => $product->stock_qty,
                        'counted_quantity' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                return $countSession;
            });

            return ApiResponse::data([
                'count_id' => $result,
                'items' => $products->count(),
            ], 201, ['message' => 'Cyclic inventory count started']);
        } catch (\Throwable $e) {
            Log::error('startCyclicCount failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao iniciar contagem cíclica.', 500);
        }
    }

    public function submitCount(SubmitCountRequest $request, int $countId): JsonResponse
    {
        $tenantId = $this->tenantId();
        $exists = DB::table('inventory_counts')->where('id', $countId)->where('tenant_id', $tenantId)->exists();
        if (! $exists) {
            abort(404, 'Contagem de inventário não encontrada.');
        }
        $request->validated();
        $result = DB::transaction(function () use ($countId, $request) {
            foreach ($request->input('items') as $item) {
                DB::table('inventory_count_items')
                    ->where('inventory_count_id', $countId)
                    ->where('product_id', $item['product_id'])
                    ->update([
                        'counted_quantity' => $item['counted_quantity'],
                        'counted_by' => $request->user()->id,
                        'counted_at' => now(),
                        'updated_at' => now(),
                    ]);
            }

            $pending = DB::table('inventory_count_items')
                ->where('inventory_count_id', $countId)
                ->whereNull('counted_quantity')
                ->count();

            if ($pending === 0) {
                DB::table('inventory_counts')
                    ->where('id', $countId)
                    ->update(['status' => 'completed', 'completed_at' => now(), 'updated_at' => now()]);
            }

            $divergences = DB::table('inventory_count_items')
                ->where('inventory_count_id', $countId)
                ->whereNotNull('counted_quantity')
                ->whereRaw('counted_quantity != system_quantity')
                ->get();

            return [
                'pending_items' => $pending,
                'divergences' => $divergences->count(),
                'divergence_details' => $divergences,
            ];
        });

        return ApiResponse::data($result);
    }

    // ─── #19B Rastreabilidade de Garantia ───────────────────────

    public function warrantyTracking(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId();
        $query = WarrantyTracking::where('tenant_id', $tenantId)
            ->with(['workOrder', 'product', 'equipment', 'customer']);

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->input('customer_id'));
        }
        if ($request->filled('equipment_id')) {
            $query->where('equipment_id', $request->input('equipment_id'));
        }
        if ($request->filled('status')) {
            $status = $request->input('status');
            if ($status === 'active') {
                $query->where('warranty_end_at', '>=', now()->toDateString());
            } elseif ($status === 'expired') {
                $query->where('warranty_end_at', '<', now()->toDateString());
            } elseif ($status === 'expiring') {
                $query->where('warranty_end_at', '>=', now()->toDateString())
                    ->where('warranty_end_at', '<=', now()->addDays(30)->toDateString());
            }
        }

        $warranties = $query->orderBy('warranty_end_at')->paginate(20);

        return ApiResponse::paginated($warranties);
    }

    public function warrantyLookup(WarrantyLookupRequest $request): JsonResponse
    {
        $request->validated();
        $tenantId = $this->tenantId();
        $query = WarrantyTracking::where('tenant_id', $tenantId);

        if ($request->filled('serial_number')) {
            $query->where('serial_number', $request->input('serial_number'));
        }
        if ($request->filled('work_order_id')) {
            $query->where('work_order_id', $request->input('work_order_id'));
        }
        if ($request->filled('equipment_id')) {
            $query->where('equipment_id', $request->input('equipment_id'));
        }

        $warranties = $query->with(['workOrder', 'product', 'equipment'])->get();

        return ApiResponse::data([
            'total' => $warranties->count(),
            'active' => $warranties->filter(fn ($w) => $w->warranty_end_at && $w->warranty_end_at->isFuture())->count(),
            'expired' => $warranties->filter(fn ($w) => $w->warranty_end_at && $w->warranty_end_at->isPast())->count(),
            'warranties' => $warranties,
        ]);
    }

    // ─── #20B Comparador Automático de Cotações ─────────────────

    public function comparePurchaseQuotes(ComparePurchaseQuotesRequest $request): JsonResponse
    {
        $request->validated();
        $tenantId = $this->tenantId();
        $quotes = DB::table('purchase_quotes')
            ->where('tenant_id', $tenantId)
            ->whereIn('id', $request->input('purchase_quote_ids'))
            ->get();

        $comparison = [];
        foreach ($quotes as $quote) {
            $items = DB::table('purchase_quote_items')
                ->where('purchase_quote_id', $quote->id)
                ->get();

            $comparison[] = [
                'quote_id' => $quote->id,
                'supplier' => $quote->supplier_name ?? "Supplier #{$quote->supplier_id}",
                'total' => $items->sum('total'),
                'delivery_days' => $quote->delivery_days ?? null,
                'payment_terms' => $quote->payment_terms ?? null,
                'items_count' => $items->count(),
            ];
        }

        $minTotal = collect($comparison)->min('total');
        $scored = collect($comparison)->map(function ($q) use ($minTotal) {
            $priceScore = $minTotal > 0 ? round(($minTotal / $q['total']) * 100, 1) : 100;
            $deliveryScore = isset($q['delivery_days']) ? max(0, 100 - $q['delivery_days'] * 5) : 50;
            $q['score'] = round($priceScore * 0.7 + $deliveryScore * 0.3, 1);

            return $q;
        })->sortByDesc('score')->values();

        return ApiResponse::data([
            'comparison' => $scored,
            'recommended' => $scored->first(),
        ]);
    }

    // ─── #21B Análise de Estoque de Giro Lento ─────────────────

    public function slowMovingAnalysis(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId();
        $days = $request->input('days', 90);
        $threshold = Carbon::now()->subDays($days);

        $slowMoving = DB::table('products')
            ->where('products.tenant_id', $tenantId)
            ->where('products.is_active', true)
            ->where('products.stock_qty', '>', 0)
            ->leftJoin('stock_movements', function ($join) use ($threshold, $tenantId) {
                $join->on('products.id', '=', 'stock_movements.product_id')
                    ->where('stock_movements.tenant_id', '=', $tenantId)
                    ->where('stock_movements.created_at', '>=', $threshold)
                    ->whereIn('stock_movements.type', ['exit']);
            })
            ->selectRaw('products.id, products.name, products.code, products.stock_qty,
                          products.cost_price, (products.stock_qty * COALESCE(products.cost_price, 0)) as capital_invested,
                          COUNT(stock_movements.id) as movement_count,
                          MAX(stock_movements.created_at) as last_movement')
            ->groupBy('products.id', 'products.name', 'products.code', 'products.stock_qty', 'products.cost_price')
            ->having('movement_count', '=', 0)
            ->orderByDesc('capital_invested')
            ->limit(50)
            ->get();

        return ApiResponse::data([
            'period_days' => $days,
            'slow_moving_count' => $slowMoving->count(),
            'total_capital_locked' => round($slowMoving->sum('capital_invested'), 2),
            'products' => $slowMoving,
        ]);
    }
}
