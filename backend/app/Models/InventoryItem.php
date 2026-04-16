<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property numeric-string|null $expected_quantity
 * @property numeric-string|null $counted_quantity
 * @property numeric-string|null $adjustment_quantity
 */
class InventoryItem extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'inventory_id',
        'product_id',
        'batch_id',
        'product_serial_id',
        'expected_quantity',
        'counted_quantity',
        'adjustment_quantity',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'expected_quantity' => 'decimal:4',
            'counted_quantity' => 'decimal:4',
            'adjustment_quantity' => 'decimal:4',
        ];
    }

    public function inventory(): BelongsTo
    {
        return $this->belongsTo(Inventory::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function productSerial(): BelongsTo
    {
        return $this->belongsTo(ProductSerial::class);
    }

    protected $appends = ['discrepancy'];

    public function getDiscrepancyAttribute(): float
    {
        if ($this->counted_quantity === null) {
            return 0;
        }

        return (float) $this->counted_quantity - (float) ($this->expected_quantity ?? 0);
    }
}
