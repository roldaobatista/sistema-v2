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
 * @property Carbon|null $approved_at
 */
class MaterialRequest extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'reference', 'requester_id', 'work_order_id', 'warehouse_id',
        'status', 'priority', 'justification', 'rejection_reason', 'approved_by', 'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
        ];

    }

    public function items(): HasMany
    {
        return $this->hasMany(MaterialRequestItem::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
