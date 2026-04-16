<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $account_receivable_id
 * @property numeric-string|null $original_amount
 */
class DebtRenegotiationItem extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'debt_renegotiation_id', 'account_receivable_id', 'original_amount',
    ];

    protected function casts(): array
    {
        return ['original_amount' => 'decimal:2'];
    }

    public function renegotiation(): BelongsTo
    {
        return $this->belongsTo(DebtRenegotiation::class);
    }

    public function receivable(): BelongsTo
    {
        return $this->belongsTo(AccountReceivable::class, 'account_receivable_id');
    }
}
