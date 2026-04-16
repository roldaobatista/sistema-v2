<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $contract_end_date
 * @property numeric-string|null $current_value
 * @property numeric-string|null $renewal_value
 * @property int|null $alert_days_before
 * @property Carbon|null $notified_at
 * @property Carbon|null $renewed_at
 */
class CrmContractRenewal extends Model
{
    use Auditable, BelongsToTenant;

    protected $table = 'crm_contract_renewals';

    protected $fillable = [
        'tenant_id', 'customer_id', 'deal_id', 'contract_end_date',
        'alert_days_before', 'status', 'current_value', 'renewal_value',
        'notes', 'notified_at', 'renewed_at',
    ];

    protected function casts(): array
    {
        return [
            'contract_end_date' => 'date',
            'current_value' => 'decimal:2',
            'renewal_value' => 'decimal:2',
            'alert_days_before' => 'integer',
            'notified_at' => 'datetime',
            'renewed_at' => 'datetime',
        ];
    }

    public const STATUSES = [
        'pending' => 'Pendente',
        'notified' => 'Notificado',
        'in_negotiation' => 'Em Negociação',
        'renewed' => 'Renovado',
        'lost' => 'Não Renovado',
    ];

    // ─── Scopes ─────────────────────────────────────────

    public function scopePending($q)
    {
        return $q->where('status', 'pending');
    }

    public function scopeUpcoming($q, int $days = 60)
    {
        return $q->where('contract_end_date', '<=', now()->addDays($days))
            ->where('contract_end_date', '>=', now())
            ->whereIn('status', ['pending', 'notified']);
    }

    // ─── Relationships ──────────────────────────────────

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(CrmDeal::class, 'deal_id');
    }
}
