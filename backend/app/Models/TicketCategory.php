<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property bool|null $is_active
 */
class TicketCategory extends Model
{
    use Auditable, BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id', 'name', 'description', 'is_active', 'sla_policy_id', 'default_priority',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];

    }

    public function slaPolicy()
    {
        return $this->belongsTo(SlaPolicy::class);
    }
}
