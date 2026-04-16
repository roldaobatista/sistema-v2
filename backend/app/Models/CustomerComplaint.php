<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $resolved_at
 * @property Carbon|null $response_due_at
 * @property Carbon|null $responded_at
 */
class CustomerComplaint extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id', 'customer_id', 'work_order_id', 'equipment_id',
        'description', 'category', 'severity', 'status', 'resolution',
        'assigned_to', 'resolved_at', 'response_due_at', 'responded_at',
    ];

    protected function casts(): array
    {
        return [
            'resolved_at' => 'date',
            'response_due_at' => 'date',
            'responded_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class, 'equipment_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function correctiveActions(): HasMany
    {
        return $this->hasMany(CorrectiveAction::class, 'sourceable_id')
            ->where('sourceable_type', self::class);
    }
}
