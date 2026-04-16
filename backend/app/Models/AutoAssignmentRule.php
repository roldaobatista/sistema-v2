<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property array<int|string, mixed>|null $conditions
 * @property array<int|string, mixed>|null $technician_ids
 * @property array<int|string, mixed>|null $required_skills
 * @property bool|null $is_active
 * @property int|null $priority
 */
class AutoAssignmentRule extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $appends = [
        'criteria',
        'action',
    ];

    protected $fillable = [
        'tenant_id',
        'name',
        'entity_type',
        'strategy',
        'conditions',
        'technician_ids',
        'required_skills',
        'priority',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'conditions' => 'array',
            'technician_ids' => 'array',
            'required_skills' => 'array',
            'is_active' => 'boolean',
            'priority' => 'integer',
        ];

    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return array<int|string, mixed>
     */
    public function getCriteriaAttribute(): array
    {
        return $this->conditions ?? [];
    }

    /**
     * @return array<int|string, mixed>
     */
    public function getActionAttribute(): array
    {
        return [
            'assignTo' => match ($this->strategy) {
                'proximity' => 'closest',
                'least_loaded' => 'least_loaded',
                'skill_match' => 'skill_match',
                default => 'round_robin',
            },
        ];
    }
}
