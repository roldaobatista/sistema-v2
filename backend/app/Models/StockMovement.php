<?php

namespace App\Models;

use App\Enums\StockMovementType;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int|null $product_id
 * @property int|null $work_order_id
 * @property int|null $warehouse_id
 * @property int|null $batch_id
 * @property int|null $product_serial_id
 * @property int|null $target_warehouse_id
 * @property int|null $created_by
 * @property StockMovementType $type
 * @property float $quantity
 * @property float|null $unit_cost
 * @property string|null $reference
 * @property string|null $notes
 * @property bool $scanned_via_qr
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Product|null $product
 * @property-read WorkOrder|null $workOrder
 * @property-read User|null $createdByUser
 */
class StockMovement extends Model
{
    use Auditable, BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'product_id',
        'work_order_id',
        'warehouse_id',
        'batch_id',
        'product_serial_id',
        'target_warehouse_id',
        'type',
        'quantity',
        'unit_cost',
        'reference',
        'notes',
        'created_by',
        'scanned_via_qr',
    ];

    protected function casts(): array
    {
        return [
            'type' => StockMovementType::class,
            'quantity' => 'decimal:2',
            'unit_cost' => 'decimal:2',
            'scanned_via_qr' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Alias para createdByUser - Mantido para compatibilidade
     */
    public function user(): BelongsTo
    {
        return $this->createdByUser();
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function targetWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'target_warehouse_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function productSerial(): BelongsTo
    {
        return $this->belongsTo(ProductSerial::class, 'product_serial_id');
    }

    protected static function booted(): void
    {
        static::created(function (StockMovement $movement) {
            $movement->applyToProductStock();
        });
    }

    /**
     * Aplica o delta desta movimentação ao stock_qty do produto.
     * Entry/Return incrementam; Exit/Reserve decrementam; Adjustment soma diretamente.
     */
    public function applyToProductStock(): void
    {
        $product = $this->product;
        if (! $product) {
            return;
        }

        if ($this->type === StockMovementType::Transfer) {
            $this->handleTransfer();
        } else {
            $this->handleRegularMovement();
        }

        // Atualiza saldo global cacheado no produto para compatibilidade e performance de listagem
        $product->update([
            'stock_qty' => WarehouseStock::where('product_id', $product->id)->sum('quantity'),
        ]);
    }

    protected function handleRegularMovement(): void
    {
        // Transfer é tratado em handleTransfer(), não deve entrar aqui
        if ($this->type === StockMovementType::Transfer) {
            return;
        }

        $direction = $this->type->affectsStock();
        // Adjustment (direction=0) → delta = quantity diretamente (permite + e -)
        // Entry/Return (direction=1) → delta positivo
        // Exit/Reserve (direction=-1) → delta negativo
        $delta = $direction === 0
            ? (float) $this->quantity
            : (float) $this->quantity * $direction;

        $stock = $this->lockStockRow($this->warehouse_id);

        $stock->increment('quantity', $delta);
    }

    protected function handleTransfer(): void
    {
        if (! $this->warehouse_id || ! $this->target_warehouse_id) {
            return;
        }

        // Saída do armazém de origem
        $sourceStock = $this->lockStockRow($this->warehouse_id);
        $sourceStock->decrement('quantity', (float) $this->quantity);

        // Entrada no armazém de destino
        $targetStock = $this->lockStockRow($this->target_warehouse_id);
        $targetStock->increment('quantity', (float) $this->quantity);
    }

    private function lockStockRow(?int $warehouseId): WarehouseStock
    {
        $batchId = $this->batch_id;

        $query = WarehouseStock::query()
            ->where('warehouse_id', $warehouseId)
            ->where('product_id', $this->product_id)
            ->when($batchId !== null, fn ($q) => $q->where('batch_id', $batchId), fn ($q) => $q->whereNull('batch_id'));

        $stock = (clone $query)->lockForUpdate()->first();
        if ($stock) {
            return $stock;
        }

        WarehouseStock::firstOrCreate(
            ['warehouse_id' => $warehouseId, 'product_id' => $this->product_id, 'batch_id' => $batchId],
            ['quantity' => 0],
        );

        return $query->lockForUpdate()->firstOrFail();
    }
}
