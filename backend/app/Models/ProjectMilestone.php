<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\ProjectMilestoneFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $planned_start
 * @property Carbon|null $planned_end
 * @property Carbon|null $actual_start
 * @property Carbon|null $actual_end
 * @property numeric-string|null $billing_value
 * @property numeric-string|null $billing_percent
 * @property numeric-string|null $weight
 * @property array<int|string, mixed>|null $dependencies
 * @property Carbon|null $completed_at
 */
class ProjectMilestone extends Model
{
    use Auditable, BelongsToTenant;

    /** @use HasFactory<ProjectMilestoneFactory> */
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'project_id',
        'name',
        'status',
        'order',
        'planned_start',
        'planned_end',
        'actual_start',
        'actual_end',
        'billing_value',
        'billing_percent',
        'invoice_id',
        'weight',
        'dependencies',
        'deliverables',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'planned_start' => 'date',
            'planned_end' => 'date',
            'actual_start' => 'date',
            'actual_end' => 'date',
            'billing_value' => 'decimal:2',
            'billing_percent' => 'decimal:2',
            'weight' => 'decimal:2',
            'dependencies' => 'array',
            'completed_at' => 'datetime',
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
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * @return HasMany<ProjectTimeEntry, $this>
     */
    public function timeEntries(): HasMany
    {
        return $this->hasMany(ProjectTimeEntry::class, 'milestone_id');
    }
}
