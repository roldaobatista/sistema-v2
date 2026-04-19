<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property bool|null $is_completed
 * @property int|null $sort_order
 * @property Carbon|null $completed_at
 */
class AgendaSubtask extends Model
{
    use BelongsToTenant;

    protected $table = 'central_subtasks';

    protected $fillable = [
        'tenant_id',
        'agenda_item_id',
        'title',
        'is_completed',
        'sort_order',
        'completed_by',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'is_completed' => 'boolean',
            'sort_order' => 'integer',
            'completed_at' => 'datetime',
        ];

    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(AgendaItem::class, 'agenda_item_id');
    }

    public function completedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }
}
