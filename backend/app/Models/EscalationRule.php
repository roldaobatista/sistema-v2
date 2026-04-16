<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property bool|null $is_active
 * @property int|null $trigger_minutes
 * @property array<int|string, mixed>|null $action_payload
 */
class EscalationRule extends Model
{
    use Auditable, BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id', 'sla_policy_id', 'name', 'trigger_minutes', 'action_type', 'action_payload', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'trigger_minutes' => 'integer',
            'action_payload' => 'array',
        ];

    }

    public function slaPolicy()
    {
        return $this->belongsTo(SlaPolicy::class);
    }
}
