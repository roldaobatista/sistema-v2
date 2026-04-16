<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property array<int|string, mixed>|null $conditions
 * @property array<int|string, mixed>|null $actions
 * @property bool|null $is_active
 * @property int|null $execution_count
 * @property Carbon|null $last_executed_at
 */
class AutomationRule extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'name', 'trigger_event', 'conditions', 'actions',
        'is_active', 'execution_count', 'last_executed_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'conditions' => 'array',
            'actions' => 'array',
            'is_active' => 'boolean',
            'execution_count' => 'integer',
            'last_executed_at' => 'datetime',
        ];

    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
