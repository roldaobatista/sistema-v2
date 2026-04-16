<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int|null $visits_count
 * @property int|null $deals_won
 * @property numeric-string|null $deals_value
 * @property int|null $new_clients
 * @property int|null $activities_count
 * @property float|null $coverage_percent
 * @property float|null $csat_avg
 * @property int|null $commitments_on_time
 * @property int|null $commitments_total
 * @property int|null $total_points
 * @property int|null $rank_position
 */
class GamificationScore extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id', 'user_id', 'period', 'period_type',
        'visits_count', 'deals_won', 'deals_value', 'new_clients',
        'activities_count', 'coverage_percent', 'csat_avg',
        'commitments_on_time', 'commitments_total',
        'total_points', 'rank_position',
    ];

    protected function casts(): array
    {
        return [
            'visits_count' => 'integer',
            'deals_won' => 'integer',
            'deals_value' => 'decimal:2',
            'new_clients' => 'integer',
            'activities_count' => 'integer',
            'coverage_percent' => 'float',
            'csat_avg' => 'float',
            'commitments_on_time' => 'integer',
            'commitments_total' => 'integer',
            'total_points' => 'integer',
            'rank_position' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeMonthly($q)
    {
        return $q->where('period_type', 'monthly');
    }

    public function scopeCurrentPeriod($q, string $type = 'monthly')
    {
        $period = $type === 'monthly' ? now()->format('Y-m') : now()->format('Y-\\WW');

        return $q->where('period_type', $type)->where('period', $period);
    }
}
