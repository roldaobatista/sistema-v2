<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int|null $score
 * @property Carbon|null $responded_at
 */
class NpsSurvey extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'customer_id', 'work_order_id',
        'score', 'feedback', 'category', 'responded_at',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'responded_at' => 'datetime',
        ];

    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }
}
