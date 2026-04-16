<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Models\Batch;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Models\WorkOrder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class StockService
{
    /**
     * Resolve warehouse for WO: technician warehouse if assigned_to, else central.
     */
    public function resolveWarehouseIdForWorkOrder(WorkOrder $workOrder): ?int
    {
        $tenantId = $workOrder->tenant_id;
        if ($workOrder->assigned_to) {
            $w = Warehouse::where('tenant_id', $tenantId)
                ->where('type', Warehouse::TYPE_TECHNICIAN)
                ->where('user_id', $workOrder->assigned_to)
                ->first();
            if ($w) {
                return (int) $w->id;
            }
        }
        $central = Warehouse::where('tenant_id', $tenantId)
            ->where('type', Warehouse::TYPE_FIXED)
            ->whereNull('user_id')
            ->whereNull('vehicle_id')
            ->first();

        return $central ? (int) $central->id : null;
    }

    /**
     * Saldo disponível no armazém (soma de quantity em warehouse_stocks para o produto).
     */
    public function getAvailableQuantity(Product $product, int $warehouseId): float
    {
        return (float) WarehouseStock::where('product_id', $product->id)
            ->where('warehouse_id', $warehouseId)
            ->sum('quantity');
    }

    public function reserve(Product $product, float $qty, WorkOrder $workOrder, ?int $warehouseId = null, string $strategy = 'FIFO'): StockMovement
    {
        $warehouseId = $warehouseId ?? $this->resolveWarehouseIdForWorkOrder($workOrder);
        if ($warehouseId === null) {
            throw ValidationException::withMessages([
                'stock' => ['Nenhum armazém definido para esta OS. Atribua um técnico ou configure o armazém central.'],
            ]);
        }

        return $this->createMovementWithBatchSelection(
            product: $product,
            type: StockMovementType::Reserve,
            quantity: $qty,
            warehouseId: $warehouseId,
            workOrder: $workOrder,
            reference: "OS-{$workOrder->number}",
            strategy: $strategy,
        );
    }

    public function deduct(Product $product, float $qty, WorkOrder $workOrder, ?int $warehouseId = null, string $strategy = 'FIFO'): StockMovement
    {
        $warehouseId = $warehouseId ?? $this->resolveWarehouseIdForWorkOrder($workOrder);

        return $this->createMovementWithBatchSelection(
            product: $product,
            type: StockMovementType::Exit,
            quantity: $qty,
            warehouseId: $warehouseId,
            workOrder: $workOrder,
            reference: "OS-{$workOrder->number} (faturamento)",
            strategy: $strategy,
        );
    }

    public function returnStock(Product $product, float $qty, WorkOrder $workOrder, ?int $warehouseId = null, string $strategy = 'FIFO'): StockMovement
    {
        $warehouseId = $warehouseId ?? $this->resolveWarehouseIdForWorkOrder($workOrder);

        return $this->createMovementWithBatchSelection(
            product: $product,
            type: StockMovementType::Return,
            quantity: $qty,
            warehouseId: $warehouseId,
            workOrder: $workOrder,
            reference: "OS-{$workOrder->number} (cancelamento)",
            strategy: $strategy,
        );
    }

    public function manualEntry(
        Product $product,
        float $qty,
        int $warehouseId,
        ?int $batchId = null,
        ?int $serialId = null,
        float $unitCost = 0,
        ?string $notes = null,
        ?User $user = null
    ): StockMovement {
        return $this->createMovement(
            product: $product,
            type: StockMovementType::Entry,
            quantity: $qty,
            warehouseId: $warehouseId,
            batchId: $batchId,
            serialId: $serialId,
            unitCost: $unitCost ?: $product->cost_price,
            notes: $notes,
            user: $user,
            reference: 'Entrada manual',
        );
    }

    public function manualExit(
        Product $product,
        float $qty,
        int $warehouseId,
        ?int $batchId = null,
        ?int $serialId = null,
        ?string $notes = null,
        ?User $user = null
    ): StockMovement {
        return $this->createMovement(
            product: $product,
            type: StockMovementType::Exit,
            quantity: $qty,
            warehouseId: $warehouseId,
            batchId: $batchId,
            serialId: $serialId,
            notes: $notes,
            user: $user,
            reference: 'Saída manual',
        );
    }

    public function manualReturn(
        Product $product,
        float $qty,
        int $warehouseId,
        ?int $batchId = null,
        ?int $serialId = null,
        ?string $notes = null,
        ?User $user = null
    ): StockMovement {
        return $this->createMovement(
            product: $product,
            type: StockMovementType::Return,
            quantity: $qty,
            warehouseId: $warehouseId,
            batchId: $batchId,
            serialId: $serialId,
            notes: $notes,
            user: $user,
            reference: 'Devolução manual',
        );
    }

    public function manualReserve(
        Product $product,
        float $qty,
        int $warehouseId,
        ?int $batchId = null,
        ?int $serialId = null,
        ?string $notes = null,
        ?User $user = null
    ): StockMovement {
        // Validação de saldo é feita dentro de createMovement->guardAvailableStock com lockForUpdate
        // para evitar race condition (TOCTOU)
        return $this->createMovement(
            product: $product,
            type: StockMovementType::Reserve,
            quantity: $qty,
            warehouseId: $warehouseId,
            batchId: $batchId,
            serialId: $serialId,
            notes: $notes,
            user: $user,
            reference: 'Reserva manual',
        );
    }

    public function manualAdjustment(
        Product $product,
        float $qty,
        int $warehouseId,
        ?int $batchId = null,
        ?int $serialId = null,
        ?string $notes = null,
        ?User $user = null
    ): StockMovement {
        return $this->createMovement(
            product: $product,
            type: StockMovementType::Adjustment,
            quantity: $qty,
            warehouseId: $warehouseId,
            batchId: $batchId,
            serialId: $serialId,
            notes: $notes,
            user: $user,
            reference: 'Ajuste de inventário',
        );
    }

    public function transfer(
        Product $product,
        float $qty,
        int $fromWarehouseId,
        int $toWarehouseId,
        ?int $batchId = null,
        ?int $serialId = null,
        ?string $notes = null,
        ?User $user = null
    ): StockMovement {
        return $this->createMovement(
            product: $product,
            type: StockMovementType::Transfer,
            quantity: $qty,
            warehouseId: $fromWarehouseId,
            targetWarehouseId: $toWarehouseId,
            batchId: $batchId,
            serialId: $serialId,
            notes: $notes,
            user: $user,
            reference: 'Transferência entre armazéns',
        );
    }

    /**
     * Select batches using FIFO (by creation date) or FEFO (by expiration date) strategy.
     *
     * Returns a collection of ['batch' => Batch, 'available' => float] sorted by strategy order.
     * FIFO: oldest created_at first.
     * FEFO: earliest expires_at first, then falls back to FIFO for batches without expiration.
     *
     * @param  string  $strategy  'FIFO' or 'FEFO'
     * @return Collection<int, array{batch: Batch, available: float}>
     */
    public function selectBatches(Product $product, int $warehouseId, string $strategy = 'FIFO'): Collection
    {
        $query = WarehouseStock::query()
            ->where('product_id', $product->id)
            ->where('warehouse_id', $warehouseId)
            ->whereNotNull('batch_id')
            ->where('quantity', '>', 0)
            ->with('batch');

        $stocks = $query->get();

        // Sort based on strategy
        $sorted = $stocks->sortBy(function (WarehouseStock $stock) use ($strategy) {
            /** @var Batch|null $batch */
            $batch = $stock->batch;
            if ($strategy === 'FEFO') {
                // Batches with expiration date come first (sorted by earliest expiry),
                // then batches without expiration (sorted by creation date)
                if ($batch && $batch->expires_at) {
                    return '0_'.$batch->expires_at->format('Y-m-d H:i:s');
                }

                return '1_'.($batch ? $batch->created_at->format('Y-m-d H:i:s') : '9999');
            }

            // FIFO: sort by batch creation date
            return $batch ? $batch->created_at->format('Y-m-d H:i:s').'_'.$batch->id : '9999';
        })->values();

        return $sorted
            ->filter(fn (WarehouseStock $stock): bool => $stock->batch instanceof Batch)
            ->map(function (WarehouseStock $stock): array {
                /** @var Batch $batch */
                $batch = $stock->batch;

                return [
                    'batch' => $batch,
                    'available' => (float) $stock->quantity,
                ];
            })
            ->values();
    }

    /**
     * Create stock movements consuming batches in FIFO/FEFO order.
     *
     * When a product has batches in the warehouse, this method splits the requested quantity
     * across batches in the correct order. When no batches exist, creates a single movement
     * without batch_id (backward compatible).
     *
     * @return StockMovement The first (or only) movement created
     */
    private function createMovementWithBatchSelection(
        Product $product,
        StockMovementType $type,
        float $quantity,
        int $warehouseId,
        ?WorkOrder $workOrder = null,
        ?string $reference = null,
        string $strategy = 'FIFO',
    ): StockMovement {
        $batches = $this->selectBatches($product, $warehouseId, $strategy);

        // No batches available — fall back to non-batch movement
        if ($batches->isEmpty()) {
            return $this->createMovement(
                product: $product,
                type: $type,
                quantity: $quantity,
                warehouseId: $warehouseId,
                workOrder: $workOrder,
                reference: $reference,
            );
        }

        $remaining = $quantity;
        $firstMovement = null;

        foreach ($batches as $entry) {
            if ($remaining <= 0) {
                break;
            }

            /** @var Batch $batch */
            $batch = $entry['batch'];
            $available = $entry['available'];
            $consume = min($remaining, $available);

            $movement = $this->createMovement(
                product: $product,
                type: $type,
                quantity: $consume,
                warehouseId: $warehouseId,
                batchId: $batch->id,
                workOrder: $workOrder,
                reference: $reference,
            );

            $firstMovement ??= $movement;
            $remaining = (float) bcsub((string) $remaining, (string) $consume, 4);
        }

        // If batches didn't cover the full quantity, create a non-batch movement for the remainder
        // (this preserves existing behavior where products may have partial batch tracking)
        if ($remaining > 0) {
            $movement = $this->createMovement(
                product: $product,
                type: $type,
                quantity: $remaining,
                warehouseId: $warehouseId,
                workOrder: $workOrder,
                reference: $reference,
            );
            $firstMovement ??= $movement;
        }

        return $firstMovement;
    }

    private function createMovement(
        Product $product,
        StockMovementType $type,
        float $quantity,
        ?int $warehouseId = null,
        ?int $targetWarehouseId = null,
        ?int $batchId = null,
        ?int $serialId = null,
        ?WorkOrder $workOrder = null,
        ?string $reference = null,
        float $unitCost = 0,
        ?string $notes = null,
        ?User $user = null,
    ): StockMovement {
        return DB::transaction(function () use (
            $product, $type, $quantity, $warehouseId, $targetWarehouseId,
            $batchId, $serialId, $workOrder, $reference, $unitCost, $notes, $user
        ) {
            $normalizedQuantity = $type === StockMovementType::Adjustment
                ? $quantity
                : abs($quantity);

            $this->guardAvailableStock(
                product: $product,
                type: $type,
                quantity: $normalizedQuantity,
                warehouseId: $warehouseId,
                batchId: $batchId,
            );

            $movement = StockMovement::create([
                'tenant_id' => $product->tenant_id,
                'product_id' => $product->id,
                'warehouse_id' => $warehouseId,
                'target_warehouse_id' => $targetWarehouseId,
                'batch_id' => $batchId,
                'product_serial_id' => $serialId,
                'work_order_id' => $workOrder?->id,
                'type' => $type->value,
                'quantity' => $normalizedQuantity,
                'unit_cost' => $unitCost,
                'reference' => $reference,
                'notes' => $notes,
                'created_by' => $user?->id ?? (Auth::check() ? Auth::id() : null),
            ]);

            if ($product->is_kit && $type === StockMovementType::Exit) {
                $this->explodeKit($product, $quantity, $warehouseId, StockMovementType::Exit, $notes, $user);
            } elseif ($product->is_kit && $type === StockMovementType::Entry) {
                $this->explodeKit($product, $quantity, $warehouseId, StockMovementType::Entry, $notes, $user);
            } elseif ($product->is_kit && $type === StockMovementType::Adjustment) {
                $childType = $quantity > 0 ? StockMovementType::Entry : StockMovementType::Exit;
                $this->explodeKit($product, abs($quantity), $warehouseId, $childType, $notes, $user);
            }

            return $movement;
        });
    }

    private function guardAvailableStock(
        Product $product,
        StockMovementType $type,
        float $quantity,
        ?int $warehouseId,
        ?int $batchId,
    ): void {
        if ($warehouseId === null) {
            return;
        }

        if (! in_array($type, [StockMovementType::Reserve, StockMovementType::Exit, StockMovementType::Transfer], true)) {
            return;
        }

        $stock = $this->lockWarehouseStock($warehouseId, $product->id, $batchId);
        $available = (float) $stock->quantity;

        if ($available >= $quantity) {
            return;
        }

        throw ValidationException::withMessages([
            'quantity' => [
                "Saldo insuficiente no armazém. Disponível: {$available}, solicitado: {$quantity}. Produto: {$product->name}.",
            ],
        ]);
    }

    private function lockWarehouseStock(int $warehouseId, int $productId, ?int $batchId): WarehouseStock
    {
        // Garante que o registro existe antes de adquirir lock, evitando TOCTOU
        WarehouseStock::firstOrCreate(
            ['warehouse_id' => $warehouseId, 'product_id' => $productId, 'batch_id' => $batchId],
            ['quantity' => 0],
        );

        return WarehouseStock::query()
            ->where('warehouse_id', $warehouseId)
            ->where('product_id', $productId)
            ->when($batchId !== null, fn ($q) => $q->where('batch_id', $batchId), fn ($q) => $q->whereNull('batch_id'))
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function explodeKit(Product $kit, float $quantity, int $warehouseId, StockMovementType $type, ?string $notes = null, ?User $user = null, int $depth = 0): void
    {
        if ($depth > 5) {
            Log::warning('Kit explosion exceeded max depth', ['kit_id' => $kit->id, 'depth' => $depth]);

            return;
        }

        $items = $kit->kitItems()->with('child')->get();

        foreach ($items as $item) {
            if (! $item->child) {
                continue;
            }

            $childQty = (float) bcmul((string) $item->quantity, (string) $quantity, 4);

            $this->createMovement(
                product: $item->child,
                type: $type,
                quantity: $childQty,
                warehouseId: $warehouseId,
                notes: "Automático: Explosão do Kit {$kit->name}. ".($notes ?? ''),
                user: $user,
                reference: "Kit: {$kit->id}",
            );
        }
    }

    /**
     * Gera o Kardex (histórico de movimentação) de um produto com saldo progressivo
     */
    public function getKardex(int $productId, int $warehouseId, ?string $dateFrom = null, ?string $dateTo = null)
    {
        $tenantId = app()->bound('current_tenant_id') ? (int) app('current_tenant_id') : null;

        if (! $tenantId) {
            return collect();
        }

        $runningBalance = 0;

        if ($dateFrom) {
            $priorBalance = StockMovement::where('tenant_id', $tenantId)
                ->where('product_id', $productId)
                ->where(function ($q) use ($warehouseId) {
                    $q->where('warehouse_id', $warehouseId)
                        ->orWhere(function ($q2) use ($warehouseId) {
                            $q2->where('type', 'transfer')
                                ->where('target_warehouse_id', $warehouseId);
                        });
                })
                ->where('created_at', '<', $dateFrom)
                ->selectRaw("
                    SUM(CASE
                        WHEN type = 'adjustment' THEN quantity
                        WHEN type IN ('entry','return') THEN quantity
                        WHEN type IN ('exit','reserve') THEN -quantity
                        WHEN type = 'transfer' AND warehouse_id = ? THEN -quantity
                        WHEN type = 'transfer' AND target_warehouse_id = ? THEN quantity
                        ELSE 0
                    END) as balance
                ", [$warehouseId, $warehouseId])
                ->value('balance');

            $runningBalance = (string) ($priorBalance ?? 0);
        }

        $query = StockMovement::where('tenant_id', $tenantId)
            ->where('product_id', $productId)
            ->where(function ($q) use ($warehouseId) {
                $q->where('warehouse_id', $warehouseId)
                    ->orWhere(function ($q2) use ($warehouseId) {
                        $q2->where('type', 'transfer')
                            ->where('target_warehouse_id', $warehouseId);
                    });
            })
            ->with(['batch', 'productSerial', 'createdByUser:id,name'])
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc');

        if ($dateFrom) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $movements = $query->get();

        return $movements->map(function ($movement) use (&$runningBalance, $warehouseId) {
            $type = $movement->type;
            $qty = (string) $movement->quantity;

            if ($type === StockMovementType::Adjustment) {
                $runningBalance = bcadd($runningBalance, $qty, 2);
            } elseif ($type === StockMovementType::Transfer) {
                // Se o armazém é origem → saída; se é destino → entrada
                if ((int) $movement->warehouse_id === $warehouseId) {
                    $runningBalance = bcsub($runningBalance, $qty, 2);
                } else {
                    $runningBalance = bcadd($runningBalance, $qty, 2);
                }
            } else {
                $sign = $type->affectsStock();
                $delta = bcmul($qty, (string) $sign, 2);
                $runningBalance = bcadd($runningBalance, $delta, 2);
            }

            return [
                'id' => $movement->id,
                'date' => $movement->created_at->toDateTimeString(),
                'type' => $type->value,
                'type_label' => $type->label(),
                'quantity' => (float) $qty,
                'batch' => $movement->batch?->code,
                'serial' => $movement->productSerial?->serial_number,
                'notes' => $movement->notes,
                'user' => $movement->createdByUser?->name,
                'balance' => (float) $runningBalance,
            ];
        });
    }
}
