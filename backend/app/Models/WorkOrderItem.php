<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Services\StockService;
use App\Support\Decimal;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int|null $work_order_id
 * @property numeric-string|null $quantity
 * @property numeric-string|null $unit_price
 * @property numeric-string|null $cost_price
 * @property numeric-string|null $discount
 * @property numeric-string|null $total
 */
class WorkOrderItem extends Model
{
    use BelongsToTenant, HasFactory;

    public const TYPE_PRODUCT = 'product';

    public const TYPE_SERVICE = 'service';

    protected $fillable = [
        'tenant_id',
        'work_order_id',
        'type',
        'reference_id',
        'description',
        'quantity',
        'unit_price',
        'cost_price',
        'discount',
        'total',
        'warehouse_id',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'discount' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        // Auto-calcula total ao salvar
        static::saving(function (self $item) {
            $subtotal = bcmul(Decimal::string($item->quantity), Decimal::string($item->unit_price), 2);
            $result = bcsub($subtotal, Decimal::string($item->discount), 2);
            $item->total = bccomp($result, '0', 2) < 0 ? '0.00' : $result;
        });

        // Auto-popular cost_price a partir do Product
        static::creating(function (self $item) {
            if ($item->type === self::TYPE_PRODUCT && $item->reference_id && ! $item->cost_price) {
                $item->cost_price = Decimal::string(Product::where('id', $item->reference_id)->value('cost_price'));
            }
        });

        // Recalcula total da OS + controle de estoque via StockService
        static::created(function (self $item) {
            $item->workOrder?->recalculateTotal();
            if ($item->type === self::TYPE_PRODUCT && $item->reference_id) {
                $product = Product::find($item->reference_id);
                if ($product && $product->track_stock) {
                    $stockService = app(StockService::class);
                    $stockService->reserve($product, (float) $item->quantity, $item->workOrder, $item->warehouse_id);
                    static::createUsedStockItemIfTechnician($item, (float) $item->quantity);
                }
            }
        });

        static::updated(function (self $item) {
            $item->workOrder?->recalculateTotal();

            // Detecta mudanças relevantes para estoque
            if ($item->isDirty(['type', 'reference_id', 'quantity'])) {
                /** @var StockService $stockService */
                $stockService = app(StockService::class);

                $oldType = $item->getOriginal('type');
                $oldRefId = $item->getOriginal('reference_id');
                $oldQty = (float) $item->getOriginal('quantity');

                $newType = $item->type;
                $newRefId = $item->reference_id;
                $newQty = (float) $item->quantity;

                // 1. Se mudou o PRODUTO (ID ou Tipo) -> Estorna o anterior COMPLETO
                if ($oldType === self::TYPE_PRODUCT && $oldRefId && ($oldRefId != $newRefId || $oldType != $newType)) {
                    $oldProduct = Product::find($oldRefId);
                    if ($oldProduct && $oldProduct->track_stock) {
                        $stockService->returnStock($oldProduct, $oldQty, $item->workOrder);
                        UsedStockItem::where('work_order_item_id', $item->id)->delete();
                    }
                }

                // 2. Se mudou o PRODUTO (ID ou Tipo) -> Reserva o novo COMPLETO
                if ($newType === self::TYPE_PRODUCT && $newRefId && ($oldRefId != $newRefId || $oldType != $newType)) {
                    $newProduct = Product::find($newRefId);
                    if ($newProduct && $newProduct->track_stock) {
                        $stockService->reserve($newProduct, $newQty, $item->workOrder);
                        static::createUsedStockItemIfTechnician($item, $newQty);
                    }
                }

                // 3. Se é o MESMO produto e só mudou a QUANTIDADE -> Ajusta a diferença
                if ($newType === self::TYPE_PRODUCT && $newRefId && $oldRefId == $newRefId && $oldType == $newType) {
                    $product = Product::find($newRefId);
                    if ($product && $product->track_stock) {
                        $diff = $newQty - $oldQty;
                        if ($diff > 0) {
                            $stockService->reserve($product, $diff, $item->workOrder);
                        } elseif ($diff < 0) {
                            $stockService->returnStock($product, abs($diff), $item->workOrder);
                        }
                        static::syncUsedStockItemQuantity($item, $newQty);
                    }
                }
            }
        });

        static::deleted(function (self $item) {
            $item->workOrder?->recalculateTotal();
            if ($item->type === self::TYPE_PRODUCT && $item->reference_id) {
                $product = Product::find($item->reference_id);
                if ($product && $product->track_stock) {
                    app(StockService::class)->returnStock($product, (float) $item->quantity, $item->workOrder);
                }
                UsedStockItem::where('work_order_item_id', $item->id)->delete();
            }
        });
    }

    protected static function createUsedStockItemIfTechnician(self $item, float $quantity): void
    {
        $stockService = app(StockService::class);
        $warehouseId = $item->warehouse_id ?? $stockService->resolveWarehouseIdForWorkOrder($item->workOrder);
        if (! $warehouseId) {
            return;
        }
        $warehouse = Warehouse::find($warehouseId);
        if (! $warehouse || ! $warehouse->isTechnician()) {
            return;
        }
        UsedStockItem::updateOrCreate(
            ['work_order_item_id' => $item->id],
            [
                'tenant_id' => $item->tenant_id,
                'work_order_id' => $item->work_order_id,
                'product_id' => $item->reference_id,
                'technician_warehouse_id' => $warehouseId,
                'quantity' => $quantity,
                'status' => UsedStockItem::STATUS_PENDING_RETURN,
            ]
        );
    }

    protected static function syncUsedStockItemQuantity(self $item, float $quantity): void
    {
        if ($item->type !== self::TYPE_PRODUCT || ! $item->reference_id) {
            return;
        }
        UsedStockItem::where('work_order_item_id', $item->id)->update(['quantity' => $quantity]);
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'reference_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'reference_id');
    }
}
