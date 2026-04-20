<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property bool|null $ativo
 * @property array<int|string, mixed>|null $acao_config
 * @property string|null $min_priority
 * @property string|null $action_type
 * @property int|null $assignee_user_id
 * @property string|null $target_role
 */
class AgendaRule extends Model
{
    use BelongsToTenant;

    protected $table = 'central_rules';

    protected $fillable = [
        'tenant_id', 'name', 'description', 'active',
        'event_trigger', 'item_type', 'status_trigger', 'min_priority',
        'action_type', 'action_config',
        'assignee_user_id', 'target_role',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'action_config' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ── Scopes ──

    public function scopeAtivas($query)
    {
        return $query->where('active', true);
    }

    public function scopeParaEvento($query, string $evento)
    {
        return $query->where('event_trigger', $evento);
    }
}
