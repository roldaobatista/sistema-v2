<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property array<int|string, mixed>|null $regions
 * @property array<int|string, mixed>|null $zip_code_ranges
 * @property bool|null $is_active
 */
class CrmTerritory extends Model
{
    use Auditable, BelongsToTenant, SoftDeletes;

    protected $table = 'crm_territories';

    protected $fillable = [
        'tenant_id', 'name', 'description', 'regions',
        'zip_code_ranges', 'manager_id', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'regions' => 'array',
            'zip_code_ranges' => 'array',
            'is_active' => 'boolean',
        ];
    }

    // ─── Scopes ─────────────────────────────────────────

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    // ─── Relationships ──────────────────────────────────

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(CrmTerritoryMember::class, 'territory_id');
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class, 'territory_id');
    }
}
