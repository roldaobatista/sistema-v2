<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $period_start
 * @property Carbon|null $period_end
 * @property numeric-string|null $target_revenue
 * @property numeric-string|null $achieved_revenue
 * @property int|null $target_deals
 * @property int|null $target_new_customers
 * @property int|null $target_activities
 * @property int|null $achieved_deals
 * @property int|null $achieved_new_customers
 * @property int|null $achieved_activities
 */
class CrmSalesGoal extends Model
{
    use Auditable, BelongsToTenant;

    protected $table = 'crm_sales_goals';

    protected $fillable = [
        'tenant_id', 'user_id', 'territory_id', 'period_type',
        'period_start', 'period_end', 'target_revenue', 'target_deals',
        'target_new_customers', 'target_activities', 'achieved_revenue',
        'achieved_deals', 'achieved_new_customers', 'achieved_activities',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'target_revenue' => 'decimal:2',
            'achieved_revenue' => 'decimal:2',
            'target_deals' => 'integer',
            'target_new_customers' => 'integer',
            'target_activities' => 'integer',
            'achieved_deals' => 'integer',
            'achieved_new_customers' => 'integer',
            'achieved_activities' => 'integer',
        ];
    }

    public const PERIOD_TYPES = [
        'weekly' => 'Semanal',
        'monthly' => 'Mensal',
        'quarterly' => 'Trimestral',
        'yearly' => 'Anual',
    ];

    // ─── Methods ────────────────────────────────────────

    public function revenueProgress(): float
    {
        if ($this->target_revenue <= 0) {
            return 0;
        }

        return round(($this->achieved_revenue / $this->target_revenue) * 100, 2);
    }

    public function dealsProgress(): float
    {
        if ($this->target_deals <= 0) {
            return 0;
        }

        return round(($this->achieved_deals / $this->target_deals) * 100, 2);
    }

    public function isAchieved(): bool
    {
        return $this->achieved_revenue >= $this->target_revenue
            && $this->achieved_deals >= $this->target_deals;
    }

    // ─── Relationships ──────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function territory(): BelongsTo
    {
        return $this->belongsTo(CrmTerritory::class, 'territory_id');
    }
}
