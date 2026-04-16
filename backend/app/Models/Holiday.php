<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property bool|null $is_national
 * @property bool|null $is_recurring
 */
class Holiday extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'name', 'date', 'is_national', 'is_recurring',
    ];

    protected function casts(): array
    {
        return [
            'is_national' => 'boolean',
            'is_recurring' => 'boolean',
        ];

    }

    public function setDateAttribute(string|\DateTimeInterface|null $value): void
    {
        $this->attributes['date'] = $value
            ? Carbon::parse($value)->toDateString()
            : null;
    }

    /**
     * Check if a given date is a holiday for this tenant.
     */
    public static function isHoliday(int $tenantId, string $date): bool
    {
        $dateObj = \Carbon\Carbon::parse($date);

        return static::where('tenant_id', $tenantId)
            ->where(function ($q) use ($dateObj) {
                $q->where('date', $dateObj->toDateString())
                    ->orWhere(function ($q2) use ($dateObj) {
                        $q2->where('is_recurring', true)
                            ->whereMonth('date', $dateObj->month)
                            ->whereDay('date', $dateObj->day);
                    });
            })
            ->exists();
    }
}
