<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $violated_at
 * @property int|null $minutes_exceeded
 */
class SlaViolation extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id', 'portal_ticket_id', 'sla_policy_id', 'violation_type', 'violated_at', 'minutes_exceeded',
    ];

    protected function casts(): array
    {
        return [
            'violated_at' => 'datetime',
            'minutes_exceeded' => 'integer',
        ];

    }

    public function ticket()
    {
        return $this->belongsTo(PortalTicket::class, 'portal_ticket_id');
    }

    public function slaPolicy()
    {
        return $this->belongsTo(SlaPolicy::class);
    }
}
