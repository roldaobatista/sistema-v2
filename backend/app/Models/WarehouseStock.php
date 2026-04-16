<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @global Intentionally global */
class WarehouseStock extends Model
{
    use HasFactory;

    protected $fillable = [
        'warehouse_id',
        'product_id',
        'batch_id',
        'quantity',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }
}
