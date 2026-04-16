<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @global Intentionally global */
class CrmTerritoryMember extends Model
{
    use BelongsToTenant;

    protected $table = 'crm_territory_members';

    protected $fillable = [
        'tenant_id', 'territory_id', 'user_id', 'role',
    ];

    public const ROLES = [
        'manager' => 'Gerente',
        'member' => 'Membro',
    ];

    // ─── Relationships ──────────────────────────────────

    public function territory(): BelongsTo
    {
        return $this->belongsTo(CrmTerritory::class, 'territory_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
