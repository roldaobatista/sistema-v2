<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $name
 * @property float $multiplier
 * @property string|null $applies_to_role
 * @property string|null $applies_to_calculation_type
 * @property bool $active
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class CommissionCampaign extends Model
{
    use Auditable, BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id', 'name', 'multiplier',
        'applies_to_role', 'applies_to_calculation_type',
        'starts_at', 'ends_at', 'active',
    ];

    protected function casts(): array
    {
        return [
            'multiplier' => 'decimal:4',
            'starts_at' => 'date',
            'ends_at' => 'date',
            'active' => 'boolean',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('active', true)
            ->where('starts_at', '<=', now()->toDateString())
            ->where('ends_at', '>=', now()->toDateString());
    }

    public function isCurrentlyActive(): bool
    {
        return $this->active
            && $this->starts_at <= now()
            && $this->ends_at >= now();
    }
}
