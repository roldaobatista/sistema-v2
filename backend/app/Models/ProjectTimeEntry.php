<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\ProjectTimeEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $date
 * @property numeric-string|null $hours
 * @property bool|null $billable
 */
class ProjectTimeEntry extends Model
{
    use Auditable, BelongsToTenant;

    /** @use HasFactory<ProjectTimeEntryFactory> */
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'project_id',
        'project_resource_id',
        'milestone_id',
        'work_order_id',
        'date',
        'hours',
        'description',
        'billable',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'hours' => 'decimal:2',
            'billable' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<ProjectResource, $this>
     */
    public function resource(): BelongsTo
    {
        return $this->belongsTo(ProjectResource::class, 'project_resource_id');
    }

    /**
     * @return BelongsTo<ProjectMilestone, $this>
     */
    public function milestone(): BelongsTo
    {
        return $this->belongsTo(ProjectMilestone::class, 'milestone_id');
    }

    /**
     * @return BelongsTo<WorkOrder, $this>
     */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }
}
