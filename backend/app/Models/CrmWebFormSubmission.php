<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property array<int|string, mixed>|null $data
 */
class CrmWebFormSubmission extends Model
{
    use BelongsToTenant;

    protected $table = 'crm_web_form_submissions';

    protected $fillable = [
        'tenant_id', 'form_id', 'customer_id', 'deal_id', 'data',
        'ip_address', 'user_agent', 'utm_source',
        'utm_medium', 'utm_campaign',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
        ];
    }

    // ─── Relationships ──────────────────────────────────

    public function form(): BelongsTo
    {
        return $this->belongsTo(CrmWebForm::class, 'form_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(CrmDeal::class, 'deal_id');
    }
}
