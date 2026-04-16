<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PriceHistory;
use App\Models\Product;
use App\Models\Service;
use App\Models\WorkOrderItem;
use App\Support\ApiResponse;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PriceHistoryController extends Controller
{
    use ResolvesCurrentTenant;

    public function index(Request $request): JsonResponse
    {
        try {
            $tenantId = $this->tenantId();
            $query = PriceHistory::where('tenant_id', $tenantId)
                ->with('changedByUser')
                ->orderByDesc('created_at');

            if ($request->filled('priceable_type')) {
                $query->where('priceable_type', $request->priceable_type);
            }

            if ($request->filled('priceable_id')) {
                $query->where('priceable_id', $request->priceable_id);
            }

            if ($request->filled('date_from')) {
                $query->whereDate('created_at', '>=', $request->date_from);
            }

            if ($request->filled('date_to')) {
                $query->whereDate('created_at', '<=', $request->date_to);
            }

            return ApiResponse::paginated($query->paginate(min((int) ($request->per_page ?? 25), 100)));
        } catch (\Exception $e) {
            Log::error('PriceHistory index failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao listar histórico de preços.', 500);
        }
    }

    public function forProduct(Request $request, int $productId): JsonResponse
    {
        try {
            return ApiResponse::paginated(
                PriceHistory::where('tenant_id', $this->tenantId())
                    ->with('changedByUser')
                    ->where('priceable_type', Product::class)
                    ->where('priceable_id', $productId)
                    ->orderByDesc('created_at')
                    ->paginate(min((int) ($request->per_page ?? 25), 100))
            );
        } catch (\Exception $e) {
            Log::error('PriceHistory forProduct failed', ['error' => $e->getMessage(), 'productId' => $productId]);

            return ApiResponse::message('Erro ao buscar histórico do produto.', 500);
        }
    }

    public function forService(Request $request, int $serviceId): JsonResponse
    {
        try {
            return ApiResponse::paginated(
                PriceHistory::where('tenant_id', $this->tenantId())
                    ->with('changedByUser')
                    ->where('priceable_type', Service::class)
                    ->where('priceable_id', $serviceId)
                    ->orderByDesc('created_at')
                    ->paginate(min((int) ($request->per_page ?? 25), 100))
            );
        } catch (\Exception $e) {
            Log::error('PriceHistory forService failed', ['error' => $e->getMessage(), 'serviceId' => $serviceId]);

            return ApiResponse::message('Erro ao buscar histórico do serviço.', 500);
        }
    }

    /**
     * Retorna últimos preços praticados para um cliente específico,
     * baseado nos itens de OS anteriores.
     * Útil para exibir sugestão de preço ao adicionar itens.
     */
    public function customerItemPrices(Request $request, int $customerId): JsonResponse
    {
        try {
            $type = $request->get('type'); // product ou service
            $referenceId = $request->get('reference_id');

            $query = WorkOrderItem::select(
                'work_order_items.type',
                'work_order_items.reference_id',
                'work_order_items.description',
                'work_order_items.unit_price',
                'work_order_items.discount',
                'work_orders.os_number',
                'work_orders.created_at as wo_date',
            )
                ->join('work_orders', 'work_order_items.work_order_id', '=', 'work_orders.id')
                ->where('work_orders.tenant_id', $this->tenantId())
                ->where('work_orders.customer_id', $customerId)
                ->whereIn('work_orders.status', ['completed', 'delivered', 'invoiced'])
                ->orderByDesc('work_orders.created_at');

            if ($type) {
                $query->where('work_order_items.type', $type);
            }
            if ($referenceId) {
                $query->where('work_order_items.reference_id', $referenceId);
            }

            $items = $query->limit(20)->get();

            // Agrupa por item e pega os últimos 5 preços praticados
            $grouped = $items->groupBy(fn ($i) => $i->type.'-'.$i->reference_id)
                ->map(function ($records) {
                    $last = $records->first();

                    return [
                        'type' => $last->type,
                        'reference_id' => $last->reference_id,
                        'description' => $last->description,
                        'last_price' => (float) $last->unit_price,
                        'last_discount' => (float) $last->discount,
                        'last_os' => $last->os_number,
                        'last_date' => $last->wo_date,
                        'history' => $records->take(5)->map(fn ($r) => [
                            'unit_price' => (float) $r->unit_price,
                            'discount' => (float) $r->discount,
                            'os_number' => $r->os_number,
                            'date' => $r->wo_date,
                        ])->values(),
                    ];
                })
                ->values();

            return ApiResponse::data($grouped);
        } catch (\Exception $e) {
            Log::error('CustomerItemPrices failed', ['error' => $e->getMessage(), 'customerId' => $customerId]);

            return ApiResponse::message('Erro ao buscar histórico de preços do cliente.', 500);
        }
    }
}
