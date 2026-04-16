<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $date
 * @property int|null $rating
 */
class TechnicianFeedback extends Model
{
    use BelongsToTenant, HasFactory;

    protected $table = 'technician_feedbacks';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'work_order_id',
        'date',
        'type',
        'message',
        'rating',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'rating' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }
}
