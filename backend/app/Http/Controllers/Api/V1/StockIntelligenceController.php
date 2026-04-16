<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Stock\StockAutoRequestRequest;
use App\Http\Requests\Stock\StockIntelligenceMonthsRequest;
use App\Models\MaterialRequest;
use App\Models\MaterialRequestItem;
use App\Models\StockMovement;
use App\Support\ApiResponse;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StockIntelligenceController extends Controller
{
    use ResolvesCurrentTenant;

    public function abcCurve(StockIntelligenceMonthsRequest $request): JsonResponse
    {
        try {
            $months = (int) ($request->validated('months') ?? 12);
            $since = now()->subMonths($months);

            $tenantId = $this->tenantId();
            $items = DB::table('stock_movements')
                ->join('products', 'stock_movements.product_id', '=', 'products.id')
                ->where('stock_movements.type', 'exit')
                ->where('stock_movements.created_at', '>=', $since)
                ->where('stock_movements.tenant_id', $tenantId)
                ->where('products.tenant_id', $tenantId)
                ->select(
                    'products.id',
                    'products.name',
                    'products.code',
                    'products.unit',
                    DB::raw('SUM(ABS(stock_movements.quantity)) as total_qty'),
                    DB::raw('SUM(ABS(stock_movements.quantity) * stock_movements.unit_cost) as total_value'),
                )
                ->groupBy('products.id', 'products.name', 'products.code', 'products.unit')
                ->orderByDesc('total_value')
                ->get();

            $grandTotal = $items->sum('total_value');
            $cumulative = 0;
            $classified = $items->map(function ($item) use ($grandTotal, &$cumulative) {
                $pct = $grandTotal > 0 ? ($item->total_value / $grandTotal) * 100 : 0;
                $cumulative += $pct;

                $class = match (true) {
                    $cumulative <= 80 => 'A',
                    $cumulative <= 95 => 'B',
                    default => 'C',
                };

                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'code' => $item->code,
                    'unit' => $item->unit,
                    'total_qty' => round($item->total_qty, 2),
                    'total_value' => round($item->total_value, 2),
                    'percentage' => round($pct, 2),
                    'cumulative' => round($cumulative, 2),
                    'class' => $class,
                ];
            });

            $summary = [
                'A' => $classified->where('class', 'A')->count(),
                'B' => $classified->where('class', 'B')->count(),
                'C' => $classified->where('class', 'C')->count(),
                'total_value' => round($grandTotal, 2),
                'period_months' => $months,
            ];

            return ApiResponse::data($classified->values(), 200, ['summary' => $summary]);
        } catch (\Exception $e) {
            Log::error('StockIntelligence abcCurve failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao calcular curva ABC.', 500);
        }
    }

    public function turnover(StockIntelligenceMonthsRequest $request): JsonResponse
    {
        try {
            $months = (int) ($request->validated('months') ?? 12);
            $since = now()->subMonths($months);
            $tenantId = $this->tenantId();

            $items = DB::table('products')
                ->leftJoin('stock_movements', function ($join) use ($since, $tenantId) {
                    $join->on('stock_movements.product_id', '=', 'products.id')
                        ->where('stock_movements.tenant_id', $tenantId)
                        ->where('stock_movements.type', 'exit')
                        ->where('stock_movements.created_at', '>=', $since);
                })
                ->where('products.tenant_id', $tenantId)
                ->where('products.is_active', true)
                ->select(
                    'products.id',
                    'products.name',
                    'products.code',
                    'products.unit',
                    'products.stock_qty',
                    DB::raw('COALESCE(SUM(ABS(stock_movements.quantity)), 0) as total_exits'),
                )
                ->groupBy('products.id', 'products.name', 'products.code', 'products.unit', 'products.stock_qty')
                ->orderByDesc('total_exits')
                ->get();

            $result = $items->map(function ($item) use ($months) {
                $stockQty = (float) $item->stock_qty;
                $exits = (float) $item->total_exits;

                $turnoverRate = $stockQty > 0 ? round($exits / $stockQty, 2) : ($exits > 0 ? 999 : 0);

                $dailyExits = $months > 0 ? $exits / ($months * 30) : 0;
                $coverageDays = $dailyExits > 0 ? round($stockQty / $dailyExits) : ($stockQty > 0 ? 999 : 0);

                $classification = match (true) {
                    $turnoverRate >= 6 => 'fast',
                    $turnoverRate >= 2 => 'normal',
                    $turnoverRate > 0 => 'slow',
                    default => 'stale',
                };

                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'code' => $item->code,
                    'unit' => $item->unit,
                    'stock_qty' => round($stockQty, 2),
                    'total_exits' => round($exits, 2),
                    'turnover_rate' => $turnoverRate,
                    'coverage_days' => min($coverageDays, 999),
                    'classification' => $classification,
                ];
            });

            $summary = [
                'fast' => $result->where('classification', 'fast')->count(),
                'normal' => $result->where('classification', 'normal')->count(),
                'slow' => $result->where('classification', 'slow')->count(),
                'stale' => $result->where('classification', 'stale')->count(),
                'period_months' => $months,
            ];

            return ApiResponse::data($result->values(), 200, ['summary' => $summary]);
        } catch (\Exception $e) {
            Log::error('StockIntelligence turnover failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao calcular giro de estoque.', 500);
        }
    }

    public function averageCost(Request $request): JsonResponse
    {
        try {
            $tenantId = $this->tenantId();

            $items = DB::table('products')
                ->leftJoin('stock_movements', function ($join) use ($tenantId) {
                    $join->on('stock_movements.product_id', '=', 'products.id')
                        ->where('stock_movements.tenant_id', $tenantId)
                        ->where('stock_movements.type', 'entry')
                        ->where('stock_movements.unit_cost', '>', 0);
                })
                ->where('products.tenant_id', $tenantId)
                ->where('products.is_active', true)
                ->select(
                    'products.id',
                    'products.name',
                    'products.code',
                    'products.unit',
                    'products.stock_qty',
                    'products.cost_price',
                    DB::raw('COALESCE(SUM(ABS(stock_movements.quantity) * stock_movements.unit_cost), 0) as total_cost'),
                    DB::raw('COALESCE(SUM(ABS(stock_movements.quantity)), 0) as total_qty_entered'),
                )
                ->groupBy('products.id', 'products.name', 'products.code', 'products.unit', 'products.stock_qty', 'products.cost_price')
                ->orderBy('products.name')
                ->get();

            $result = $items->map(function ($item) {
                $avgCost = $item->total_qty_entered > 0
                    ? (float) bcdiv((string) $item->total_cost, (string) $item->total_qty_entered, 4)
                    : (float) $item->cost_price;
                $stockValue = (float) bcmul((string) $avgCost, (string) $item->stock_qty, 2);

                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'code' => $item->code,
                    'unit' => $item->unit,
                    'stock_qty' => round((float) $item->stock_qty, 2),
                    'current_cost' => round((float) $item->cost_price, 4),
                    'average_cost' => $avgCost,
                    'total_entries' => round((float) $item->total_qty_entered, 2),
                    'stock_value' => $stockValue,
                ];
            });

            $totalValue = $result->sum('stock_value');

            return ApiResponse::data($result->values(), 200, ['total_value' => round($totalValue, 2)]);
        } catch (\Exception $e) {
            Log::error('StockIntelligence averageCost failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao calcular custo médio.', 500);
        }
    }

    public function reorderPoints(StockIntelligenceMonthsRequest $request): JsonResponse
    {
        try {
            $tenantId = $this->tenantId();
            $months = (int) ($request->validated('months') ?? 3);
            $since = now()->subMonths($months);

            $items = DB::table('products')
                ->leftJoin('stock_movements', function ($join) use ($since, $tenantId) {
                    $join->on('stock_movements.product_id', '=', 'products.id')
                        ->where('stock_movements.tenant_id', $tenantId)
                        ->where('stock_movements.type', 'exit')
                        ->where('stock_movements.created_at', '>=', $since);
                })
                ->where('products.tenant_id', $tenantId)
                ->where('products.is_active', true)
                ->where('products.stock_min', '>', 0)
                ->select(
                    'products.id',
                    'products.name',
                    'products.code',
                    'products.unit',
                    'products.stock_qty',
                    'products.stock_min',
                    'products.cost_price',
                    DB::raw('COALESCE(SUM(ABS(stock_movements.quantity)), 0) as total_exits'),
                )
                ->groupBy('products.id', 'products.name', 'products.code', 'products.unit', 'products.stock_qty', 'products.stock_min', 'products.cost_price')
                ->orderBy('products.stock_qty')
                ->get();

            $result = $items->map(function ($item) use ($months) {
                $stockQty = (float) $item->stock_qty;
                $stockMin = (float) $item->stock_min;
                $exits = (float) $item->total_exits;

                $dailyConsumption = $months > 0 ? $exits / ($months * 30) : 0;
                $daysUntilMin = $dailyConsumption > 0 ? round(max(0, $stockQty - $stockMin) / $dailyConsumption) : 999;
                $suggestedQty = max(0, (float) bcsub(bcmul((string) $stockMin, '2', 2), (string) $stockQty, 2));
                $estimatedCost = (float) bcmul((string) $suggestedQty, (string) $item->cost_price, 2);

                $urgency = match (true) {
                    $stockQty <= 0 => 'critical',
                    $stockQty <= $stockMin => 'urgent',
                    $daysUntilMin <= 7 => 'soon',
                    default => 'ok',
                };

                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'code' => $item->code,
                    'unit' => $item->unit,
                    'stock_qty' => round($stockQty, 2),
                    'stock_min' => round($stockMin, 2),
                    'daily_consumption' => round($dailyConsumption, 3),
                    'days_until_min' => min($daysUntilMin, 999),
                    'suggested_qty' => $suggestedQty,
                    'estimated_cost' => $estimatedCost,
                    'urgency' => $urgency,
                ];
            });

            $needAttention = $result->whereIn('urgency', ['critical', 'urgent', 'soon'])->values();
            $totalEstimated = $needAttention->sum('estimated_cost');

            return ApiResponse::data($needAttention, 200, [
                'all' => $result->values(),
                'summary' => [
                    'critical' => $result->where('urgency', 'critical')->count(),
                    'urgent' => $result->where('urgency', 'urgent')->count(),
                    'soon' => $result->where('urgency', 'soon')->count(),
                    'ok' => $result->where('urgency', 'ok')->count(),
                    'estimated_reorder_cost' => round($totalEstimated, 2),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('StockIntelligence reorderPoints failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao calcular pontos de reposição.', 500);
        }
    }

    public function autoRequest(StockAutoRequestRequest $request): JsonResponse
    {
        try {
            $tenantId = $this->tenantId();
            $months = (int) ($request->validated('months') ?? 3);
            $since = now()->subMonths($months);
            $urgencyFilter = $request->validated('urgency', ['critical', 'urgent']);

            $items = DB::table('products')
                ->leftJoin('stock_movements', function ($join) use ($since, $tenantId) {
                    $join->on('stock_movements.product_id', '=', 'products.id')
                        ->where('stock_movements.tenant_id', $tenantId)
                        ->where('stock_movements.type', 'exit')
                        ->where('stock_movements.created_at', '>=', $since);
                })
                ->where('products.tenant_id', $tenantId)
                ->where('products.is_active', true)
                ->where('products.stock_min', '>', 0)
                ->select(
                    'products.id',
                    'products.name',
                    'products.code',
                    'products.stock_qty',
                    'products.stock_min',
                    'products.cost_price',
                    DB::raw('COALESCE(SUM(ABS(stock_movements.quantity)), 0) as total_exits'),
                )
                ->groupBy('products.id', 'products.name', 'products.code', 'products.stock_qty', 'products.stock_min', 'products.cost_price')
                ->get();

            $needsReorder = $items->filter(function ($item) use ($months, $urgencyFilter) {
                $stockQty = (float) $item->stock_qty;
                $stockMin = (float) $item->stock_min;
                $exits = (float) $item->total_exits;
                $dailyConsumption = $months > 0 ? $exits / ($months * 30) : 0;
                $daysUntilMin = $dailyConsumption > 0 ? max(0, $stockQty - $stockMin) / $dailyConsumption : 999;

                $urgency = match (true) {
                    $stockQty <= 0 => 'critical',
                    $stockQty <= $stockMin => 'urgent',
                    $daysUntilMin <= 7 => 'soon',
                    default => 'ok',
                };

                return in_array($urgency, $urgencyFilter);
            });

            if ($needsReorder->isEmpty()) {
                return ApiResponse::data(['message' => 'Nenhum produto abaixo do ponto de reposição.', 'created' => 0]);
            }

            $materialRequest = DB::transaction(function () use ($needsReorder, $tenantId, $request) {
                $mr = MaterialRequest::create([
                    'tenant_id' => $tenantId,
                    'reference' => 'AUTO-'.now()->format('Ymd-His'),
                    'requester_id' => $request->user()->id,
                    'status' => 'pending',
                    'priority' => 'high',
                    'justification' => 'Solicitação automática — produtos abaixo do ponto de reposição',
                ]);

                foreach ($needsReorder as $product) {
                    $suggestedQty = max(1, (float) bcsub(bcmul((string) $product->stock_min, '2', 2), (string) $product->stock_qty, 2));
                    MaterialRequestItem::create([
                        'material_request_id' => $mr->id,
                        'product_id' => $product->id,
                        'quantity_requested' => $suggestedQty,
                        'notes' => "Estoque: {$product->stock_qty}, Mín: {$product->stock_min}",
                    ]);
                }

                return $mr->load('items.product:id,name,code');
            });

            return ApiResponse::data([
                'message' => 'Solicitação de materiais criada com sucesso.',
                'created' => $needsReorder->count(),
                'material_request' => $materialRequest,
            ], 201);
        } catch (\Exception $e) {
            Log::error('StockIntelligence autoRequest failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao gerar solicitação automática de materiais.', 500);
        }
    }

    public function reservations(Request $request): JsonResponse
    {
        try {
            $tenantId = $this->tenantId();

            $reservations = StockMovement::with(['product:id,name,code,unit', 'workOrder:id,number', 'warehouse:id,name'])
                ->where('tenant_id', $tenantId)
                ->where('type', 'reserve')
                ->orderByDesc('created_at')
                ->paginate(min($request->integer('per_page', 50), 100));

            $reservations->getCollection()->transform(fn ($m) => [
                'id' => $m->id,
                'product' => $m->product ? ['id' => $m->product->id, 'name' => $m->product->name, 'code' => $m->product->code] : null,
                'warehouse' => $m->warehouse ? ['id' => $m->warehouse->id, 'name' => $m->warehouse->name] : null,
                'work_order' => $m->workOrder ? ['id' => $m->workOrder->id, 'number' => $m->workOrder->number] : null,
                'quantity' => abs((float) $m->quantity),
                'reference' => $m->reference,
                'notes' => $m->notes,
                'created_at' => $m->created_at->toISOString(),
            ]);

            return ApiResponse::data($reservations);
        } catch (\Exception $e) {
            Log::error('StockIntelligence reservations failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao listar reservas.', 500);
        }
    }

    public function expiringBatches(Request $request): JsonResponse
    {
        try {
            $tenantId = $this->tenantId();
            $days = $request->integer('days', 30);
            $deadline = now()->addDays($days);

            $batches = DB::table('batches')
                ->join('products', 'batches.product_id', '=', 'products.id')
                ->where('batches.tenant_id', $tenantId)
                ->where('products.tenant_id', $tenantId)
                ->whereNotNull('batches.expires_at')
                ->where('batches.expires_at', '<=', $deadline)
                ->where('batches.expires_at', '>=', now())
                ->select(
                    'batches.id',
                    'batches.code',
                    'batches.expires_at',
                    'products.id as product_id',
                    'products.name as product_name',
                    'products.code as product_code',
                )
                ->orderBy('batches.expires_at')
                ->limit(100)
                ->get();

            $expired = DB::table('batches')
                ->where('tenant_id', $tenantId)
                ->whereNotNull('expires_at')
                ->where('expires_at', '<', now())
                ->count();

            return ApiResponse::data($batches->map(fn ($b) => [
                'id' => $b->id,
                'code' => $b->code,
                'expires_at' => $b->expires_at,
                'days_until_expiry' => (int) now()->diffInDays($b->expires_at, false),
                'product' => [
                    'id' => $b->product_id,
                    'name' => $b->product_name,
                    'code' => $b->product_code,
                ],
            ]), 200, [
                'summary' => [
                    'expiring_count' => $batches->count(),
                    'already_expired' => $expired,
                    'filter_days' => $days,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('StockIntelligence expiringBatches failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao buscar lotes próximos ao vencimento.', 500);
        }
    }

    public function staleProducts(Request $request): JsonResponse
    {
        try {
            $tenantId = $this->tenantId();
            $days = $request->integer('days', 90);
            $since = now()->subDays($days);

            $products = DB::table('products')
                ->leftJoin('stock_movements', function ($join) use ($since, $tenantId) {
                    $join->on('stock_movements.product_id', '=', 'products.id')
                        ->where('stock_movements.tenant_id', $tenantId)
                        ->where('stock_movements.created_at', '>=', $since);
                })
                ->where('products.tenant_id', $tenantId)
                ->where('products.is_active', true)
                ->where('products.stock_qty', '>', 0)
                ->groupBy('products.id', 'products.name', 'products.code', 'products.unit', 'products.stock_qty', 'products.cost_price')
                ->havingRaw('COUNT(stock_movements.id) = 0')
                ->select(
                    'products.id',
                    'products.name',
                    'products.code',
                    'products.unit',
                    'products.stock_qty',
                    'products.cost_price',
                )
                ->orderByDesc('products.stock_qty')
                ->limit(100)
                ->get();

            $result = $products->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'code' => $p->code,
                'unit' => $p->unit,
                'stock_qty' => round((float) $p->stock_qty, 2),
                'stock_value' => round((float) $p->stock_qty * (float) $p->cost_price, 2),
            ]);

            return ApiResponse::data($result, 200, [
                'summary' => [
                    'stale_count' => $result->count(),
                    'total_stale_value' => round($result->sum('stock_value'), 2),
                    'filter_days' => $days,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('StockIntelligence staleProducts failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao buscar produtos parados.', 500);
        }
    }
}
