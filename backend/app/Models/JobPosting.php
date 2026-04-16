<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property numeric-string|null $salary_range_min
 * @property numeric-string|null $salary_range_max
 * @property Carbon|null $opened_at
 * @property Carbon|null $closed_at
 */
class JobPosting extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'title',
        'department_id',
        'position_id',
        'description',
        'requirements',
        'salary_range_min',
        'salary_range_max',
        'status',
        'opened_at',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'salary_range_min' => 'decimal:2',
            'salary_range_max' => 'decimal:2',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];

    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function candidates(): HasMany
    {
        return $this->hasMany(Candidate::class);
    }
}
