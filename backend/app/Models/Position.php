<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property bool|null $is_active
 */
class Position extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'department_id',
        'level',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];

    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return HasMany<SkillRequirement, $this>
     */
    public function skillRequirements(): HasMany
    {
        return $this->hasMany(SkillRequirement::class);
    }
}
