<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Support\Decimal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property numeric-string|null $quantity
 * @property numeric-string|null $original_price
 * @property numeric-string|null $cost_price
 * @property numeric-string|null $unit_price
 * @property numeric-string|null $discount_percentage
 * @property numeric-string|null $subtotal
 */
class QuoteItem extends Model
{
    use BelongsToTenant, \Illuminate\Database\Eloquent\Factories\HasFactory;

    protected $appends = ['description'];

    protected $fillable = [
        'tenant_id', 'quote_id', 'quote_equipment_id', 'type', 'product_id', 'service_id',
        'custom_description', 'quantity', 'original_price', 'cost_price', 'unit_price',
        'discount_percentage', 'subtotal', 'sort_order', 'internal_note',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'original_price' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'discount_percentage' => 'decimal:2',
            'subtotal' => 'decimal:2',
        ];
    }

    public function marginPercentage(): float
    {
        $sub = (float) $this->subtotal;
        if ($sub <= 0) {
            return 0;
        }
        $cost = bcmul(Decimal::string($this->cost_price), Decimal::string($this->quantity), 2);

        return round((($sub - (float) $cost) / $sub) * 100, 1);
    }

    protected static function booted(): void
    {
        static::saving(function (self $item) {
            // Auto-popular quote_id a partir do equipment para manter integridade
            if (! $item->quote_id && $item->quote_equipment_id) {
                $item->quote_id = QuoteEquipment::where('id', $item->quote_equipment_id)->value('quote_id');
            }

            $price = Decimal::string($item->unit_price);
            if ((float) $item->discount_percentage > 0) {
                $discountFactor = bcsub('1', bcdiv(Decimal::string($item->discount_percentage), '100', 6), 6);
                $price = bcmul($price, $discountFactor, 6);
            }
            $item->subtotal = bcmul($price, Decimal::string($item->quantity), 2);
        });

        static::saved(function (self $item) {
            $item->quoteEquipment?->quote?->recalculateTotal();
        });

        static::deleted(function (self $item) {
            $item->quoteEquipment?->quote?->recalculateTotal();
        });
    }

    public function quoteEquipment(): BelongsTo
    {
        return $this->belongsTo(QuoteEquipment::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function getDescriptionAttribute(): ?string
    {
        if (! empty($this->custom_description)) {
            return $this->custom_description;
        }

        if ($this->product_id && $this->product) {
            return $this->product->name;
        }

        if ($this->service_id && $this->service) {
            return $this->service->name;
        }

        return null;
    }
}
