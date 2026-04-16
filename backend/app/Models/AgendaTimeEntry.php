<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $started_at
 * @property Carbon|null $stopped_at
 * @property int|null $duration_seconds
 */
class AgendaTimeEntry extends Model
{
    use BelongsToTenant;

    protected $table = 'central_time_entries';

    protected $fillable = [
        'tenant_id',
        'agenda_item_id',
        'user_id',
        'started_at',
        'stopped_at',
        'duration_seconds',
        'descricao',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'stopped_at' => 'datetime',
            'duration_seconds' => 'integer',
        ];

    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(AgendaItem::class, 'agenda_item_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
