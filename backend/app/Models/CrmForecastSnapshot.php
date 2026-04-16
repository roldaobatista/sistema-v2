<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $snapshot_date
 * @property Carbon|null $period_start
 * @property Carbon|null $period_end
 * @property numeric-string|null $pipeline_value
 * @property numeric-string|null $weighted_value
 * @property numeric-string|null $best_case
 * @property numeric-string|null $worst_case
 * @property numeric-string|null $committed
 * @property numeric-string|null $won_value
 * @property int|null $deal_count
 * @property int|null $won_count
 * @property array<int|string, mixed>|null $by_stage
 * @property array<int|string, mixed>|null $by_user
 */
class CrmForecastSnapshot extends Model
{
    use Auditable, BelongsToTenant;

    protected $table = 'crm_forecast_snapshots';

    protected $fillable = [
        'tenant_id', 'snapshot_date', 'period_type', 'period_start',
        'period_end', 'pipeline_value', 'weighted_value', 'best_case',
        'worst_case', 'committed', 'deal_count', 'won_value', 'won_count',
        'by_stage', 'by_user',
    ];

    protected function casts(): array
    {
        return [
            'snapshot_date' => 'date',
            'period_start' => 'date',
            'period_end' => 'date',
            'pipeline_value' => 'decimal:2',
            'weighted_value' => 'decimal:2',
            'best_case' => 'decimal:2',
            'worst_case' => 'decimal:2',
            'committed' => 'decimal:2',
            'won_value' => 'decimal:2',
            'deal_count' => 'integer',
            'won_count' => 'integer',
            'by_stage' => 'array',
            'by_user' => 'array',
        ];
    }

    public const PERIOD_TYPES = [
        'monthly' => 'Mensal',
        'quarterly' => 'Trimestral',
        'yearly' => 'Anual',
    ];
}
