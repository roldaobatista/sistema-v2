<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $start_date
 * @property Carbon|null $end_date
 * @property Carbon|null $next_generation_date
 * @property Carbon|null $last_generated_at
 * @property bool|null $is_active
 * @property array<int|string, mixed>|null $metadata
 * @property int|null $day_of_month
 * @property int|null $day_of_week
 * @property int|null $interval
 */
class WorkOrderRecurrence extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'service_id',
        'name',
        'description',
        'frequency',
        'interval',
        'day_of_month',
        'day_of_week',
        'start_date',
        'end_date',
        'last_generated_at',
        'next_generation_date',
        'is_active',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'next_generation_date' => 'date',
            'last_generated_at' => 'datetime',
            'is_active' => 'boolean',
            'metadata' => 'array',
            'day_of_month' => 'integer',
            'day_of_week' => 'integer',
            'interval' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
