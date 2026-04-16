<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Stock\IndexProductKardexRequest;
use App\Http\Requests\Stock\ProductKardexMonthlySummaryRequest;
use App\Models\Product;
use App\Models\StockMovement;
use App\Support\ApiResponse;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProductKardexController extends Controller
{
    use ResolvesCurrentTenant;

    public function index(IndexProductKardexRequest $request, Product $product): JsonResponse
    {
        try {
            if ($deny = $this->ensureTenantOwnership($product, 'Produto')) {
                return $deny;
            }
            $tenantId = $this->tenantId();

            $query = StockMovement::where('product_id', $product->id)
                ->where('tenant_id', $tenantId)
                ->with(['user:id,name', 'warehouse:id,name']);

            if ($request->filled('warehouse_id')) {
                $query->where('warehouse_id', $request->input('warehouse_id'));
            }

            if ($request->filled('type')) {
                $query->where('type', $request->input('type'));
            }

            if ($request->filled('from')) {
                $query->where('created_at', '>=', $request->input('from'));
            }

            if ($request->filled('to')) {
                $query->where('created_at', '<=', $request->input('to').' 23:59:59');
            }

            $perPage = min((int) $request->input('per_page', 50), 200);

            $movements = $query->orderByDesc('created_at')->paginate($perPage);

            $stats = StockMovement::where('product_id', $product->id)
                ->where('tenant_id', $tenantId)
                ->selectRaw('
                    SUM(CASE WHEN quantity > 0 THEN quantity ELSE 0 END) as total_in,
                    SUM(CASE WHEN quantity < 0 THEN ABS(quantity) ELSE 0 END) as total_out,
                    COUNT(*) as total_movements,
                    MIN(created_at) as first_movement,
                    MAX(created_at) as last_movement
                ')
                ->first();

            return ApiResponse::data([
                'product' => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'code' => $product->code,
                    'current_stock' => $product->stock_qty ?? 0,
                ],
                'stats' => $stats,
                'data' => $movements,
            ]);
        } catch (\Exception $e) {
            Log::error('ProductKardex index failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao buscar kardex do produto', 500);
        }
    }

    public function monthlySummary(ProductKardexMonthlySummaryRequest $request, Product $product): JsonResponse
    {
        try {
            if ($deny = $this->ensureTenantOwnership($product, 'Produto')) {
                return $deny;
            }
            $tenantId = $this->tenantId();

            $months = min((int) $request->validated('months', 12), 24);

            $monthExpr = DB::getDriverName() === 'sqlite'
                ? "strftime('%Y-%m', created_at)"
                : "DATE_FORMAT(created_at, '%Y-%m')";

            $results = StockMovement::where('product_id', $product->id)
                ->where('tenant_id', $tenantId)
                ->where('created_at', '>=', now()->subMonths($months))
                ->selectRaw("
                    {$monthExpr} as month,
                    SUM(CASE WHEN quantity > 0 THEN quantity ELSE 0 END) as entries,
                    SUM(CASE WHEN quantity < 0 THEN ABS(quantity) ELSE 0 END) as exits,
                    COUNT(*) as movements
                ")
                ->groupByRaw($monthExpr)
                ->orderBy('month')
                ->get();

            return ApiResponse::data($results);
        } catch (\Exception $e) {
            Log::error('ProductKardex monthlySummary failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao buscar resumo mensal', 500);
        }
    }
}
