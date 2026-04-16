<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\ProjectResourceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property numeric-string|null $allocation_percent
 * @property Carbon|null $start_date
 * @property Carbon|null $end_date
 * @property numeric-string|null $hourly_rate
 * @property numeric-string|null $total_hours_planned
 * @property numeric-string|null $total_hours_logged
 */
class ProjectResource extends Model
{
    use Auditable, BelongsToTenant;

    /** @use HasFactory<ProjectResourceFactory> */
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'project_id',
        'user_id',
        'role',
        'allocation_percent',
        'start_date',
        'end_date',
        'hourly_rate',
        'total_hours_planned',
        'total_hours_logged',
    ];

    protected function casts(): array
    {
        return [
            'allocation_percent' => 'decimal:2',
            'start_date' => 'date',
            'end_date' => 'date',
            'hourly_rate' => 'decimal:2',
            'total_hours_planned' => 'decimal:2',
            'total_hours_logged' => 'decimal:2',
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
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<ProjectTimeEntry, $this>
     */
    public function timeEntries(): HasMany
    {
        return $this->hasMany(ProjectTimeEntry::class);
    }
}
