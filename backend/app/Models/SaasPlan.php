<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Database\Factories\SaasPlanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property array<int, string>|null $modules
 *
 * @global Intentionally global
 */
class SaasPlan extends Model
{
    use Auditable;

    /** @use HasFactory<SaasPlanFactory> */
    use HasFactory;

    protected $fillable = [
        'name', 'slug', 'description', 'monthly_price', 'annual_price',
        'modules', 'max_users', 'max_work_orders_month', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'modules' => 'array',
            'monthly_price' => 'decimal:2',
            'annual_price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<SaasSubscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(SaasSubscription::class, 'plan_id');
    }

    /**
     * @return HasMany<Tenant, $this>
     */
    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class, 'current_plan_id');
    }

    public function hasModule(string $module): bool
    {
        return in_array($module, is_array($this->modules) ? $this->modules : [], true);
    }

    public function getPriceForCycle(string $cycle): string
    {
        return $cycle === 'annual'
            ? (string) $this->annual_price
            : (string) $this->monthly_price;
    }
}
