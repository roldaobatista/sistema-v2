<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property numeric-string|null $total
 * @property numeric-string|null $total_amount
 * @property Carbon|null $valid_until
 * @property Carbon|null $approved_at
 */
class PurchaseQuotation extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'reference', 'supplier_id', 'status',
        'total', 'total_amount', 'notes', 'valid_until',
        'created_by', 'requested_by', 'approved_by', 'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'total' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'valid_until' => 'date',
            'approved_at' => 'datetime',
        ];

    }

    public function setRequestedByAttribute(?int $value): void
    {
        $this->attributes['created_by'] = $value;
    }

    public function getRequestedByAttribute(): ?int
    {
        return isset($this->attributes['requested_by'])
            ? (int) $this->attributes['requested_by']
            : (isset($this->attributes['created_by']) ? (int) $this->attributes['created_by'] : null);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseQuotationItem::class);
    }
}
