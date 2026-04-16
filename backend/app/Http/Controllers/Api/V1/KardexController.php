<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Stock\ShowKardexRequest;
use App\Models\Product;
use App\Models\Warehouse;
use App\Services\StockService;
use App\Support\ApiResponse;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class KardexController extends Controller
{
    use ResolvesCurrentTenant;

    public function __construct(protected StockService $stockService) {}

    public function show(ShowKardexRequest $request, Product $product): JsonResponse
    {
        try {
            if ($deny = $this->ensureTenantOwnership($product, 'Produto')) {
                return $deny;
            }
            $tenantId = $this->tenantId();

            $validated = $request->validated();

            $kardex = $this->stockService->getKardex(
                productId: $product->id,
                warehouseId: $validated['warehouse_id'],
                dateFrom: $validated['date_from'] ?? null,
                dateTo: $validated['date_to'] ?? null
            );

            $payload = [
                'product' => $product->only(['id', 'name', 'code']),
                'warehouse' => Warehouse::findOrFail($validated['warehouse_id'])->only(['id', 'name']),
                'data' => $kardex,
            ];

            return ApiResponse::data($payload);
        } catch (\Exception $e) {
            Log::error('Kardex show failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao buscar kardex', 500);
        }
    }
}
