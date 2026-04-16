<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Stock\SubmitCountPwaRequest;
use App\Models\Inventory;
use App\Models\Notification;
use App\Models\SystemAlert;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Support\ApiResponse;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class InventoryPwaController extends Controller
{
    use ResolvesCurrentTenant;

    /**
     * Armazéns que o usuário logado pode inventariar (técnico: 1; motorista: do(s) veículo(s)).
     */
    public function myWarehouses(Request $request): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $this->tenantId();

        $warehouses = Warehouse::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where(function ($q) use ($user) {
                $q->where(function ($q2) use ($user) {
                    $q2->where('type', Warehouse::TYPE_TECHNICIAN)->where('user_id', $user->id);
                })->orWhere(function ($q2) use ($user) {
                    $q2->where('type', Warehouse::TYPE_VEHICLE)
                        ->whereHas('vehicle', fn ($v) => $v->where('assigned_user_id', $user->id));
                });
            })
            ->with('vehicle:id,plate')
            ->get(['id', 'name', 'code', 'type', 'vehicle_id']);

        return ApiResponse::data($warehouses);
    }

    /**
     * Produtos do armazém com quantidade esperada (para o PWA exibir e o usuário informar contagem).
     */
    public function warehouseProducts(Request $request, int $warehouseId): JsonResponse
    {
        $warehouse = $this->authorizeWarehouseForUser($warehouseId);

        $stocks = WarehouseStock::whereBelongsTo($warehouse, 'warehouse')
            ->with('product:id,name,code,unit')
            ->get();

        $items = $stocks->map(fn ($s) => [
            'product_id' => $s->product_id,
            'product' => $s->product,
            'expected_quantity' => (float) $s->quantity,
        ]);

        return ApiResponse::data($items);
    }

    /**
     * Submete contagem do inventário (técnico/motorista). Cria ou atualiza inventário; se houver diferença, gera alerta crítico.
     */
    public function submitCounts(SubmitCountPwaRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $warehouse = $this->authorizeWarehouseForUser($validated['warehouse_id']);

        $tenantId = $this->tenantId();

        return DB::transaction(function () use ($validated, $tenantId, $warehouse) {
            $inventory = Inventory::where('tenant_id', $tenantId)
                ->where('warehouse_id', $validated['warehouse_id'])
                ->whereIn('status', [Inventory::STATUS_OPEN, Inventory::STATUS_PROCESSING])
                ->lockForUpdate()
                ->first();

            if (! $inventory) {
                $inventory = Inventory::create([
                    'tenant_id' => $tenantId,
                    'warehouse_id' => $validated['warehouse_id'],
                    'reference' => 'PWA - '.$warehouse->name.' - '.now()->format('d/m/Y H:i'),
                    'status' => Inventory::STATUS_OPEN,
                    'created_by' => Auth::id(),
                ]);
                $stocks = WarehouseStock::whereBelongsTo($warehouse, 'warehouse')->get();
                foreach ($stocks as $stock) {
                    $inventory->items()->create([
                        'product_id' => $stock->product_id,
                        'batch_id' => $stock->batch_id,
                        'expected_quantity' => $stock->quantity,
                    ]);
                }
            }

            $hasDiscrepancy = false;
            $discrepancyDetail = [];

            // Pre-load warehouse stocks and inventory items to avoid N+1 queries inside the loop
            $productIds = collect($validated['items'])->pluck('product_id')->unique();
            $warehouseStocks = WarehouseStock::whereBelongsTo($warehouse, 'warehouse')
                ->whereIn('product_id', $productIds)
                ->pluck('quantity', 'product_id');
            $existingItems = $inventory->items()->whereIn('product_id', $productIds)->get()->keyBy('product_id');

            foreach ($validated['items'] as $row) {
                $item = $existingItems->get($row['product_id']);
                if (! $item) {
                    $item = $inventory->items()->create([
                        'product_id' => $row['product_id'],
                        'batch_id' => null,
                        'expected_quantity' => $warehouseStocks->get($row['product_id'], 0),
                    ]);
                }
                $expected = (float) $item->expected_quantity;
                $counted = (float) ($row['counted_quantity'] ?? 0);
                $item->update(['counted_quantity' => $counted]);
                if (abs($counted - $expected) > 0.0001) {
                    $hasDiscrepancy = true;
                    $discrepancyDetail[] = "Produto #{$item->product_id}: esperado {$expected}, contado {$counted}";
                }
            }

            if ($hasDiscrepancy) {
                $this->createInventoryDiscrepancyAlert($inventory, $warehouse, $discrepancyDetail);
            }

            return ApiResponse::data([
                'inventory_id' => $inventory->id,
                'has_discrepancy' => $hasDiscrepancy,
                'data' => $inventory->fresh('items.product'),
            ], 201, [
                'message' => $hasDiscrepancy
                    ? 'Contagem recebida. Foi detectada diferença em relação ao esperado; o responsável do estoque foi notificado.'
                    : 'Contagem recebida.',
            ]);
        });
    }

    protected function authorizeWarehouseForUser(int $warehouseId): Warehouse
    {
        $user = Auth::user();
        $w = Warehouse::where('tenant_id', $this->tenantId())
            ->whereKey($warehouseId)
            ->first();
        if (! $w) {
            abort(404);
        }
        if ($w->type === Warehouse::TYPE_TECHNICIAN && (int) $w->user_id === (int) $user->id) {
            return $w;
        }
        if ($w->type === Warehouse::TYPE_VEHICLE && $w->vehicle_id) {
            $w->loadMissing('vehicle');
            if ($w->vehicle && (int) $w->vehicle->assigned_user_id === (int) $user->id) {
                return $w;
            }
        }
        abort(403, 'Você não pode inventariar este armazém.');
    }

    protected function createInventoryDiscrepancyAlert(Inventory $inventory, Warehouse $warehouse, array $detail): void
    {
        $tenantId = $inventory->tenant_id;
        $title = 'Diferença no inventário - '.$warehouse->name;
        $message = implode('; ', array_slice($detail, 0, 5));
        if (count($detail) > 5) {
            $message .= ' (e mais '.(count($detail) - 5).')';
        }

        SystemAlert::create([
            'tenant_id' => $tenantId,
            'alert_type' => 'inventory_discrepancy_critical',
            'severity' => 'critical',
            'title' => $title,
            'message' => $message,
            'status' => 'active',
            'alertable_type' => Inventory::class,
            'alertable_id' => $inventory->id,
        ]);

        $estoquistas = User::where('tenant_id', $tenantId)->role('estoquista')->pluck('id');
        $link = '/estoque/inventarios/'.$inventory->id;
        foreach ($estoquistas as $uid) {
            Notification::notify($tenantId, $uid, 'inventory_discrepancy_critical', $title, [
                'message' => $message,
                'link' => $link,
                'data' => ['inventory_id' => $inventory->id],
            ]);
        }
    }
}
