<?php

namespace App\Models;

use App\Enums\DebtRenegotiationStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property numeric-string|null $original_total
 * @property numeric-string|null $negotiated_total
 * @property numeric-string|null $discount_amount
 * @property numeric-string|null $interest_amount
 * @property numeric-string|null $fine_amount
 * @property int|null $new_installments
 * @property Carbon|null $first_due_date
 * @property Carbon|null $approved_at
 * @property DebtRenegotiationStatus|null $status
 */
class DebtRenegotiation extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'customer_id', 'description', 'original_total', 'negotiated_total',
        'discount_amount', 'interest_amount', 'fine_amount',
        'new_installments', 'first_due_date', 'notes', 'status',
        'created_by', 'approved_by', 'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'original_total' => 'decimal:2',
            'negotiated_total' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'interest_amount' => 'decimal:2',
            'fine_amount' => 'decimal:2',
            'new_installments' => 'integer',
            'first_due_date' => 'date',
            'approved_at' => 'datetime',
            'status' => DebtRenegotiationStatus::class,
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return HasMany<DebtRenegotiationItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(DebtRenegotiationItem::class);
    }
}
