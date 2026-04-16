<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property numeric-string|null $quantity
 * @property Carbon|null $disposed_at
 */
class EcologicalDisposal extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'product_id', 'quantity', 'disposal_method',
        'certificate_number', 'disposal_company', 'status',
        'disposed_at', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'disposed_at' => 'datetime',
        ];

    }
}
