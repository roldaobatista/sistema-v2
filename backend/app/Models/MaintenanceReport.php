<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\MaintenanceReportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property array<int|string, mixed>|null $parts_replaced
 * @property array<int|string, mixed>|null $photo_evidence
 * @property bool|null $requires_calibration_after
 * @property bool|null $requires_ipem_verification
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 */
class MaintenanceReport extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<MaintenanceReportFactory> */
    use HasFactory;

    protected $fillable = [
        'tenant_id', 'work_order_id', 'equipment_id', 'performed_by', 'approved_by',
        'defect_found', 'probable_cause', 'corrective_action',
        'parts_replaced', 'seal_status', 'new_seal_number',
        'condition_before', 'condition_after',
        'requires_calibration_after', 'requires_ipem_verification',
        'notes', 'photo_evidence',
        'started_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'parts_replaced' => 'array',
            'photo_evidence' => 'array',
            'requires_calibration_after' => 'boolean',
            'requires_ipem_verification' => 'boolean',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<WorkOrder, $this>
     */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    /**
     * @return BelongsTo<Equipment, $this>
     */
    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
