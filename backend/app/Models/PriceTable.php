<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property numeric-string|null $multiplier
 * @property bool|null $is_default
 * @property bool|null $is_active
 * @property Carbon|null $valid_from
 * @property Carbon|null $valid_until
 */
class PriceTable extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'name', 'region', 'customer_type', 'multiplier',
        'is_default', 'is_active', 'valid_from', 'valid_until',
    ];

    protected function casts(): array
    {
        return [
            'multiplier' => 'decimal:4',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'valid_from' => 'date',
            'valid_until' => 'date',
        ];

    }

    public function items(): HasMany
    {
        return $this->hasMany(PriceTableItem::class);
    }

    public function getIsValidAttribute(): bool
    {
        $now = now()->toDateString();
        if ($this->valid_from && $this->valid_from > $now) {
            return false;
        }
        if ($this->valid_until && $this->valid_until < $now) {
            return false;
        }

        return true;
    }
}
