<?php

namespace App\Models;

use App\Enums\WorkOrderStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property WorkOrderStatus|null $from_status
 * @property WorkOrderStatus|null $to_status
 */
class WorkOrderStatusHistory extends Model
{
    use BelongsToTenant, HasFactory;

    protected $table = 'work_order_status_history';

    protected $fillable = ['tenant_id', 'work_order_id', 'user_id', 'from_status', 'to_status', 'notes'];

    protected function casts(): array
    {
        return [
            'from_status' => WorkOrderStatus::class,
            'to_status' => WorkOrderStatus::class,
        ];

    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
