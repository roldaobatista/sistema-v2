<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property array<int|string, mixed>|null $default_tasks
 * @property bool|null $is_active
 */
class OnboardingTemplate extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id', 'name', 'type', 'default_tasks', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'default_tasks' => 'array',
            'is_active' => 'boolean',
        ];

    }

    public function checklists(): HasMany
    {
        return $this->hasMany(OnboardingChecklist::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
