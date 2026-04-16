<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\PurchaseQuotationItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property numeric-string|null $quantity
 * @property numeric-string|null $unit_price
 * @property numeric-string|null $total
 */
class PurchaseQuotationItem extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<PurchaseQuotationItemFactory> */
    use HasFactory;

    protected $fillable = [
        'purchase_quotation_id',
        'product_id',
        'quantity',
        'unit_price',
        'total',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit_price' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function purchaseQuotation(): BelongsTo
    {
        return $this->belongsTo(PurchaseQuotation::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
