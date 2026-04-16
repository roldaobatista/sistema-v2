<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\AccreditationScopeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property array<int, string>|null $equipment_categories
 * @property Carbon|null $valid_until
 * @property bool $is_active
 * @property Carbon|null $valid_from
 */
class AccreditationScope extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<AccreditationScopeFactory> */
    use HasFactory;

    protected $fillable = [
        'tenant_id', 'accreditation_number', 'accrediting_body',
        'scope_description', 'equipment_categories',
        'valid_from', 'valid_until', 'certificate_file', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'equipment_categories' => 'array',
            'valid_from' => 'date',
            'valid_until' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->where('valid_until', '>=', now()->toDateString());
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForCategory(Builder $query, string $category): Builder
    {
        return $query->whereJsonContains('equipment_categories', $category);
    }

    public function coversCategory(string $category): bool
    {
        return in_array($category, $this->equipment_categories ?? [], true);
    }

    public function isExpired(): bool
    {
        return $this->valid_until->isPast();
    }

    public function isValid(): bool
    {
        return $this->is_active && ! $this->isExpired();
    }

    /**
     * @return HasMany<EquipmentCalibration, $this>
     */
    public function calibrations(): HasMany
    {
        return $this->hasMany(EquipmentCalibration::class);
    }
}
