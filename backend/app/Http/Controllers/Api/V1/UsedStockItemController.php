<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Stock\ReportUsedStockItemRequest;
use App\Models\UsedStockItem;
use App\Models\Warehouse;
use App\Services\StockService;
use App\Support\ApiResponse;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UsedStockItemController extends Controller
{
    use ResolvesCurrentTenant;

    public function __construct(
        private readonly StockService $stockService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $tenantId = $this->resolvedTenantId();

        $query = UsedStockItem::with(['workOrder:id,os_number,number', 'product:id,name,code', 'technicianWarehouse.user:id,name'])
            ->where('tenant_id', $tenantId);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('work_order_id')) {
            $query->where('work_order_id', $request->work_order_id);
        }
        if ($request->filled('technician_warehouse_id')) {
            $query->where('technician_warehouse_id', $request->technician_warehouse_id);
        }

        $items = $query->orderByDesc('created_at')->paginate(min($request->integer('per_page', 20), 100));

        return ApiResponse::paginated($items);
    }

    /**
     * Técnico informa: devolvi ou cliente ficou (pendente de confirmação do estoquista).
     */
    public function report(ReportUsedStockItemRequest $request, UsedStockItem $usedStockItem): JsonResponse
    {
        $this->authorizeTenant($usedStockItem);
        $validated = $request->validated();

        try {
            DB::transaction(function () use ($usedStockItem, $validated) {
                $locked = UsedStockItem::lockForUpdate()->find($usedStockItem->id);
                if (! $locked || $locked->status !== UsedStockItem::STATUS_PENDING_RETURN) {
                    abort(422, 'Item não está pendente de informação.');
                }

                $locked->update([
                    'status' => UsedStockItem::STATUS_PENDING_CONFIRMATION,
                    'reported_by' => auth()->id(),
                    'reported_at' => now(),
                    'disposition_type' => $validated['disposition_type'],
                    'disposition_notes' => $validated['disposition_notes'] ?? null,
                ]);
            });
        } catch (HttpResponseException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('UsedStockItemController: report failed', ['id' => $usedStockItem->id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao registrar informação do item.', 500);
        }

        return ApiResponse::message(
            'Informação registrada. Aguardando confirmação do estoquista.',
            200,
            ['used_stock_item' => $usedStockItem->fresh(['workOrder', 'product'])]
        );
    }

    /**
     * Estoquista confirma devolução: gera entrada no estoque central.
     */
    public function confirmReturn(Request $request, UsedStockItem $usedStockItem): JsonResponse
    {
        $this->authorizeTenant($usedStockItem);

        $tenantId = $usedStockItem->tenant_id;
        $central = Warehouse::where('tenant_id', $tenantId)->where('type', Warehouse::TYPE_FIXED)->whereNull('user_id')->whereNull('vehicle_id')->first();
        if (! $central) {
            return ApiResponse::message('Armazém central não configurado.', 422);
        }

        try {
            DB::transaction(function () use ($usedStockItem, $central) {
                $locked = UsedStockItem::lockForUpdate()->find($usedStockItem->id);
                if (! $locked || $locked->status !== UsedStockItem::STATUS_PENDING_CONFIRMATION || $locked->disposition_type !== 'return') {
                    abort(422, 'Item não está pendente de confirmação de devolução.');
                }

                $locked->update([
                    'status' => UsedStockItem::STATUS_RETURNED,
                    'confirmed_by' => auth()->id(),
                    'confirmed_at' => now(),
                ]);
                $product = $locked->product;
                if ($product) {
                    $this->stockService->manualEntry($product, (float) $locked->quantity, $central->id, null, null, 0, 'Devolução peça usada (OS)');
                }
            });
        } catch (HttpResponseException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('UsedStockItemController: confirmReturn failed', ['id' => $usedStockItem->id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao confirmar devolução.', 500);
        }

        return ApiResponse::message(
            'Devolução confirmada e entrada no estoque central registrada.',
            200,
            ['used_stock_item' => $usedStockItem->fresh(['workOrder', 'product'])]
        );
    }

    /**
     * Estoquista confirma baixa sem devolução (cliente ficou, descarte, etc.).
     */
    public function confirmWriteOff(Request $request, UsedStockItem $usedStockItem): JsonResponse
    {
        $this->authorizeTenant($usedStockItem);

        try {
            DB::transaction(function () use ($usedStockItem) {
                $locked = UsedStockItem::lockForUpdate()->find($usedStockItem->id);
                if (! $locked || $locked->status !== UsedStockItem::STATUS_PENDING_CONFIRMATION || $locked->disposition_type !== 'write_off') {
                    abort(422, 'Item não está pendente de confirmação de baixa.');
                }

                $locked->update([
                    'status' => UsedStockItem::STATUS_WRITTEN_OFF_NO_RETURN,
                    'confirmed_by' => auth()->id(),
                    'confirmed_at' => now(),
                ]);
            });
        } catch (HttpResponseException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('UsedStockItemController: confirmWriteOff failed', ['id' => $usedStockItem->id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao confirmar baixa.', 500);
        }

        return ApiResponse::message(
            'Baixa sem devolução registrada.',
            200,
            ['used_stock_item' => $usedStockItem->fresh(['workOrder', 'product'])]
        );
    }

    protected function authorizeTenant(UsedStockItem $item): void
    {
        $tenantId = $this->resolvedTenantId();
        if ($item->tenant_id !== $tenantId) {
            abort(404);
        }
    }
}
