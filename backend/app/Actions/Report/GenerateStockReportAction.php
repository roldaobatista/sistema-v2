<?php

namespace App\Actions\Report;

use App\Models\Product;
use Illuminate\Support\Facades\DB;

class GenerateStockReportAction extends BaseReportAction
{
    /**
     * @param  array<int|string, mixed>  $filters
     * @return array<int|string, mixed>
     */
    public function execute(int $tenantId, array $filters): array
    {

        $products = Product::where('tenant_id', $tenantId)
            ->selectRaw('id, name, code, code as sku, stock_qty, stock_min, stock_min as min_stock, cost_price, sell_price, sell_price as sale_price')
            ->orderBy('name')
            ->limit(500)
            ->get();

        $totalProducts = Product::where('tenant_id', $tenantId)->count();
        $outOfStock = Product::where('tenant_id', $tenantId)->where('stock_qty', '<=', 0)->count();
        $lowStock = Product::where('tenant_id', $tenantId)
            ->where('stock_qty', '>', 0)
            ->whereNotNull('stock_min')
            ->whereColumn('stock_qty', '<=', 'stock_min')
            ->count();

        $totalValue = Product::where('tenant_id', $tenantId)
            ->selectRaw('SUM(stock_qty * cost_price) as total')
            ->value('total') ?? 0;

        $totalSaleValue = Product::where('tenant_id', $tenantId)
            ->selectRaw('SUM(stock_qty * sell_price) as total')
            ->value('total') ?? 0;

        $recentMovements = DB::table('stock_movements')
            ->join('products', 'stock_movements.product_id', '=', 'products.id')
            ->leftJoin('work_orders', 'stock_movements.work_order_id', '=', 'work_orders.id')
            ->where('stock_movements.tenant_id', $tenantId)
            ->orderByDesc('stock_movements.created_at')
            ->limit(50)
            ->select(
                'stock_movements.id',
                'products.name as product_name',
                'stock_movements.quantity',
                'stock_movements.type',
                DB::raw('COALESCE(work_orders.os_number, work_orders.number, stock_movements.reference) as reference'),
                'stock_movements.created_at'
            )
            ->get()
            ->map(function ($m) {
                $type = $m->type;
                $isIn = in_array($type, ['entry', 'return'], true);

                return [
                    'id' => $m->id,
                    'product_name' => $m->product_name,
                    'quantity' => $m->quantity,
                    'type' => $isIn ? 'in' : 'out',
                    'movement_type' => $type,
                    'reference' => $m->reference ?? '—',
                    'created_at' => $m->created_at,
                ];
            });

        return [
            'summary' => [
                'total_products' => $totalProducts,
                'out_of_stock' => $outOfStock,
                'low_stock' => $lowStock,
                'total_cost_value' => round((float) $totalValue, 2),
                'total_sale_value' => round((float) $totalSaleValue, 2),
            ],
            'products' => $products,
            'recent_movements' => $recentMovements,
        ];
    }
}
