<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $applied_at
 */
class SealApplication extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'work_order_id',
        'equipment_id',
        'seal_number',
        'location',
        'applied_by',
        'applied_at',
    ];

    protected function casts(): array
    {
        return [
            'applied_at' => 'datetime',
        ];
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function appliedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applied_by');
    }
}
