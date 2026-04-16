<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $due_date
 * @property Carbon|null $completed_at
 * @property Carbon|null $verified_at
 */
class QualityCorrectiveAction extends Model
{
    use BelongsToTenant, HasFactory;

    public const STATUS_OPEN = 'open';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_VERIFIED = 'verified';

    protected $fillable = [
        'tenant_id',
        'quality_audit_id',
        'quality_audit_item_id',
        'description',
        'root_cause',
        'action_taken',
        'responsible_id',
        'due_date',
        'completed_at',
        'verified_at',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'completed_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    public function audit(): BelongsTo
    {
        return $this->belongsTo(QualityAudit::class, 'quality_audit_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(QualityAuditItem::class, 'quality_audit_item_id');
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
