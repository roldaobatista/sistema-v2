<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Carbon\Carbon;
use Database\Factories\JourneyPolicyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int|null $daily_hours_limit
 * @property int|null $weekly_hours_limit
 * @property int|null $monthly_hours_limit
 * @property int|null $break_minutes
 * @property bool|null $displacement_counts_as_work
 * @property bool|null $wait_time_counts_as_work
 * @property bool|null $travel_meal_counts_as_break
 * @property bool|null $auto_suggest_clock_on_displacement
 * @property bool|null $pre_assigned_break
 * @property int|null $overnight_min_hours
 * @property int|null $oncall_multiplier_percent
 * @property int|null $overtime_50_percent_limit
 * @property int|null $overtime_100_percent_limit
 * @property bool|null $saturday_is_overtime
 * @property bool|null $sunday_is_overtime
 * @property array<int|string, mixed>|null $custom_rules
 * @property bool|null $is_default
 * @property bool|null $is_active
 */
class JourneyPolicy extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<JourneyPolicyFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'name',
        'regime_type',
        'daily_hours_limit',
        'weekly_hours_limit',
        'monthly_hours_limit',
        'break_minutes',
        'displacement_counts_as_work',
        'wait_time_counts_as_work',
        'travel_meal_counts_as_break',
        'auto_suggest_clock_on_displacement',
        'pre_assigned_break',
        'overnight_min_hours',
        'oncall_multiplier_percent',
        'overtime_50_percent_limit',
        'overtime_100_percent_limit',
        'saturday_is_overtime',
        'sunday_is_overtime',
        'custom_rules',
        'is_default',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'daily_hours_limit' => 'integer',
            'weekly_hours_limit' => 'integer',
            'monthly_hours_limit' => 'integer',
            'break_minutes' => 'integer',
            'displacement_counts_as_work' => 'boolean',
            'wait_time_counts_as_work' => 'boolean',
            'travel_meal_counts_as_break' => 'boolean',
            'auto_suggest_clock_on_displacement' => 'boolean',
            'pre_assigned_break' => 'boolean',
            'overnight_min_hours' => 'integer',
            'oncall_multiplier_percent' => 'integer',
            'overtime_50_percent_limit' => 'integer',
            'overtime_100_percent_limit' => 'integer',
            'saturday_is_overtime' => 'boolean',
            'sunday_is_overtime' => 'boolean',
            'custom_rules' => 'array',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @param  mixed  $query
     * @return mixed
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  mixed  $query
     * @return mixed
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    public function isOvertimeDay(Carbon $date): bool
    {
        if ($date->isSaturday() && $this->saturday_is_overtime) {
            return true;
        }

        if ($date->isSunday() && $this->sunday_is_overtime) {
            return true;
        }

        return false;
    }
}
