<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Support\ApiResponse;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WarehouseStockController extends Controller
{
    use ResolvesCurrentTenant;

    public function index(Request $request): JsonResponse
    {
        try {
            $tenantId = $this->tenantId();
            $query = WarehouseStock::with(['warehouse', 'product', 'batch'])
                ->whereHas('warehouse', fn ($q) => $q->where('tenant_id', $tenantId));

            if ($request->filled('warehouse_id')) {
                $query->where('warehouse_id', $request->warehouse_id);
            }

            if ($request->filled('product_id')) {
                $query->where('product_id', $request->product_id);
            }

            if ($request->filled('batch_id')) {
                $query->where('batch_id', $request->batch_id);
            }

            if ($request->boolean('hide_empty', true)) {
                $query->where('quantity', '>', 0);
            }

            $stocks = $query->paginate(min($request->integer('per_page', 50), 100));

            return ApiResponse::paginated($stocks);
        } catch (\Exception $e) {
            Log::error('WarehouseStock index failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao listar estoque', 500);
        }
    }

    public function byWarehouse(Request $request, Warehouse $warehouse): JsonResponse
    {
        try {
            if ($deny = $this->ensureTenantOwnership($warehouse, 'Armazém')) {
                return $deny;
            }

            $stocks = $warehouse->stocks()
                ->with(['product:id,name,code,unit', 'batch'])
                ->where('quantity', '>', 0)
                ->get();

            return ApiResponse::data($stocks, 200, ['warehouse' => $warehouse]);
        } catch (\Exception $e) {
            Log::error('WarehouseStock byWarehouse failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao buscar estoque por armazém', 500);
        }
    }

    public function byProduct(Request $request, Product $product): JsonResponse
    {
        try {
            if ($deny = $this->ensureTenantOwnership($product, 'Produto')) {
                return $deny;
            }
            $tenantId = $this->tenantId();

            $stocks = $product->warehouseStocks()
                ->whereHas('warehouse', fn ($q) => $q->where('tenant_id', $tenantId))
                ->with(['warehouse:id,name,type', 'batch'])
                ->where('quantity', '>', 0)
                ->get();

            return ApiResponse::data($stocks, 200, ['product' => $product]);
        } catch (\Exception $e) {
            Log::error('WarehouseStock byProduct failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao buscar estoque por produto', 500);
        }
    }
}
