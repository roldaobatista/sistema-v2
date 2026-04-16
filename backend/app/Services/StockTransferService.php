<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Models\Notification;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class StockTransferService
{
    public function createTransfer(
        int $fromWarehouseId,
        int $toWarehouseId,
        array $items,
        ?string $notes = null,
        ?int $createdBy = null
    ): StockTransfer {
        $from = Warehouse::findOrFail($fromWarehouseId);
        $to = Warehouse::findOrFail($toWarehouseId);
        $tenantId = $from->tenant_id;
        $createdBy = $createdBy ?? auth()->id();

        $this->validateWarehouses($from, $to, $tenantId);

        return DB::transaction(function () use ($from, $to, $items, $notes, $createdBy, $tenantId, $fromWarehouseId) {
            // Validação de saldo dentro da transação para evitar race condition TOCTOU
            $this->validateItemsAndBalanceWithLock($fromWarehouseId, $items, $tenantId);
            $toUserId = $this->resolveToUserId($to, $from);
            $requiresAcceptance = $toUserId !== null || ($from->isVehicle() && $to->isCentral());
            $status = $requiresAcceptance
                ? StockTransfer::STATUS_PENDING_ACCEPTANCE
                : StockTransfer::STATUS_ACCEPTED;

            $transfer = StockTransfer::create([
                'tenant_id' => $tenantId,
                'from_warehouse_id' => $from->id,
                'to_warehouse_id' => $to->id,
                'status' => $status,
                'notes' => $notes,
                'to_user_id' => $toUserId,
                'created_by' => $createdBy,
            ]);

            foreach ($items as $row) {
                StockTransferItem::create([
                    'stock_transfer_id' => $transfer->id,
                    'product_id' => $row['product_id'],
                    'quantity' => $row['quantity'],
                ]);
            }

            if ($status === StockTransfer::STATUS_ACCEPTED) {
                $this->applyTransfer($transfer);
            } else {
                $this->notifyTransferPending($transfer, $from, $to);
            }

            return $transfer->load(['items.product', 'fromWarehouse', 'toWarehouse', 'toUser']);
        });
    }

    public function acceptTransfer(StockTransfer $transfer, int $acceptedBy): StockTransfer
    {
        $this->authorizeAccept($transfer, $acceptedBy);

        return DB::transaction(function () use ($transfer, $acceptedBy) {
            $locked = StockTransfer::lockForUpdate()->findOrFail($transfer->id);

            if ($locked->status !== StockTransfer::STATUS_PENDING_ACCEPTANCE) {
                throw ValidationException::withMessages(['transfer' => ['Esta transferência não está pendente de aceite.']]);
            }

            $locked->load('items');
            $this->validateItemsAndBalanceWithLock($locked->from_warehouse_id, $locked->items->toArray(), $locked->tenant_id);

            $locked->update([
                'status' => StockTransfer::STATUS_ACCEPTED,
                'accepted_at' => now(),
                'accepted_by' => $acceptedBy,
                'rejected_at' => null,
                'rejected_by' => null,
                'rejection_reason' => null,
            ]);
            $this->applyTransfer($locked);

            return $locked->fresh(['items.product', 'fromWarehouse', 'toWarehouse']);
        });
    }

    public function rejectTransfer(StockTransfer $transfer, int $rejectedBy, ?string $reason = null): StockTransfer
    {
        $this->authorizeReject($transfer, $rejectedBy);

        return DB::transaction(function () use ($transfer, $rejectedBy, $reason) {
            $locked = StockTransfer::lockForUpdate()->findOrFail($transfer->id);

            if ($locked->status !== StockTransfer::STATUS_PENDING_ACCEPTANCE) {
                throw ValidationException::withMessages(['transfer' => ['Esta transferência não está pendente de aceite.']]);
            }

            $locked->update([
                'status' => StockTransfer::STATUS_REJECTED,
                'rejected_at' => now(),
                'rejected_by' => $rejectedBy,
                'rejection_reason' => $reason,
            ]);

            return $locked->fresh(['items.product', 'fromWarehouse', 'toWarehouse']);
        });
    }

    protected function applyTransfer(StockTransfer $transfer): void
    {
        $fromId = $transfer->from_warehouse_id;
        $toId = $transfer->to_warehouse_id;
        $userId = $transfer->accepted_by ?? $transfer->created_by;

        foreach ($transfer->items as $item) {
            $qty = (float) $item->quantity;
            $product = Product::where('tenant_id', $transfer->tenant_id)->find($item->product_id);
            if (! $product) {
                throw ValidationException::withMessages([
                    'items' => ["Produto ID {$item->product_id} não encontrado neste tenant."],
                ]);
            }

            $source = $this->lockWarehouseStock($fromId, $product->id);
            $balance = $source ? (float) $source->quantity : 0;
            if ($balance < $qty) {
                throw ValidationException::withMessages([
                    'items' => ["Saldo insuficiente para o produto {$product->name} no armazém de origem."],
                ]);
            }

            StockMovement::create([
                'tenant_id' => $transfer->tenant_id,
                'product_id' => $product->id,
                'warehouse_id' => $fromId,
                'target_warehouse_id' => $toId,
                'batch_id' => null,
                'type' => StockMovementType::Transfer,
                'quantity' => $qty,
                'unit_cost' => $product->cost_price ?? 0,
                'reference' => "Transferência #{$transfer->id}",
                'notes' => $transfer->notes,
                'created_by' => $userId,
            ]);
        }
    }

    protected function resolveToUserId(Warehouse $to, Warehouse $from): ?int
    {
        if ($to->isTechnician() && $to->user_id) {
            return $to->user_id;
        }
        if ($to->isVehicle() && $to->vehicle_id && $to->vehicle) {
            return $to->vehicle->assigned_user_id;
        }
        if ($from->isVehicle() && $to->isCentral()) {
            return null;
        }

        return null;
    }

    protected function validateWarehouses(Warehouse $from, Warehouse $to, int $tenantId): void
    {
        if ($from->id === $to->id) {
            throw ValidationException::withMessages(['to_warehouse_id' => ['Origem e destino devem ser diferentes.']]);
        }
        if ($from->tenant_id !== $tenantId || $to->tenant_id !== $tenantId) {
            throw ValidationException::withMessages(['warehouses' => ['Armazéns devem pertencer ao tenant.']]);
        }
    }

    protected function validateItemsAndBalance(int $fromWarehouseId, array $items, int $tenantId): void
    {
        foreach ($items as $row) {
            $productId = $row['product_id'];
            $qty = (float) ($row['quantity'] ?? 0);
            if ($qty <= 0) {
                throw ValidationException::withMessages(['items' => ['Quantidade deve ser maior que zero.']]);
            }
            $stock = WarehouseStock::where('warehouse_id', $fromWarehouseId)
                ->where('product_id', $productId)
                ->first();
            $balance = $stock ? (float) $stock->quantity : 0;
            if ($balance < $qty) {
                $product = Product::find($productId);
                $name = $product ? $product->name : "ID {$productId}";

                throw ValidationException::withMessages([
                    'items' => ["Saldo insuficiente para {$name} no armazém de origem (disponível: {$balance})."],
                ]);
            }
        }
    }

    protected function validateItemsAndBalanceWithLock(int $fromWarehouseId, array $items, int $tenantId): void
    {
        foreach ($items as $row) {
            $productId = $row['product_id'];
            $qty = (float) ($row['quantity'] ?? 0);
            if ($qty <= 0) {
                throw ValidationException::withMessages(['items' => ['Quantidade deve ser maior que zero.']]);
            }
            $stock = $this->lockWarehouseStock($fromWarehouseId, $productId);
            $balance = (float) $stock->quantity;
            if ($balance < $qty) {
                $product = Product::find($productId);
                $name = $product ? $product->name : "ID {$productId}";

                throw ValidationException::withMessages([
                    'items' => ["Saldo insuficiente para {$name} no armazém de origem (disponível: {$balance})."],
                ]);
            }
        }
    }

    protected function authorizeAccept(StockTransfer $transfer, int $userId): void
    {
        if ($transfer->to_user_id === $userId) {
            return;
        }
        $user = User::find($userId);
        if ($user && $user->hasRole('estoquista')) {
            $from = $transfer->fromWarehouse;
            if ($from && $from->isVehicle()) {
                return;
            }
        }

        throw ValidationException::withMessages(['transfer' => ['Você não pode aceitar esta transferência.']]);
    }

    protected function authorizeReject(StockTransfer $transfer, int $userId): void
    {
        $this->authorizeAccept($transfer, $userId);
    }

    protected function lockWarehouseStock(int $warehouseId, int $productId): WarehouseStock
    {
        // Garante que o registro existe antes de adquirir lock, evitando TOCTOU
        WarehouseStock::firstOrCreate(
            ['warehouse_id' => $warehouseId, 'product_id' => $productId, 'batch_id' => null],
            ['quantity' => 0],
        );

        return WarehouseStock::query()
            ->where('warehouse_id', $warehouseId)
            ->where('product_id', $productId)
            ->whereNull('batch_id')
            ->lockForUpdate()
            ->firstOrFail();
    }

    protected function notifyTransferPending(StockTransfer $transfer, Warehouse $from, Warehouse $to): void
    {
        try {
            if ($transfer->to_user_id) {
                Notification::notify(
                    $transfer->tenant_id,
                    $transfer->to_user_id,
                    'stock_transfer_pending_acceptance',
                    "Transferência de estoque pendente de seu aceite (#{$transfer->id})",
                    [
                        'message' => "De: {$from->name} → Para: {$to->name}",
                        'link' => "/estoque/transferencias/{$transfer->id}",
                        'data' => ['stock_transfer_id' => $transfer->id],
                    ]
                );
            }

            if ($from->isVehicle() && $to->isTechnician()) {
                $this->notifyEstoquistas($transfer, $from, $to);
            }
            if ($from->isVehicle() && $to->isCentral()) {
                $this->notifyEstoquistas($transfer, $from, $to);
            }
        } catch (\Throwable $e) {
            Log::warning('StockTransferService: notifyTransferPending failed', [
                'transfer_id' => $transfer->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function notifyEstoquistas(StockTransfer $transfer, Warehouse $from, Warehouse $to): void
    {
        $users = User::where('tenant_id', $transfer->tenant_id)
            ->role('estoquista')
            ->pluck('id');
        foreach ($users as $uid) {
            Notification::notify(
                $transfer->tenant_id,
                $uid,
                'stock_transfer_vehicle_to_technician',
                "Transferência de estoque: {$from->name} → {$to->name} (#{$transfer->id})",
                [
                    'message' => 'Transferência criada; acompanhe em Transferências.',
                    'link' => "/estoque/transferencias/{$transfer->id}",
                    'data' => ['stock_transfer_id' => $transfer->id],
                ]
            );
        }
    }
}
