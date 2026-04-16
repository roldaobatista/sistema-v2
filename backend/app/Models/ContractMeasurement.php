<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property array<int|string, mixed>|null $items
 * @property numeric-string|null $total_accepted
 * @property numeric-string|null $total_rejected
 */
class ContractMeasurement extends Model
{
    use Auditable, BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id', 'contract_id', 'period', 'items', 'total_accepted', 'total_rejected', 'notes', 'status', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'items' => 'json',
            'total_accepted' => 'decimal:2',
            'total_rejected' => 'decimal:2',
        ];

    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }
}
