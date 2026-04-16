<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property numeric-string|null $new_value
 * @property Carbon|null $new_end_date
 * @property Carbon|null $effective_date
 * @property Carbon|null $approved_at
 */
class ContractAddendum extends Model
{
    use Auditable, BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id', 'contract_id', 'type', 'description', 'new_value', 'new_end_date', 'effective_date', 'status', 'created_by', 'approved_by', 'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'new_value' => 'decimal:2',
            'new_end_date' => 'date',
            'effective_date' => 'date',
            'approved_at' => 'datetime',
        ];

    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }
}
